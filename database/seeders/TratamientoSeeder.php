<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TratamientoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MedicamentoSeeder::class,
            DosisSeeder::class,
            FrecuenciaSeeder::class,
            DuracionSeeder::class,
        ]);
    }
}
