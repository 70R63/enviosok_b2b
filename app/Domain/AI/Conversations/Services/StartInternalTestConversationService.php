<?php

namespace App\Domain\AI\Conversations\Services;

use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Enums\ConversationChannel;
use App\Domain\AI\Conversations\Enums\ConversationStatus;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StartInternalTestConversationService
{
    public function __construct(private AiLifecycleAuthorization $auth, private AiTenantBoundary $tenants) {}

    public function start(User $actor, Agent $agent): Conversation
    {
        $authorized = $this->auth->authorize($actor);
        $this->tenants->assertResourceBelongsToCurrentTenant($agent);

        return DB::transaction(function () use ($authorized, $agent) {
            $authorized = $this->auth->revalidate($authorized);
            $locked = Agent::query()->lockForUpdate()->findOrFail($agent->id);
            $version = AgentVersion::query()->where('agent_id', $locked->id)->whereIn('status', [AgentVersionStatus::Draft->value, AgentVersionStatus::Testing->value])->orderByDesc('version_number')->lockForUpdate()->firstOrFail();
            $conversation = new Conversation;
            $conversation->agent_id = $locked->id;
            $conversation->agent_version_id = $version->id;
            $conversation->channel = ConversationChannel::InternalTest;
            $conversation->status = ConversationStatus::Open;
            $conversation->turn_in_progress = false;
            $conversation->next_sequence = 1;
            $conversation->created_by_user_id = $authorized->actorUserId;
            $conversation->save();

            return $conversation;
        });
    }
}
