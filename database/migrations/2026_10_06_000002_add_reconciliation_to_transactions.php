<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cari ekstrede "MUTABIK" işareti: müşteriyle o satıra kadar mutabık kalındığı an ve kimin işaretlediği.
     * İleride anlaşmazlık olursa "şu tarihte mutabık kalmıştık" denebilsin.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dateTime('reconciled_at')->nullable()->after('updated_by');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn('reconciled_at');
        });
    }
};
