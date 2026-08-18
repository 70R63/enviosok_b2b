@extends('tenant.admin.layout')
@section('title','Rastreo operativo')
@section('content')
@php($trackingEvents=$shipment->events)
@php($trackingStatus=$shipment->status)
@php($trackingNumber=$shipment->tracking_number)
@php($trackingUpdatedAt=$shipment->updated_at)
@php($trackingEyebrow='RASTREO OPERATIVO')
@php($trackingActions='<a class="z-btn z-btn--outline" href="'.route('tenant.admin.operations.show',$operation->uuid,false).'">Volver al detalle operativo</a><a class="z-btn" href="'.route('tenant.admin.operations.guide',$operation->uuid,false).'">Descargar guía PDF</a>')
<div class="tracking-shell">@include('tenant.tracking._header') @include('tenant.tracking._stepper') @include('tenant.tracking._timeline')</div>
@endsection
