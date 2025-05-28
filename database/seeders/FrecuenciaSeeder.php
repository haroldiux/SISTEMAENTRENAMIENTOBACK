<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FrecuenciaSeeder extends Seeder
{
    public function run(): void
    {
        $frecuencias = [
            'CADA 4 HORAS',
            'CADA 6 HORAS',
            'CADA 8 HORAS',
            'CADA 12 HORAS',
            'CADA 24 HORAS',
            'UNA VEZ AL DÍA',
            'DOS VECES AL DÍA',
            'TRES VECES AL DÍA',
            'CUATRO VECES AL DÍA',
            'EN AYUNAS',
            'CON LAS COMIDAS',
            'DESPUÉS DE LAS COMIDAS',
            'ANTES DE DORMIR',
            'SEGÚN NECESIDAD',
            'CADA TERCER DÍA'
        ];

        foreach ($frecuencias as $frecuencia) {
            DB::table('frecuencias')->insert([
                'descripcion' => $frecuencia,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
