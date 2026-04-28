<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Menambah kolom untuk menyimpan Serial Number / ID dari ORIN
            $table->string('orin_device_sn')->nullable()->after('license_plate');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('orin_device_sn');
        });
    }
};