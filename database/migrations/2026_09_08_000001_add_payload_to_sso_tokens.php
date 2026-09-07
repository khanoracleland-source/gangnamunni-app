<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // patient_app_exchange rows only: stashes the exact token bundle
        // issued on first claim, so a retry with the same code + same
        // device (e.g. after a lost response) returns byte-identical
        // tokens instead of minting a second, orphaned set.
        DB::statement("ALTER TABLE sso_tokens ADD COLUMN payload TEXT NULL AFTER user_agent");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sso_tokens DROP COLUMN payload");
    }
};
