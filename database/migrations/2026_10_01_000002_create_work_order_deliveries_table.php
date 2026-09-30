<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parçalı çıkış: bir atölye girişi birden fazla seferde teslim edilebilir.
     * Giriş − çıkışlar = atölyede kalan. Fire cariye işlenmez.
     */
    public function up(): void
    {
        Schema::create('work_order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('delivered_at');
            $table->decimal('gross_out', 12, 3);
            $table->decimal('has_out', 12, 3);
            $table->string('labor_basis', 10);                 // gram | toplam
            $table->decimal('labor_rate', 14, 3);
            $table->decimal('labor_total', 18, 3);
            $table->foreignId('labor_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('out_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('labor_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('delivered_at');
        });

        // Mevcut tek seferlik teslimleri çıkış kaydına dönüştür, fire kayıtlarını sil
        foreach (DB::table('work_orders')->whereNotNull('gross_out')->get() as $order) {
            DB::table('work_order_deliveries')->insert([
                'work_order_id' => $order->id,
                'delivered_at' => $order->delivered_at,
                'gross_out' => $order->gross_out,
                'has_out' => $order->has_out,
                'labor_basis' => $order->labor_basis ?? 'gram',
                'labor_rate' => $order->labor_rate ?? 0,
                'labor_total' => $order->labor_total ?? 0,
                'labor_currency_id' => $order->labor_currency_id ?? DB::table('currencies')->where('code', 'TRY')->value('id'),
                'out_transaction_id' => $order->out_transaction_id,
                'labor_transaction_id' => $order->transaction_id,
                'created_by' => $order->created_by,
                'created_at' => $order->updated_at,
                'updated_at' => $order->updated_at,
            ]);

            if ($order->fire_transaction_id) {
                DB::table('work_orders')->where('id', $order->id)->update(['fire_transaction_id' => null]);
                DB::table('transactions')->where('id', $order->fire_transaction_id)->delete();
            }
        }

        // Kalanı olan fişler tekrar atölyede; tamamı çıkmış olanlar tamamlandı
        DB::table('work_orders')->update(['status' => DB::raw(
            "CASE WHEN gross_out IS NOT NULL AND gross_out >= gross_in THEN 'tamamlandi' ELSE 'atolyede' END"
        )]);

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropConstrainedForeignId('out_transaction_id');
            $table->dropConstrainedForeignId('fire_transaction_id');
            $table->dropConstrainedForeignId('labor_currency_id');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['delivered_at']);
            $table->dropColumn([
                'delivered_at', 'gross_out', 'has_out', 'fire_gram', 'fire_has', 'fire_bearer',
                'labor_basis', 'labor_rate', 'labor_total',
            ]);

            // Fiş kapatılınca atölyede kalan fire sayılır (sadece rapor için, cariye işlenmez)
            $table->date('closed_at')->nullable()->after('status');
        });

        // Cariye hiç işlenmemiş girişler varsa işle (giriş has'ı → cari alacak)
        $hasId = DB::table('currencies')->where('code', 'HAS')->value('id');

        foreach (DB::table('work_orders')->whereNull('in_transaction_id')->get() as $order) {
            $id = DB::table('transactions')->insertGetId([
                'date' => $order->received_at,
                'type' => 'cari_alacak',
                'account_id' => $order->account_id,
                'currency_id' => $hasId,
                'amount' => $order->has_in,
                'account_direction' => -1,
                'cash_direction' => 0,
                'document_no' => $order->number,
                'description' => "Atölye girişi: {$order->product}",
                'created_by' => $order->created_by,
                'updated_by' => $order->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('work_orders')->where('id', $order->id)->update(['in_transaction_id' => $id]);
        }
    }

    public function down(): void
    {
        // Geri dönüş desteklenmiyor: veri yapısı değişti.
        throw new RuntimeException('Bu migration geri alınamaz.');
    }
};
