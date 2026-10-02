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
        // Küsurat atılır: 123,457 × 0,595 = 73,456915 → 73,456
        $this->assertSame(73_456, Workshop::hasMilli(Amount::toMilli('123.457'), '0.5950'));
        // Kullanıcının örneği: 26,25 × 0,595 = 15,61875 → 15,618
        $this->assertSame(15_618, Workshop::hasMilli(Amount::toMilli('26.25'), '0.5950'));
        // 6,97 × 0,625 = 4,35625 → 4,356
        $this->assertSame(4_356, Workshop::hasMilli(Amount::toMilli('6.97'), '0.6250'));
    }

    public function test_fire_orani_hesaplanir(): void
    {
        $this->assertSame(20.0, Workshop::fireRate(40_000, 200_000));
        $this->assertSame(0.0, Workshop::fireRate(0, 0));
    }

    public function test_milyem_bicimlendirilir(): void
    {
        $this->assertSame('0,585', Workshop::formatPurity('0.5850'));
        $this->assertSame('0,9165', Workshop::formatPurity('0.9165'));
        $this->assertSame('1,000', Workshop::formatPurity('1.0000'));
    }
}
