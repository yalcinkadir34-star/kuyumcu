@extends('layouts.app')

@section('title', 'Hareketler')

@section('content')
    @use('App\Support\Amount')

    <x-page-header title="Hareketler" subtitle="Tüm cari ve kasa işlemleri">
        <x-slot:actions>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Hareket
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-44 flex-1">
            <label for="q" class="label">Ara</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Cari, açıklama, belge no…">
        </div>
        <div>
            <label for="tur" class="label">İşlem</label>
            <select id="tur" name="tur" class="input">
                <option value="">Tümü</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('tur') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="birim" class="label">Birim</label>
            <select id="birim" name="birim" class="input">
                <option value="">Tümü</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((int) request('birim') === $currency->id)>{{ $currency->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="baslangic" class="label">Başlangıç</label>
            <input id="baslangic" type="date" name="baslangic" value="{{ request('baslangic') }}" class="input">
        </div>
        <div>
            <label for="bitis" class="label">Bitiş</label>
            <input id="bitis" type="date" name="bitis" value="{{ request('bitis') }}" class="input">
        </div>
        <button class="btn btn-secondary">Filtrele</button>
        @if (request()->hasAny(['q', 'tur', 'birim', 'baslangic', 'bitis']))
            <a href="{{ route('transactions.index') }}" class="btn text-stone-500 hover:text-stone-800">Temizle</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem</th>
                        <th>Cari</th>
                        <th>Kasa</th>
                        <th>Açıklama</th>
                        <th class="text-right">Tutar</th>
                        <th>Kaydeden</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="whitespace-nowrap text-stone-600">{{ $transaction->date->format('d.m.Y H:i:s') }}</td>
                            <td><span class="badge {{ $transaction->type->badgeClass() }}">{{ $transaction->type->label() }}</span></td>
                            <td>
                                @if ($transaction->account)
                                    <a href="{{ route('accounts.show', $transaction->account) }}" class="text-stone-900 hover:text-gold-700">{{ $transaction->account->name }}</a>
                                @else
                                    <span class="text-stone-400">—</span>
                                @endif
                            </td>
                            <td class="text-stone-600">{{ $transaction->cashRegister?->name ?? '—' }}</td>
                            <td class="max-w-64 text-stone-600">
                                <div class="truncate" title="{{ $transaction->description }}">{{ $transaction->description ?: '—' }}</div>
                                @if ($transaction->document_no)
                                    <div class="text-xs text-stone-400">Belge: {{ $transaction->document_no }}</div>
                                @endif
                            </td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap">{{ Amount::format($transaction->amount, $transaction->currency) }}</td>
                            <td class="text-xs whitespace-nowrap text-stone-500">{{ $transaction->creator?->name ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('transactions.edit', $transaction) }}" class="rounded p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" title="Düzenle" aria-label="Düzenle">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-stone-500">Hareket bulunamadı.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>
@endsection
