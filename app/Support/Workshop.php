<?php

namespace App\Support;

/**
 * Atölye hesapları. Gramlar "binde bir" birimli tam sayı (Amount), milyem ise
 * "on binde bir" birimli tam sayı olarak hesaplanır: 0,585 → 5850.
 */
class Workshop
{
    public const PURITY_SCALE = 10000;

    /**
     * Kullanıcının girdiği milyemi "0.5850" biçimine çevirir; geçersizse null.
     * Kabul edilenler: "0,585", "0.585", ",585", "585" (binde olarak), "0,9165"
     */
    public static function parsePurity(?string $input): ?string
    {
        $value = str_replace(' ', '', trim((string) $input));

        if (str_starts_with($value, ',') || str_starts_with($value, '.')) {
            $value = '0'.$value;
        }

        $value = str_replace(',', '.', $value);

        if (! preg_match('/^\d+(\.\d{1,4})?$/', $value)) {
            return null;
        }

        // "585" gibi binde yazılmış değerler
        if (! str_contains($value, '.') && (int) $value > 1 && (int) $value <= 1000) {
            $value = '0.'.str_pad($value, 3, '0', STR_PAD_LEFT);

            if ($value === '0.1000') {
                $value = '1';
            }
        }

        $scaled = self::purityToInt($value);

        if ($scaled <= 0 || $scaled > self::PURITY_SCALE) {
            return null;
        }

        return intdiv($scaled, self::PURITY_SCALE).'.'.str_pad((string) ($scaled % self::PURITY_SCALE), 4, '0', STR_PAD_LEFT);
    }

    public static function purityToInt(string|float|null $purity): int
    {
        [$int, $frac] = array_pad(explode('.', (string) ($purity ?? '0')), 2, '');

        return (int) $int * self::PURITY_SCALE + (int) str_pad(substr($frac, 0, 4), 4, '0');
    }

    /** Ekranda milyem: "0.5850" → "0,585" */
    public static function formatPurity(string|float|null $purity): string
    {
        $scaled = self::purityToInt($purity);
        $frac = rtrim(str_pad((string) ($scaled % self::PURITY_SCALE), 4, '0', STR_PAD_LEFT), '0');

        return intdiv($scaled, self::PURITY_SCALE).','.str_pad($frac, 3, '0');
    }

    /** Has karşılığı: gram × milyem (binde bir gram, yarım yukarı yuvarlanır). */
    public static function hasMilli(int $gramMilli, string|float $purity): int
    {
        $product = $gramMilli * self::purityToInt($purity);
        $half = intdiv(self::PURITY_SCALE, 2);

        return $product >= 0
            ? intdiv($product + $half, self::PURITY_SCALE)
            : -intdiv(-$product + $half, self::PURITY_SCALE);
    }

    /** Fire yüzdesi (ör. 20.0). Giriş sıfırsa 0. */
    public static function fireRate(int $fireMilli, int $grossInMilli): float
    {
        return $grossInMilli > 0 ? round($fireMilli * 100 / $grossInMilli, 2) : 0.0;
    }

    /**
     * İşçilik toplamı (binde bir birim), birimin ondalığına yuvarlanmış.
     * 'gram': gram başı ücret × çıkış gramı | 'toplam': girilen tutar olduğu gibi
     */
    public static function laborMilli(string $basis, int $rateMilli, int $grossOutMilli, int $decimals): int
    {
        $total = $basis === 'gram'
            ? intdiv($rateMilli * $grossOutMilli + intdiv(Amount::SCALE, 2), Amount::SCALE)
            : $rateMilli;

        return Amount::roundMilli($total, $decimals);
    }
}
