<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\GoogleDrive;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YedeklemeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Storage::fake('local');

        config([
            'kuyumcu.backup.google_client_id' => 'test-client',
            'kuyumcu.backup.google_client_secret' => 'test-secret',
        ]);

        // backup:run yerine sahte bir yedek dosyası oluştur (testte mysqldump çalıştırılmaz)
        Artisan::command('backup:run {--only-db} {--disable-notifications}', function () {
            Storage::disk('local')->put('kuyumcu-yedek/kuyumcu-2026-10-01-10-00-00.zip', 'zip-icerigi');
        });
        Artisan::command('backup:clean {--disable-notifications}', fn () => 0);
    }

    private function driveBagla(): void
    {
        Setting::putSecret('google_drive_refresh_token', 'refresh-token');
        Setting::put('google_drive_email', 'atolye@gmail.com');
        Setting::put('google_drive_folder_id', 'klasor-123');
    }

    public function test_drive_bagli_degilken_yedek_sadece_yerelde_saklanir(): void
    {
        $this->actingAs($this->admin)->post(route('backups.store'))->assertSessionHas('success');

        $log = BackupLog::first();
        $this->assertTrue($log->isSuccessful());
        $this->assertSame('manuel', $log->trigger);
        $this->assertSame('bagli_degil', $log->drive_status);
        $this->assertSame('kuyumcu-2026-10-01-10-00-00.zip', $log->file_name);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_drive_bagliyken_yedek_yuklenir_ve_eskiler_silinir(): void
    {
        $this->driveBagla();
        config(['kuyumcu.backup.drive_keep' => 2]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'expires_in' => 3600]),
            'www.googleapis.com/upload/*' => Http::response(['id' => 'drive-dosya-1']),
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => [
                ['id' => 'a', 'name' => '3.zip', 'createdTime' => '2026-10-03'],
                ['id' => 'b', 'name' => '2.zip', 'createdTime' => '2026-10-02'],
                ['id' => 'c', 'name' => '1.zip', 'createdTime' => '2026-10-01'],
            ]]),
            'www.googleapis.com/drive/v3/files/c' => Http::response(null, 204),
        ]);

        $log = app(BackupService::class)->run(BackupLog::TRIGGER_OTOMATIK);

        $this->assertSame('yuklendi', $log->drive_status);
        $this->assertSame('drive-dosya-1', $log->drive_file_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'upload/drive/v3/files')
            && str_contains($request->body(), 'klasor-123')
            && str_contains($request->body(), 'zip-icerigi'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/files/c'));
    }

    public function test_drive_hatasi_yedegi_bozmaz_ve_kaydedilir(): void
    {
        $this->driveBagla();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']),
            'www.googleapis.com/upload/*' => Http::response(['error' => 'quota'], 403),
        ]);

        $this->actingAs($this->admin)->post(route('backups.store'))->assertSessionHas('error');

        $log = BackupLog::first();
        $this->assertTrue($log->isSuccessful());
        $this->assertSame('hata', $log->drive_status);
        $this->assertStringContainsString('Google Drive', $log->error);
    }

    public function test_drive_klasoru_ayarliysa_yedek_klasore_kopyalanir_ve_eskiler_silinir(): void
    {
        $root = storage_path('framework/testing/drive-'.uniqid());
        $folder = "{$root}/Kuyumcu Yedekleri";
        mkdir($folder, 0777, true);
        config(['kuyumcu.backup.drive_klasoru' => $folder, 'kuyumcu.backup.drive_keep' => 2]);

        foreach (['eski-1.zip' => 100, 'eski-2.zip' => 50] as $name => $age) {
            file_put_contents("{$folder}/{$name}", 'x');
            touch("{$folder}/{$name}", time() - $age);
        }

        try {
            $this->actingAs($this->admin)->post(route('backups.store'))->assertSessionHas('success');

            $log = BackupLog::first();
            $this->assertSame('yuklendi', $log->drive_status);
            $this->assertSame('zip-icerigi', file_get_contents("{$folder}/kuyumcu-2026-10-01-10-00-00.zip"));
            $this->assertFileDoesNotExist("{$folder}/eski-1.zip");
            $this->assertFileExists("{$folder}/eski-2.zip");

            $this->get(route('backups.index'))
                ->assertSee('Masaüstü klasörü')
                ->assertDontSee('Google Drive kurulumu');
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_drive_klasoru_yoksa_yedek_yerelde_kalir_hata_kaydedilir(): void
    {
        config(['kuyumcu.backup.drive_klasoru' => storage_path('framework/testing/olmayan-surucu/alt/Kuyumcu Yedekleri')]);

        $this->actingAs($this->admin)->post(route('backups.store'))->assertSessionHas('error');

        $log = BackupLog::first();
        $this->assertTrue($log->isSuccessful());
        $this->assertSame('hata', $log->drive_status);
        $this->assertStringContainsString('Google Drive programı açık mı', $log->error);
    }

    public function test_google_baglantisi_kurulur(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'yeni-refresh', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['emailAddress' => 'atolye@gmail.com']]),
            'www.googleapis.com/drive/v3/files?fields=id' => Http::response(['id' => 'yeni-klasor']),
        ]);

        $redirect = $this->actingAs($this->admin)->get(route('backups.google.connect'));
        $redirect->assertRedirectContains('accounts.google.com');
        $state = session('google_drive_state');

        $this->get(route('backups.google.callback', ['code' => 'kod', 'state' => $state]))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success');

        $drive = app(GoogleDrive::class);
        $this->assertTrue($drive->isConnected());
        $this->assertSame('atolye@gmail.com', $drive->accountEmail());
        $this->assertSame('yeni-refresh', Setting::getSecret('google_drive_refresh_token'));
        $this->assertNotSame('yeni-refresh', Setting::get('google_drive_refresh_token')); // şifreli saklanır
        $this->assertSame('yeni-klasor', Setting::get('google_drive_folder_id'));
    }

    public function test_sahte_state_ile_baglanti_reddedilir(): void
    {
        $this->actingAs($this->admin)->get(route('backups.google.connect'));

        $this->get(route('backups.google.callback', ['code' => 'kod', 'state' => 'sahte']))
            ->assertSessionHas('error');

        $this->assertFalse(app(GoogleDrive::class)->isConnected());
    }

    public function test_baglanti_kaldirilir(): void
    {
        $this->driveBagla();
        Http::fake(['oauth2.googleapis.com/revoke*' => Http::response()]);

        $this->actingAs($this->admin)->delete(route('backups.google.disconnect'))->assertSessionHas('success');

        $this->assertFalse(app(GoogleDrive::class)->isConnected());
    }

    public function test_yedek_indirilir(): void
    {
        $this->actingAs($this->admin)->post(route('backups.store'));

        $this->get(route('backups.download', BackupLog::first()))
            ->assertOk()
            ->assertDownload('kuyumcu-2026-10-01-10-00-00.zip');
    }

    public function test_otomatik_yedek_gunde_uc_kez_planlanir(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'yedek:al'));

        $this->assertSame(['0 10 * * *', '0 15 * * *', '0 20 * * *'], $events->pluck('expression')->values()->all());
    }

    public function test_yedek_komutu_calisir(): void
    {
        $this->artisan('yedek:al')->assertSuccessful();

        $this->assertSame('otomatik', BackupLog::first()->trigger);
    }

    public function test_personel_yedekleme_sayfasini_goremez(): void
    {
        $personel = User::factory()->create(['role' => User::ROLE_PERSONEL]);

        $this->actingAs($personel)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($personel)->post(route('backups.store'))->assertForbidden();
        $this->actingAs($personel)->get(route('dashboard'))->assertOk()->assertDontSee('Yedek Al');
    }

    public function test_yedekleme_sayfasi_acilir(): void
    {
        $this->actingAs($this->admin)->post(route('backups.store'));

        $this->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Şimdi Yedek Al')
            ->assertSee('Google Drive kurulumu')
            ->assertSee('kuyumcu-2026-10-01-10-00-00.zip');
    }
}
