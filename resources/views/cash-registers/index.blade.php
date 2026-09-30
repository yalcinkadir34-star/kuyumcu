@extends('layouts.app')

@section('title', 'Kasalar')

@section('content')
    <x-page-header title="Kasalar" subtitle="Nakit kasa, banka ve POS hesaplarının birim bazında mevcudu">
        <x-slot:actions>
            <a href="{{ route('cash-registers.create') }}" class="btn btn-primary">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Kasa
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kasa</th>
                        <th>Tür</th>
                        @foreach ($currencies as $currency)
                            <th class="text-right">{{ $currency->code === 'HAS' ? 'Has (gr)' : $currency->code }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registers as $register)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('cash-registers.show', $register) }}'">
                            <td>
                                <a href="{{ route('cash-registers.show', $register) }}" class="font-medium text-stone-900 hover:text-gold-700">{{ $register->name }}</a>
                                @unless ($register->is_active)
                                    <span class="badge ml-1 bg-stone-100 text-stone-500 ring-stone-300">Pasif</span>
                                @endunless
                            </td>
                            <td class="text-stone-600">{{ $register->type->label() }}</td>
                            @foreach ($currencies as $currency)
                                <td class="text-right">
                                    <x-kasa-bakiye :milli="$balances[$register->id][$currency->id] ?? 0" :currency="$currency" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($registers->count() > 1)
                    <tfoot>
                        <tr class="bg-stone-50">
                            <td colspan="2" class="px-4 py-2.5 text-sm font-semibold text-stone-600">Toplam</td>
                            @foreach ($currencies as $currency)
                                <td class="px-4 py-2.5 text-right">
                                    <x-kasa-bakiye :milli="collect($balances)->sum(fn ($b) => $b[$currency->id] ?? 0)" :currency="$currency" />
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
