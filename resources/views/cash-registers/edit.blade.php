@extends('layouts.app')

@section('title', 'Kasa Düzenle')

@section('content')
    <div class="max-w-3xl">
        <x-page-header :title="$register->name" subtitle="Bilgileri düzenle" :back="route('cash-registers.show', $register)" />

        <form method="POST" action="{{ route('cash-registers.update', $register) }}">
            @method('PUT')
            @include('cash-registers._form')
        </form>

        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('cash-registers.destroy', $register) }}" class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4"
                  data-confirm="{{ $register->name }} silinsin mi?">
                @csrf
                @method('DELETE')
                <p class="text-sm text-stone-600">Sadece hiç hareketi olmayan kasalar silinebilir.</p>
                <button class="btn btn-danger">Kasayı Sil</button>
            </form>
        @endif
    </div>
@endsection
