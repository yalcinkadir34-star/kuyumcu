<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uygulama ayarları (anahtar-değer). Gizli değerler şifreli saklanır.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Alınan yedeklerin geçmişi
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 20);                  // manuel | otomatik
            $table->string('status', 20);                   // basarili | hata
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();        // local diskteki yol
            $table->unsignedBigInteger('size')->nullable(); // bayt
            $table->string('drive_status', 20)->nullable(); // yuklendi | bagli_degil | hata
            $table->string('drive_file_id')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('settings');
    }
};
