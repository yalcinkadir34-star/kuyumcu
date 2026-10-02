<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Çıkış türü:
     *   atolye: müşterinin atölyeye getirdiği üründen teslim → ramattan düşer
     *   satis:  atölyenin kendi ürünü (ör. müşterinin verdiği has karşılığı) → ramatı etkilemez
     * Mevcut çıkışların hepsi "atolye".
     */
    public function up(): void
    {
        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->string('kind', 10)->default('atolye')->after('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_deliveries', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
