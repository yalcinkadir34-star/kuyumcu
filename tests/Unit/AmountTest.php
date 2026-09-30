<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Support\Amount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountTest extends TestCase
{
    public static function gecerliGirdiler(): array
    {
        return [
            'tam sayı' => ['1500', '1500'],
            'virgüllü' => ['2,5', '2.5'],
            'binlik ve virgül' => ['1.250,50', '1250.50'],
            'çok binlik noktalı' => ['1.250.000', '1250000'],
            'noktalı ondalık' => ['12.5', '12.5'],
            'boşluklu' => [' 1 250,75 ', '1250.75'],
            'gram' => ['12,345', '12.345'],
            'baştaki sıfır' => ['007', '7'],
        ];
    }

    #[DataProvider('gecerliGirdiler')]
    public function test_gecerli_tutarlar_cozumlenir(string $girdi, string $beklenen): void
    {
        $this->assertSame($beklenen, Amount::parse($girdi));
    }

    public static function gecersizGirdiler(): array
    {
        return [
            'boş' => [''],
            'harf' => ['abc'],
            'belirsiz nokta' => ['1.500'],
            'eksi' => ['-5'],
            'iki virgül' => ['1,2,3'],
        ];
    }

    #[DataProvider('gecersizGirdiler')]
    public function test_gecersiz_tutarlar_reddedilir(string $girdi): void
    {
        $this->assertNull(Amount::parse($girdi));
    }

    public function test_bicimlendirme_turk_formatinda_yapilir(): void
    {
        $tl = new Currency(['symbol' => '₺', 'decimals' => 2]);
        $has = new Currency(['symbol' => 'gr', 'decimals' => 3]);

        $this->assertSame('1.234.567,89 ₺', Amount::format('1234567.890', $tl));
        $this->assertSame('-50,00 ₺', Amount::format('-50', $tl));
        $this->assertSame('117,000 gr', Amount::format('117', $has));
        $this->assertSame('0,01 ₺', Amount::format('0.005', $tl));
    }

    public function test_binde_bir_hesap_kayan_nokta_hatasi_yapmaz(): void
    {
        $toplam = Amount::toMilli('0.1') + Amount::toMilli('0.2');

        $this->assertSame(300, $toplam);
        $this->assertSame(-1250500, Amount::toMilli('-1250.5'));
        $this->assertSame('1250,5', Amount::forInput('1250.500'));
    }
}
