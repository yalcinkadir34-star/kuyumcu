@extends('layouts.app')

@section('title', 'Cariler')

@section('content')
    <x-page-header title="Cari Hesaplar" subtitle="Müşteri, tedarikçi ve personel hesapları">
        <x-slot:actions>
            <a href="{{ route('accounts.create') }}" class="btn btn-primary">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Cari
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-48 flex-1">
            <label for="q" class="label">Ara</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Ad, kod, telefon…">
        </div>
        <div>
            <label for="tur" class="label">Tür</label>
            <select id="tur" name="tur" class="input">
                <option value="">Tümü</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('tur') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="durum" class="label">Durum</label>
            <select id="durum" name="durum" class="input">
                <option value="">Tümü</option>
                <option value="aktif" @selected(request('durum') === 'aktif')>Aktif</option>
                <option value="pasif" @selected(request('durum') === 'pasif')>Pasif</option>
            </select>
        </div>
        <button class="btn btn-secondary">Filtrele</button>
        @if (request()->hasAny(['q', 'tur', 'durum']))
            <a href="{{ route('accounts.index') }}" class="btn text-stone-500 hover:text-stone-800">Temizle</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>Cari</th>
                        <th>Tür</th>
                        <th>Telefon</th>
                        @foreach ($currencies as $currency)
                            <th class="text-right">{{ $currency->code === 'HAS' ? 'Has (gr)' : $currency->code }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('accounts.show', $account) }}'">
                            <td class="font-mono text-xs text-stone-500">{{ $account->code }}</td>
                            <td>
                                <a href="{{ route('accounts.show', $account) }}" class="font-medium text-stone-900 hover:text-gold-700">{{ $account->name }}</a>
                                @unless ($account->is_active)
                                    <span class="badge ml-1 bg-stone-100 text-stone-500 ring-stone-300">Pasif</span>
                                @endunless
                                @if ($account->contact_person)
                                    <div class="text-xs text-stone-500">{{ $account->contact_person }}</div>
                                @endif
                            </td>
                            <td class="text-stone-600">{{ $account->type->label() }}</td>
                            <td class="whitespace-nowrap text-stone-600">{{ $account->phone ?? '—' }}</td>
                            @foreach ($currencies as $currency)
                                <td class="text-right">
                                    <x-cari-bakiye :milli="$balances[$account->id][$currency->id] ?? 0" :currency="$currency" />
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + $currencies->count() }}" class="py-12 text-center text-stone-500">
                                @if (request()->hasAny(['q', 'tur', 'durum']))
                                    Aramanıza uyan cari bulunamadı.
                                @else
                                    Henüz cari eklenmedi. <a href="{{ route('accounts.create') }}" class="font-medium text-gold-700 hover:underline">İlk cariyi ekleyin</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-stone-500">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1"><span class="rounded bg-amber-100 px-1 font-bold text-amber-800">B</span> Borçlu: cari bize borçlu</span>
            <span class="inline-flex items-center gap-1"><span class="rounded bg-sky-100 px-1 font-bold text-sky-800">A</span> Alacaklı: biz cariye borçluyuz</span>
        </div>
        {{ $accounts->links() }}
    </div>
@endsection
