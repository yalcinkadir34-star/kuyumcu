@extends('layouts.app')

@section('title', $account->name)

@section('content')
    <x-page-header :title="$account->name" :back="route('accounts.index')">
        <x-slot:subtitle>
            {{ $account->code }} · {{ $account->type->label() }}
            @if ($account->phone) · {{ $account->phone }} @endif
            @unless ($account->is_active) · <span class="font-medium text-red-600">Pasif</span> @endunless
        </x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('accounts.edit', $account) }}" class="btn btn-secondary">Düzenle</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Bakiye kartları --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($currencies->where('is_active', true) as $currency)
            @php $milli = $balances[$currency->id] ?? 0; @endphp
            <div class="card p-5">
                <div class="text-sm font-medium text-stone-500">{{ $currency->name }}</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-stone-900">
                    {{ \App\Support\Amount::formatMilli(abs($milli), $currency) }}
                </div>
                <div class="mt-1 text-xs">
                    @if ($milli > 0)
                        <span class="font-medium text-amber-700">Borçlu: cari bize borçlu</span>
                    @elseif ($milli < 0)
                        <span class="font-medium text-sky-700">Alacaklı: biz cariye borçluyuz</span>
                    @else
                        <span class="text-stone-400">Bakiye yok</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Hızlı işlemler --}}
    <div class="mt-6 flex flex-wrap gap-2">
        @foreach ($quickTypes as $type)
            <a href="{{ route('transactions.create', ['tur' => $type->value, 'cari' => $account->id]) }}"
               class="btn {{ $loop->first ? 'btn-gold' : 'btn-secondary' }}" title="{{ $type->hint() }}">
                + {{ $type->label() }}
            </a>
        @endforeach
    </div>

    {{-- Ekstre --}}
    <div class="mt-6">
        @include('partials.ledger', [
            'ledger' => $ledger,
            'mode' => 'cari',
            'title' => 'Cari Ekstre',
        ])
    </div>

    @if ($account->address || $account->notes || $account->tax_number)
        <div class="card mt-6 grid gap-4 p-5 text-sm sm:grid-cols-3">
            @if ($account->tax_number)
                <div>
                    <div class="text-stone-500">Vergi dairesi / No</div>
                    <div class="mt-1 text-stone-900">{{ $account->tax_office ?? '—' }} / {{ $account->tax_number }}</div>
                </div>
            @endif
            @if ($account->address)
                <div>
                    <div class="text-stone-500">Adres</div>
                    <div class="mt-1 whitespace-pre-line text-stone-900">{{ $account->address }}</div>
                </div>
            @endif
            @if ($account->notes)
                <div>
                    <div class="text-stone-500">Notlar</div>
                    <div class="mt-1 whitespace-pre-line text-stone-900">{{ $account->notes }}</div>
                </div>
            @endif
        </div>
    @endif
@endsection
