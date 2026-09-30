@extends('layouts.app')

@section('title', 'Yeni Cari')

@section('content')
    <div class="max-w-3xl">
        <x-page-header title="Yeni Cari" :back="route('accounts.index')" />

        <form method="POST" action="{{ route('accounts.store') }}">
            @include('accounts._form')
        </form>
    </div>
@endsection
