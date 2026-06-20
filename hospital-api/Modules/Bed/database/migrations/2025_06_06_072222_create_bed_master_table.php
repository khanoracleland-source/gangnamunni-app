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
        Schema::create('bed_master', function (Blueprint $table) {
            $table->id();
            $table->string('bed');
            $table->unsignedBigInteger('bed_type_id');
            $table->decimal('charges', 10, 2)->default(0);
            $table->integer('capacity')->default(1);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('clinic_admin_id')->nullable();
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->boolean('is_under_maintenance')->default(false);
           
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bed_type_id')->references('id')->on('bed_type')->onDelete('cascade');
            $table->foreign('clinic_admin_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('clinic_id')->references('id')->on('clinic')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bed_master');
    }
}; 
