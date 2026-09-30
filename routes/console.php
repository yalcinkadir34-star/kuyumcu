<?php

use App\Models\WorkOrder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
