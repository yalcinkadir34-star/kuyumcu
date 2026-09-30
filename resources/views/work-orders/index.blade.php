@extends('layouts.app')

@section('title', 'Atölye')

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

    <x-page-header title="Atölye" subtitle="Fason işçilik: giriş, fire ve teslim takibi">
        <x-slot:actions>
            <a href="{{ route('work-orders.create') }}" class="btn btn-primary">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Atölyeye Giriş
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Atölyedeki ürün</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $summary['atolyede_adet'] }} fiş</div>
            <div class="mt-1 text-xs text-stone-500">{{ Amount::formatMilli($summary['atolyede_gram'], $gr) }} brüt</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Atölyedeki has</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-gold-800">{{ Amount::formatMilli($summary['atolyede_has'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">Firmalara ait emanet altın</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Bu ay fire</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-red-700">{{ Amount::formatMilli($summary['ay_fire'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">{{ $summary['ay_adet'] }} teslimde · ortalama %{{ number_format($summary['ay_fire_orani'], 2, ',', '.') }}</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Bu ay fire (has)</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-red-700">{{ Amount::formatMilli($summary['ay_fire_has'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">Rafineden geri kazanılabilecek</div>
        </div>
    </div>

    <div class="mt-6 mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="flex rounded-lg border border-stone-300 bg-white p-0.5 text-sm shadow-sm">
            @foreach (['atolyede' => 'Atölyede', 'teslim_edildi' => 'Teslim edilen', 'tumu' => 'Tümü'] as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['durum' => $value, 'page' => null]) }}"
                   @class(['rounded-md px-3 py-1.5', 'bg-stone-900 text-white' => $status === $value, 'text-stone-600 hover:bg-stone-100' => $status !== $value])>{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="durum" value="{{ $status }}">
            <input name="q" value="{{ request('q') }}" class="input w-48 py-1.5" placeholder="Fiş no, ürün, firma…" aria-label="Ara">
            <select name="cari" class="input w-auto py-1.5" aria-label="Firma">
                <option value="">Tüm firmalar</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((int) request('cari') === $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="input w-auto py-1.5" aria-label="Giriş başlangıç">
            <input type="date" name="bitis" value="{{ request('bitis') }}" class="input w-auto py-1.5" aria-label="Giriş bitiş">
            <button class="btn btn-secondary py-1.5">Filtrele</button>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fiş</th>
                        <th>Giriş</th>
                        <th>Firma / Ürün</th>
                        <th class="text-right">Giriş gr</th>
                        <th class="text-right">Milyem</th>
                        <th class="text-right">Has</th>
                        <th class="text-right">Çıkış gr</th>
                        <th class="text-right">Fire</th>
                        <th class="text-right">İşçilik</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('work-orders.show', $order) }}'">
                            <td>
                                <a href="{{ route('work-orders.show', $order) }}" class="font-mono text-xs font-medium text-stone-700 hover:text-gold-700">{{ $order->number }}</a>
                                <div>
                                    @if ($order->isDelivered())
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Teslim</span>
                                    @else
                                        <span class="badge bg-amber-50 text-amber-800 ring-amber-600/20">Atölyede</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-stone-600">{{ $order->received_at->format('d.m.Y') }}</td>
                            <td>
                                <div class="font-medium text-stone-900">{{ $order->account->name }}</div>
                                <div class="max-w-64 truncate text-xs text-stone-500">{{ $order->product }}</div>
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Amount::format($order->gross_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums text-stone-600">{{ Workshop::formatPurity($order->purity) }}</td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap text-gold-800">{{ Amount::format($order->has_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $order->gross_out !== null ? Amount::format($order->gross_out, $gr, false) : '—' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                @if ($order->fire_gram !== null)
                                    <span class="text-red-700">{{ Amount::format($order->fire_gram, $gr, false) }}</span>
                                    <div class="text-xs text-stone-500">%{{ number_format($order->fireRate(), 1, ',', '.') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $order->labor_total !== null ? Amount::format($order->labor_total, $order->laborCurrency) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-stone-500">
                                @if ($status === 'atolyede')
                                    Atölyede bekleyen ürün yok. <a href="{{ route('work-orders.create') }}" class="font-medium text-gold-700 hover:underline">Yeni giriş yapın</a>.
                                @else
                                    Kayıt bulunamadı.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
