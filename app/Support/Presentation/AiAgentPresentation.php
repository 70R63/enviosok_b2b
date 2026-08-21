<?php

namespace App\Support\Presentation;

use App\Domain\AI\Agents\Enums\AgentType;

final class AiAgentPresentation
{
    private const TYPES = [
        'custom_operational' => 'Agente operativo personalizado',
        'customer_service' => 'Agente de atención al cliente',
        'sales' => 'Agente de ventas',
        'booking' => 'Agente de citas',
        'quote' => 'Agente de cotizaciones',
    ];
    private const CONFIDENCE = ['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
    private const OUTCOMES = [
        'operational_request_created' => 'Solicitud operativa creada',
        'lead_captured' => 'Lead capturado', 'lead_qualified' => 'Lead calificado',
        'conversation_resolved' => 'Conversación resuelta', 'human_handoff' => 'Transferencia a una persona',
        'appointment_requested' => 'Cita solicitada', 'quote_requested' => 'Cotización solicitada',
    ];
    private const CHANNELS = ['webchat' => 'Chat web', 'whatsapp' => 'WhatsApp', 'api' => 'API'];

    public static function agentType(AgentType|string $value): string { return self::TYPES[$value instanceof AgentType ? $value->value : $value] ?? 'Agente personalizado'; }
    public static function alternative(string $value): string { return self::TYPES[$value] ?? 'Alternativa disponible'; }
    public static function confidence(string $value): string { return self::CONFIDENCE[$value] ?? 'No determinada'; }
    public static function outcome(string $value): string { return self::OUTCOMES[$value] ?? 'Resultado configurado'; }
    public static function channel(string $value): string { return self::CHANNELS[$value] ?? 'Canal configurado'; }
}
