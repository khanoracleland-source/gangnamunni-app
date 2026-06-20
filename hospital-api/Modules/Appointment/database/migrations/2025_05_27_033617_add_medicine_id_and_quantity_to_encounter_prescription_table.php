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
            $table->unsignedBigInteger('medicine_id')->nullable()->after('user_id');
            $table->integer('quantity')->nullable()->after('medicine_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encounter_prescription', function (Blueprint $table) {
            $table->dropColumn(['medicine_id', 'quantity']);
        });
    }
};
