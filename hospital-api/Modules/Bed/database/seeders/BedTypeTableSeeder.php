<?php

namespace Modules\Bed\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BedTypeTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('bed_type')->insert([
            ['type' => 'ICU', 'description' => 'Specialized for intensive care units, where critically ill patients require advanced monitoring and support.', 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'Executive Single', 'description' => 'Ward for male patients requiring basic medical care without intensive monitoring.', 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'Urgent',  'description' => 'Ward for female patients requiring basic medical care without intensive monitoring.', 'created_at' => $now, 'updated_at' => $now],
           
        ]);
    }
}
