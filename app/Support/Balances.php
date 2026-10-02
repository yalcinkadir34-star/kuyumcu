<?php

namespace App\Support;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Bakiye sorguları. Tüm sonuçlar "binde bir" birimli tam sayıdır (bkz. Amount).
 */
class Balances
{
    /**
     * Carilerin birim bazında bakiyeleri.
     * Pozitif: cari bize borçlu, negatif: biz cariye borçluyuz.
     *
     * @param  array<int>  $accountIds
     * @return array<int, array<int, int>> account_id => [currency_id => milli]
     */
    public static function forAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $rows = DB::table('transactions')
            ->whereIn('account_id', $accountIds)
            ->where('account_direction', '!=', 0)
            ->groupBy('account_id', 'currency_id')
            ->selectRaw('account_id, currency_id, SUM(amount * account_direction) as total')
            ->get();

        return self::group($rows, 'account_id');
    }

    /**
     * Bir carinin, verilen hareket anına kadarki (o hareket dahil) bakiyesi.
     * Fişlerde "bu işlemden sonraki bakiye" için: fiş sonradan yazdırılsa da aynı kalır.
     *
     * @return array<int, int> currency_id => milli
     */
    public static function forAccountUntil(int $accountId, string $dateTime, int $transactionId): array
    {
        $rows = DB::table('transactions')
            ->where('account_id', $accountId)
            ->where('account_direction', '!=', 0)
            ->where(fn ($q) => $q->where('date', '<', $dateTime)
                ->orWhere(fn ($q) => $q->where('date', $dateTime)->where('id', '<=', $transactionId)))
            ->groupBy('account_id', 'currency_id')
            ->selectRaw('account_id, currency_id, SUM(amount * account_direction) as total')
            ->get();

        return self::group($rows, 'account_id')[$accountId] ?? [];
    }

    /**
     * @param  array<int>  $cashRegisterIds
     * @return array<int, array<int, int>> cash_register_id => [currency_id => milli]
     */
    public static function forCashRegisters(array $cashRegisterIds): array
    {
        if ($cashRegisterIds === []) {
            return [];
        }

        $rows = DB::table('transactions')
            ->whereIn('cash_register_id', $cashRegisterIds)
            ->where('cash_direction', '!=', 0)
            ->groupBy('cash_register_id', 'currency_id')
            ->selectRaw('cash_register_id, currency_id, SUM(amount * cash_direction) as total')
            ->get();

        return self::group($rows, 'cash_register_id');
    }

    /**
     * Genel bilanço: her birim için kasa mevcudu, atölyedeki has, cari alacak/borç
     * toplamları ve net durum.
     *
     * @return array<int, array{kasa:int, atolye:int, alacak:int, borc:int, net:int}> currency_id => değerler
     */
    public static function summary(): array
    {
        $result = [];
        $empty = ['kasa' => 0, 'atolye' => 0, 'alacak' => 0, 'borc' => 0, 'net' => 0];

        // Atölyede işlem gören ürünlerin has karşılığı (firmalara ait, karşılığı carilerde alacak olarak duruyor)
        $hasId = DB::table('currencies')->where('code', 'HAS')->value('id');
        // Atölyede (ramatta) kalan: kalan gram × giriş milyemi, tüm fişler
        $inWorkshop = WorkOrder::query()->withDeliveryTotals()->get()
            ->sum(fn (WorkOrder $order) => $order->remainingHasMilli());

        if ($hasId && $inWorkshop !== 0) {
            $result[$hasId] = [...$empty, 'atolye' => $inWorkshop];
        }

        $cash = DB::table('transactions')
            ->where('cash_direction', '!=', 0)
            ->groupBy('currency_id')
            ->selectRaw('currency_id, SUM(amount * cash_direction) as total')
            ->get();

        foreach ($cash as $row) {
            $result[$row->currency_id] ??= $empty;
            $result[$row->currency_id]['kasa'] = Amount::toMilli($row->total);
        }

        // Her carinin bakiyesi ayrı hesaplanır; borçlu olanlar alacağımız, alacaklı olanlar borcumuzdur.
        $accounts = DB::table('transactions')
            ->whereNotNull('account_id')
            ->where('account_direction', '!=', 0)
            ->groupBy('account_id', 'currency_id')
            ->selectRaw('account_id, currency_id, SUM(amount * account_direction) as total')
            ->get();

        foreach ($accounts as $row) {
            $milli = Amount::toMilli($row->total);
            $result[$row->currency_id] ??= $empty;

            if ($milli > 0) {
                $result[$row->currency_id]['alacak'] += $milli;
            } elseif ($milli < 0) {
                $result[$row->currency_id]['borc'] += -$milli;
            }
        }

        foreach ($result as &$values) {
            $values['net'] = $values['kasa'] + $values['atolye'] + $values['alacak'] - $values['borc'];
        }

        return $result;
    }

    private static function group(iterable $rows, string $key): array
    {
        $result = [];

        foreach ($rows as $row) {
            $milli = Amount::toMilli($row->total);

            if ($milli !== 0) {
                $result[$row->{$key}][$row->currency_id] = $milli;
            }
        }

        return $result;
    }
}
