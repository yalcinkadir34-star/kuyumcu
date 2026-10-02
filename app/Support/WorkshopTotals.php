<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Atölye toplamları, müşteri bazında. Giriş (work_orders) ve çıkış (work_order_deliveries)
 * birbirinden bağımsızdır; ramat = müşterinin girişleri − atölye çıkışları.
 * "Satış" türündeki çıkışlar (atölyenin kendi ürünü) bu toplamlara girmez.
 *
 * Değerler "binde bir" birimli tam sayılardır (bkz. Amount).
 *   ramat_has: ramat gramı × müşterinin ortalama giriş milyemi (giriş has / giriş gram)
 *   has_borcu: giriş has − çıkış has (müşterinin carisinde biriken has borcumuz)
 */
class WorkshopTotals
{
    /**
     * @param  array<int>|null  $accountIds  null = tüm müşteriler
     * @return array<int, array> account_id => toplamlar
     */
    public static function forAccounts(?array $accountIds = null, ?string $from = null, ?string $to = null): array
    {
        $until = $to ? Carbon::parse($to)->addDay()->toDateString() : null;

        $in = DB::table('work_orders')
            ->when($accountIds !== null, fn ($q) => $q->whereIn('account_id', $accountIds))
            ->when($from, fn ($q) => $q->where('received_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('received_at', '<', $until))
            ->groupBy('account_id')
            ->selectRaw('account_id, COUNT(*) as adet, SUM(gross_in) as gram, SUM(has_in) as has')
            ->get()->keyBy('account_id');

        // Sadece atölyedeki üründen yapılan çıkışlar ramatı etkiler (satış çıkışları hariç)
        $out = DB::table('work_order_deliveries')
            ->where('kind', 'atolye')
            ->when($accountIds !== null, fn ($q) => $q->whereIn('account_id', $accountIds))
            ->when($from, fn ($q) => $q->where('delivered_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('delivered_at', '<', $until))
            ->groupBy('account_id')
            ->selectRaw('account_id, COUNT(*) as adet, SUM(gross_out) as gram, SUM(has_out) as has')
            ->get()->keyBy('account_id');

        $result = [];

        foreach ($in->keys()->merge($out->keys())->unique() as $accountId) {
            $result[$accountId] = self::build($in->get($accountId), $out->get($accountId));
        }

        return $result;
    }

    /** Tek müşterinin toplamları (hareketi yoksa sıfırlar). */
    public static function forAccount(int $accountId): array
    {
        return self::forAccounts([$accountId])[$accountId] ?? self::build(null, null);
    }

    /** Birden fazla müşterinin toplamlarını birleştirir (toplam satırı için). */
    public static function sum(array $rows): array
    {
        $total = self::build(null, null);

        foreach ($rows as $row) {
            foreach (['giris_adet', 'giris_gram', 'giris_has', 'cikis_adet', 'cikis_gram', 'cikis_has', 'ramat_gram', 'ramat_has', 'has_borcu'] as $key) {
                $total[$key] += $row[$key];
            }
        }

        $total['oran'] = Workshop::fireRate($total['ramat_gram'], $total['giris_gram']);

        return $total;
    }

    private static function build(?object $in, ?object $out): array
    {
        $inGram = Amount::toMilli($in->gram ?? 0);
        $inHas = Amount::toMilli($in->has ?? 0);
        $outGram = Amount::toMilli($out->gram ?? 0);
        $outHas = Amount::toMilli($out->has ?? 0);
        $ramatGram = $inGram - $outGram;

        return [
            'giris_adet' => (int) ($in->adet ?? 0),
            'giris_gram' => $inGram,
            'giris_has' => $inHas,
            'cikis_adet' => (int) ($out->adet ?? 0),
            'cikis_gram' => $outGram,
            'cikis_has' => $outHas,
            'ramat_gram' => $ramatGram,
            // Ortalama giriş milyemiyle (küsurat atılır, has hesabındaki gibi)
            'ramat_has' => $inGram > 0 ? intdiv($ramatGram * $inHas, $inGram) : 0,
            'has_borcu' => $inHas - $outHas,
            'oran' => Workshop::fireRate($ramatGram, $inGram),
        ];
    }
}
