@extends('layouts.app')

@section('title', 'Cari Düzenle')

@section('content')
    <div class="max-w-3xl">
        <x-page-header :title="$account->name" :subtitle="$account->code.' · Bilgileri düzenle'" :back="route('accounts.show', $account)" />

        <form method="POST" action="{{ route('accounts.update', $account) }}">
            @method('PUT')
            @include('accounts._form')
        </form>

        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4"
                  data-confirm="{{ $account->name }} carisi silinsin mi?">
                @csrf
                @method('DELETE')
                <p class="text-sm text-stone-600">Sadece hiç hareketi olmayan cariler silinebilir.</p>
                <button class="btn btn-danger">Cariyi Sil</button>
            </form>
        @endif
    </div>
@endsection
