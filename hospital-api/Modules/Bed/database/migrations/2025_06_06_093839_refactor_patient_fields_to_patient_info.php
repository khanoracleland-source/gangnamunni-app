<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create the patient_info table first
        Schema::create('patient_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->unique();

            $table->string('weight')->nullable();
            $table->string('height')->nullable();
            $table->string('blood_pressure')->nullable();
            $table->string('heart_rate')->nullable();
            $table->string('blood_group')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('billing_record', function (Blueprint $table) {
            $table->double('bed_charges')->default(0);
            $table->longText('bed_allocation_charges')->nullable();
        });
        
    }

    public function down(): void
    {
        // Drop patient_info table
        Schema::dropIfExists('patient_info');
    }
};
