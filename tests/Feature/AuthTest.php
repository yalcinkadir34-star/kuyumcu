<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_sifresiz_acilir_ve_otomatik_giris_yapilir(): void
    {
        $user = User::factory()->create(['name' => 'Ahmet Usta']);

        $this->get('/')->assertOk()->assertSee('Merhaba, Ahmet Usta');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_her_sayfa_sifresiz_acilir(): void
    {
        User::factory()->create();

        $this->get(route('accounts.index'))->assertOk();
    }

    public function test_ilk_aktif_kullaniciyla_giris_yapilir(): void
    {
        User::factory()->create(['is_active' => false]);
        $aktif = User::factory()->create();
        User::factory()->create();

        $this->get('/')->assertOk();

        $this->assertAuthenticatedAs($aktif);
    }

    public function test_aktif_kullanici_yoksa_program_acilmaz(): void
    {
        User::factory()->create(['is_active' => false]);

        $this->get('/')->assertStatus(503);
        $this->assertGuest();
    }

    public function test_oturum_acikken_pasif_edilen_kullanici_cikarilir(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
        $this->assertGuest();
    }

    public function test_kilit_sifre_ve_cikis_tuslari_yok(): void
    {
        User::factory()->create();

        $this->get('/')->assertOk()
            ->assertDontSee('Kilitle')
            ->assertDontSee('Şifre değiştir')
            ->assertDontSee('Çıkış yap');

        $this->get('/giris')->assertNotFound();
        $this->get('/kilit')->assertNotFound();
        $this->get('/sifre')->assertNotFound();
    }
}
