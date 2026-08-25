<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE sso_tokens MODIFY user_type " .
            "ENUM('gnuboard','hms','admin','web_remember','patient_app_remember') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('sso_tokens')
            ->whereIn('user_type', ['web_remember', 'patient_app_remember'])
            ->delete();

        DB::statement(
            "ALTER TABLE sso_tokens MODIFY user_type " .
            "ENUM('gnuboard','hms','admin') NOT NULL"
        );
    }
};
