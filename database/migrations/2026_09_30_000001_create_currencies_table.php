<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hesap birimleri: para birimleri ve altın (gram) aynı tabloda tutulur.
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 50);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('currencies')->insert([
            ['code' => 'TRY', 'name' => 'Türk Lirası', 'symbol' => '₺', 'decimals' => 2, 'sort' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name' => 'Amerikan Doları', 'symbol' => '$', 'decimals' => 2, 'sort' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'sort' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'HAS', 'name' => 'Has Altın', 'symbol' => 'gr', 'decimals' => 3, 'sort' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
