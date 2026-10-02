<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaporTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $firma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->firma = Account::factory()->create(['name' => 'Rapor Firması']);
    }

    private function giris(string $gram, string $milyem, string $tarih): WorkOrder
    {
        $this->actingAs($this->user)->post(route('work-orders.store'), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar zincir',
            'received_at' => $tarih,
            'gross_in' => $gram,
            'purity' => $milyem,
        ])->assertSessionHasNoErrors();

        return WorkOrder::latest('id')->first();
    }

    private function cikis(WorkOrder $order, string $gram, string $milyem, string $tarih): void
    {
        $this->actingAs($this->user)->post(route('workshop-deliveries.store'), [
            'account_id' => $order->account_id,
            'delivered_at' => $tarih,
            'gross_out' => $gram,
            'purity_out' => $milyem,
            'kind' => 'atolye',
        ])->assertSessionHasNoErrors();
    }

    public function test_musteri_secilmeden_secim_ekrani_acilir(): void
    {
        $this->actingAs($this->user)->get(route('reports.customer'))
            ->assertOk()
            ->assertSee('Raporu Göster')
            ->assertDontSee('Hareket Dökümü');
    }

    public function test_yillik_musteri_raporu(): void
    {
        // Geçen yıldan devir: 10 gr × 0,585 = 5,85 has
        $this->giris('10', '0,585', '2025-12-20T10:00:00');

        // Bu yıl: Ocak'ta 50 gr 0,595 giriş (29,75 has), Mart'ta 25 gr 0,625 çıkış (15,625 has)
        $order = $this->giris('50', '0,595', '2026-01-15T09:30:00');
        $this->cikis($order, '25', '0,625', '2026-03-10T14:00:00');

        $this->actingAs($this->user)
            ->get(route('reports.customer', ['cari' => $this->firma->id, 'yil' => 2026]))
            ->assertOk()
            ->assertSee('Müşteri Hesap Raporu')
            ->assertSee('Rapor Firması')
            ->assertSee('2026 yılı')
            ->assertSee('Alacağınız 5,850 gr')          // dönem başı (devir)
            ->assertSee('29,750')                        // verilen has
            ->assertSee('15,625')                        // teslim has
            ->assertSee('Alacağınız 19,975 gr')          // dönem sonu: 5,85 + 29,75 − 15,625
            ->assertSeeInOrder(['Ocak', '50,000', '29,750', 'Şubat', 'Mart', '25,000', '15,625'])
            ->assertSee('Atölye girişi · 14 ayar zincir')
            ->assertSee('Teslim')
            ->assertSee('0,625')
            ->assertSee('A00002')
            ->assertSee('T00001');
    }

    public function test_ozel_tarih_araligi(): void
    {
        $this->giris('10', '0,585', '2026-01-10T10:00:00');
        $this->giris('20', '0,585', '2026-06-10T10:00:00');

        $this->actingAs($this->user)
            ->get(route('reports.customer', ['cari' => $this->firma->id, 'baslangic' => '2026-06-01', 'bitis' => '2026-06-30']))
            ->assertOk()
            ->assertSee('01.06.2026 – 30.06.2026')
            ->assertSee('Alacağınız 5,850 gr')   // devir: Ocak girişi
            ->assertSee('11,700')                 // Haziran girişi: 20 × 0,585
            ->assertDontSee('Aylık Özet');
    }

    public function test_elle_girilen_cari_hareket_raporda_gorunur(): void
    {
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'type' => 'cari_alacak',
            'account_id' => $this->firma->id,
            'currency_id' => Currency::firstWhere('code', 'HAS')->id,
            'amount' => '46,43',
            'date' => '2026-02-01T10:00:00',
            'description' => 'Açılış bakiyesi',
        ]);

        $this->actingAs($this->user)
            ->get(route('reports.customer', ['cari' => $this->firma->id, 'yil' => 2026]))
            ->assertOk()
            ->assertSee('Açılış bakiyesi')
            ->assertSee('Alacağınız 46,430 gr');
    }
}
