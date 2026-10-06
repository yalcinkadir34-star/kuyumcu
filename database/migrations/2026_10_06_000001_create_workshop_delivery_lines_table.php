<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bir atölye çıkışı birden fazla satırdan oluşur (farklı ürün/milyem/işçilik):
     *   work_order_deliveries      → çıkış başlığı: no, müşteri, tür, tarih, not
     *   work_order_delivery_lines  → satırlar: ürün, gram, çıkış milyemi, has, cari kaydı
     * Mevcut çıkışların her biri tek satıra dönüştürülür; has ve cari kayıtları aynen taşınır.
     */
    public function up(): void
    {
        Schema::create('work_order_delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_delivery_id')->constrained()->cascadeOnDelete();
            $table->string('product')->nullable();
            $table->decimal('gross_out', 12, 3);
            $table->decimal('purity_out', 5, 4);
            $table->decimal('has_out', 12, 3);
            $table->foreignId('out_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('work_order_deliveries')->orderBy('id')->get() as $d) {
            DB::table('work_order_delivery_lines')->insert([
                'work_order_delivery_id' => $d->id,
                'product' => $d->product,
                'gross_out' => $d->gross_out,
                'purity_out' => $d->purity_out,
                'has_out' => $d->has_out,
                'out_transaction_id' => $d->out_transaction_id,
                'created_at' => $d->created_at,
                'updated_at' => $d->updated_at,
            ]);
        }

        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('out_transaction_id');
        });

        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->dropColumn(['product', 'gross_out', 'purity_out', 'has_out']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Bu migration geri alınamaz.');
    }
};
