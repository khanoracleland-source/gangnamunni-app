<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE sso_tokens MODIFY user_type " .
            "ENUM('gnuboard','hms','admin','web_remember','patient_app_remember','patient_app_access','patient_app_exchange','patient_app_bridge') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('sso_tokens')
            ->whereIn('user_type', ['patient_app_access', 'patient_app_exchange', 'patient_app_bridge'])
            ->delete();

        DB::statement(
            "ALTER TABLE sso_tokens MODIFY user_type " .
            "ENUM('gnuboard','hms','admin','web_remember','patient_app_remember') NOT NULL"
        );
    }
};
