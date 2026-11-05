<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CashRegister;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Balances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariKasaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CashRegister $kasa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->kasa = CashRegister::firstWhere('name', 'Merkez Kasa');
    }

    private function birim(string $code): Currency
    {
        return Currency::firstWhere('code', $code);
    }

    private function hareketGir(array $data)
    {
        return $this->actingAs($this->user)->post(route('transactions.store'), $data + [
            'date' => '2026-09-30',
            'currency_id' => $this->birim('TRY')->id,
        ]);
    }

    public function test_varsayilan_birimler_ve_kasa_olusur(): void
    {
        $this->assertSame(['TRY', 'USD', 'EUR', 'HAS'], Currency::orderBy('sort')->pluck('code')->all());
        $this->assertNotNull($this->kasa);
    }

    public function test_cari_olusturulur_ve_kod_otomatik_verilir(): void
    {
        $this->actingAs($this->user)
            ->post(route('accounts.store'), ['type' => 'musteri', 'name' => 'Altın Döküm Ltd.', 'is_active' => '1'])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('accounts.store'), ['type' => 'tedarikci', 'name' => 'Rafineri A.Ş.']);

        $this->assertSame(['C00001', 'C00002'], Account::orderBy('id')->pluck('code')->all());
    }

    public function test_tahsilat_kasayi_artirir_carinin_borcunu_azaltir(): void
    {
        $cari = Account::factory()->create();

        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '10.000,00'])
            ->assertRedirect(route('accounts.show', $cari));
        $this->hareketGir(['type' => 'tahsilat', 'account_id' => $cari->id, 'cash_register_id' => $this->kasa->id, 'amount' => '2.500,50']);

        $tl = $this->birim('TRY')->id;

        // 10.000 borç − 2.500,50 tahsilat = 7.499,50 borçlu
        $this->assertSame(7_499_500, $cari->balances()[$tl]);
        $this->assertSame(2_500_500, $this->kasa->balances()[$tl]);
    }

    public function test_odeme_kasayi_azaltir_cariyi_borclandirir(): void
    {
        $cari = Account::factory()->create();

        $this->hareketGir(['type' => 'cari_alacak', 'account_id' => $cari->id, 'amount' => '500']);
        $this->hareketGir(['type' => 'odeme', 'account_id' => $cari->id, 'cash_register_id' => $this->kasa->id, 'amount' => '200']);

        $tl = $this->birim('TRY')->id;

        $this->assertSame(-300_000, $cari->balances()[$tl]); // biz 300 TL borçluyuz
        $this->assertSame(-200_000, $this->kasa->balances()[$tl]);
    }

    public function test_has_altin_ayri_birim_olarak_tutulur(): void
    {
        $cari = Account::factory()->create();
        $has = $this->birim('HAS');

        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '117,000', 'currency_id' => $has->id]);
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '1500']);

        $bakiye = $cari->balances();

        $this->assertSame(117_000, $bakiye[$has->id]);
        $this->assertSame(1_500_000, $bakiye[$this->birim('TRY')->id]);
    }

    public function test_tahsilatta_cari_ve_kasa_zorunludur(): void
    {
        $this->hareketGir(['type' => 'tahsilat', 'amount' => '100'])
            ->assertSessionHasErrors(['account_id', 'cash_register_id']);

        $this->assertSame(0, Transaction::count());
    }

    public function test_kasa_gideri_cari_istemez_ve_cariye_islemez(): void
    {
        $cari = Account::factory()->create();

        $this->hareketGir(['type' => 'kasa_cikis', 'cash_register_id' => $this->kasa->id, 'account_id' => $cari->id, 'amount' => '750', 'description' => 'Kira'])
            ->assertSessionHasNoErrors();

        $this->assertNull(Transaction::first()->account_id);
        $this->assertSame([], $cari->balances());
    }

    public function test_hatali_tutarlar_reddedilir(): void
    {
        $cari = Account::factory()->create();
        $veri = ['type' => 'cari_borc', 'account_id' => $cari->id];

        $this->hareketGir($veri + ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->hareketGir($veri + ['amount' => 'abc'])->assertSessionHasErrors('amount');
        $this->hareketGir($veri + ['amount' => '1.500'])->assertSessionHasErrors('amount');
        $this->hareketGir($veri + ['amount' => '10,555'])->assertSessionHasErrors('amount'); // TL'de 3 ondalık olmaz

        $this->assertSame(0, Transaction::count());
    }

    public function test_hareket_duzenlenince_yonler_yeniden_hesaplanir(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100']);
        $hareket = Transaction::first();

        $this->actingAs($this->user)->put(route('transactions.update', $hareket), [
            'type' => 'cari_alacak',
            'account_id' => $cari->id,
            'currency_id' => $this->birim('TRY')->id,
            'amount' => '100',
            'date' => '2026-09-30',
        ])->assertSessionHasNoErrors();

        $this->assertSame(TransactionType::CariAlacak, $hareket->fresh()->type);
        $this->assertSame(-100_000, $cari->balances()[$this->birim('TRY')->id]);
    }

    public function test_genel_bilanco_hesaplanir(): void
    {
        $borclu = Account::factory()->create();
        $alacakli = Account::factory()->create();
        $tl = $this->birim('TRY')->id;

        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $borclu->id, 'amount' => '1000']);
        $this->hareketGir(['type' => 'cari_alacak', 'account_id' => $alacakli->id, 'amount' => '400']);
        $this->hareketGir(['type' => 'kasa_giris', 'cash_register_id' => $this->kasa->id, 'amount' => '250']);

        $ozet = Balances::summary()[$tl];

        $this->assertSame(250_000, $ozet['kasa']);
        $this->assertSame(1_000_000, $ozet['alacak']);
        $this->assertSame(400_000, $ozet['borc']);
        $this->assertSame(850_000, $ozet['net']);
    }

    public function test_cari_ekstresi_yuruyen_bakiye_ve_devir_gosterir(): void
    {
        $cari = Account::factory()->create(['name' => 'Ekstre Kuyumculuk']);

        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '1000', 'date' => '2026-09-01']);
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '500', 'date' => '2026-09-20', 'description' => 'Bilezik işçiliği']);

        $this->actingAs($this->user)
            ->get(route('accounts.show', ['account' => $cari, 'baslangic' => '2026-09-10']))
            ->assertOk()
            ->assertSee('Devreden bakiye')
            ->assertSee('Bilezik işçiliği')
            ->assertSee('1.500,00 ₺');
    }

    public function test_ayni_gundeki_hareketler_saat_sirasina_gore_dizilir(): void
    {
        $cari = Account::factory()->create();

        // Önce öğleden sonraki işlem girildi, sonra sabahki: ekstre saat sırasına göre dizmeli
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '200', 'date' => '2026-10-02T15:30:45', 'description' => 'Öğleden sonra']);
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100', 'date' => '2026-10-02T09:05:10', 'description' => 'Sabah']);

        $this->assertSame('2026-10-02 15:30:45', Transaction::firstWhere('description', 'Öğleden sonra')->date->format('Y-m-d H:i:s'));

        $this->actingAs($this->user)
            ->get(route('accounts.show', $cari))
            ->assertOk()
            ->assertSeeInOrder(['02.10.2026 09:05:10', 'Sabah', '02.10.2026 15:30:45', 'Öğleden sonra']);
    }

    public function test_bitis_tarihi_o_gunun_tamamini_kapsar(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100', 'date' => '2026-10-02T23:59:59', 'description' => 'Gece yarısından önce']);

        $this->actingAs($this->user)
            ->get(route('transactions.index', ['baslangic' => '2026-10-02', 'bitis' => '2026-10-02']))
            ->assertSee('Gece yarısından önce');

        $this->get(route('accounts.show', ['account' => $cari, 'bitis' => '2026-10-02']))
            ->assertSee('Gece yarısından önce');
    }

    public function test_cari_ekstrede_mutabik_isaretlenir_ve_tarih_gorunur(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100']);
        $hareket = Transaction::first();

        $this->actingAs($this->user)->get(route('accounts.show', $cari))->assertSee('Mutabakat')->assertSee('MUTABIK');

        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(14, 30));
        $this->actingAs($this->user)->post(route('transactions.reconcile', $hareket))->assertSessionHas('success');

        $hareket->refresh();
        $this->assertTrue($hareket->isReconciled());
        $this->assertSame($this->user->id, $hareket->reconciled_by);

        $this->get(route('accounts.show', $cari))->assertSee('06.10.2026 14:30');

        // Tekrar işaretlemek ilk tarihi değiştirmez
        $this->travel(2)->days();
        $this->post(route('transactions.reconcile', $hareket));
        $this->assertSame('06.10.2026 14:30', $hareket->fresh()->reconciled_at->format('d.m.Y H:i'));

        // Yönetici işareti kaldırabilir
        $this->delete(route('transactions.unreconcile', $hareket))->assertSessionHas('success');
        $this->assertFalse($hareket->fresh()->isReconciled());
    }

    public function test_personel_mutabik_isaretini_kaldiramaz(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100']);
        $hareket = Transaction::first();
        $personel = User::factory()->create(['role' => User::ROLE_PERSONEL]);

        $this->actingAs($personel)->post(route('transactions.reconcile', $hareket))->assertSessionHas('success');
        $this->actingAs($personel)->delete(route('transactions.unreconcile', $hareket))->assertForbidden();
        $this->assertTrue($hareket->fresh()->isReconciled());
    }

    public function test_kasa_ekstresinde_mutabakat_sutunu_yok(): void
    {
        $this->hareketGir(['type' => 'kasa_giris', 'cash_register_id' => $this->kasa->id, 'amount' => '100']);

        $this->actingAs($this->user)->get(route('cash-registers.show', $this->kasa))->assertOk()->assertDontSee('Mutabakat');
    }

    public function test_sayfalar_acilir(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'tahsilat', 'account_id' => $cari->id, 'cash_register_id' => $this->kasa->id, 'amount' => '100']);

        $this->actingAs($this->user);

        foreach ([
            route('dashboard'),
            route('accounts.index'),
            route('accounts.create'),
            route('accounts.show', $cari),
            route('accounts.edit', $cari),
            route('cash-registers.index'),
            route('cash-registers.create'),
            route('cash-registers.show', $this->kasa),
            route('cash-registers.edit', $this->kasa),
            route('transactions.index'),
            route('transactions.create', ['tur' => 'odeme', 'cari' => $cari->id]),
            route('transactions.edit', Transaction::first()),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_hareketi_olan_cari_silinemez(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100']);

        $this->actingAs($this->user)->delete(route('accounts.destroy', $cari))->assertSessionHas('error');

        $this->assertModelExists($cari);
    }

    public function test_personel_hareket_silemez(): void
    {
        $cari = Account::factory()->create();
        $this->hareketGir(['type' => 'cari_borc', 'account_id' => $cari->id, 'amount' => '100']);

        $personel = User::factory()->create(['role' => User::ROLE_PERSONEL]);

        $this->actingAs($personel)->delete(route('transactions.destroy', Transaction::first()))->assertForbidden();
        $this->assertSame(1, Transaction::count());
    }
}
