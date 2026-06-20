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
        Schema::table('patient_encounters', function (Blueprint $table) {
            $table->tinyInteger('prescription_payment_status')->default(0)->after('prescription_status')->comment('0 = Unpaid, 1 = Paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_encounters', function (Blueprint $table) {
            $table->dropColumn('prescription_payment_status');
        });
    }
};
