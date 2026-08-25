<?php

namespace App\Domain\AI\Channels\WhatsApp\Services;

use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Channels\WhatsApp\Jobs\ProcessWhatsAppInboundJob;
use App\Domain\AI\Channels\WhatsApp\Models\{WhatsAppChannel,WhatsAppDelivery,WhatsAppInboundReceipt,WhatsAppSession};
use App\Domain\AI\Channels\WhatsApp\Support\WhatsAppDeliveryStatusPolicy;
use App\Domain\AI\Conversations\Data\SendConversationMessageData;
use App\Domain\AI\Conversations\Enums\{ConversationChannel,ConversationMessageRole,ConversationMessageStatus,ConversationStatus};
use App\Domain\AI\Conversations\Exceptions\ConversationTurnBusyException;
use App\Domain\AI\Conversations\Models\{Conversation,ConversationMessage};
use App\Domain\AI\Conversations\Services\SendInternalConversationMessageService;
use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\{DB,Queue,RateLimiter};

final class ProcessWhatsAppInboundService
{
    public function __construct(
        private SendInternalConversationMessageService $core,
        private WhatsAppDeliveryService $deliveries,
        private ConfirmWhatsAppActionService $confirm,
        private ResolveWhatsAppChannelService $resolver,
        private AiLifecycleAuthorization $authorization,
        private EntitlementService $entitlements,
        private WhatsAppDeliveryStatusPolicy $statusPolicy,
    ) {}

    public function process(int $id): void
    {
        $receipt = DB::transaction(function () use ($id) {
            $receipt = WhatsAppInboundReceipt::query()->lockForUpdate()->findOrFail($id);
            if ($receipt->status !== 'received') return null;
            $receipt->status = 'processing';
            $receipt->save();
            return $receipt;
        });
        if (! $receipt) return;

        try {
            $payload = $receipt->payload_encrypted;
            $stored = WhatsAppChannel::query()->findOrFail($receipt->whatsapp_channel_id);
            try {
                $channel = $this->resolver->resolve($stored->webhook_key);
            } catch (\Throwable) {
                $this->finish($receipt, 'succeeded');
                return;
            }
            if ($receipt->event_type === 'status') {
                $this->status($channel, $payload);
                $this->finish($receipt, 'succeeded');
                return;
            }
            if (! in_array($receipt->event_type, ['text', 'interactive'], true)) {
                $this->finish($receipt, 'succeeded');
                return;
            }
            $contact = (string) $payload['from'];
            $rateKey = 'ai-whatsapp-runtime:'.$channel->id.':'.hash('sha256', $contact);
            if ($receipt->safe_error_code !== 'turn_waiting' && ! RateLimiter::attempt($rateKey, (int) config('ai.whatsapp.runtime_per_minute', 30), fn () => true, 60)) {
                $this->finish($receipt, 'succeeded');
                return;
            }
            $session = $this->session($channel, $contact, $payload['profile_name'] ?? null);
            $session->service_window_ends_at = now()->addSeconds((int) config('ai.whatsapp.customer_service_window_seconds', 86400));
            $session->save();
            if ($receipt->event_type === 'interactive') {
                $this->confirm->handle($channel, $session, (string) $payload['reply_id']);
                $this->finish($receipt, 'succeeded');
                return;
            }
            $text = (string) $payload['text'];
            if (! mb_check_encoding($text, 'UTF-8') || trim($text) === '' || strlen($text) > (int) config('ai.whatsapp.max_message_bytes', 8000)) {
                $this->finish($receipt, 'failed', 'message_invalid');
                return;
            }
            $conversation = Conversation::query()->findOrFail($session->conversation_id);
            $actor = User::query()->findOrFail($channel->created_by_user_id);
            if ($conversation->status === ConversationStatus::HumanActive) {
                $this->appendHumanQueueMessage($actor, $conversation, $text);
                $this->finish($receipt, 'succeeded');
                return;
            }
            $result = $this->core->send($actor, $conversation, SendConversationMessageData::from($text), fn () => $this->authorizeTurn($channel->id, $session->id, $conversation->id));
            $pending = ActionRun::query()->where('conversation_id', $conversation->id)->where('status', ActionRunStatus::AwaitingConfirmation->value)->latest('id')->first();
            $pending ? $this->deliveries->confirmation($channel, $session, $result->assistantMessage, $pending) : $this->deliveries->text($channel, $session, $result->assistantMessage);
            $this->finish($receipt, 'succeeded');
        } catch (ConversationTurnBusyException) {
            $this->defer($receipt);
        } catch (\Throwable) {
            $this->finish($receipt, 'failed', 'inbound_processing_failed');
        }
    }

    private function authorizeTurn(int $channelId, int $sessionId, int $conversationId): void
    {
        $candidate = WhatsAppChannel::query()->findOrFail($channelId);
        Agent::query()->lockForUpdate()->findOrFail($candidate->agent_id);
        $channel = WhatsAppChannel::query()->lockForUpdate()->findOrFail($channelId);
        $session = WhatsAppSession::query()->where('whatsapp_channel_id', $channel->id)->lockForUpdate()->findOrFail($sessionId);
        if (! $channel->enabled || (int) $session->conversation_id !== $conversationId || ! $session->insideWindow()) throw new \DomainException('channel_unavailable');
        $tenant = Tenant::query()->findOrFail($channel->tenant_id);
        $this->entitlements->lockCurrentAuthority($tenant);
        if (! $this->entitlements->has($tenant, (string) config('ai.entitlement.module_code', 'AI_CORE'))) throw new \DomainException('channel_unavailable');
    }

    private function session(WhatsAppChannel $candidate, string $contact, ?string $name): WhatsAppSession
    {
        return DB::transaction(function () use ($candidate, $contact, $name) {
            $agent = Agent::query()->lockForUpdate()->findOrFail($candidate->agent_id);
            $channel = WhatsAppChannel::query()->lockForUpdate()->findOrFail($candidate->id);
            if (! $channel->enabled) throw new \DomainException('channel_unavailable');
            $hash = hash('sha256', $contact);
            $session = WhatsAppSession::query()->where('whatsapp_channel_id', $channel->id)->where('contact_hash', $hash)->whereNull('closed_at')->lockForUpdate()->first();
            if ($session) return $session;
            $version = AgentVersion::query()->where('agent_id', $agent->id)->whereKey($agent->current_published_version_id)->lockForUpdate()->firstOrFail();
            if ($version->status !== AgentVersionStatus::Published || ! $version->agent_contract_version_id) throw new \DomainException('channel_unavailable');
            $conversation = new Conversation;
            $conversation->agent_id = $agent->id;
            $conversation->agent_version_id = $version->id;
            $conversation->channel = ConversationChannel::WhatsApp;
            $conversation->status = ConversationStatus::Open;
            $conversation->turn_in_progress = false;
            $conversation->next_sequence = 1;
            $conversation->created_by_user_id = $channel->created_by_user_id;
            $conversation->save();
            $session = new WhatsAppSession;
            $session->tenant_id = $channel->tenant_id;
            $session->whatsapp_channel_id = $channel->id;
            $session->conversation_id = $conversation->id;
            $session->setContact($contact);
            $session->setProfileName($name);
            $session->service_window_ends_at = now();
            $session->save();
            return $session;
        });
    }

    private function appendHumanQueueMessage(User $actor, Conversation $conversation, string $text): void
    {
        DB::transaction(function () use ($actor, $conversation, $text) {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $sequence = $locked->reserveVisitorMessageDuringHumanHandoff($this->authorization->authorize($actor));
            $message = new ConversationMessage;
            $message->conversation_id = $locked->id;
            $message->sequence = $sequence;
            $message->role = ConversationMessageRole::User;
            $message->status = ConversationMessageStatus::Completed;
            $message->content = $text;
            $message->completed_at = now();
            $message->needs_handoff = false;
            $message->save();
        });
    }

    private function status(WhatsAppChannel $channel, array $payload): void
    {
        DB::transaction(function () use ($channel, $payload) {
            $providerId = (string) ($payload['provider_delivery_id'] ?? '');
            $incoming = (string) ($payload['status'] ?? '');
            if ($providerId === '') return;
            $deliveryId = DB::table('ai_whatsapp_deliveries as d')->join('ai_whatsapp_sessions as s', 's.id', '=', 'd.whatsapp_session_id')->where('s.whatsapp_channel_id', $channel->id)->where('s.tenant_id', $channel->tenant_id)->where('d.tenant_id', $channel->tenant_id)->where('d.provider_message_id', $providerId)->value('d.id');
            if (! $deliveryId) return;
            $delivery = WhatsAppDelivery::query()->where('tenant_id', $channel->tenant_id)->lockForUpdate()->findOrFail($deliveryId);
            if (! $this->statusPolicy->permits($delivery->status, $incoming)) return;
            $delivery->status = $incoming;
            $delivery->safe_error_code = $incoming === 'failed' ? 'provider_delivery_failed' : null;
            $delivery->save();
        });
    }

    private function defer(WhatsAppInboundReceipt $receipt): void
    {
        $queued = DB::transaction(function () use ($receipt) {
            $locked = WhatsAppInboundReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->status !== 'processing') return false;
            $locked->status = 'received';
            $locked->safe_error_code = 'turn_waiting';
            $locked->save();
            return true;
        });
        if ($queued) Queue::later(now()->addSeconds((int) config('ai.whatsapp.contention_retry_seconds', 5)), new ProcessWhatsAppInboundJob($receipt->tenant_id, $receipt->id));
    }

    private function finish(WhatsAppInboundReceipt $receipt, string $status, ?string $error = null): void
    {
        DB::transaction(function () use ($receipt, $status, $error) {
            $locked = WhatsAppInboundReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $locked->status = $status;
            $locked->safe_error_code = $error;
            $locked->save();
        });
    }
}
