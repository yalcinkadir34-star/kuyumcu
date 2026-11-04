<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSessionUnlocked;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KilitTest extends TestCase
{
    use RefreshDatabase;

    public function test_iki_dakika_bos_kalinca_ekran_kilitlenir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionUnlocked::LAST_ACTIVITY => now()->subMinutes(3)->timestamp])
            ->get(route('accounts.index'))
            ->assertRedirect(route('lock.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_iki_dakikadan_once_kilitlenmez(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionUnlocked::LAST_ACTIVITY => now()->subMinutes(1)->timestamp])
            ->get(route('accounts.index'))
            ->assertOk();
    }

    public function test_kilitle_tusu_ekrani_kilitler_ve_veri_gostermez(): void
    {
        $user = User::factory()->create(['name' => 'Ahmet Usta']);

        $this->actingAs($user)->get(route('lock.show'))
            ->assertOk()
            ->assertSee('Ekran kilitli')
            ->assertDontSee('Cariler');

        $this->get('/')->assertRedirect(route('lock.show'));
    }

    public function test_dogru_sifreyle_kilit_acilir_ve_kalinan_sayfaya_donulur(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionUnlocked::LAST_ACTIVITY => now()->subMinutes(10)->timestamp])
            ->get(route('accounts.index'));

        $this->post(route('lock.unlock'), ['password' => 'password'])
            ->assertRedirect(route('accounts.index'));

        $this->get(route('accounts.index'))->assertOk();
    }

    public function test_yanlis_sifreyle_kilit_acilmaz(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('lock.show'));

        $this->from(route('lock.show'))
            ->post(route('lock.unlock'), ['password' => 'yanlis'])
            ->assertRedirect(route('lock.show'))
            ->assertSessionHasErrors('password');

        $this->get('/')->assertRedirect(route('lock.show'));
    }

    public function test_kilitliyken_ajax_istegi_reddedilir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('lock.show'));

        $this->postJson(route('accounts.quick-store'), ['name' => 'Yeni'])->assertStatus(423);
    }

    public function test_giris_ekraninda_beni_hatirla_yok(): void
    {
        $this->get(route('login'))->assertDontSee('Beni hatırla');
    }

    public function test_kullanici_kendi_sifresini_degistirir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'yeni1234',
            'password_confirmation' => 'yeni1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('yeni1234', $user->fresh()->password));
    }

    public function test_mevcut_sifre_yanlissa_sifre_degismez(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('password.edit'))->put(route('password.update'), [
            'current_password' => 'yanlis',
            'password' => 'yeni1234',
            'password_confirmation' => 'yeni1234',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
