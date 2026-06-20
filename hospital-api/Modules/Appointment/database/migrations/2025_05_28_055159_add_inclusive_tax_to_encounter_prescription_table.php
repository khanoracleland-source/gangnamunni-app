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
        Schema::table('encounter_prescription', function (Blueprint $table) {
            $table->json('inclusive_tax')->after('instruction')->nullable();
            $table->string('inclusive_tax_amount')->after('inclusive_tax')->nullable();
            $table->string('medicine_price')->after('inclusive_tax_amount')->nullable();
            $table->string('total_amount')->after('medicine_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encounter_prescription', function (Blueprint $table) {
            $table->dropColumn(['inclusive_tax', 'inclusive_tax_amount', 'medicine_price', 'total_amount']);
        });
    }
};
