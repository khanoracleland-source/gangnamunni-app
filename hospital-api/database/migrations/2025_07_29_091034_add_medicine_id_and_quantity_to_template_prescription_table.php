<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_prescription', function (Blueprint $table) {
            $table->unsignedBigInteger('medicine_id')->nullable()->after('template_id');
            $table->integer('quantity')->default(1)->after('frequency');

           
        });
    }

    public function down(): void
    {
        Schema::table('template_prescription', function (Blueprint $table) {
          

            $table->dropColumn('medicine_id');
            $table->dropColumn('quantity');
        });
    }
};
