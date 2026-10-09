<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderDelivery;
use App\Models\WorkOrderDeliveryLine;
use App\Support\Balances;
use App\Support\WorkshopTotals;
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
            'received_at' => '2026-09-01T10:00:00',
            'gross_in' => '200',
            'purity' => '0,585',
        ])->assertSessionHasNoErrors();

        return WorkOrder::latest('id')->first();
    }

    /**
     * Çıkış gönderir. Tek satırlık çıkış için gross_out / purity_out / product düz verilebilir;
     * çok satırlı için 'lines' verilir.
     */
    private function cikis(array $data = [])
    {
        $line = [
            'product' => $data['product'] ?? null,
            'gross_out' => $data['gross_out'] ?? '160',
            'purity_out' => $data['purity_out'] ?? '0,585',
        ];
        unset($data['product'], $data['gross_out'], $data['purity_out']);

        return $this->actingAs($this->user)->post(route('workshop-deliveries.store'), $data + [
            'account_id' => $this->firma->id,
            'delivered_at' => '2026-09-10T10:00:00',
            'kind' => 'atolye',
            'lines' => [$line],
        ]);
    }

    private function satir(int $index = 0): WorkOrderDeliveryLine
    {
        return WorkOrderDeliveryLine::orderBy('id')->skip($index)->firstOrFail();
    }

    private function hasBakiye(?Account $account = null): int
    {
        return ($account ?? $this->firma)->balances()[Currency::firstWhere('code', 'HAS')->id] ?? 0;
    }

    public function test_kullanicinin_ornegi_iscilik_milyemle_hesaplanir(): void
    {
        // Önceki bakiye: 46,43 gr has borcumuz
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'type' => 'cari_alacak',
            'account_id' => $this->firma->id,
            'currency_id' => Currency::firstWhere('code', 'HAS')->id,
            'amount' => '46,43',
            'date' => '2026-09-30T10:00:00',
        ]);

        // Giriş: 26,25 gr × 0,595 (işçilik dahil) = 15,61875 → küsurat atılır → 15,618 has
        $order = $this->giris(['gross_in' => '26,25', 'purity' => '0,595']);
        $this->assertSame('15.618', $order->has_in);

        // Çıkış: 6,97 gr × 0,625 (işçilik dahil) = 4,356 has
        $this->cikis(['gross_out' => '6,97', 'purity_out' => '0,625'])->assertSessionHasNoErrors();
        $this->assertSame('4.356', $this->satir()->has_out);

        // Has borcumuz: 46,43 + 15,618 − 4,356 = 57,692
        $this->assertSame(-57_692, $this->hasBakiye());
        $this->assertSame(19_280, WorkshopTotals::forAccount($this->firma->id)['ramat_gram']);
    }

    public function test_giriste_has_hesaplanir_ve_cariye_alacak_yazilir(): void
    {
        $order = $this->giris();

        $this->assertSame('A00001', $order->number);
        $this->assertSame('117.000', $order->has_in);
        $this->assertSame(-117_000, $this->hasBakiye());
    }

    public function test_cikis_giris_fisinden_bagimsiz_musteriye_yapilir(): void
    {
        // Kullanıcının örneği: 50 gr 0,585 giriş, 25 gr 0,625 çıkış → 25 gr ramatta
        $this->giris(['gross_in' => '50']);
        $this->cikis(['gross_out' => '25', 'purity_out' => '0,625', 'product' => '14 ayar zincir'])->assertSessionHasNoErrors();

        $delivery = WorkOrderDelivery::first();
        $this->assertSame('T00001', $delivery->number);
        $this->assertSame($this->firma->id, $delivery->account_id);
        $this->assertSame('15.625', $this->satir()->has_out);

        $totals = WorkshopTotals::forAccount($this->firma->id);
        $this->assertSame(25_000, $totals['ramat_gram']);
        $this->assertSame(13_625, $totals['has_borcu']); // 29,25 − 15,625
        $this->assertSame(-13_625, $this->hasBakiye());
    }

    public function test_cikis_birden_fazla_girisin_toplamindan_yapilabilir(): void
    {
        // İki ayrı giriş (30 + 20 gr); tek çıkışta 45 gr verilebilir, hangi fişten olduğu önemsiz
        $this->giris(['gross_in' => '30']);
        $this->giris(['gross_in' => '20']);

        $this->cikis(['gross_out' => '45', 'purity_out' => '0,625'])->assertSessionHasNoErrors();

        $this->assertSame(5_000, WorkshopTotals::forAccount($this->firma->id)['ramat_gram']);
    }

    public function test_cikis_musterinin_kalanindan_fazla_olamaz(): void
    {
        $this->giris(['gross_in' => '50']);
        $this->cikis(['gross_out' => '40']);

        $this->cikis(['gross_out' => '11'])->assertSessionHasErrors('lines');
        $this->assertSame(1, WorkOrderDelivery::count());

        // Başka müşterinin girişi bu müşterinin kalanına sayılmaz
        $baska = Account::factory()->create();
        $this->cikis(['account_id' => $baska->id, 'gross_out' => '1'])->assertSessionHasErrors('lines');
    }

    public function test_atolyede_girisi_olmayan_musteriye_satis_cikisi_yapilir(): void
    {
        // Kullanıcının durumu: müşteri has altın verdi, atölye kendi ürününü işleyip verdi
        $musteri = Account::factory()->create(['name' => 'Has Veren Müşteri']);
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'type' => 'cari_alacak',
            'account_id' => $musteri->id,
            'currency_id' => Currency::firstWhere('code', 'HAS')->id,
            'amount' => '20',
            'date' => '2026-09-05T10:00:00',
            'description' => 'Has altın verdi',
        ]);

        $this->actingAs($this->user)->get(route('workshop-deliveries.create'))->assertOk()->assertSee('Has Veren Müşteri');

        $this->cikis(['account_id' => $musteri->id, 'kind' => 'satis', 'gross_out' => '30', 'purity_out' => '0,625', 'product' => 'Bilezik'])
            ->assertSessionHasNoErrors();

        $delivery = WorkOrderDelivery::first();
        $this->assertTrue($delivery->isSale());
        $this->assertSame('18.750', $this->satir()->has_out); // 30 × 0,625

        // Cari: 20 alacak − 18,75 = 1,25 has borcumuz kaldı
        $this->assertSame(-1_250, $this->hasBakiye($musteri));

        // Ramat etkilenmez, müşteri ramat hesabında görünmez
        $this->assertSame([], WorkshopTotals::forAccounts([$musteri->id]));
        $this->assertStringContainsString('Satış', Transaction::latest('id')->first()->description);
    }

    public function test_satis_cikisi_ramati_etkilemez_atolye_cikisi_sinirli_kalir(): void
    {
        $this->giris(['gross_in' => '50']);

        // Atölye ürünü: 50'den fazlası çıkamaz; satış ise sınırsız ve ramatı değiştirmez
        $this->cikis(['kind' => 'atolye', 'gross_out' => '60'])->assertSessionHasErrors('lines');
        $this->cikis(['kind' => 'satis', 'gross_out' => '60'])->assertSessionHasNoErrors();

        $this->assertSame(50_000, WorkshopTotals::forAccount($this->firma->id)['ramat_gram']);
        $this->actingAs($this->user)->get(route('workshop-deliveries.index'))->assertSee('Satış');
    }

    public function test_tek_cikista_birden_fazla_satir_farkli_milyemle(): void
    {
        // Kullanıcının isteği: aynı müşteriye tek işlemde 0,585 / 0,750 / 0,333 ve iki ayrı 0,585 (farklı işçilik)
        $this->giris(['gross_in' => '200']);

        $this->cikis(['lines' => [
            ['product' => '14 ayar zincir', 'gross_out' => '10', 'purity_out' => '0,585'],
            ['product' => '14 ayar bilezik', 'gross_out' => '5', 'purity_out' => '0,625'],
            ['product' => '18 ayar yüzük', 'gross_out' => '4', 'purity_out' => '0,750'],
            ['product' => '8 ayar küpe', 'gross_out' => '3', 'purity_out' => '0,333'],
            ['product' => '', 'gross_out' => '', 'purity_out' => ''], // boş satır yok sayılır
        ]])->assertSessionHasNoErrors();

        $delivery = WorkOrderDelivery::with('lines')->first();
        $this->assertSame('T00001', $delivery->number);
        $this->assertCount(4, $delivery->lines);
        $this->assertSame(22_000, $delivery->grossOutMilli());
        // 5,85 + 3,125 + 3 + 0,999 = 12,974
        $this->assertSame(12_974, $delivery->hasOutMilli());

        // Her satır ayrı cari kaydı, aynı çıkış numarasıyla
        $this->assertSame(4, Transaction::where('document_no', 'T00001')->count());
        $this->assertSame(-117_000 + 12_974, $this->hasBakiye());
        $this->assertSame(178_000, WorkshopTotals::forAccount($this->firma->id)['ramat_gram']);

        // Fişte satırlar ayrı ayrı ve toplam
        $this->actingAs($this->user)->get(route('workshop-deliveries.receipt', $delivery))
            ->assertOk()
            ->assertSeeInOrder(['14 ayar zincir', '10,000', '0,585', '5,850', '14 ayar bilezik', '0,625', '3,125', '18 ayar yüzük', '0,750', '8 ayar küpe', '0,333', '0,999', 'Toplam', '22,000', '12,974 gr']);

        // Çıkış silinince tüm satırların cari kayıtları geri alınır
        $this->actingAs($this->user)->delete(route('workshop-deliveries.destroy', $delivery));
        $this->assertSame(0, Transaction::where('document_no', 'T00001')->count());
        $this->assertSame(0, WorkOrderDeliveryLine::count());
    }

    public function test_cok_satirli_cikista_toplam_kalani_asamaz(): void
    {
        $this->giris(['gross_in' => '20']);

        $this->cikis(['lines' => [
            ['gross_out' => '12', 'purity_out' => '0,585'],
            ['gross_out' => '9', 'purity_out' => '0,585'],
        ]])->assertSessionHasErrors('lines');

        $this->cikis(['lines' => []])->assertSessionHasErrors('lines');
        $this->assertSame(0, WorkOrderDelivery::count());
    }

    public function test_cikis_turu_zorunlu(): void
    {
        $this->giris();

        $this->cikis(['kind' => ''])->assertSessionHasErrors('kind');
        $this->cikis(['kind' => 'baska'])->assertSessionHasErrors('kind');
    }

    public function test_cikis_milyemi_zorunlu_ve_gecerli_olmali(): void
    {
        $this->giris();

        $this->cikis(['purity_out' => ''])->assertSessionHasErrors('lines.0.purity_out');
        $this->cikis(['purity_out' => 'abc'])->assertSessionHasErrors('lines.0.purity_out');
        $this->cikis(['purity_out' => '1,5'])->assertSessionHasErrors('lines.0.purity_out');

        // Binde yazım kabul edilir: 625 → 0,625
        $this->cikis(['purity_out' => '625'])->assertSessionHasNoErrors();
        $this->assertSame('0.6250', $this->satir()->purity_out);
    }

    public function test_cikis_silinince_cari_kaydi_geri_alinir(): void
    {
        $this->giris();
        $this->cikis();

        $this->actingAs($this->user)
            ->delete(route('workshop-deliveries.destroy', WorkOrderDelivery::first()))
            ->assertRedirect();

        $this->assertSame(0, WorkOrderDelivery::count());
        $this->assertSame(1, Transaction::count());
        $this->assertSame(-117_000, $this->hasBakiye());
    }

    public function test_giris_duzenlenince_cari_has_guncellenir(): void
    {
        $order = $this->giris();
        $this->cikis(['purity_out' => '0,625']);

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01T10:00:00',
            'gross_in' => '200',
            'purity' => '0,595',
        ])->assertSessionHasNoErrors();

        // Giriş 200 × 0,595 = 119, çıkış 160 × 0,625 = 100 → 19
        $this->assertSame(-19_000, $this->hasBakiye());
    }

    public function test_giris_cikislarin_altina_dusurulemez_ve_silinemez(): void
    {
        $order = $this->giris();
        $this->cikis(['gross_out' => '160']);

        $this->actingAs($this->user)->put(route('work-orders.update', $order), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01T10:00:00',
            'gross_in' => '150',
            'purity' => '0,585',
        ])->assertSessionHasErrors('gross_in');

        $this->actingAs($this->user)->delete(route('work-orders.destroy', $order))->assertSessionHas('error');
        $this->assertModelExists($order);
    }

    public function test_cikisi_karsilanan_giris_silinebilir(): void
    {
        $this->giris(['gross_in' => '100']);
        $fazla = $this->giris(['gross_in' => '50']);
        $this->cikis(['gross_out' => '90']);

        // 150 − 50 = 100 ≥ 90 çıkış → silinebilir
        $this->actingAs($this->user)->delete(route('work-orders.destroy', $fazla))->assertRedirect(route('work-orders.index'));

        $this->assertModelMissing($fazla);
        $this->assertSame(10_000, WorkshopTotals::forAccount($this->firma->id)['ramat_gram']);
    }

    public function test_atolyeye_bagli_cari_kaydi_hareketlerden_degistirilemez(): void
    {
        $order = $this->giris();
        $this->cikis();

        $giris = Transaction::firstWhere('type', TransactionType::CariAlacak);
        $cikis = Transaction::firstWhere('type', TransactionType::CariBorc);

        $this->actingAs($this->user)->get(route('transactions.edit', $giris))->assertRedirect(route('work-orders.show', $order));
        $this->actingAs($this->user)->get(route('transactions.edit', $cikis))->assertRedirect(route('workshop-deliveries.index', ['q' => 'T00001']));
    }

    public function test_bilancoda_atolyede_kalan_has_gorunur(): void
    {
        $this->giris(['purity' => '0,595']);
        $this->cikis(['purity_out' => '0,625']);

        $ozet = Balances::summary()[Currency::firstWhere('code', 'HAS')->id];

        // Atölyede 40 gr × 0,595 = 23,8 has kaldı
        $this->assertSame(23_800, $ozet['atolye']);
        // Müşteriye borcumuz: 119 − 100 = 19
        $this->assertSame(19_000, $ozet['borc']);
    }

    public function test_personel_cikis_silemez(): void
    {
        $this->giris();
        $this->cikis();
        $personel = User::factory()->create(['role' => User::ROLE_PERSONEL]);

        $this->actingAs($personel)
            ->delete(route('workshop-deliveries.destroy', WorkOrderDelivery::first()))
            ->assertForbidden();
    }

    public function test_atolye_sayfalari_acilir(): void
    {
        $bilezik = $this->giris();
        $this->cikis(['product' => 'Yüzük']);

        $this->actingAs($this->user);

        $this->get(route('work-orders.index'))->assertOk()
            ->assertSee('Atölyeye Giriş')->assertSee('Atölyeden Çıkış')->assertSee('14 ayar bilezik');
        $this->get(route('workshop-deliveries.index'))->assertOk()->assertSee('T00001')->assertSee('Yüzük');
        $this->get(route('workshop-deliveries.create'))->assertOk()->assertSee('Döküm Firması');
        $this->get(route('work-orders.create'))->assertOk();
        $this->get(route('work-orders.show', $bilezik))->assertOk()->assertSee('Bu Müşteriye Çıkış Yap')->assertSee('40,000 gr');
        $this->get(route('work-orders.edit', $bilezik))->assertOk();
    }

    public function test_cikistan_sonra_musteri_fisi_yazdirma_butonu_cikar(): void
    {
        $this->giris();

        $this->cikis()->assertSessionHas('receipt_delivery_id');

        $this->followingRedirects()->cikis(['gross_out' => '10'])->assertSee('Fişi Yazdır');
    }

    public function test_musteri_fisi_bilgileri_gosterir(): void
    {
        config(['kuyumcu.firma' => ['name' => 'Yalçın Atölye', 'phone' => '0555 111 22 33', 'address' => 'Kapalıçarşı']]);

        $this->giris(['gross_in' => '26,25', 'purity' => '0,595']);
        $this->cikis(['gross_out' => '6,97', 'purity_out' => '0,625', 'product' => '14 ayar zincir', 'delivered_at' => '2026-10-01T10:00:00']);
        $this->cikis(['gross_out' => '5', 'purity_out' => '0,625', 'delivered_at' => '2026-10-02T10:00:00']);
        [$birinci, $ikinci] = WorkOrderDelivery::orderBy('id')->get()->all();

        $this->actingAs($this->user)->get(route('workshop-deliveries.receipt', $birinci))
            ->assertOk()
            ->assertSee('Yalçın Atölye')
            ->assertSee('0555 111 22 33')
            ->assertSee('T00001')
            ->assertSee('Döküm Firması')
            ->assertSee('14 ayar zincir')
            ->assertSee('6,970')
            ->assertSee('0,625')
            ->assertSee('4,356 gr') // toplam has
            ->assertDontSee('26,250')
            // Son durum: 15,618 − 4,356 = 11,262 (ikinci çıkış sonradan yapıldı ama bu fişi etkilemez)
            ->assertSeeInOrder(['SON DURUM', 'Alacağınız (Has)', '11,262 gr'])
            ->assertDontSee('Teslim Eden')
            ->assertDontSee('Teslim Alan')
            ->assertSee('Bu fiş bilgi amaçlıdır.')
            // Sadece 80 mm fiş yazıcısı; A5 seçeneği yok
            ->assertSee('80mm auto', false)
            // İki nüsha, ayrı sayfalarda (biri müşteriye, biri atölyeye)
            ->assertSeeInOrder(['Müşteri Nüshası', 'Atölye Nüshası'])
            ->assertSee('break-after-page', false)
            ->assertDontSee('Normal (A5)');

        // Yazdırırken her nüsha ayrı istenir (ayrı yazdırma işi → yazıcı arada keser)
        $this->get(route('workshop-deliveries.receipt', [$birinci, 'nusha' => 'musteri']))
            ->assertOk()
            ->assertSee('Müşteri Nüshası')
            ->assertDontSee('Atölye Nüshası')
            ->assertDontSee('break-after-page', false);
        $this->get(route('workshop-deliveries.receipt', [$birinci, 'nusha' => 'atolye']))
            ->assertSee('Atölye Nüshası')
            ->assertDontSee('Müşteri Nüshası');

        // İkinci çıkış: son durum 11,262 − 5 × 0,625 = 8,137
        $this->get(route('workshop-deliveries.receipt', $ikinci))
            ->assertOk()
            ->assertSee('T00002')
            ->assertSee('8,137 gr');
    }

    public function test_giris_ve_cikista_sesli_uyari_isareti_gonderilir(): void
    {
        $this->actingAs($this->user)->post(route('work-orders.store'), [
            'account_id' => $this->firma->id,
            'product' => '14 ayar bilezik',
            'received_at' => '2026-09-01T10:00:00',
            'gross_in' => '200',
            'purity' => '0,585',
        ])->assertSessionHas('ses', 'giris');

        $this->get(route('work-orders.show', WorkOrder::first()))->assertSee('data-sound="giris"', false);

        $this->cikis()->assertSessionHas('ses', 'cikis');
        $this->get(route('workshop-deliveries.index'))
            ->assertSee('data-sound="cikis"', false)
            ->assertSee('data-print-receipt', false); // fiş yeni sekme açmadan yazdırılır

        // Bir sonraki sayfada ses tekrar çalmaz
        $this->get(route('workshop-deliveries.index'))->assertDontSee('data-sound', false);
    }

    public function test_giris_formunda_ayar_milyem_tablosu_var(): void
    {
        // Ürüne "14 ayar" yazınca milyemi dolduran tablo sayfaya aktarılır (doldurma tarayıcıda yapılır)
        $this->actingAs($this->user)->get(route('work-orders.create'))
            ->assertOk()
            ->assertSee('data-ayar-milyem=\'{"8":"0,333","10":"0,417","14":"0,585","18":"0,750","22":"0,916"}\'', false)
            ->assertSee('data-product', false)
            ->assertSeeInOrder(['8 ayar', '10 ayar', '0,417', '14 ayar', '18 ayar', '22 ayar']);
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
}
