@php($subtotal=(float)($checkout->subtotal_amount ?? $checkout->total_amount))
@php($taxRate=(float)($checkout->tax_rate ?? 0))
@php($tax=(float)($checkout->tax_amount ?? 0))
@php($taxLabel=$taxRate>0 ? 'IVA ('.number_format($taxRate*100,0).'%)' : 'IVA')
@php($origin=data_get($checkout->shipping_data_snapshot,'sender.address'))
@php($destination=data_get($checkout->shipping_data_snapshot,'recipient.address'))
<article class="pending-checkout-card">
<header class="pending-checkout-card__head"><div><strong>{{ data_get($checkout->quote_snapshot,'service','Servicio cotizado') }}</strong><small>{{ $checkout->created_at->format('d/m/Y H:i') }}</small></div><span class="z-badge z-badge--warning">Pago pendiente</span></header>
<div class="pending-checkout-card__route"><span>{{ \App\Support\Presentation\AddressPresenter::compact($origin) }}</span><x-zigo.icon name="chevron" /><span>{{ \App\Support\Presentation\AddressPresenter::compact($destination) }}</span></div>
<dl class="pending-checkout-card__amounts"><div><dt>Subtotal</dt><dd>${{ number_format($subtotal,2) }}</dd></div><div><dt>{{ $taxLabel }}</dt><dd>${{ number_format($tax,2) }}</dd></div><div class="total"><dt>Total</dt><dd>${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }}</dd></div></dl>
<a class="z-btn z-btn--outline" href="{{ route($checkout->status==='DRAFT'?'tenant.customer.app.checkout.summary':'tenant.customer.app.checkout.payment',$checkout->uuid,false) }}">Continuar pago</a>
</article>
