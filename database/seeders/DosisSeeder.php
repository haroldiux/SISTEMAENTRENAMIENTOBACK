<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DosisSeeder extends Seeder
{
    public function run(): void
    {
        $dosis = [
            '5MG',
            '10MG',
            '20MG',
            '25MG',
            '40MG',
            '50MG',
            '80MG',
            '100MG',
            '200MG',
            '250MG',
            '500MG',
            '750MG',
            '1G',
            '1.5G',
            '2G'
        ];

        foreach ($dosis as $d) {
            DB::table('dosis')->insert([
                'descripcion' => $d,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
