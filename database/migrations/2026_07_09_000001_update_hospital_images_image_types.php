<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hospital_images') || ! Schema::hasColumn('hospital_images', 'image_type')) {
            return;
        }

        DB::statement("ALTER TABLE hospital_images MODIFY image_type ENUM('main', 'interior', 'exterior', 'facility', 'etc', 'logo') DEFAULT 'main'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('hospital_images') || ! Schema::hasColumn('hospital_images', 'image_type')) {
            return;
        }

        DB::table('hospital_images')
            ->where('image_type', 'logo')
            ->update(['image_type' => 'etc']);

        DB::statement("ALTER TABLE hospital_images MODIFY image_type ENUM('main', 'interior', 'exterior', 'facility', 'etc') DEFAULT 'main'");
    }
};
