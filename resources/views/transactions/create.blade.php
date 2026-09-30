@extends('layouts.app')

@section('title', 'Yeni Hareket')

@section('content')
    <div class="max-w-3xl">
        <x-page-header title="Yeni Hareket" subtitle="Tahsilat, ödeme, cari dekont veya kasa işlemi" :back="$back" />

        <form method="POST" action="{{ route('transactions.store') }}">
            @include('transactions._form')
        </form>
    </div>
@endsection
