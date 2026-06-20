<?php

namespace Modules\Bed\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BedMasterTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Get bed types with their IDs
        $bedTypes = DB::table('bed_type')->select('id', 'type')->get()->keyBy('type');

        $beds = [
             // ICU - 4 beds
            ['bed' => 'ICU-001', 'bed_type_id' => $bedTypes['ICU']->id, 'charges' => 5000, 'capacity' => 1, 'description' => 'ICU bed with advanced monitoring systems', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'ICU-002', 'bed_type_id' => $bedTypes['ICU']->id, 'charges' => 5000, 'capacity' => 1, 'description' => 'ICU bed with ventilator support', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'ICU-003', 'bed_type_id' => $bedTypes['ICU']->id, 'charges' => 5000, 'capacity' => 1, 'description' => 'ICU bed for critical care', 'status' => 1, 'is_under_maintenance' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'ICU-004', 'bed_type_id' => $bedTypes['ICU']->id, 'charges' => 5000, 'capacity' => 1, 'description' => 'ICU bed with cardiac monitoring', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],

            // Executive Single - 3 beds
            ['bed' => 'EXE-001', 'bed_type_id' => $bedTypes['Executive Single']->id, 'charges' => 3500, 'capacity' => 1, 'description' => 'Executive single room with premium amenities', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'EXE-002', 'bed_type_id' => $bedTypes['Executive Single']->id, 'charges' => 3500, 'capacity' => 1, 'description' => 'Executive room with AC and TV', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'EXE-003', 'bed_type_id' => $bedTypes['Executive Single']->id, 'charges' => 3500, 'capacity' => 1, 'description' => 'Premium executive bed with attached bathroom', 'status' => 0, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],

            // Urgent (General) - 3 beds
            ['bed' => 'URG-001', 'bed_type_id' => $bedTypes['Urgent']->id, 'charges' => 1500, 'capacity' => 2, 'description' => 'General urgent care bed', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'URG-002', 'bed_type_id' => $bedTypes['Urgent']->id, 'charges' => 1500, 'capacity' => 2, 'description' => 'Shared urgent care bed', 'status' => 1, 'is_under_maintenance' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['bed' => 'URG-003', 'bed_type_id' => $bedTypes['Urgent']->id, 'charges' => 1500, 'capacity' => 2, 'description' => 'General urgent ward bed', 'status' => 1, 'is_under_maintenance' => 1, 'created_at' => $now, 'updated_at' => $now],

         
        ];

        DB::table('bed_master')->insert($beds);
    }
}