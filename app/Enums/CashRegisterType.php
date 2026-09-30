<?php

namespace App\Enums;

enum CashRegisterType: string
{
    case Nakit = 'nakit';
    case Banka = 'banka';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Nakit => 'Nakit Kasa',
            self::Banka => 'Banka Hesabı',
            self::Pos => 'POS',
        };
    }
}
