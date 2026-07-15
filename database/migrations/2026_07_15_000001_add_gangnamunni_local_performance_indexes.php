<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('gangnamunni_consultations', 'idx_gn_consultations_created_at', ['created_at']);
        $this->addIndex('gangnamunni_consultations', 'idx_gn_consultations_status_created', ['status', 'created_at']);
        $this->addIndex('hospital_reviews', 'idx_hospital_reviews_created_at', ['created_at']);
        $this->addIndex('hospital_reviews', 'idx_hospital_reviews_hospital_id', ['hospital_id']);
        $this->addIndex('gangnamunni_ads', 'idx_gn_ads_is_active', ['is_active']);
        $this->addIndex('gangnamunni_ads', 'idx_gn_ads_status', ['status']);
        $this->addIndex('gangnamunni_hospitals', 'idx_gn_hospitals_status', ['status']);
        $this->addIndex('gangnamunni_bookings', 'idx_gn_bookings_date_time', ['booking_date', 'booking_time']);
        $this->addIndex('gangnamunni_bookings', 'idx_gn_bookings_status_created', ['status', 'created_at']);
        $this->addIndex('gangnamunni_fix_log', 'idx_gn_fix_log_fix_date_id', ['fix_date', 'id']);
        $this->addIndex('gangnamunni_dev_timeline', 'idx_gn_dev_timeline_event_date', ['event_date']);
        $this->addIndex('appointments', 'idx_appointments_opd_date', ['opd_date']);
        $this->addIndex('ipd_patient_departments', 'idx_ipd_patient_departments_is_discharge', ['is_discharge']);
        $this->addIndex('beds', 'idx_beds_is_available', ['is_available']);
        $this->addIndex('opd_patient_departments', 'idx_opd_patient_departments_appointment_date', ['appointment_date']);
        $this->addIndex('gangnamunni_notifications', 'idx_gn_notifications_admin_read_created', ['admin_id', 'is_read', 'created_at']);
        $this->addIndex('gangnamunni_user_notifications', 'idx_gn_user_notifications_member_read_created', ['member_id', 'is_read', 'created_at']);
        $this->addIndex('gangnamunni_user_notifications', 'idx_gn_user_notifications_member_created', ['member_id', 'created_at']);
    }

    public function down(): void
    {
        foreach ([
            ['gangnamunni_consultations', 'idx_gn_consultations_created_at'],
            ['gangnamunni_consultations', 'idx_gn_consultations_status_created'],
            ['hospital_reviews', 'idx_hospital_reviews_created_at'],
            ['hospital_reviews', 'idx_hospital_reviews_hospital_id'],
            ['gangnamunni_ads', 'idx_gn_ads_is_active'],
            ['gangnamunni_ads', 'idx_gn_ads_status'],
            ['gangnamunni_hospitals', 'idx_gn_hospitals_status'],
            ['gangnamunni_bookings', 'idx_gn_bookings_date_time'],
            ['gangnamunni_bookings', 'idx_gn_bookings_status_created'],
            ['gangnamunni_fix_log', 'idx_gn_fix_log_fix_date_id'],
            ['gangnamunni_dev_timeline', 'idx_gn_dev_timeline_event_date'],
            ['appointments', 'idx_appointments_opd_date'],
            ['ipd_patient_departments', 'idx_ipd_patient_departments_is_discharge'],
            ['beds', 'idx_beds_is_available'],
            ['opd_patient_departments', 'idx_opd_patient_departments_appointment_date'],
            ['gangnamunni_notifications', 'idx_gn_notifications_admin_read_created'],
            ['gangnamunni_user_notifications', 'idx_gn_user_notifications_member_read_created'],
            ['gangnamunni_user_notifications', 'idx_gn_user_notifications_member_created'],
        ] as [$table, $index]) {
            $this->dropIndex($table, $index);
        }
    }

    private function addIndex(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->index($columns, $index);
        });
    }

    private function dropIndex(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }
};
