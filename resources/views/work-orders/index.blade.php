@extends('layouts.app')

@section('title', 'Atölye · Girişler')

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

    @include('work-orders._header', ['active' => 'giris'])

    <form method="GET" class="mt-4 mb-4 flex flex-wrap items-center justify-end gap-2">
        <input name="q" value="{{ request('q') }}" class="input w-48 py-1.5" placeholder="Fiş no, ürün, müşteri…" aria-label="Ara">
        <select name="cari" class="input w-auto py-1.5" aria-label="Müşteri">
            <option value="">Tüm müşteriler</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) request('cari') === $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
        <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="input w-auto py-1.5" aria-label="Başlangıç">
        <input type="date" name="bitis" value="{{ request('bitis') }}" class="input w-auto py-1.5" aria-label="Bitiş">
        <button class="btn btn-secondary py-1.5">Filtrele</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fiş</th>
                        <th>Tarih</th>
                        <th>Müşteri / Ürün</th>
                        <th class="text-right">Gram</th>
                        <th class="text-right">Milyem</th>
                        <th class="text-right">Has</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('work-orders.show', $order) }}'">
                            <td>
                                <a href="{{ route('work-orders.show', $order) }}" class="font-mono text-xs font-medium text-stone-700 hover:text-gold-700">{{ $order->number }}</a>
                            </td>
                            <td class="whitespace-nowrap text-stone-600">{{ $order->received_at->format('d.m.Y H:i:s') }}</td>
                            <td>
                                <div class="font-medium text-stone-900">{{ $order->account->name }}</div>
                                <div class="max-w-64 truncate text-xs text-stone-500">{{ $order->product }}</div>
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Amount::format($order->gross_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums text-stone-600">{{ Workshop::formatPurity($order->purity) }}</td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap text-gold-800">{{ Amount::format($order->has_in, $gr, false) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-stone-500">
                                @if (request()->hasAny(['q', 'cari', 'baslangic', 'bitis']))
                                    Kayıt bulunamadı.
                                @else
                                    Henüz atölye girişi yok. <a href="{{ route('work-orders.create') }}" class="font-medium text-gold-700 hover:underline">Yeni giriş yapın</a>.
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
