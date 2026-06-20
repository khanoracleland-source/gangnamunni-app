<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        app()['cache']->forget('spatie.permission.cache');

        $doctorReceptionistPermissions = [
            'add_allocations',
            'edit_allocations',
            'delete_allocations',
            'view_allocations',
        ];

        $adminVendorPermissions = [
            'view_bed_type',
            'add_bed_type',
            'edit_bed_type',
            'delete_bed_type',
            'add_bed_master',
            'edit_bed_master',
            'delete_bed_master',
            'view_bed_master',
            'add_allocations',
            'edit_allocations',
            'delete_allocations',
            'view_allocations',
        ];

        // Fetch roles
        $adminRole        = Role::where('name', 'admin')->first();
        $demoAdminRole    = Role::where('name', 'demo_admin')->first();
        $vendorRole       = Role::where('name', 'vendor')->first();
        $doctorRole       = Role::where('name', 'doctor')->first();
        $receptionistRole = Role::where('name', 'receptionist')->first();


        // ----------------------------
        // ASSIGN PERMISSIONS TO ADMIN + VENDOR
        // ----------------------------
        foreach ($adminVendorPermissions as $permName) {

            $permission = Permission::updateOrCreate(
                ['name' => $permName, 'guard_name' => 'web'], // UNIQUE keys
                ['is_fixed' => 1] // fields to update if exists
            );

            if ($adminRole) {
                $adminRole->givePermissionTo($permission);
            }
            if ($demoAdminRole) {
                $demoAdminRole->givePermissionTo($permission);
            }
            if ($vendorRole) {
                $vendorRole->givePermissionTo($permission);
            }
        }


        // ----------------------------
        // ASSIGN PERMISSIONS TO DOCTOR + RECEPTIONIST
        // ----------------------------
        foreach ($doctorReceptionistPermissions as $permName) {

            $permission = Permission::updateOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['is_fixed' => 1]
            );

            if ($doctorRole) {
                $doctorRole->givePermissionTo($permission);
            }

            if ($receptionistRole) {
                $receptionistRole->givePermissionTo($permission);
            }
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
