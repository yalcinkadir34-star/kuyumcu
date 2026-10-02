<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İşlem tarihleri saat:dakika:saniye ile tutulur; ekstre ve listeler gün içinde de
     * gerçek işlem sırasına göre dizilir.
     */
    public function up(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dateTime('date')->change());
        Schema::table('work_orders', fn (Blueprint $table) => $table->dateTime('received_at')->change());
        Schema::table('work_order_deliveries', fn (Blueprint $table) => $table->dateTime('delivered_at')->change());

        // Mevcut kayıtlar 00:00:00 oldu; saatini kaydın sisteme girildiği andan al (gün aynı kalır)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE transactions SET `date` = TIMESTAMP(DATE(`date`), TIME(created_at)) WHERE created_at IS NOT NULL');
            DB::statement('UPDATE work_orders SET received_at = TIMESTAMP(DATE(received_at), TIME(created_at)) WHERE created_at IS NOT NULL');
            DB::statement('UPDATE work_order_deliveries SET delivered_at = TIMESTAMP(DATE(delivered_at), TIME(created_at)) WHERE created_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->date('date')->change());
        Schema::table('work_orders', fn (Blueprint $table) => $table->date('received_at')->change());
        Schema::table('work_order_deliveries', fn (Blueprint $table) => $table->date('delivered_at')->change());
    }
};
