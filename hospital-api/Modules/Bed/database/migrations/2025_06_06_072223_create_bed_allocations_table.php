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
        Schema::create('bed_allocations', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('patient_id'); // Patient reference
            $table->unsignedBigInteger('clinic_id'); // Clinic reference
            $table->unsignedBigInteger('clinic_admin_id')->nullable(); // Encounter reference
            $table->unsignedBigInteger('encounter_id')->nullable(); // Encounter reference
            $table->unsignedBigInteger('bed_master_id'); // Room & bed reference
            $table->unsignedBigInteger('bed_type_id')->nullable();
            // Basic info
            $table->date('assign_date')->nullable();
            $table->date('discharge_date')->nullable();
            $table->boolean('status')->default(true); // Active/Inactive toggle
            $table->text('description')->nullable();

            // IPD/OPD Fields
            // $table->string('weight')->nullable(); // example: "60"
            // $table->string('height')->nullable(); // example: "170 cm"
            // $table->string('blood_pressure')->nullable(); // example: "120/80"
            // $table->string('heart_rate')->nullable(); // example: "78 bpm"
            // $table->string('blood_group')->nullable(); // example: "B+"
            $table->string('temperature')->nullable(); // example: "37.4°C"
            $table->text('symptoms')->nullable(); // example: "Fever, cough"
            $table->text('notes')->nullable(); // Additional remarks
            $table->boolean('bed_payment_status')->default(0); // 'completed' or 'pending'
            $table->double('charge', 10, 2)->default(0); // pulled from bed_masters
            $table->double('per_bed_charge', 10, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('clinic_id')->references('id')->on('clinic')->onDelete('cascade');
            $table->foreign('clinic_admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('encounter_id')->references('id')->on('patient_encounters')->onDelete('cascade');
            $table->foreign('bed_master_id')->references('id')->on('bed_master')->onDelete('cascade');
            $table->foreign('bed_type_id')->references('id')->on('bed_type')->onDelete('cascade');
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bed_allocations');
    }
};
