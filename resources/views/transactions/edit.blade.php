@extends('layouts.app')

@section('title', 'Hareket Düzenle')

@section('content')
    <div class="max-w-3xl">
        <x-page-header title="Hareket Düzenle" :back="$back">
            <x-slot:subtitle>
                #{{ $transaction->id }} · Kaydeden: {{ $transaction->creator?->name ?? '—' }}, {{ $transaction->created_at->format('d.m.Y H:i') }}
            </x-slot:subtitle>
        </x-page-header>

        <form method="POST" action="{{ route('transactions.update', $transaction) }}">
            @method('PUT')
            @include('transactions._form')
        </form>

        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4"
                  data-confirm="Bu hareket kalıcı olarak silinsin mi? Bakiyeler yeniden hesaplanacak.">
                @csrf
                @method('DELETE')
                <p class="text-sm text-stone-600">Silinen hareket geri alınamaz.</p>
                <button class="btn btn-danger">Hareketi Sil</button>
            </form>
        @endif
    </div>
@endsection
