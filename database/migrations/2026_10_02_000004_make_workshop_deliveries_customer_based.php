<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Atölye çıkışları artık bir giriş fişine değil, doğrudan müşteriye bağlıdır.
     * ("Ürünü girdiğimde direk çıkış yapmıyorum. Hangi fişten çıktığımın önemi yok.")
     * Ramat = müşterinin tüm girişleri − tüm çıkışları.
     *
     * Mevcut çıkışlar: müşterisi ve ürün adı bağlı olduğu fişten alınır, T00001… numarası verilir.
     */
    public function up(): void
    {
        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->string('number', 20)->nullable()->after('id');
            $table->foreignId('account_id')->nullable()->after('number')->constrained()->restrictOnDelete();
            $table->string('product')->nullable()->after('account_id');
        });

        $deliveries = DB::table('work_order_deliveries as d')
            ->join('work_orders as o', 'o.id', '=', 'd.work_order_id')
            ->orderBy('d.delivered_at')
            ->orderBy('d.id')
            ->get(['d.id', 'o.account_id', 'o.product']);

        foreach ($deliveries as $i => $row) {
            DB::table('work_order_deliveries')->where('id', $row->id)->update([
                'number' => 'T'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'account_id' => $row->account_id,
                'product' => $row->product,
            ]);
        }

        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
            $table->unique('number');
            $table->index(['account_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Bu migration geri alınamaz.');
    }
};
