<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('accurate_do_id')->unique(); // Menyimpan ID Surat Jalan dari Accurate
            $table->string('accurate_do_number'); // Menyimpan Nomor Surat Jalan (DO)
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade'); // Relasi ke Sopir
            
            // ─── TAMBAHAN KOLOM ALAMAT & KORDINAT ───
            $table->text('alamat_tujuan')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            
            $table->enum('status', ['Menunggu', 'Di Perjalanan', 'Selesai'])->default('Menunggu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};