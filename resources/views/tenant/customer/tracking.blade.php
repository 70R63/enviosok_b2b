@extends('tenant.layout')
@section('title','Rastreo '.$shipment->tracking_number)
@section('content')
@php($trackingEvents=$shipment->events)
@php($trackingStatus=$shipment->status)
@php($trackingNumber=$shipment->tracking_number)
@php($trackingUpdatedAt=$shipment->updated_at)
@php($trackingEyebrow='RASTREO DE ENVÍO')
<div class="tracking-shell">@include('tenant.tracking._header') @include('tenant.tracking._stepper') @include('tenant.tracking._timeline')<div class="tracking-footer"><a class="z-btn z-btn--outline" href="{{ route('tenant.customer.app.shipments',[],false) }}"><x-zigo.icon name="shipments" />Volver a mis envíos</a></div></div>
@endsection
