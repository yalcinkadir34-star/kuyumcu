<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RamatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function giris(Account $firma, string $gram, string $milyem, string $tarih = '2026-10-01T10:00:00'): WorkOrder
    {
        $this->actingAs($this->user)->post(route('work-orders.store'), [
            'account_id' => $firma->id,
            'product' => '14 ayar',
            'received_at' => $tarih,
            'gross_in' => $gram,
            'purity' => $milyem,
        ])->assertSessionHasNoErrors();

        return WorkOrder::latest('id')->first();
    }

    private function cikis(WorkOrder $order, string $gram, string $milyem): void
    {
        $this->actingAs($this->user)->post(route('work-orders.deliver', $order), [
            'delivered_at' => '2026-10-02T10:00:00',
            'gross_out' => $gram,
            'purity_out' => $milyem,
        ])->assertSessionHasNoErrors();
    }

    public function test_kullanicinin_ornegi_musteri_bazinda_ramat(): void
    {
        $firma = Account::factory()->create(['name' => 'Örnek Döküm']);

        // 50 gr 0,585 ile girdi, 25 gr 0,625 ile çıktı → 25 gr ramatta
        $order = $this->giris($firma, '50', '0,585');
        $this->cikis($order, '25', '0,625');

        $this->actingAs($this->user)->get(route('ramat.index'))
            ->assertOk()
            ->assertSee('Örnek Döküm')
            ->assertSeeInOrder([
                '50,000',   // giren
                '25,000',   // çıkan
                '25,000',   // ramat gram
                '%50,0',
                '14,625',   // ramat has: 25 × 0,585
                '13,625',   // has borcu: 29,25 − 15,625
            ]);
    }

    public function test_toplam_ve_musteri_detayi(): void
    {
        $a = Account::factory()->create(['name' => 'Birinci Firma']);
        $b = Account::factory()->create(['name' => 'İkinci Firma']);

        $this->cikis($this->giris($a, '50', '0,585'), '42', '0,625');   // 8 gr ramat
        $this->cikis($this->giris($a, '20', '0,585'), '18', '0,625');   // 2 gr ramat
        $this->giris($b, '10', '0,585');                                  // 10 gr ramat (hiç çıkmadı)

        $this->actingAs($this->user)->get(route('ramat.index'))
            ->assertOk()
            ->assertSee('Toplam')
            ->assertSee('20,000 gr'); // 8 + 2 + 10

        // Müşteri detayı: sadece o müşterinin fişleri
        $this->get(route('ramat.index', ['cari' => $a->id]))
            ->assertOk()
            ->assertSee('Birinci Firma · fiş bazında ramat')
            ->assertSee('A00001')
            ->assertSee('A00002')
            ->assertDontSee('A00003');
    }

    public function test_tarih_araligi_filtresi(): void
    {
        $firma = Account::factory()->create(['name' => 'Tarih Firma']);
        $this->giris($firma, '10', '0,585', '2026-09-15T10:00:00');
        $this->giris($firma, '30', '0,585', '2026-10-01T10:00:00');

        $this->actingAs($this->user)
            ->get(route('ramat.index', ['baslangic' => '2026-10-01', 'bitis' => '2026-10-31']))
            ->assertOk()
            ->assertSee('30,000 gr')
            ->assertDontSee('40,000');
    }
}
