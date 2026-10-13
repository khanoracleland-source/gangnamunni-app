<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 검색 광고 과금: 같은 IP 의 병원별 일일 과금 횟수 제한(ad_slot_ip_daily_charge_limit) 조회용 인덱스.
 */
return new class extends Migration
{
    private const INDEX = 'idx_slot_event_hospital_ip';

    public function up(): void
    {
        if (! Schema::hasTable('gangnamunni_ad_slot_events') || $this->indexExists()) {
            return;
        }
        Schema::table('gangnamunni_ad_slot_events', function (Blueprint $table) {
            $table->index(['hospital_id', 'visitor_ip_hash', 'created_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gangnamunni_ad_slot_events') || ! $this->indexExists()) {
            return;
        }
        Schema::table('gangnamunni_ad_slot_events', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'gangnamunni_ad_slot_events')
            ->where('index_name', self::INDEX)
            ->exists();
    }
};
