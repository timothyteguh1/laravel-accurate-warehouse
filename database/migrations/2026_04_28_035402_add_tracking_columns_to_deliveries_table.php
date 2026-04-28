<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Kolom untuk mencatat jam keberangkatan & kepulangan
            $table->timestamp('waktu_berangkat')->nullable()->after('status');
            $table->timestamp('waktu_kembali')->nullable()->after('waktu_berangkat');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['waktu_berangkat', 'waktu_kembali']);
        });
    }
};