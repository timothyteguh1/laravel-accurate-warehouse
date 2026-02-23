<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Driver;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = [
            [
                'name' => 'Budi Santoso',
                'license_plate' => 'L 1234 AB',
            ],
            [
                'name' => 'Ahmad Rifa\'i',
                'license_plate' => 'W 9876 XYZ',
            ],
            [
                'name' => 'Rahmat Hidayat',
                'license_plate' => 'S 4567 CD',
            ],
            [
                'name' => 'Joko Mulyono',
                'license_plate' => 'N 8899 EE',
            ],
            [
                'name' => 'Slamet Riyadi',
                'license_plate' => 'L 5555 ZZ',
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::create($driver);
        }
    }
}