@extends('layouts.app')

@section('title', 'Giriş Düzenle')

@section('content')
    <div class="max-w-3xl">
        <x-page-header :title="$order->number.' · Giriş bilgileri'" subtitle="Gram, milyem veya müşteri değişirse cari has kaydı da güncellenir" :back="route('work-orders.show', $order)" />

        <form method="POST" action="{{ route('work-orders.update', $order) }}">
            @method('PUT')
            @include('work-orders._form')
        </form>

        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('work-orders.destroy', $order) }}" class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4"
                  data-confirm="{{ $order->number }} giriş fişi silinsin mi? Carideki giriş kaydı da silinecek.">
                @csrf
                @method('DELETE')
                <p class="text-sm text-stone-600">Yanlış girilen fişi silebilirsiniz.</p>
                <button class="btn btn-danger">Fişi Sil</button>
            </form>
        @endif
    </div>
@endsection
