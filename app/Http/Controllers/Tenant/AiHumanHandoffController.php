<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Handoff\Enums\HumanHandoffStatus;
use App\Domain\AI\Handoff\Models\HumanHandoff;
use App\Domain\AI\Handoff\Services\ReleaseHumanHandoffService;
use App\Domain\AI\Handoff\Services\SendHumanConversationMessageService;
use App\Domain\AI\Handoff\Services\TakeHumanHandoffService;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CloseConversationRequest;
use App\Http\Requests\Tenant\SendHumanConversationMessageRequest;
use App\Http\Support\AiLaunchpadHttpGate;

final class AiHumanHandoffController extends Controller
{
    public function index(AiLaunchpadHttpGate $gate, AiTenantBoundary $tenants)
    {
        $gate->ensure(auth()->user());

        return view('tenant.admin.ai-handoffs.index', ['tenant' => $tenants->requireTenant(), 'handoffs' => HumanHandoff::query()->whereIn('status', [HumanHandoffStatus::Requested->value, HumanHandoffStatus::Active->value])->with(['conversation.agent', 'assignee'])->latest('requested_at')->paginate(20)]);
    }

    public function take(CloseConversationRequest $request, HumanHandoff $handoff, AiLaunchpadHttpGate $gate, TakeHumanHandoffService $service)
    {
        $gate->ensure($request->user());
        $gate->assertCurrentTenant($handoff);
        try {
            $service->take($request->user(), $handoff);
        } catch (\DomainException) {
            return back()->withErrors(['handoff' => 'La conversación ya no está disponible.']);
        }

        return redirect()->route('tenant.admin.ai-conversations.show', $handoff->conversation);
    }

    public function send(SendHumanConversationMessageRequest $request, Conversation $conversation, AiLaunchpadHttpGate $gate, SendHumanConversationMessageService $service)
    {
        $gate->ensure($request->user());
        $gate->assertCurrentTenant($conversation);
        try {
            $service->send($request->user(), $conversation, $request->messageData());
        } catch (\DomainException) {
            return back()->withErrors(['message' => 'Sólo la persona asignada puede responder.']);
        }

        return back();
    }

    public function release(CloseConversationRequest $request, HumanHandoff $handoff, AiLaunchpadHttpGate $gate, ReleaseHumanHandoffService $service)
    {
        $gate->ensure($request->user());
        $gate->assertCurrentTenant($handoff);
        try {
            $service->release($request->user(), $handoff);
        } catch (\DomainException) {
            return back()->withErrors(['handoff' => 'No fue posible devolver el control a IA.']);
        }

        return redirect()->route('tenant.admin.ai-conversations.show', $handoff->conversation);
    }
}
