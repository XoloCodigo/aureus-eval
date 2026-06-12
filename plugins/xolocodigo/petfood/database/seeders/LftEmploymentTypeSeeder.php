<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Security\Models\User;

/**
 * Mexican LFT-aligned employment types.
 *
 * The upstream Webkul\Employee\Database\Seeders\EmploymentTypeSeeder
 * seeds generic European/US contract labels (Permanent, Temporary,
 * Seasonal, Interim, Full-Time, Intern, Student, Apprenticeship, Thesis,
 * Statutory, Employee) that do not map cleanly to the Mexican Ley
 * Federal del Trabajo (LFT) contract taxonomy.
 *
 * Mexican LFT contract types (Art. 35-39-F):
 *  - Tiempo indeterminado (Art. 25)       — default open-ended employment
 *  - Tiempo determinado (Art. 37)          — fixed-term, requires legal cause
 *  - Obra determinada (Art. 36)            — until a specific project completes
 *  - Capacitación inicial (Art. 39-A)      — initial training, max 3 + 3 months
 *  - Por temporada (Art. 39-F)             — for discontinuous seasonal work
 *  - Periodo de prueba (Art. 39-A)         — probation period (30 days, or 180 for managerial)
 *
 * This seeder DELETES the generic types and inserts the LFT-aligned set.
 * If you need the generic types alongside (multi-jurisdictional companies),
 * comment out the delete() call and skip the ones that overlap by name.
 *
 * Run after the upstream EmploymentTypeSeeder.
 */
class LftEmploymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('employees_employment_types')) {
            $this->command?->warn('LftEmploymentTypeSeeder: skipped — employees_employment_types table does not exist.');

            return;
        }

        DB::table('employees_employment_types')->delete();

        $user = User::first();

        $types = [
            ['sort' => 1, 'name' => 'Tiempo indeterminado',   'code' => 'lft_indeterminado',     'description' => 'Contrato por tiempo indeterminado (LFT Art. 25). El default para empleados de planta.'],
            ['sort' => 2, 'name' => 'Tiempo determinado',     'code' => 'lft_determinado',       'description' => 'Contrato por tiempo determinado (LFT Art. 37). Requiere causa legal específica.'],
            ['sort' => 3, 'name' => 'Obra determinada',       'code' => 'lft_obra_determinada',  'description' => 'Contrato por obra o proyecto determinado (LFT Art. 36).'],
            ['sort' => 4, 'name' => 'Capacitación inicial',   'code' => 'lft_capacitacion',      'description' => 'Capacitación inicial (LFT Art. 39-A). Máximo 3 meses, prorrogable 3 meses más.'],
            ['sort' => 5, 'name' => 'Por temporada',          'code' => 'lft_temporada',         'description' => 'Trabajo discontinuo / por temporada (LFT Art. 39-F).'],
            ['sort' => 6, 'name' => 'Periodo de prueba',      'code' => 'lft_prueba',            'description' => 'Periodo de prueba (LFT Art. 39-A). 30 días general; hasta 180 para puestos de dirección, gerencia o trabajos técnicos especializados.'],
        ];

        $rows = collect($types)->map(fn ($t) => array_merge($t, [
            'creator_id' => $user?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toArray();

        // Drop the description field if the table doesn't have it.
        if (! $this->columnExists('employees_employment_types', 'description')) {
            $rows = array_map(function ($row) {
                unset($row['description']);

                return $row;
            }, $rows);
        }

        DB::table('employees_employment_types')->insert($rows);

        $this->command?->info('LftEmploymentTypeSeeder: '.count($rows).' LFT employment types seeded.');
    }

    private function columnExists(string $table, string $column): bool
    {
        return in_array($column, Schema::getColumnListing($table));
    }
}
