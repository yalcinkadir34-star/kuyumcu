@extends('layouts.app')

@section('title', 'Atölyeye Giriş')

@section('content')
    <div class="max-w-3xl">
        <x-page-header title="Atölyeye Giriş" subtitle="Firmadan gelen ürünü tartıp milyemiyle kaydedin" :back="route('work-orders.index')" />

        <form method="POST" action="{{ route('work-orders.store') }}">
            @include('work-orders._form')
        </form>
    </div>
@endsection
