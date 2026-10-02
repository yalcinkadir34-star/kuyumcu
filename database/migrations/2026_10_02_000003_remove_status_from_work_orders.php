<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiş kapatma kaldırıldı: müşteri sürekli ürün gönderdiği için fişler hiçbir zaman "bitmez",
     * ramatta kalan Ramat sayfasında izlenir. Durum (atölyede/tamamlandı) ve kapanış tarihi silinir.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'received_at']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'closed_at']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['received_at']);
            $table->string('status', 20)->default('atolyede')->after('product');
            $table->date('closed_at')->nullable()->after('status');
            $table->index(['status', 'received_at']);
        });
    }
};
