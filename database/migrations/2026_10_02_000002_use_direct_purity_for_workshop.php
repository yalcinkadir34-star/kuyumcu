<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İşçilik ayrı girilmez; giriş ve çıkışta işçilik dahil milyem doğrudan yazılır
     * (ör. giriş 0,595, çıkış 0,625). Has değerleri değişmez:
     *   work_orders.purity                 ← purity + labor_purity_in
     *   work_order_deliveries.purity_out   ← (eski) purity + labor_purity
     */
    public function up(): void
    {
        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->decimal('purity_out', 5, 4)->default(0)->after('gross_out');
        });

        // Önce çıkışlar (eski ayar milyemiyle), sonra girişler dönüştürülür
        foreach (DB::table('work_order_deliveries as d')->join('work_orders as o', 'o.id', '=', 'd.work_order_id')
            ->select('d.id', 'd.labor_purity', 'o.purity')->get() as $row) {
            DB::table('work_order_deliveries')->where('id', $row->id)
                ->update(['purity_out' => round($row->purity + $row->labor_purity, 4)]);
        }

        foreach (DB::table('work_orders')->select('id', 'purity', 'labor_purity_in')->get() as $row) {
            DB::table('work_orders')->where('id', $row->id)
                ->update(['purity' => round($row->purity + $row->labor_purity_in, 4)]);
        }

        Schema::table('work_order_deliveries', fn (Blueprint $table) => $table->dropColumn('labor_purity'));
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropColumn('labor_purity_in'));
    }

    public function down(): void
    {
        throw new RuntimeException('Bu migration geri alınamaz.');
    }
};
