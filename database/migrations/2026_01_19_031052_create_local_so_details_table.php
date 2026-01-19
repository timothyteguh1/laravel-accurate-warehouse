<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('local_so_details', function (Blueprint $table) {
        $table->id();
        $table->string('so_number');        // Simpan No SO (ex: SO.2026.01.00009)
        $table->string('item_no');          // Simpan Kode Barang (ex: LPT-359)
        $table->bigInteger('accurate_detail_id'); // <--- INI YG PALING PENTING
        $table->integer('quantity');
        $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_so_details');
    }
};
