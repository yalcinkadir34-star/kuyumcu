<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_misafir_giris_sayfasina_yonlendirilir(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_giris_sayfasi_acilir(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Giriş yap');
    }

    public function test_dogru_bilgilerle_giris_yapilir(): void
    {
        $user = User::factory()->create(['username' => 'usta']);

        $this->post(route('login.attempt'), ['username' => 'usta', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_yanlis_sifre_ile_giris_yapilamaz(): void
    {
        User::factory()->create(['username' => 'usta']);

        $this->from(route('login'))
            ->post(route('login.attempt'), ['username' => 'usta', 'password' => 'yanlis'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_pasif_kullanici_giris_yapamaz(): void
    {
        User::factory()->create(['username' => 'usta', 'is_active' => false]);

        $this->post(route('login.attempt'), ['username' => 'usta', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_oturum_acikken_pasif_edilen_kullanici_cikarilir(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_bes_hatali_denemeden_sonra_giris_engellenir(): void
    {
        User::factory()->create(['username' => 'usta']);

        foreach (range(1, 5) as $i) {
            $this->post(route('login.attempt'), ['username' => 'usta', 'password' => 'yanlis']);
        }

        $this->post(route('login.attempt'), ['username' => 'usta', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_giris_yapan_kullanici_dashboardu_gorur(): void
    {
        $user = User::factory()->create(['name' => 'Ahmet Usta']);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Merhaba, Ahmet Usta');
    }

    public function test_cikis_yapilir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
