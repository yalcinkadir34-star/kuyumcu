<?php

use App\Models\BackupLog;
use App\Models\WorkOrder;
use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('atolye:yeniden-hesapla', function () {
    $count = 0;

    WorkOrder::query()->orderBy('id')->each(function (WorkOrder $order) use (&$count) {
        $order->saveWithTransactions();
        $count++;
    });

    $this->info("{$count} atölye fişinin has ve cari kayıtları yeniden hesaplandı.");
})->purpose('Atölye fişlerinin has değerlerini ve bağlı cari kayıtlarını güncel formülle yeniden hesaplar');

Artisan::command('yedek:al', function (BackupService $backups) {
    $log = $backups->run(BackupLog::TRIGGER_OTOMATIK);

    if (! $log->isSuccessful()) {
        $this->error('Yedek alınamadı: '.$log->error);

        return 1;
    }

    $this->info("Yedek alındı: {$log->file_name} ({$log->sizeLabel()})");
    $this->line(match ($log->drive_status) {
        'yuklendi' => 'Google Drive\'a yüklendi.',
        'bagli_degil' => 'Google Drive bağlı değil, sadece bu bilgisayarda saklandı.',
        default => 'Google Drive\'a yüklenemedi: '.$log->error,
    });

    return 0;
})->purpose('Veritabanı yedeği alır ve Google Drive\'a yükler');

// Otomatik yedek: günde 3 kez (config/kuyumcu.php → backup.times)
foreach (config('kuyumcu.backup.times') as $time) {
    Schedule::command('yedek:al')->dailyAt($time)->withoutOverlapping();
}
