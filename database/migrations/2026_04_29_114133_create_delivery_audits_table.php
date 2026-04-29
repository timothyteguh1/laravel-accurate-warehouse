<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->onDelete('cascade');

            // ── 1. PROOF OF DELIVERY ──────────────────────────────
            $table->enum('pod_status', ['lengkap', 'belum_lengkap', 'tidak_ada'])
                  ->default('belum_lengkap')
                  ->comment('Status kelengkapan TTD & stempel DO fisik');
            $table->text('pod_catatan')->nullable()
                  ->comment('Catatan tambahan kondisi DO fisik');

            // ── 2. RETUR BARANG ───────────────────────────────────
            $table->boolean('ada_retur')->default(false);
            $table->decimal('retur_persen', 5, 2)->default(0.00)
                  ->comment('Persentase barang yang diretur (0–100)');
            $table->enum('retur_alasan', ['barang_rusak', 'salah_kirim', 'ditolak_pelanggan', 'lainnya'])
                  ->nullable();
            $table->text('retur_catatan')->nullable();

            // ── 3. REKONSILIASI UANG JALAN ────────────────────────
            $table->decimal('uang_diberikan', 15, 2)->default(0)
                  ->comment('Uang jalan yang diserahkan ke sopir sebelum berangkat');
            $table->decimal('biaya_bbm',   15, 2)->default(0);
            $table->decimal('biaya_tol',   15, 2)->default(0);
            $table->decimal('biaya_kuli',  15, 2)->default(0);
            $table->decimal('biaya_lain',  15, 2)->default(0)
                  ->comment('Biaya tak terduga lainnya');
            $table->text('catatan_biaya')->nullable();
            // sisa_uang = uang_diberikan - (bbm + tol + kuli + lain) → dihitung di accessor

            // ── 4. FINALISASI ACCURATE ────────────────────────────
            $table->enum('accurate_action', ['none', 'close_do', 'create_invoice'])
                  ->default('none')
                  ->comment('Aksi yang akan dikirim ke Accurate saat Approve');
            $table->json('accurate_result')->nullable()
                  ->comment('Response mentah dari Accurate API setelah approve');

            // ── STATUS AUDIT ──────────────────────────────────────
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->string('audited_by')->nullable()
                  ->comment('Nama admin yang melakukan approve');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_audits');
    }
};