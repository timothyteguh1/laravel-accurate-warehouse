<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'accurate_do_id', 
        'accurate_do_number', 
        'driver_id', 
        'status',
        // ─── TAMBAHAN ALLOW INSERT ───
        'alamat_tujuan',
        'latitude',
        'longitude',
        // ─── TAMBAHAN TAHAP 1 (TRACKING WAKTU) ───
        'waktu_berangkat',
        'waktu_kembali'
    ];

    // Mengubah format database menjadi objek Carbon DateTime otomatis
    protected $casts = [
        'waktu_berangkat' => 'datetime',
        'waktu_kembali'   => 'datetime',
    ];

    // Relasi: 1 Pengiriman (DO) dibawa oleh 1 Sopir
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}