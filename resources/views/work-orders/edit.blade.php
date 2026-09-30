@extends('layouts.app')

@section('title', 'Giriş Düzenle')

@section('content')
    <div class="max-w-3xl">
        <x-page-header :title="$order->number.' · Giriş bilgileri'" :back="route('work-orders.show', $order)">
            <x-slot:subtitle>
                @if ($order->deliveries()->exists())
                    Çıkış yapılmış. Milyem veya firma değişirse çıkışların cari kayıtları da güncellenir.
                @else
                    Atölyedeki ürünün giriş bilgilerini düzenleyin
                @endif
            </x-slot:subtitle>
        </x-page-header>

        <form method="POST" action="{{ route('work-orders.update', $order) }}">
            @method('PUT')
            @include('work-orders._form')
        </form>

        @if (auth()->user()->isAdmin() && ! $order->deliveries()->exists())
            <form method="POST" action="{{ route('work-orders.destroy', $order) }}" class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4"
                  data-confirm="{{ $order->number }} fişi silinsin mi?">
                @csrf
                @method('DELETE')
                <p class="text-sm text-stone-600">Yanlış girilen fişi silebilirsiniz.</p>
                <button class="btn btn-danger">Fişi Sil</button>
            </form>
        @endif
    </div>
@endsection
