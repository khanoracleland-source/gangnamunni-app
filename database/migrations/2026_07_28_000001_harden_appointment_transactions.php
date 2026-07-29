<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Preserve legacy duplicate rows while making their identifiers explicit
        // and unique. Silently deleting financial history is not acceptable.
        DB::table('appointment_transactions')
            ->select('transaction_id')
            ->whereNotNull('transaction_id')
            ->groupBy('transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('transaction_id')
            ->get()
            ->each(function ($duplicate): void {
                $ids = DB::table('appointment_transactions')
                    ->where('transaction_id', $duplicate->transaction_id)
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($ids->slice(1) as $id) {
                    DB::table('appointment_transactions')
                        ->where('id', $id)
                        ->update([
                            'transaction_id' => substr(
                                'legacy-duplicate-'.$id.'-'.$duplicate->transaction_id,
                                0,
                                255
                            ),
                        ]);
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
        });

        // Keep amount on rollback: installations may have had this financial
        // column before this migration, and dropping it would destroy data.
    }
};
