<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accurate_tokens', function (Blueprint $table) {
            $table->id(); // Kita nanti cuma pakai ID 1
            $table->text('access_token'); // Token Masuk
            $table->text('refresh_token'); // Token untuk perpanjang masa aktif
            $table->string('token_type')->default('Bearer');
            $table->string('scope')->nullable();
            
            // Simpan Session Database Accurate juga disini biar sekalian aman
            // Jadi tidak perlu login ulang buat buka database
            $table->string('session')->nullable(); 
            $table->string('host')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accurate_tokens');
    }
};