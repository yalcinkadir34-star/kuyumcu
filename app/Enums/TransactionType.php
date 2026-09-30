<?php

namespace App\Enums;

/**
 * Hareket türleri ve cari/kasa üzerindeki etkileri.
 *
 * Cari yönü:  +1 = cari borçlanır (bize borçlanır), -1 = cari alacaklanır (biz borçlanırız)
 * Kasa yönü:  +1 = kasaya giriş, -1 = kasadan çıkış
 */
enum TransactionType: string
{
    case Tahsilat = 'tahsilat';
    case Odeme = 'odeme';
    case CariBorc = 'cari_borc';
    case CariAlacak = 'cari_alacak';
    case KasaGiris = 'kasa_giris';
    case KasaCikis = 'kasa_cikis';

    public function label(): string
    {
        return match ($this) {
            self::Tahsilat => 'Tahsilat',
            self::Odeme => 'Ödeme',
            self::CariBorc => 'Cari Borçlandırma',
            self::CariAlacak => 'Cari Alacaklandırma',
            self::KasaGiris => 'Kasa Giriş',
            self::KasaCikis => 'Kasa Çıkış',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Tahsilat => 'Cariden para/altın alındı: kasaya girer, carinin borcu azalır.',
            self::Odeme => 'Cariye para/altın verildi: kasadan çıkar, cari borçlanır.',
            self::CariBorc => 'Veresiye satış, işçilik vb.: cari borçlanır, kasa değişmez.',
            self::CariAlacak => 'Cariden emanet/alış vb.: cari alacaklanır, kasa değişmez.',
            self::KasaGiris => 'Cari dışı gelir: kasaya girer.',
            self::KasaCikis => 'Cari dışı gider (kira, fatura vb.): kasadan çıkar.',
        };
    }

    public function accountDirection(): int
    {
        return match ($this) {
            self::Tahsilat, self::CariAlacak => -1,
            self::Odeme, self::CariBorc => 1,
            self::KasaGiris, self::KasaCikis => 0,
        };
    }

    public function cashDirection(): int
    {
        return match ($this) {
            self::Tahsilat, self::KasaGiris => 1,
            self::Odeme, self::KasaCikis => -1,
            self::CariBorc, self::CariAlacak => 0,
        };
    }

    public function requiresAccount(): bool
    {
        return $this->accountDirection() !== 0;
    }

    public function requiresCashRegister(): bool
    {
        return $this->cashDirection() !== 0;
    }

    /** Arayüzdeki rozet renkleri (Tailwind sınıfları). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Tahsilat, self::KasaGiris => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Odeme, self::KasaCikis => 'bg-red-50 text-red-700 ring-red-600/20',
            self::CariBorc => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::CariAlacak => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        };
    }
}
