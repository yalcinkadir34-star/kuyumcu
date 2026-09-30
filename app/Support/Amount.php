<?php

namespace App\Support;

use App\Models\Currency;

/**
 * Tutar yardımcıları.
 *
 * Hesaplamalar kayan nokta (float) hatası olmasın diye "binde bir" birimli
 * tam sayılarla yapılır: 1234,567 → 1234567.
 */
class Amount
{
    public const SCALE = 1000;

    /**
     * Kullanıcının girdiği tutarı "1234.567" biçimine çevirir; geçersizse null.
     *
     * Kabul edilenler: "1234", "1234,5", "1.234,56", "1234.5", "1.234.567"
     * Belirsiz olduğu için reddedilen: "1.500" (bin beş yüz mü, bir buçuk mu?)
     */
    public static function parse(?string $input): ?string
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim((string) $input));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            // Türk biçimi: nokta binlik ayırıcı, virgül ondalık
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } elseif (preg_match('/^\d+\.\d{3}$/', $value)) {
            return null;
        }

        if (! preg_match('/^\d{1,15}(\.\d+)?$/', $value)) {
            return null;
        }

        [$int, $frac] = array_pad(explode('.', $value), 2, '');
        $int = ltrim($int, '0') ?: '0';

        return $frac === '' ? $int : $int.'.'.$frac;
    }

    /** Ondalık basamak sayısı (ör. "12.50" → 2, "12" → 0). */
    public static function decimalsOf(string $normalized): int
    {
        $parts = explode('.', $normalized);

        return isset($parts[1]) ? strlen(rtrim($parts[1], '0')) : 0;
    }

    public static function toMilli(string|int|float|null $value): int
    {
        $value = is_float($value) || (is_string($value) && stripos($value, 'e') !== false)
            ? sprintf('%.3F', (float) $value)
            : (string) ($value ?? '0');
        $negative = str_starts_with($value, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($value, '-+')), 2, '');
        $milli = (int) $int * self::SCALE + (int) str_pad(substr($frac, 0, 3), 3, '0');

        return $negative ? -$milli : $milli;
    }

    /**
     * Binde bir birimli tam sayıyı ekranda gösterilecek biçime çevirir.
     * Örn: 1234567, TL → "1.234,57 ₺"   |   1500, HAS → "1,500 gr"
     */
    public static function formatMilli(int $milli, ?Currency $currency = null, bool $withSymbol = true): string
    {
        $decimals = $currency?->decimals ?? 2;
        $negative = $milli < 0;
        $abs = abs($milli);

        // Gösterilecek basamağa yuvarla (yarım yukarı)
        $factor = 10 ** (3 - $decimals);
        $rounded = intdiv($abs + intdiv($factor, 2), $factor);

        $divisor = 10 ** $decimals;
        $int = number_format(intdiv($rounded, $divisor), 0, ',', '.');
        $frac = $decimals > 0 ? ','.str_pad((string) ($rounded % $divisor), $decimals, '0', STR_PAD_LEFT) : '';

        $text = ($negative && $rounded > 0 ? '-' : '').$int.$frac;

        return $withSymbol && $currency ? $text.' '.$currency->symbol : $text;
    }

    public static function format(string|int|float|null $value, ?Currency $currency = null, bool $withSymbol = true): string
    {
        return self::formatMilli(self::toMilli($value), $currency, $withSymbol);
    }

    /** Binde bir birimli sayıyı istenen ondalık basamağa yuvarlar (yarım yukarı). */
    public static function roundMilli(int $milli, int $decimals): int
    {
        $factor = 10 ** (3 - $decimals);
        $rounded = intdiv(abs($milli) + intdiv($factor, 2), $factor) * $factor;

        return $milli < 0 ? -$rounded : $rounded;
    }

    /** Binde bir birimli sayıyı veritabanı biçimine çevirir: 1234567 → "1234.567" */
    public static function fromMilli(int $milli): string
    {
        $sign = $milli < 0 ? '-' : '';
        $abs = abs($milli);

        return $sign.intdiv($abs, self::SCALE).'.'.str_pad((string) ($abs % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Form alanında gösterim için: "1234.500" → "1234,5" */
    public static function forInput(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $milli = self::toMilli($value);
        $frac = rtrim(str_pad((string) (abs($milli) % self::SCALE), 3, '0', STR_PAD_LEFT), '0');

        return ($milli < 0 ? '-' : '').intdiv(abs($milli), self::SCALE).($frac !== '' ? ','.$frac : '');
    }
}
