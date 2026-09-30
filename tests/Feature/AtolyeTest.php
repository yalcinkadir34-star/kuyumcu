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

    private function teslim(WorkOrder $order, array $data = [])
    {
        return $this->actingAs($this->user)->post(route('work-orders.deliver', $order), $data + [
            'delivered_at' => '2026-09-10',
            'gross_out' => '160',
            'fire_bearer' => 'firma',
            'labor_basis' => 'gram',
            'labor_rate' => '15',
            'labor_currency_id' => Currency::firstWhere('code', 'TRY')->id,
        ]);
    }

    public function test_giriste_has_karsiligi_hesaplanir(): void
    {
        $order = $this->giris();

        $this->assertSame('A00001', $order->number);
        $this->assertSame('117.000', $order->has_in);
        $this->assertSame('0.5850', $order->purity);
        $this->assertSame(WorkOrder::STATUS_ATOLYEDE, $order->status);
    }

    private function hasBakiye(): int
    {
        return $this->firma->balances()[Currency::firstWhere('code', 'HAS')->id] ?? 0;
    }

    public function test_giriste_has_firmanin_carisine_alacak_yazilir(): void
    {
        // Kullanıcının örneği: 26,25 gr × 0,595 = 15,619 gr has → firmaya borcumuz artar
        $this->giris(['gross_in' => '26,25', 'purity' => '0,595']);

        $this->assertSame(-15_619, $this->hasBakiye());
    }

    public function test_fireyi_firma_ustlenirse_has_borcu_kapanir(): void
    {
        $order = $this->giris();
        $this->assertSame(-117_000, $this->hasBakiye());

        $this->teslim($order, ['fire_bearer' => 'firma']);

        // 117 alacak − 93,6 teslim − 23,4 fire = 0
        $this->assertSame(0, $this->hasBakiye());
    }

    public function test_fireyi_atolye_ustlenirse_fire_firmaya_borc_kalir(): void
    {
        $order = $this->giris();
        $this->teslim($order, ['fire_bearer' => 'atolye']);

        // 117 alacak − 93,6 teslim = 23,4 gr has firmaya borcumuz
        $this->assertSame(-23_400, $this->hasBakiye());
        $this->assertNull($order->fresh()->fire_transaction_id);
    }

    public function test_giris_duzenlenince_cari_has_guncellenir(): void
    {
        $order = $this->giris();

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01',
            'gross_in' => '100',
            'purity' => '0,585',
        ]);

        $this->assertSame(-58_500, $this->hasBakiye());
        $this->assertSame(1, Transaction::count());
    }

    public function test_fis_silinince_cari_kaydi_da_silinir(): void
    {
        $order = $this->giris();

        $this->actingAs($this->user)->delete(route('work-orders.destroy', $order))->assertRedirect();

        $this->assertSame(0, Transaction::count());
    }

    public function test_fise_bagli_cari_kaydi_hareketlerden_degistirilemez(): void
    {
        $order = $this->giris();
        $kayit = Transaction::first();

        $this->actingAs($this->user)->get(route('transactions.edit', $kayit))
            ->assertRedirect(route('work-orders.show', $order));
        $this->actingAs($this->user)->delete(route('transactions.destroy', $kayit))
            ->assertRedirect(route('work-orders.show', $order));

        $this->assertSame(1, Transaction::count());
    }

    public function test_bilancoda_atolyedeki_has_gorunur(): void
    {
        $this->giris();
        $has = Currency::firstWhere('code', 'HAS')->id;

        $ozet = Balances::summary()[$has];

        // Atölyede 117 has var, firmaya 117 has borçluyuz → net 0
        $this->assertSame(117_000, $ozet['atolye']);
        $this->assertSame(117_000, $ozet['borc']);
        $this->assertSame(0, $ozet['net']);
    }

    public function test_milyem_urune_gore_elle_girilir(): void
    {
        $order = $this->giris(['purity' => '0,595', 'gross_in' => '100']);

        $this->assertSame('59.500', $order->has_in);
    }

    public function test_teslimde_fire_hesaplanir_ve_iscilik_cariye_islenir(): void
    {
        $order = $this->giris();

        $this->teslim($order)->assertSessionHasNoErrors()->assertRedirect(route('work-orders.show', $order));
        $order->refresh();

        // 200 − 160 = 40 gr fire (%20), has: 40 × 0,585 = 23,4
        $this->assertSame('40.000', $order->fire_gram);
        $this->assertSame('23.400', $order->fire_has);
        $this->assertSame('93.600', $order->has_out);
        $this->assertSame(20.0, $order->fireRate());

        // İşçilik: 160 gr × 15 TL = 2.400 TL, cariye borç
        $this->assertSame('2400.000', $order->labor_total);
        $transaction = $order->transaction;
        $this->assertSame(TransactionType::CariBorc, $transaction->type);
        $this->assertSame($this->firma->id, $transaction->account_id);
        $this->assertSame('A00001', $transaction->document_no);
        $this->assertSame(2_400_000, $this->firma->balances()[Currency::firstWhere('code', 'TRY')->id]);
    }

    public function test_iscilik_has_altin_olarak_da_alinabilir(): void
    {
        $order = $this->giris();
        $has = Currency::firstWhere('code', 'HAS');

        $this->teslim($order, ['labor_rate' => '0,02', 'labor_currency_id' => $has->id])->assertSessionHasNoErrors();

        // 160 gr × 0,02 = 3,2 gr has işçilik
        $this->assertSame(3_200, $this->firma->balances()[$has->id]);
    }

    public function test_teslim_duzeltilince_cari_kaydi_guncellenir(): void
    {
        $order = $this->giris();
        $this->teslim($order);
        $this->teslim($order, ['gross_out' => '170', 'labor_basis' => 'toplam', 'labor_rate' => '3.000,00']);

        $order->refresh();

        $this->assertSame('30.000', $order->fire_gram);
        $this->assertSame(4, Transaction::count()); // giriş, teslim, fire, işçilik
        $this->assertSame(0, $this->hasBakiye());
        $this->assertSame(3_000_000, $this->firma->balances()[Currency::firstWhere('code', 'TRY')->id]);
    }

    public function test_iscilik_sifirsa_cariye_kayit_yapilmaz(): void
    {
        $order = $this->giris();
        $this->teslim($order, ['labor_rate' => '0']);

        $this->assertNull($order->fresh()->transaction_id);
        $this->assertSame(3, Transaction::count()); // giriş, teslim, fire (işçilik yok)
    }

    public function test_teslim_geri_alinabilir(): void
    {
        $order = $this->giris();
        $this->teslim($order);

        $this->actingAs($this->user)->delete(route('work-orders.undeliver', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame(WorkOrder::STATUS_ATOLYEDE, $order->status);
        $this->assertNull($order->fire_gram);
        $this->assertSame(1, Transaction::count()); // sadece giriş kaydı kalır
        $this->assertSame(-117_000, $this->hasBakiye());
    }

    public function test_hatali_girisler_reddedilir(): void
    {
        $this->actingAs($this->user)->post(route('work-orders.store'), [
            'account_id' => $this->firma->id,
            'product' => 'Deneme',
            'received_at' => '2026-09-01',
            'gross_in' => '0',
            'purity' => '1,5',
        ])->assertSessionHasErrors(['gross_in', 'purity']);

        $order = $this->giris();

        $this->teslim($order, ['delivered_at' => '2026-08-01'])->assertSessionHasErrors('delivered_at');
        $this->teslim($order, ['gross_out' => 'abc'])->assertSessionHasErrors('gross_out');
    }

    public function test_giris_duzenlenince_fire_yeniden_hesaplanir(): void
    {
        $order = $this->giris();
        $this->teslim($order);

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01',
            'gross_in' => '210',
            'purity' => '0,585',
        ])->assertSessionHasNoErrors();

        $this->assertSame('50.000', $order->fresh()->fire_gram);
    }

    public function test_personel_teslimi_geri_alamaz(): void
    {
        $order = $this->giris();
        $this->teslim($order);

        $personel = User::factory()->create(['role' => User::ROLE_PERSONEL]);

        $this->actingAs($personel)->delete(route('work-orders.undeliver', $order))->assertForbidden();
    }

    public function test_atolye_sayfalari_acilir(): void
    {
        $bekleyen = $this->giris();
        $teslim = $this->giris(['product' => 'Yüzük']);
        $this->teslim($teslim);

        $this->actingAs($this->user);

        $this->get(route('work-orders.index'))->assertOk()->assertSee('14 ayar bilezik')->assertDontSee('Yüzük');
        $this->get(route('work-orders.index', ['durum' => 'teslim_edildi']))->assertOk()->assertSee('Yüzük');
        $this->get(route('work-orders.create'))->assertOk();
        $this->get(route('work-orders.show', $bekleyen))->assertOk()->assertSee('Teslim Et');
        $this->get(route('work-orders.show', $teslim))->assertOk()->assertSee('40,000 gr');
        $this->get(route('work-orders.edit', $teslim))->assertOk();
    }
}
