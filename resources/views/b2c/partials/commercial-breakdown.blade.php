@php
    $snapshot = (array) ($commercialSnapshot ?? []);
    $breakdown = (array) ($snapshot['commercial_breakdown'] ?? []);
    $labels = [
        'base' => 'Envío',
        'area_extendida' => 'Cargo por área extendida',
        'kg_extra' => 'Kilogramos adicionales',
        'seguro' => 'Protección del envío',
        'otros' => 'Otros cargos',
    ];
@endphp

@if(array_key_exists('base', $breakdown))
    <div class="commercial-breakdown" data-commercial-breakdown>
        @foreach($labels as $concept => $label)
            @if((float) ($breakdown[$concept] ?? 0) > 0)
                <div class="summary-row commercial-concept" data-concept="{{ $concept }}">
                    <span>{{ $label }}</span>
                    <strong>${{ number_format((float) ($breakdown[$concept] ?? 0), 2) }} MXN</strong>
                </div>
            @endif
        @endforeach
        <div class="summary-row commercial-subtotal"><span>Subtotal</span><strong>${{ number_format((float) $snapshot['commercial_subtotal'], 2) }} MXN</strong></div>
        <div class="summary-row commercial-vat"><span>IVA</span><strong>${{ number_format((float) $snapshot['vat'], 2) }} MXN</strong></div>
        <div class="summary-row total commercial-total"><span>Total</span><strong>${{ number_format((float) $snapshot['customer_total'], 2) }} MXN</strong></div>
    </div>
@endif
