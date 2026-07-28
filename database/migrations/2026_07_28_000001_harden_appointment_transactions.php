<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('appointment_transactions', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable()->after('transaction_id');
            }
        });

        Schema::table('appointment_transactions', function (Blueprint $table) {
            $table->unique('transaction_id', 'appointment_transactions_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_transactions', function (Blueprint $table) {
            $table->dropUnique('appointment_transactions_transaction_id_unique');
            if (Schema::hasColumn('appointment_transactions', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }
};
