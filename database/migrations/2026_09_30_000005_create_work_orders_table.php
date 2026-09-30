<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Atölye iş emirleri (fason işçilik). Firmadan gelen ürün tartılıp milyemiyle
        // girilir; işlem sonrası tekrar tartılarak fire ve işçilik hesaplanır.
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('product');
            $table->string('status', 20)->default('atolyede'); // atolyede | teslim_edildi

            // Giriş
            $table->date('received_at');
            $table->decimal('gross_in', 12, 3);       // giriş brüt gram
            $table->decimal('purity', 5, 4);          // milyem, ör. 0.5850
            $table->decimal('has_in', 12, 3);         // gross_in × purity

            // Çıkış
            $table->date('delivered_at')->nullable();
            $table->decimal('gross_out', 12, 3)->nullable();  // tartıdaki net gram
            $table->decimal('has_out', 12, 3)->nullable();
            $table->decimal('fire_gram', 12, 3)->nullable();  // gross_in − gross_out
            $table->decimal('fire_has', 12, 3)->nullable();

            // İşçilik
            $table->string('labor_basis', 10)->nullable();    // gram | toplam
            $table->decimal('labor_rate', 14, 3)->nullable(); // gram başı ücret
            $table->decimal('labor_total', 18, 3)->nullable();
            $table->foreignId('labor_currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete(); // işçilik cari kaydı

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index(['account_id', 'received_at']);
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
