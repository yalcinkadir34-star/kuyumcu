<?php

namespace App\Enums;

enum AccountType: string
{
    case Musteri = 'musteri';
    case Tedarikci = 'tedarikci';
    case Personel = 'personel';
    case Diger = 'diger';

    public function label(): string
    {
        return match ($this) {
            self::Musteri => 'Müşteri',
            self::Tedarikci => 'Tedarikçi',
            self::Personel => 'Personel',
            self::Diger => 'Diğer',
        };
    }
}
