<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Balances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtolyeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $firma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->firma = Account::factory()->create(['name' => 'Döküm Firması']);
    }

    private function giris(array $data = []): WorkOrder
    {
        $this->actingAs($this->user)->post(route('work-orders.store'), $data + [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01',
            'gross_in' => '200',
            'purity' => '0,585',
        ])->assertSessionHasNoErrors();

        return WorkOrder::latest('id')->first();
    }

    private function cikis(WorkOrder $order, array $data = [])
    {
        return $this->actingAs($this->user)->post(route('work-orders.deliver', $order), $data + [
            'delivered_at' => '2026-09-10',
            'gross_out' => '160',
            'labor_basis' => 'gram',
            'labor_rate' => '15',
            'labor_currency_id' => $this->birim('TRY')->id,
        ]);
    }

    private function birim(string $code): Currency
    {
        return Currency::firstWhere('code', $code);
    }

    private function bakiye(string $code): int
    {
        return $this->firma->balances()[$this->birim($code)->id] ?? 0;
    }

    public function test_giriste_has_hesaplanir_ve_cariye_alacak_yazilir(): void
    {
        $order = $this->giris();

        $this->assertSame('A00001', $order->number);
        $this->assertSame('117.000', $order->has_in);
        $this->assertSame(WorkOrder::STATUS_ATOLYEDE, $order->status);
        $this->assertSame(-117_000, $this->bakiye('HAS')); // firmaya 117 gr has borçluyuz
    }

    public function test_kullanicinin_ornegi_parcali_cikis_kalan_atolyede_kalir(): void
    {
        // 26,25 gr × 0,595 giriş, 6,97 gr çıkış → 19,28 gr atölyede kalır
        $order = $this->giris(['gross_in' => '26,25', 'purity' => '0,595']);
        $this->cikis($order, ['gross_out' => '6,97', 'labor_rate' => '0'])->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertSame(19_280, $order->remainingMilli());
        $this->assertSame(WorkOrder::STATUS_ATOLYEDE, $order->status);

        // Cari: 15,619 alacak − 4,147 çıkış = 11,472 gr has borcumuz devam ediyor. Fire kaydı yok.
        $this->assertSame(-11_472, $this->bakiye('HAS'));
        $this->assertSame(2, Transaction::count());
    }

    public function test_birden_fazla_cikis_yapilabilir(): void
    {
        $order = $this->giris();

        $this->cikis($order, ['gross_out' => '100']);
        $this->cikis($order, ['gross_out' => '60']);

        $order->refresh();

        $this->assertCount(2, $order->deliveries);
        $this->assertSame(40_000, $order->remainingMilli());
        $this->assertSame(20.0, $order->remainingRate());

        // 117 − 58,5 − 35,1 = 23,4 gr has borcumuz kalır
        $this->assertSame(-23_400, $this->bakiye('HAS'));
        // İşçilik: (100 + 60) × 15 = 2.400 TL
        $this->assertSame(2_400_000, $this->bakiye('TRY'));
    }

    public function test_cikis_kalandan_fazla_olamaz(): void
    {
        $order = $this->giris();
        $this->cikis($order, ['gross_out' => '150']);

        $this->cikis($order, ['gross_out' => '60'])->assertSessionHasErrors('gross_out');
        $this->assertCount(1, $order->deliveries()->get());
    }

    public function test_iscilik_has_olarak_alinabilir(): void
    {
        $order = $this->giris();

        $this->cikis($order, ['labor_rate' => '0,02', 'labor_currency_id' => $this->birim('HAS')->id])
            ->assertSessionHasNoErrors();

        // −117 + 93,6 (çıkış) + 3,2 (160 × 0,02 işçilik) = −20,2
        $this->assertSame(-20_200, $this->bakiye('HAS'));
    }

    public function test_iscilik_sifirsa_iscilik_kaydi_olusmaz(): void
    {
        $order = $this->giris();
        $this->cikis($order, ['labor_rate' => '0']);

        $this->assertNull($order->deliveries()->first()->labor_transaction_id);
        $this->assertSame(2, Transaction::count()); // giriş + çıkış
    }

    public function test_fis_kapatilinca_kalan_fire_sayilir_cariye_islenmez(): void
    {
        $order = $this->giris();
        $this->cikis($order);

        $this->actingAs($this->user)->post(route('work-orders.close', $order))->assertRedirect();
        $order->refresh();

        $this->assertTrue($order->isClosed());
        $this->assertSame(40_000, $order->remainingMilli());
        $this->assertSame(-23_400, $this->bakiye('HAS')); // cari değişmedi

        $this->actingAs($this->user)->delete(route('work-orders.reopen', $order));
        $this->assertFalse($order->fresh()->isClosed());
    }

    public function test_cikis_silinince_cari_kayitlari_geri_alinir(): void
    {
        $order = $this->giris();
        $this->cikis($order);
        $delivery = $order->deliveries()->first();

        $this->actingAs($this->user)
            ->delete(route('work-orders.deliveries.destroy', [$order, $delivery]))
            ->assertRedirect();

        $this->assertSame(1, Transaction::count()); // sadece giriş
        $this->assertSame(-117_000, $this->bakiye('HAS'));
        $this->assertSame(0, $this->bakiye('TRY'));
    }

    public function test_milyem_duzenlenince_giris_ve_cikislar_yeniden_hesaplanir(): void
    {
        $order = $this->giris();
        $this->cikis($order, ['labor_rate' => '0']);

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01',
            'gross_in' => '200',
            'purity' => '0,595',
        ])->assertSessionHasNoErrors();

        // 200 × 0,595 = 119 alacak, 160 × 0,595 = 95,2 çıkış → 23,8
        $this->assertSame(-23_800, $this->bakiye('HAS'));
        $this->assertSame('95.200', $order->deliveries()->first()->has_out);
    }

    public function test_giris_cikislardan_az_yapilamaz(): void
    {
        $order = $this->giris();
        $this->cikis($order);

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01',
            'gross_in' => '150',
            'purity' => '0,585',
        ])->assertSessionHasErrors('gross_in');
    }

    public function test_cikisi_olan_fis_silinemez_olmayan_silinir(): void
    {
        $bos = $this->giris();
        $dolu = $this->giris();
        $this->cikis($dolu);

        $this->actingAs($this->user)->delete(route('work-orders.destroy', $dolu))->assertSessionHas('error');
        $this->actingAs($this->user)->delete(route('work-orders.destroy', $bos))->assertRedirect(route('work-orders.index'));

        $this->assertModelMissing($bos);
        $this->assertModelExists($dolu);
    }

    public function test_fise_bagli_cari_kaydi_hareketlerden_degistirilemez(): void
    {
        $order = $this->giris();
        $this->cikis($order);

        foreach (Transaction::all() as $kayit) {
            $this->actingAs($this->user)->get(route('transactions.edit', $kayit))
                ->assertRedirect(route('work-orders.show', $order));
        }

        $this->assertSame(TransactionType::CariAlacak, Transaction::first()->type);
    }

    public function test_bilancoda_atolyede_kalan_has_gorunur(): void
    {
        $order = $this->giris();
        $this->cikis($order, ['labor_rate' => '0']);

        $ozet = Balances::summary()[$this->birim('HAS')->id];

        // Atölyede 40 gr × 0,585 = 23,4 has kaldı, firmaya 23,4 has borçluyuz → net 0
        $this->assertSame(23_400, $ozet['atolye']);
        $this->assertSame(23_400, $ozet['borc']);
        $this->assertSame(0, $ozet['net']);
    }

    public function test_personel_cikis_silemez(): void
    {
        $order = $this->giris();
        $this->cikis($order);
        $personel = User::factory()->create(['role' => User::ROLE_PERSONEL]);

        $this->actingAs($personel)
            ->delete(route('work-orders.deliveries.destroy', [$order, $order->deliveries()->first()]))
            ->assertForbidden();
    }

    public function test_atolye_sayfalari_acilir(): void
    {
        $acik = $this->giris();
        $kapali = $this->giris(['product' => 'Yüzük']);
        $this->cikis($kapali);
        $this->actingAs($this->user)->post(route('work-orders.close', $kapali));

        $this->actingAs($this->user);

        $this->get(route('work-orders.index'))->assertOk()->assertSee('14 ayar bilezik')->assertDontSee('Yüzük');
        $this->get(route('work-orders.index', ['durum' => 'tamamlandi']))->assertOk()->assertSee('Yüzük');
        $this->get(route('work-orders.create'))->assertOk();
        $this->get(route('work-orders.show', $acik))->assertOk()->assertSee('Yeni Çıkış');
        $this->get(route('work-orders.show', $kapali))->assertOk()->assertSee('40,000 gr');
        $this->get(route('work-orders.edit', $kapali))->assertOk();
    }
}
