@extends('layouts.app')

@section('title', 'Ana Sayfa')

@section('content')
    @use('App\Support\Amount')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold text-stone-900">Merhaba, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-stone-500">{{ $today }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('transactions.create', ['tur' => 'tahsilat']) }}" class="btn btn-gold">+ Tahsilat</a>
            <a href="{{ route('transactions.create', ['tur' => 'odeme']) }}" class="btn btn-secondary">+ Ödeme</a>
            <a href="{{ route('accounts.create') }}" class="btn btn-secondary">+ Yeni Cari</a>
        </div>
    </div>

    {{-- Özet kartları --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="card p-5">
                <div class="text-sm font-medium text-stone-500">{{ $stat['label'] }}</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-stone-900">
                    @if (array_key_exists('milli', $stat))
                        <x-kasa-bakiye :milli="$stat['milli']" :currency="$stat['currency']" />
                    @else
                        {{ $stat['value'] }}
                    @endif
                </div>
                <div class="mt-2 text-xs text-stone-400">{{ $stat['hint'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Genel bilanço --}}
    <div class="card mt-6 overflow-hidden">
        <div class="card-header">
            <div>
                <h3 class="font-semibold text-stone-900">Genel Bilanço</h3>
                <p class="text-xs text-stone-500">Net durum = Kasa mevcudu + Atölyedeki has + Carilerden alacaklar − Carilere borçlar</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Birim</th>
                        <th class="text-right">Kasa Mevcudu</th>
                        <th class="text-right">Atölyede</th>
                        <th class="text-right">Alacaklarımız</th>
                        <th class="text-right">Borçlarımız</th>
                        <th class="text-right">Net Durum</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($currencies as $currency)
                        @php $row = $summary[$currency->id] ?? ['kasa' => 0, 'atolye' => 0, 'alacak' => 0, 'borc' => 0, 'net' => 0]; @endphp
                        <tr>
                            <td class="font-medium text-stone-900">{{ $currency->name }}</td>
                            <td class="text-right"><x-kasa-bakiye :milli="$row['kasa']" :currency="$currency" /></td>
                            <td class="text-right">
                                @if ($currency->code === 'HAS')
                                    <x-kasa-bakiye :milli="$row['atolye']" :currency="$currency" />
                                @else
                                    <span class="text-stone-300">—</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums {{ $row['alacak'] ? 'text-amber-700' : 'text-stone-400' }}">{{ Amount::formatMilli($row['alacak'], $currency) }}</td>
                            <td class="text-right tabular-nums {{ $row['borc'] ? 'text-sky-700' : 'text-stone-400' }}">{{ Amount::formatMilli($row['borc'], $currency) }}</td>
                            <td class="text-right text-base"><x-kasa-bakiye :milli="$row['net']" :currency="$currency" class="font-semibold" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Son hareketler --}}
    <div class="card mt-6 overflow-hidden">
        <div class="card-header">
            <h3 class="font-semibold text-stone-900">Son Hareketler</h3>
            <a href="{{ route('transactions.index') }}" class="text-sm font-medium text-gold-700 hover:underline">Tümü →</a>
        </div>
        @if ($recent->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-stone-500">
                Henüz hareket yok. <a href="{{ route('transactions.create') }}" class="font-medium text-gold-700 hover:underline">İlk hareketi girin</a>.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <tbody>
                        @foreach ($recent as $transaction)
                            <tr>
                                <td class="w-28 whitespace-nowrap text-stone-500">{{ $transaction->date->format('d.m.Y') }}</td>
                                <td class="w-44"><span class="badge {{ $transaction->type->badgeClass() }}">{{ $transaction->type->label() }}</span></td>
                                <td>
                                    @if ($transaction->account)
                                        <a href="{{ route('accounts.show', $transaction->account) }}" class="font-medium text-stone-900 hover:text-gold-700">{{ $transaction->account->name }}</a>
                                    @else
                                        <span class="text-stone-600">{{ $transaction->cashRegister?->name }}</span>
                                    @endif
                                    @if ($transaction->description)
                                        <span class="text-stone-400">· {{ $transaction->description }}</span>
                                    @endif
                                </td>
                                <td class="text-right font-medium tabular-nums whitespace-nowrap">{{ Amount::format($transaction->amount, $transaction->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
