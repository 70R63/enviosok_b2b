<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Services\CloseConversationService;
use App\Domain\AI\Conversations\Services\SendInternalConversationMessageService;
use App\Domain\AI\Conversations\Services\StartInternalTestConversationService;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CloseConversationRequest;
use App\Http\Requests\Tenant\SendInternalConversationMessageRequest;
use App\Http\Requests\Tenant\StartInternalConversationRequest;
use App\Http\Support\AiLaunchpadHttpGate;

final class AiConversationController extends Controller
{
    public function index(AiLaunchpadHttpGate $gate, AiTenantBoundary $tenants)
    {
        $gate->ensure(auth()->user());

        return view('tenant.admin.ai-conversations.index', ['tenant' => $tenants->requireTenant(), 'conversations' => Conversation::query()->with(['agent', 'agentVersion'])->latest()->paginate(20)]);
    }

    public function store(StartInternalConversationRequest $request, Agent $agent, AiLaunchpadHttpGate $gate, StartInternalTestConversationService $service)
    {
        $gate->ensure($request->user());
        $gate->assertCurrentTenant($agent);
        try {
            $conversation = $service->start($request->user(), $agent);
        } catch (\DomainException) {
            return back()->withErrors(['conversation' => 'No fue posible iniciar la conversación de prueba.']);
        }

        return redirect()->route('tenant.admin.ai-conversations.show', $conversation);
    }

    public function show(Conversation $conversation, AiLaunchpadHttpGate $gate, AiTenantBoundary $tenants)
    {
        $gate->ensure(auth()->user());
        $gate->assertCurrentTenant($conversation);
        $conversation->load(['agent', 'agentVersion', 'messages.citations.chunk.source']);

        return view('tenant.admin.ai-conversations.show', ['tenant' => $tenants->requireTenant(), 'conversation' => $conversation]);
    }

    public function send(SendInternalConversationMessageRequest $request, Conversation $conversation, AiLaunchpadHttpGate $gate, SendInternalConversationMessageService $service)
    {
        $gate->ensure($request->user());
        $gate->assertCurrentTenant($conversation);
        try {
            $service->send($request->user(), $conversation, $request->messageData());

            return redirect()->route('tenant.admin.ai-conversations.show', $conversation);
        } catch (\Throwable) {
            return redirect()->route('tenant.admin.ai-conversations.show', $conversation)->withErrors(['message' => 'No fue posible generar la respuesta en este momento. Puedes intentar con otro mensaje.']);
        }
    }

    public function close(CloseConversationRequest $request, Conversation $conversation, AiLaunchpadHttpGate $gate, CloseConversationService $service)
    {
        $gate->ensure($request->user());
        $gate->assertCurrentTenant($conversation);
        try {
            $service->close($request->user(), $conversation);
        } catch (\DomainException) {
            return back()->withErrors(['conversation' => 'No fue posible cerrar la conversación.']);
        }

        return redirect()->route('tenant.admin.ai-conversations.show', $conversation)->with('success', 'Conversación cerrada.');
    }
}
