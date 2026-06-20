<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_record', function (Blueprint $table) {
            $table->unsignedBigInteger('pharma_id')->nullable()->after('clinic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_record', function (Blueprint $table) {
            $table->dropColumn('pharma_id');
        });
    }
};
