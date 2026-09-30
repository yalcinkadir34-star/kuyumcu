@extends('layouts.app')

@section('title', $register->name)

@section('content')
    <x-page-header :title="$register->name" :back="route('cash-registers.index')">
        <x-slot:subtitle>
            {{ $register->type->label() }}
            @unless ($register->is_active) · <span class="font-medium text-red-600">Pasif</span> @endunless
        </x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('cash-registers.edit', $register) }}" class="btn btn-secondary">Düzenle</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($currencies->where('is_active', true) as $currency)
            <div class="card p-5">
                <div class="text-sm font-medium text-stone-500">{{ $currency->name }}</div>
                <div class="mt-2 text-2xl font-semibold">
                    <x-kasa-bakiye :milli="$balances[$currency->id] ?? 0" :currency="$currency" />
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        @foreach ($quickTypes as $type)
            <a href="{{ route('transactions.create', ['tur' => $type->value, 'kasa' => $register->id]) }}"
               class="btn btn-secondary" title="{{ $type->hint() }}">
                + {{ $type->label() }}
            </a>
        @endforeach
    </div>

    <div class="mt-6">
        @include('partials.ledger', [
            'ledger' => $ledger,
            'mode' => 'kasa',
            'title' => 'Kasa Hareketleri',
        ])
    </div>
@endsection
