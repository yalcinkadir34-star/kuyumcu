@extends('layouts.app')

@section('title', 'Yeni Kasa')

@section('content')
    <div class="max-w-3xl">
        <x-page-header title="Yeni Kasa" :back="route('cash-registers.index')" />

        <form method="POST" action="{{ route('cash-registers.store') }}">
            @include('cash-registers._form')
        </form>
    </div>
@endsection
