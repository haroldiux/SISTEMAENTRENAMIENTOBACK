<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DuracionSeeder extends Seeder
{
    public function run(): void
    {
        $duraciones = [
            '1 DÍA',
            '3 DÍAS',
            '5 DÍAS',
            '7 DÍAS',
            '10 DÍAS',
            '14 DÍAS',
            '21 DÍAS',
            '1 MES',
            '2 MESES',
            '3 MESES',
            '6 MESES',
            '1 AÑO',
            'TRATAMIENTO CONTINUO',
            'HASTA MEJORÍA',
            'SEGÚN EVOLUCIÓN'
        ];

        foreach ($duraciones as $duracion) {
            DB::table('duraciones')->insert([
                'descripcion' => $duracion,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
