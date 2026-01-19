<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalSoDetail extends Model
{
    use HasFactory;

    // Menentukan nama tabel (sesuai migration yang tadi dibuat)
    protected $table = 'local_so_details';

    // Daftar kolom yang boleh diisi secara massal (Mass Assignment)
    // Ini penting agar perintah create() di controller bisa jalan
    protected $fillable = [
        'so_number',          // Nomor SO (contoh: SO.2026.01.00009)
        'item_no',            // Kode Barang (contoh: LPT-359)
        'accurate_detail_id', // ID baris dari Accurate (PENTING UNTUK LINKING)
        'quantity',           // Jumlah barang
        'status',             // Status lokal (OPEN/CLOSED)
    ];

    // (Opsional) Memastikan tipe data outputnya benar
    protected $casts = [
        'quantity' => 'integer',
        'accurate_detail_id' => 'integer',
    ];
}