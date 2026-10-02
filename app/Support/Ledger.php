<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ekstre: hareketleri tarih sırasıyla listeler, her satıra birim bazında
 * yürüyen bakiye ekler. Başlangıç tarihi verilirse öncesi "devir" olarak toplanır.
 */
class Ledger
{
    /**
     * @param  Builder  $query  Tek bir cari ya da kasanın hareketleri
     * @param  string  $directionColumn  'account_direction' veya 'cash_direction'
     * @return array{opening: array<int,int>, rows: Collection, closing: array<int,int>}
     */
    public static function build(Builder $query, string $directionColumn, ?string $from = null, ?string $to = null): array
    {
        $opening = [];

        if ($from) {
            $openingRows = (clone $query)
                ->where('date', '<', $from)
                ->groupBy('currency_id')
                ->selectRaw("currency_id, SUM(amount * {$directionColumn}) as total")
                ->toBase()
                ->get();

            foreach ($openingRows as $row) {
                $opening[$row->currency_id] = Amount::toMilli($row->total);
            }
        }

        $rows = (clone $query)
            ->when($from, fn ($q) => $q->where('date', '>=', $from))
            ->when($to, fn ($q) => $q->where('date', '<', Carbon::parse($to)->addDay()->toDateString()))
            ->with(['currency', 'account', 'cashRegister', 'creator'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $running = $opening;

        foreach ($rows as $row) {
            $effect = Amount::toMilli($row->amount) * $row->{$directionColumn};
            $running[$row->currency_id] = ($running[$row->currency_id] ?? 0) + $effect;

            $row->effect_milli = $effect;
            $row->running_milli = $running[$row->currency_id];
        }

        return [
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
        ];
    }
}
