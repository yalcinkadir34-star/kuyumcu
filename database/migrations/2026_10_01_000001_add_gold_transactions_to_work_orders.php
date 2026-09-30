<?php

use App\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Firmanın getirdiği altın cariye has olarak işlenir:
        //   giriş  → cari alacak (biz firmaya borçlanırız)
        //   teslim → cari borç   (geri verdiğimiz has kadar borcumuz düşer)
        //   fire   → firma üstlenirse cari borç
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('in_transaction_id')->nullable()->after('labor_currency_id')->constrained('transactions')->nullOnDelete();
            $table->foreignId('out_transaction_id')->nullable()->after('in_transaction_id')->constrained('transactions')->nullOnDelete();
            $table->foreignId('fire_transaction_id')->nullable()->after('out_transaction_id')->constrained('transactions')->nullOnDelete();
            $table->string('fire_bearer', 10)->nullable()->after('fire_has'); // firma | atolye
        });

        // Daha önce girilmiş fişleri cariye işle
        WorkOrder::query()->orderBy('id')->each(function (WorkOrder $order) {
            if ($order->isDelivered()) {
                $order->fire_bearer ??= WorkOrder::FIRE_FIRMA;
            }

            $order->syncGoldTransactions();
            $order->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('in_transaction_id');
            $table->dropConstrainedForeignId('out_transaction_id');
            $table->dropConstrainedForeignId('fire_transaction_id');
            $table->dropColumn('fire_bearer');
        });
    }
};
