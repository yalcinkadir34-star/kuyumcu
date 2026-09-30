<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * İşçilik milyem olarak alınır ve ayar milyemine eklenir:
     *   Giriş has = giriş gramı × (ayar milyemi + giriş işçiliği)   ör. 26,25 × (0,585 + 0,010)
     *   Çıkış has = çıkış gramı × (ayar milyemi + çıkış işçiliği)   ör.  6,97 × (0,585 + 0,040)
     * Ayrı bir işçilik cari kaydı yoktur; işçilik has hesabının içindedir.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('labor_purity_in', 5, 4)->default(0)->after('purity');
        });

        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->decimal('labor_purity', 5, 4)->default(0)->after('gross_out');
        });

        // Eski kayıtlar: işçilik "gram başı has" girildiyse bu, çıkış milyeminin tamamıdır
        // (kullanıcı 0,625 yazmıştı). İşçilik milyemi = girilen − ayar milyemi.
        $deliveries = DB::table('work_order_deliveries as d')
            ->join('work_orders as o', 'o.id', '=', 'd.work_order_id')
            ->join('currencies as c', 'c.id', '=', 'd.labor_currency_id')
            ->select('d.*', 'o.purity as order_purity', 'c.code as labor_code')
            ->get();

        foreach ($deliveries as $d) {
            $laborPurity = $d->labor_code === 'HAS' && $d->labor_basis === 'gram' && $d->labor_rate > $d->order_purity
                ? round($d->labor_rate - $d->order_purity, 4)
                : 0;

            DB::table('work_order_deliveries')->where('id', $d->id)->update(['labor_purity' => $laborPurity]);

            if ($d->labor_transaction_id) {
                DB::table('work_order_deliveries')->where('id', $d->id)->update(['labor_transaction_id' => null]);
                DB::table('transactions')->where('id', $d->labor_transaction_id)->delete();
            }
        }

        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('labor_transaction_id');
            $table->dropConstrainedForeignId('labor_currency_id');
        });

        Schema::table('work_order_deliveries', function (Blueprint $table) {
            $table->dropColumn(['labor_basis', 'labor_rate', 'labor_total']);
        });

        // Has ve cari tutarları yeni formülle yeniden hesaplanır (bkz. php artisan atolye:yeniden-hesapla)
    }

    public function down(): void
    {
        throw new RuntimeException('Bu migration geri alınamaz.');
    }
};
