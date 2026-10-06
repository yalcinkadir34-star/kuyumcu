@extends('layouts.app')

@section('title', 'Atölye · Çıkışlar')

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

    {{-- Yeni çıkıştan sonra: müşteri fişi --}}
    @if (session('receipt_delivery_id') && ($justDelivered = \App\Models\WorkOrderDelivery::find(session('receipt_delivery_id'))))
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gold-200 bg-gold-50 px-5 py-4">
            <div class="text-sm text-gold-900">
                <span class="font-semibold">Müşteri fişi hazır.</span>
                {{ $justDelivered->number }} · {{ $justDelivered->account->name }} · {{ Amount::formatMilli($justDelivered->grossOutMilli(), $gr) }} ({{ $justDelivered->lines->count() }} satır)
            </div>
            <a href="{{ route('workshop-deliveries.receipt', $justDelivered) }}" target="_blank" class="btn btn-gold">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                Fişi Yazdır
            </a>
        </div>
    @endif

    @include('work-orders._header', ['active' => 'cikis'])

    <form method="GET" class="mt-4 mb-4 flex flex-wrap items-center justify-end gap-2">
        <input name="q" value="{{ request('q') }}" class="input w-48 py-1.5" placeholder="Çıkış no, ürün, müşteri…" aria-label="Ara">
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
                        <th>No</th>
                        <th>Tarih</th>
                        <th>Müşteri / Ürün</th>
                        <th class="text-right">Gram</th>
                        <th class="text-right">Satır</th>
                        <th class="text-right">Has</th>
                        <th class="w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="font-mono text-xs font-medium text-stone-700">{{ $delivery->number }}</span>
                                @if ($delivery->isSale())
                                    <div><span class="badge bg-violet-50 text-violet-700 ring-violet-600/20" title="Atölyenin kendi ürünü, ramatı etkilemez">Satış</span></div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-stone-600">{{ $delivery->delivered_at->format('d.m.Y H:i:s') }}</td>
                            <td>
                                <div class="font-medium text-stone-900">{{ $delivery->account->name }}</div>
                                @if ($delivery->productsLabel() || $delivery->notes)
                                    <div class="max-w-64 truncate text-xs text-stone-500">{{ collect([$delivery->productsLabel(), $delivery->notes])->filter()->join(' · ') }}</div>
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Amount::formatMilli($delivery->grossOutMilli(), $gr, false) }}</td>
                            <td class="text-right tabular-nums text-stone-600" title="{{ $delivery->lines->map(fn ($l) => Workshop::formatPurity($l->purity_out))->join(', ') }}">{{ $delivery->lines->count() }}</td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap text-gold-800">{{ Amount::formatMilli($delivery->hasOutMilli(), $gr, false) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('workshop-deliveries.receipt', $delivery) }}" target="_blank"
                                   class="inline-flex items-center gap-1 rounded px-1.5 py-1 text-xs font-medium text-stone-500 hover:bg-gold-50 hover:text-gold-800" title="Müşteri fişini yazdır">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                                    Fiş
                                </a>
                                @if (auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('workshop-deliveries.destroy', $delivery) }}" class="inline"
                                          data-confirm="{{ $delivery->number }} çıkışı silinsin mi? Cari kaydı da geri alınacak.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded p-1 text-stone-400 hover:bg-red-50 hover:text-red-600" title="Çıkışı sil" aria-label="Çıkışı sil">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-stone-500">
                                @if (request()->hasAny(['q', 'cari', 'baslangic', 'bitis']))
                                    Kayıt bulunamadı.
                                @else
                                    Henüz çıkış yok. <a href="{{ route('workshop-deliveries.create') }}" class="font-medium text-gold-700 hover:underline">Atölyeden çıkış yapın</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $deliveries->links() }}</div>
@endsection
