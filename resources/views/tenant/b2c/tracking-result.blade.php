@extends('tenant.public-layout')
@section('title','Rastreo '.$trackingResult['tracking_number'])
@section('content')
@php($trackingEvents=collect($trackingResult['events']))
@php($trackingStatus=$trackingResult['status'])
@php($trackingNumber=$trackingResult['tracking_number'])
@php($trackingUpdatedAt=null)
@php($trackingEyebrow='RASTREO PÚBLICO')
<div class="tracking-shell">@include('tenant.tracking._header') @include('tenant.tracking._stepper') @include('tenant.tracking._timeline')<div class="tracking-footer"><a class="z-btn z-btn--outline" href="{{ route('tenant.b2c.tracking.legacy',[],false) }}"><x-zigo.icon name="search" />Consultar otra guía</a></div></div>
@endsection
