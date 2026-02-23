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
        'status'
    ];

    // Relasi: 1 Pengiriman (DO) dibawa oleh 1 Sopir
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}