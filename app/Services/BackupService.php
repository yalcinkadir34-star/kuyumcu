<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Veritabanı yedeği alır (spatie/laravel-backup), Google Drive'a yükler,
 * eski yedekleri temizler ve sonucu backup_logs tablosuna yazar.
 */
class BackupService
{
    public function __construct(private GoogleDrive $drive) {}

    public function run(string $trigger, ?User $user = null): BackupLog
    {
        // Aynı anda iki yedek alınmasın (ör. butona çift tıklama + otomatik yedek)
        $lock = Cache::lock('kuyumcu-yedek', 600);

        if (! $lock->get()) {
            throw new RuntimeException('Şu anda başka bir yedekleme sürüyor. Birazdan tekrar deneyin.');
        }

        try {
            return $this->backup($trigger, $user);
        } finally {
            $lock->release();
        }
    }

    private function backup(string $trigger, ?User $user): BackupLog
    {
        $log = new BackupLog(['trigger' => $trigger, 'user_id' => $user?->id]);

        try {
            $this->ensureWindowsEnvironment();
            $startedAt = time();

            $exitCode = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
            $file = $this->latestBackupFile();

            if ($exitCode !== 0 || $file === null || Storage::disk('local')->lastModified($file) < $startedAt - 5) {
                throw new RuntimeException('Yedek dosyası oluşturulamadı: '.$this->shortError(Artisan::output()));
            }

            $log->fill([
                'status' => 'basarili',
                'file_name' => basename($file),
                'file_path' => $file,
                'size' => Storage::disk('local')->size($file),
            ]);
        } catch (Throwable $e) {
            Log::error('Yedekleme hatası', ['exception' => $e]);
            $log->fill(['status' => 'hata', 'error' => $e->getMessage()])->save();

            return $log;
        }

        $this->uploadToDrive($log);
        $log->save();

        // Yerel eski yedekleri temizle (config/backup.php → cleanup)
        Artisan::call('backup:clean', ['--disable-notifications' => true]);

        return $log;
    }

    private function uploadToDrive(BackupLog $log): void
    {
        if (self::driveFolder()) {
            $this->copyToDriveFolder($log);

            return;
        }

        if (! $this->drive->isConnected()) {
            $log->drive_status = 'bagli_degil';

            return;
        }

        try {
            $log->drive_file_id = $this->drive->upload(Storage::disk('local')->path($log->file_path), $log->file_name);
            $log->drive_status = 'yuklendi';
            $this->drive->prune(config('kuyumcu.backup.drive_keep'));
        } catch (Throwable $e) {
            Log::error('Google Drive yükleme hatası', ['exception' => $e]);
            $log->drive_status ??= 'hata';
            $log->error = 'Google Drive: '.$e->getMessage();
        }
    }

    /** Google Drive masaüstü programının klasörü (config: kuyumcu.backup.drive_klasoru); boşsa null. */
    public static function driveFolder(): ?string
    {
        $folder = trim((string) config('kuyumcu.backup.drive_klasoru'));

        return $folder === '' ? null : rtrim(str_replace('\\', '/', $folder), '/');
    }

    /**
     * Yedeği Google Drive masaüstü klasörüne kopyalar; Drive programı internete yükler.
     * Klasördeki en yeni drive_keep yedek tutulur, eskileri silinir.
     */
    private function copyToDriveFolder(BackupLog $log): void
    {
        $folder = self::driveFolder();

        try {
            // Üst klasör yoksa (ör. G: sürücüsü takılı değil) Drive programı çalışmıyordur
            if (! is_dir(dirname($folder))) {
                throw new RuntimeException("{$folder} bulunamadı. Google Drive programı açık mı?");
            }

            if (! is_dir($folder) && ! mkdir($folder, 0777, true) && ! is_dir($folder)) {
                throw new RuntimeException("{$folder} klasörü oluşturulamadı.");
            }

            if (! copy(Storage::disk('local')->path($log->file_path), "{$folder}/{$log->file_name}")) {
                throw new RuntimeException("Yedek {$folder} klasörüne kopyalanamadı.");
            }

            $log->drive_status = 'yuklendi';

            collect(glob("{$folder}/*.zip") ?: [])
                ->sortByDesc(fn (string $path) => filemtime($path))
                ->slice((int) config('kuyumcu.backup.drive_keep'))
                ->each(fn (string $path) => @unlink($path));
        } catch (Throwable $e) {
            Log::error('Google Drive klasörüne kopyalama hatası', ['exception' => $e]);
            $log->drive_status ??= 'hata';
            $log->error = 'Google Drive: '.$e->getMessage();
        }
    }

    /**
     * Windows'ta web isteğinden başlatılan mysqldump, SystemRoot ortam değişkeni
     * olmadan ağ bağlantısı kuramaz ("Can't create TCP/IP socket (10106)").
     * Symfony Process alt işleme ortamı $_SERVER/$_ENV üzerinden süzerek aktardığı
     * için değişken oralara da yazılır.
     */
    private function ensureWindowsEnvironment(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows';

        putenv("SystemRoot={$systemRoot}");
        $_ENV['SystemRoot'] = $_SERVER['SystemRoot'] = $systemRoot;
    }

    /** backup:run çıktısından kullanıcıya gösterilecek kısa hata metni (yığın izi olmadan). */
    private function shortError(string $output): string
    {
        $output = preg_replace('/\s+/', ' ', $output);

        if (preg_match('/Backup failed because:\s*(.+?)(?:\s#0\s|$)/', $output, $m)) {
            $output = $m[1];
        }

        return Str::limit(trim($output), 300);
    }

    private function latestBackupFile(): ?string
    {
        return collect(Storage::disk('local')->files(config('backup.backup.name')))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sortByDesc(fn (string $path) => Storage::disk('local')->lastModified($path))
            ->first();
    }
}
