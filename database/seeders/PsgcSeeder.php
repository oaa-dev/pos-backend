<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

class PsgcSeeder extends Seeder
{
    public function run(): void
    {
        $ncr = Region::updateOrCreate(
            ['psgc_code' => '130000000'],
            ['name' => 'National Capital Region (NCR)'],
        );

        $calabarzon = Region::updateOrCreate(
            ['psgc_code' => '040000000'],
            ['name' => 'Region IV-A (CALABARZON)'],
        );

        foreach ([
            ['133900000', 'City of Manila'],
            ['137400000', 'Quezon City'],
        ] as [$code, $name]) {
            City::updateOrCreate(
                ['psgc_code' => $code],
                [
                    'region_id' => $ncr->id,
                    'province_id' => null,
                    'name' => $name,
                    'is_city' => true,
                ],
            );
        }

        $laguna = Province::updateOrCreate(
            ['psgc_code' => '043400000'],
            ['region_id' => $calabarzon->id, 'name' => 'Laguna'],
        );

        $pila = City::updateOrCreate(
            ['psgc_code' => '043424000'],
            [
                'region_id' => $calabarzon->id,
                'province_id' => $laguna->id,
                'name' => 'Pila',
                'is_city' => false,
            ],
        );

        $santaCruz = City::updateOrCreate(
            ['psgc_code' => '043428000'],
            [
                'region_id' => $calabarzon->id,
                'province_id' => $laguna->id,
                'name' => 'Santa Cruz',
                'is_city' => false,
            ],
        );

        $pilaBarangays = [
            'Aplaya', 'Bagong Pook', 'Bukal', 'Concepcion', 'Labuin',
            'Linga', 'Masico', 'Mojon', 'Pansol', 'Pinagbayanan',
            'San Antonio', 'San Miguel', 'Santa Clara Norte',
            'Santa Clara Sur', 'Tubuan',
        ];

        foreach ($pilaBarangays as $i => $name) {
            Barangay::updateOrCreate(
                ['psgc_code' => '043424'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                ['city_id' => $pila->id, 'name' => $name],
            );
        }

        foreach (['Bagumbayan', 'Bubukal', 'Calios', 'Duhat', 'Gatid'] as $i => $name) {
            Barangay::updateOrCreate(
                ['psgc_code' => '043428'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                ['city_id' => $santaCruz->id, 'name' => $name],
            );
        }
    }
}
