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
            'labor_purity_in' => '0',
        ])->assertSessionHasNoErrors();

        return WorkOrder::latest('id')->first();
    }

    private function cikis(WorkOrder $order, array $data = [])
    {
        return $this->actingAs($this->user)->post(route('work-orders.deliver', $order), $data + [
            'delivered_at' => '2026-09-10',
            'gross_out' => '160',
            'labor_purity' => '0',
        ]);
    }

    private function hasBakiye(): int
    {
        return $this->firma->balances()[Currency::firstWhere('code', 'HAS')->id] ?? 0;
    }

    public function test_kullanicinin_ornegi_iscilik_milyemle_hesaplanir(): void
    {
        // Önceki bakiye: 46,43 gr has borcumuz
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'type' => 'cari_alacak',
            'account_id' => $this->firma->id,
            'currency_id' => Currency::firstWhere('code', 'HAS')->id,
            'amount' => '46,43',
            'date' => '2026-09-30',
        ]);

        // Giriş: 26,25 gr × (0,585 + 0,010) = 15,61875 → küsurat atılır → 15,618 has
        $order = $this->giris(['gross_in' => '26,25', 'purity' => '0,585', 'labor_purity_in' => '0,010']);
        $this->assertSame('15.618', $order->has_in);
        $this->assertSame('0.5950', $order->inPurity());

        // Çıkış: 6,97 gr, ayar 0,585 + çıkış işçiliği 0,040 = 0,625 → 4,356 has
        $this->cikis($order, ['gross_out' => '6,97', 'labor_purity' => '0,040'])->assertSessionHasNoErrors();
        $this->assertSame('4.356', $order->deliveries()->first()->has_out);

        // Has borcumuz: 46,43 + 15,618 − 4,356 = 57,692. İşçilik ayrıca düşülmez.
        $this->assertSame(-57_692, $this->hasBakiye());
        $this->assertSame(19_280, $order->fresh()->remainingMilli());
    }

    public function test_giriste_has_hesaplanir_ve_cariye_alacak_yazilir(): void
    {
        $order = $this->giris();

        $this->assertSame('A00001', $order->number);
        $this->assertSame('117.000', $order->has_in);
        $this->assertSame(WorkOrder::STATUS_ATOLYEDE, $order->status);
        $this->assertSame(-117_000, $this->hasBakiye());
    }

    public function test_giris_isciligi_bos_birakilirsa_sifir_sayilir(): void
    {
        $order = $this->giris(['labor_purity_in' => '']);

        $this->assertSame('0.0000', $order->labor_purity_in);
        $this->assertSame('117.000', $order->has_in);
    }

    public function test_birden_fazla_cikis_yapilabilir(): void
    {
        $order = $this->giris();

        $this->cikis($order, ['gross_out' => '100', 'labor_purity' => '0,040']);
        $this->cikis($order, ['gross_out' => '60', 'labor_purity' => '0,040']);

        $order->refresh();

        $this->assertCount(2, $order->deliveries);
        $this->assertSame(40_000, $order->remainingMilli());
        $this->assertSame(20.0, $order->remainingRate());

        // 117 − 100 × 0,625 − 60 × 0,625 = 117 − 62,5 − 37,5 = 17
        $this->assertSame(-17_000, $this->hasBakiye());
        $this->assertSame(2, Transaction::where('type', 'cari_borc')->count());
    }

    public function test_cikis_kalandan_fazla_olamaz(): void
    {
        $order = $this->giris();
        $this->cikis($order, ['gross_out' => '150']);

        $this->cikis($order, ['gross_out' => '60'])->assertSessionHasErrors('gross_out');
        $this->assertCount(1, $order->deliveries()->get());
    }

    public function test_yanlis_yazilmis_iscilik_milyemi_reddedilir(): void
    {
        $order = $this->giris();

        // "0,40" büyük ihtimalle 0,040 yerine yazılmıştır; 0,200 üstü kabul edilmez
        $this->cikis($order, ['labor_purity' => '0,40'])->assertSessionHasErrors('labor_purity');
        $this->cikis($order, ['labor_purity' => 'abc'])->assertSessionHasErrors('labor_purity');

        // Binde yazım kabul edilir: 40 → 0,040
        $this->cikis($order, ['labor_purity' => '40'])->assertSessionHasNoErrors();
        $this->assertSame('0.0400', $order->deliveries()->first()->labor_purity);
    }

    public function test_fis_kapatilinca_kalan_fire_sayilir_cariye_islenmez(): void
    {
        $order = $this->giris();
        $this->cikis($order);

        $this->actingAs($this->user)->post(route('work-orders.close', $order))->assertRedirect();
        $order->refresh();

        $this->assertTrue($order->isClosed());
        $this->assertSame(40_000, $order->remainingMilli());
        $this->assertSame(-23_400, $this->hasBakiye()); // 117 − 93,6; cari değişmedi

        $this->actingAs($this->user)->delete(route('work-orders.reopen', $order));
        $this->assertFalse($order->fresh()->isClosed());
    }

    public function test_cikis_silinince_cari_kaydi_geri_alinir(): void
    {
        $order = $this->giris();
        $this->cikis($order);

        $this->actingAs($this->user)
            ->delete(route('work-orders.deliveries.destroy', [$order, $order->deliveries()->first()]))
            ->assertRedirect();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(-117_000, $this->hasBakiye());
    }

    public function test_milyem_duzenlenince_giris_ve_cikislar_yeniden_hesaplanir(): void
    {
        $order = $this->giris();
        $this->cikis($order, ['labor_purity' => '0,040']);

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01',
            'gross_in' => '200',
            'purity' => '0,595',
            'labor_purity_in' => '0,010',
        ])->assertSessionHasNoErrors();

        // Giriş 200 × 0,605 = 121, çıkış 160 × 0,635 = 101,6 → 19,4
        $this->assertSame(-19_400, $this->hasBakiye());
        $this->assertSame('101.600', $order->deliveries()->first()->has_out);
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

    public function test_bilancoda_atolyede_kalan_saf_has_gorunur(): void
    {
        $order = $this->giris(['labor_purity_in' => '0,010']);
        $this->cikis($order, ['labor_purity' => '0,040']);

        $ozet = Balances::summary()[Currency::firstWhere('code', 'HAS')->id];

        // Atölyede 40 gr × 0,585 = 23,4 saf has kaldı
        $this->assertSame(23_400, $ozet['atolye']);
        // Firmaya borcumuz: 200 × 0,595 − 160 × 0,625 = 119 − 100 = 19
        $this->assertSame(19_000, $ozet['borc']);
    }

    public function test_komut_tum_fisleri_yeniden_hesaplar(): void
    {
        $order = $this->giris();
        WorkOrder::whereKey($order->id)->update(['has_in' => 0]);

        $this->artisan('atolye:yeniden-hesapla')->assertSuccessful();

        $this->assertSame('117.000', $order->fresh()->has_in);
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

    public function test_cikistan_sonra_musteri_fisi_yazdirma_butonu_cikar(): void
    {
        $order = $this->giris();

        $this->cikis($order)->assertSessionHas('receipt_delivery_id');

        $this->followingRedirects()->cikis($order, ['gross_out' => '10'])->assertSee('Fişi Yazdır');
    }

    public function test_musteri_fisi_bilgileri_gosterir(): void
    {
        config(['kuyumcu.firma' => ['name' => 'Yalçın Atölye', 'phone' => '0555 111 22 33', 'address' => 'Kapalıçarşı']]);

        $order = $this->giris(['gross_in' => '26,25', 'purity' => '0,585', 'labor_purity_in' => '0,010', 'product' => '14 ayar zincir']);
        $this->cikis($order, ['gross_out' => '6,97', 'labor_purity' => '0,040']);
        $this->cikis($order, ['gross_out' => '5', 'labor_purity' => '0,040']);
        [$birinci, $ikinci] = $order->deliveries()->get()->all();

        $this->actingAs($this->user)->get(route('work-orders.deliveries.receipt', [$order, $birinci]))
            ->assertOk()
            ->assertSee('Yalçın Atölye')
            ->assertSee('0555 111 22 33')
            ->assertSee('A00001-1')
            ->assertSee('Döküm Firması')
            ->assertSee('14 ayar zincir')
            ->assertSee('6,970 gr')    // çıkış gramı
            ->assertSee('0,625')       // çıkış milyemi
            ->assertSee('4,356 gr')    // çıkış has
            ->assertDontSee('26,250 gr')  // giriş bilgisi fişte yok
            ->assertDontSee('Atölyede kalan')
            // Son durum: 15,618 alacak − 4,356 = 11,262 (ikinci çıkış sonradan yapıldı ama bu fişi etkilemez)
            ->assertSeeInOrder(['SON DURUM', 'Alacağınız (Has)', '11,262 gr'])
            ->assertSee('Bu fiş bilgi amaçlıdır.');

        // İkinci çıkışın fişi: sıra no 2, son durum 11,262 − 5 × 0,625 = 8,137
        $this->get(route('work-orders.deliveries.receipt', [$order, $ikinci, 'boyut' => '80']))
            ->assertOk()
            ->assertSee('A00001-2')
            ->assertSee('8,137 gr')
            ->assertSee('80mm auto', false);
    }

    public function test_atolye_girisinden_hizli_cari_eklenir(): void
    {
        $this->actingAs($this->user)->get(route('work-orders.create'))->assertOk()->assertSee('Yeni Cari');

        $this->postJson(route('accounts.quick-store'), ['name' => 'Yeni Döküm', 'phone' => '0555 000 11 22'])
            ->assertCreated()
            ->assertJson(['name' => 'Yeni Döküm', 'code' => 'C00002']);

        $cari = Account::firstWhere('name', 'Yeni Döküm');
        $this->assertSame('0555 000 11 22', $cari->phone);
        $this->assertTrue($cari->is_active);
    }

    public function test_hizli_cari_eklemede_ad_zorunlu(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('accounts.quick-store'), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Cari adı zorunludur.']);
    }

    public function test_baska_fisin_cikisi_ile_fis_acilmaz(): void
    {
        $a = $this->giris();
        $b = $this->giris();
        $this->cikis($a);

        $this->actingAs($this->user)
            ->get(route('work-orders.deliveries.receipt', [$b, $a->deliveries()->first()]))
            ->assertNotFound();
    }
}
