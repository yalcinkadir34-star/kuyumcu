<?php

namespace Tests\Unit;

use App\Support\Amount;
use App\Support\Workshop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkshopTest extends TestCase
{
    public static function milyemler(): array
    {
        return [
            'virgüllü' => ['0,585', '0.5850'],
            'noktalı' => ['0.595', '0.5950'],
            'binde yazılmış' => ['585', '0.5850'],
            'baştaki virgül' => [',750', '0.7500'],
            'dört hane' => ['0,9165', '0.9165'],
            'saf' => ['1', '1.0000'],
            'bin' => ['1000', '1.0000'],
        ];
    }

    #[DataProvider('milyemler')]
    public function test_milyem_cozumlenir(string $girdi, string $beklenen): void
    {
        $this->assertSame($beklenen, Workshop::parsePurity($girdi));
    }

    public function test_gecersiz_milyem_reddedilir(): void
    {
        foreach (['', '0', 'abc', '1,5', '1001', '0,58555'] as $girdi) {
            $this->assertNull(Workshop::parsePurity($girdi), "'{$girdi}' reddedilmeliydi");
        }
    }

    public function test_has_karsiligi_hesaplanir(): void
    {
        // 200 gr × 0,585 = 117 gr has
        $this->assertSame(117_000, Workshop::hasMilli(Amount::toMilli('200'), '0.5850'));
        // 40 gr fire × 0,585 = 23,4 gr has
        $this->assertSame(23_400, Workshop::hasMilli(Amount::toMilli('40'), '0.5850'));
        // 123,457 × 0,595 = 73,456915 → 73,457
        $this->assertSame(73_457, Workshop::hasMilli(Amount::toMilli('123.457'), '0.5950'));
    }

    public function test_fire_orani_hesaplanir(): void
    {
        $this->assertSame(20.0, Workshop::fireRate(40_000, 200_000));
        $this->assertSame(0.0, Workshop::fireRate(0, 0));
    }

    public function test_iscilik_hesaplanir(): void
    {
        // Gram başı 15 TL × 160 gr = 2.400 TL
        $this->assertSame(2_400_000, Workshop::laborMilli('gram', 15_000, 160_000, 2));
        // Gram başı 0,012 has × 160,5 gr = 1,926 gr has
        $this->assertSame(1_926, Workshop::laborMilli('gram', 12, 160_500, 3));
        // Gram başı 2,5 TL × 33,333 gr = 83,3325 → 83,33 TL
        $this->assertSame(83_330, Workshop::laborMilli('gram', 2_500, 33_333, 2));
        // Toplam tutar olduğu gibi
        $this->assertSame(5_000_000, Workshop::laborMilli('toplam', 5_000_000, 160_000, 2));
    }

    public function test_milyem_bicimlendirilir(): void
    {
        $this->assertSame('0,585', Workshop::formatPurity('0.5850'));
        $this->assertSame('0,9165', Workshop::formatPurity('0.9165'));
        $this->assertSame('1,000', Workshop::formatPurity('1.0000'));
    }
}
