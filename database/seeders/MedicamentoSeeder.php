<?php
// database/seeders/MedicamentoSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MedicamentoSeeder extends Seeder
{
    public function run(): void
    {
        $medicamentos = [
            'PARACETAMOL',
            'IBUPROFENO',
            'AMOXICILINA',
            'OMEPRAZOL',
            'ATORVASTATINA',
            'METFORMINA',
            'LOSARTAN',
            'SIMVASTATINA',
            'AMLODIPINO',
            'DICLOFENACO',
            'ASPIRINA',
            'CAPTOPRIL',
            'ENALAPRIL',
            'FUROSEMIDA',
            'HIDROCLOROTIAZIDA'
        ];

        foreach ($medicamentos as $medicamento) {
            DB::table('medicamentos')->insert([
                'nombre' => $medicamento,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
