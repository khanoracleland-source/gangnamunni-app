<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('rate', 15, 2)->change();
            $table->dropIndex('services_name_index');
            $table->unique(['tenant_id', 'name'], 'services_tenant_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique('services_tenant_id_name_unique');
            $table->index('name', 'services_name_index');
            $table->double('rate')->change();
        });
    }
};
