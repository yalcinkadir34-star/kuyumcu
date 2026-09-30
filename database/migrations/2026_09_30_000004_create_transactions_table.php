<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hesap hareketleri. Tutar her zaman pozitiftir; cariye ve kasaya etkisi
        // yön alanlarıyla (+1 / -1 / 0) tutulur, böylece bakiye = SUM(tutar * yön).
        //   account_direction: +1 cari borçlanır (bize borçlanır), -1 cari alacaklanır
        //   cash_direction:    +1 kasaya giriş, -1 kasadan çıkış
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('type', 30);
            $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 3);
            $table->tinyInteger('account_direction')->default(0);
            $table->tinyInteger('cash_direction')->default(0);
            $table->string('document_no', 50)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'date']);
            $table->index(['cash_register_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
