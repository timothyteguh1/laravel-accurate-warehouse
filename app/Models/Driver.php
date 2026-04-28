<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'license_plate',
        'phone',
        'orin_device_sn' // <-- Tambahan Jembatan ORIN
    ];

    // Relasi: 1 Sopir bisa membawa banyak DO (Deliveries)
    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
}