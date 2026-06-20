<?php
/**
 * ========================================
 * 강남언니 → HMS 동기화 스크립트 (양방향)
 * ========================================
 *
 * 1. 미동기화 상담 → HMS appointments 생성
 * 2. 미동기화 예약 → HMS appointments 생성
 * 3. 새 병원 → HMS tenants 자동 생성
 *
 * @file    gangnamunni/sync/sync_gangnamunni_to_hms.php
 * @updated 2026-02-10 (Phase 3-2: 양방향 동기화)
 */

require_once __DIR__ . '/db_sync_config.php';

class GangnamunniToHmsSync
{
    private $hms_conn;
    private $gn_conn;
    private $stats;
    private $log_file;

    public function __construct()
    {
        $this->hms_conn = new mysqli(
            HMS_DB_HOST, HMS_DB_USER,
            HMS_DB_PASS, HMS_DB_NAME
        );
        $this->hms_conn->set_charset('utf8mb4');

        $this->gn_conn = new mysqli(
            GANGNAMUNNI_DB_HOST, GANGNAMUNNI_DB_USER,
            GANGNAMUNNI_DB_PASS, GANGNAMUNNI_DB_NAME
        );
        $this->gn_conn->set_charset('utf8mb4');

        $log_dir = __DIR__ . '/logs';
        if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
        $this->log_file = $log_dir . '/' . date('Y-m-d') . '.log';

        $this->stats = [
            'consultations_synced' => 0, 'bookings_synced' => 0,
            'new_tenants' => 0, 'skipped' => 0, 'errors' => 0,
        ];
    }

    public function run()
    {
        $this->log("\n=== 강남언니 → HMS 동기화 시작: " . date('Y-m-d H:i:s') . " ===");

        $this->syncNewHospitals();     // 새 병원 → HMS tenant 자동 생성
        $this->syncConsultations();    // 상담 → HMS appointments
        $this->syncBookings();         // 예약 → HMS appointments

        $this->log("=== 강남언니 → HMS 동기화 완료 ===");
        foreach ($this->stats as $k => $v) {
            $this->log("  - {$k}: {$v}");
        }

        $this->hms_conn->close();
        $this->gn_conn->close();

        return $this->stats;
    }

    /**
     * 새 병원 → HMS tenant 자동 생성
     */
    private function syncNewHospitals()
    {
        $this->log("\n[1] 새 병원 → HMS tenant 생성");

        $result = $this->gn_conn->query(
            "SELECT id, name, address, phone, email
             FROM gangnamunni_hospitals
             WHERE hms_hospital_id IS NULL OR hms_hospital_id = ''
             ORDER BY id LIMIT 50"
        );

        if (!$result || $result->num_rows == 0) {
            $this->log("  새 병원 없음");
            return;
        }

        // Admin department ID
        $dept = $this->hms_conn->query("SELECT id FROM departments WHERE name = 'Admin' LIMIT 1");
        $admin_dept_id = ($dept && $dept->num_rows > 0) ? $dept->fetch_assoc()['id'] : 1;

        // 기본 플랜
        $plan = $this->hms_conn->query("SELECT id, price, frequency, trial_days, sms_limit FROM subscription_plans WHERE is_default = 1 LIMIT 1");
        $plan_data = ($plan && $plan->num_rows > 0) ? $plan->fetch_assoc() : null;

        while ($row = $result->fetch_assoc()) {
            $gn_id = (int)$row['id'];
            $name = $this->hms_conn->real_escape_string($row['name']);
            $address = $this->hms_conn->real_escape_string($row['address'] ?? '');
            $phone = $this->hms_conn->real_escape_string($row['phone'] ?? '');
            $email_raw = $row['email'] ?? '';
            if (empty($email_raw)) $email_raw = "hospital{$gn_id}@gangnamunni.local";
            $email = $this->hms_conn->real_escape_string($email_raw);
            $username = "gn{$gn_id}";
            $password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

            $this->hms_conn->begin_transaction();
            try {
                // UUID 생성
                $uuid_result = $this->hms_conn->query("SELECT UUID() as uuid");
                $tenant_id = $uuid_result->fetch_assoc()['uuid'];
                $tid_esc = $this->hms_conn->real_escape_string($tenant_id);

                // tenant 생성
                $this->hms_conn->query(
                    "INSERT INTO tenants (id, tenant_username, hospital_name, created_at, updated_at)
                     VALUES ('{$tid_esc}', '{$username}', '{$name}', NOW(), NOW())"
                );

                // admin user 생성
                $this->hms_conn->query(
                    "INSERT INTO users (department_id, first_name, last_name, email, password, phone,
                        username, hospital_name, tenant_id, gender, status,
                        is_admin_default, is_super_admin_default, city, language, theme_mode,
                        created_at, updated_at)
                     VALUES ({$admin_dept_id}, '{$name}', '', '{$email}', '{$password}', '{$phone}',
                        '{$username}', '{$name}', '{$tid_esc}', 0, 1,
                        1, 0, '{$address}', 'en', '0', NOW(), NOW())"
                );
                $user_id = $this->hms_conn->insert_id;

                // user_tenant 연결
                $this->hms_conn->query(
                    "INSERT INTO user_tenants (tenant_id, user_id, last_login_at, created_at, updated_at)
                     VALUES ('{$tid_esc}', {$user_id}, NOW(), NOW(), NOW())"
                );

                // model_has_roles (Spatie Permission - Admin role assignment)
                $this->hms_conn->query(
                    "INSERT INTO model_has_roles (role_id, model_type, model_id)
                     VALUES ({$admin_dept_id}, 'App\\\\Models\\\\User', {$user_id})"
                );

                // doctor_department 생성
                $this->hms_conn->query(
                    "INSERT INTO doctor_departments (title, tenant_id, created_at, updated_at)
                     VALUES ('Doctor', '{$tid_esc}', NOW(), NOW())"
                );

                // subscription 생성
                if ($plan_data) {
                    $this->hms_conn->query(
                        "INSERT INTO subscriptions (user_id, subscription_plan_id, plan_amount, plan_frequency,
                            starts_at, ends_at, trial_ends_at, sms_limit, status, created_at, updated_at)
                         VALUES ({$user_id}, {$plan_data['id']}, {$plan_data['price']}, {$plan_data['frequency']},
                            NOW(), DATE_ADD(NOW(), INTERVAL 3650 DAY), DATE_ADD(NOW(), INTERVAL 3650 DAY),
                            {$plan_data['sms_limit']}, 1, NOW(), NOW())"
                    );
                }

                $this->hms_conn->commit();

                // gangnamunni_hospitals 업데이트
                $tid_gn = $this->gn_conn->real_escape_string($tenant_id);
                $this->gn_conn->query(
                    "UPDATE gangnamunni_hospitals SET hms_hospital_id = '{$tid_gn}', synced_at = NOW() WHERE id = {$gn_id}"
                );

                $this->stats['new_tenants']++;
            } catch (\Exception $e) {
                $this->hms_conn->rollback();
                $this->log("  [ERR] 병원 #{$gn_id} 등록 실패: " . $e->getMessage());
                $this->stats['errors']++;
            }
        }

        $this->log("  새 tenant 생성: {$this->stats['new_tenants']}개");
    }

    /**
     * 상담 → HMS appointments
     */
    private function syncConsultations()
    {
        $this->log("\n[2] 상담 → HMS appointments");

        $result = $this->gn_conn->query(
            "SELECT c.*, h.hms_hospital_id
             FROM gangnamunni_consultations c
             JOIN gangnamunni_hospitals h ON c.hospital_id = h.id
             WHERE c.synced_to_hms = 0 AND h.hms_hospital_id IS NOT NULL AND h.hms_hospital_id != ''
             ORDER BY c.id LIMIT 100"
        );

        if (!$result || $result->num_rows == 0) {
            $this->log("  미동기화 상담 없음");
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $cid = (int)$row['id'];
            $tid = $this->hms_conn->real_escape_string($row['hms_hospital_id']);
            $name = $this->hms_conn->real_escape_string($row['name'] ?? '');
            $phone = $this->hms_conn->real_escape_string($row['phone'] ?? '');
            $message = $this->hms_conn->real_escape_string($row['message'] ?? '');
            $type = ($row['consultation_type'] ?? 'general') === 'chat' ? '채팅상담' : '일반상담';
            $problem = "[강남언니 {$type}] " . $message;
            $problem = $this->hms_conn->real_escape_string($problem);

            $date = !empty($row['preferred_date'])
                ? $this->hms_conn->real_escape_string($row['preferred_date'])
                : date('Y-m-d H:i:s', strtotime('+1 day'));

            // 환자 찾기/생성
            $patient_user_id = $this->findOrCreatePatient($name, $phone, $tid);
            if (!$patient_user_id) {
                $this->stats['errors']++;
                continue;
            }

            // 기본 의사/진료과
            $doctor = $this->hms_conn->query(
                "SELECT d.id as doctor_id, d.doctor_department_id as department_id FROM doctors d WHERE d.tenant_id = '{$tid}' LIMIT 1"
            );
            $doctor_id = 0; $dept_id = 0;
            if ($doctor && $doctor->num_rows > 0) {
                $d = $doctor->fetch_assoc();
                $doctor_id = $d['doctor_id'];
                $dept_id = $d['department_id'] ?? 0;
            }
            if ($dept_id == 0) {
                $ddept = $this->hms_conn->query(
                    "SELECT id FROM doctor_departments WHERE tenant_id = '{$tid}' LIMIT 1"
                );
                if ($ddept && $ddept->num_rows > 0) $dept_id = $ddept->fetch_assoc()['id'];
            }

            // appointment 생성
            $patient = $this->hms_conn->query(
                "SELECT id FROM patients WHERE user_id = {$patient_user_id} AND tenant_id = '{$tid}' LIMIT 1"
            );
            $patient_id = ($patient && $patient->num_rows > 0) ? $patient->fetch_assoc()['id'] : 0;

            if ($patient_id > 0 && $doctor_id > 0) {
                $this->hms_conn->query(
                    "INSERT INTO appointments (patient_id, doctor_id, department_id, opd_date, problem, is_completed, tenant_id, created_at, updated_at)
                     VALUES ({$patient_id}, {$doctor_id}, {$dept_id}, '{$date}', '{$problem}', 0, '{$tid}', NOW(), NOW())"
                );
                $apt_id = $this->hms_conn->insert_id;

                if ($apt_id) {
                    $this->gn_conn->query(
                        "UPDATE gangnamunni_consultations SET synced_to_hms = 1, hms_appointment_id = {$apt_id}, synced_at = NOW() WHERE id = {$cid}"
                    );
                    $this->stats['consultations_synced']++;
                }
            } else {
                $this->stats['errors']++;
            }
        }

        $this->log("  상담 동기화: {$this->stats['consultations_synced']}개");
    }

    /**
     * 예약 → HMS appointments
     */
    private function syncBookings()
    {
        $this->log("\n[3] 예약 → HMS appointments");

        $result = $this->gn_conn->query(
            "SELECT b.*, h.hms_hospital_id
             FROM gangnamunni_bookings b
             JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
             WHERE b.hms_appointment_id IS NULL AND h.hms_hospital_id IS NOT NULL AND h.hms_hospital_id != ''
             ORDER BY b.id LIMIT 100"
        );

        if (!$result || $result->num_rows == 0) {
            $this->log("  미동기화 예약 없음");
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $bid = (int)$row['id'];
            $tid = $this->hms_conn->real_escape_string($row['hms_hospital_id']);
            $name = $this->hms_conn->real_escape_string($row['name'] ?? '');
            $phone = $this->hms_conn->real_escape_string($row['phone'] ?? '');
            $treatment = $this->hms_conn->real_escape_string($row['treatment'] ?? '');
            $memo = $this->hms_conn->real_escape_string($row['memo'] ?? '');
            $problem = "[강남언니 예약] 시술: {$treatment}";
            if ($memo) $problem .= "\n메모: {$memo}";
            $problem = $this->hms_conn->real_escape_string($problem);

            $date = !empty($row['booking_date'])
                ? $row['booking_date'] . ' ' . substr(($row['booking_time'] ?? '10:00:00'), 0, 8)
                : date('Y-m-d H:i:s', strtotime('+1 day'));
            $date = $this->hms_conn->real_escape_string($date);

            $patient_user_id = $this->findOrCreatePatient($name, $phone, $tid);
            if (!$patient_user_id) {
                $this->stats['errors']++;
                continue;
            }

            $doctor = $this->hms_conn->query(
                "SELECT d.id as doctor_id, d.doctor_department_id as department_id FROM doctors d WHERE d.tenant_id = '{$tid}' LIMIT 1"
            );
            $doctor_id = 0; $dept_id = 0;
            if ($doctor && $doctor->num_rows > 0) {
                $d = $doctor->fetch_assoc();
                $doctor_id = $d['doctor_id'];
                $dept_id = $d['department_id'] ?? 0;
            }
            if ($dept_id == 0) {
                $ddept = $this->hms_conn->query(
                    "SELECT id FROM doctor_departments WHERE tenant_id = '{$tid}' LIMIT 1"
                );
                if ($ddept && $ddept->num_rows > 0) $dept_id = $ddept->fetch_assoc()['id'];
            }

            $patient = $this->hms_conn->query(
                "SELECT id FROM patients WHERE user_id = {$patient_user_id} AND tenant_id = '{$tid}' LIMIT 1"
            );
            $patient_id = ($patient && $patient->num_rows > 0) ? $patient->fetch_assoc()['id'] : 0;

            if ($patient_id > 0 && $doctor_id > 0) {
                $this->hms_conn->query(
                    "INSERT INTO appointments (patient_id, doctor_id, department_id, opd_date, problem, is_completed, tenant_id, created_at, updated_at)
                     VALUES ({$patient_id}, {$doctor_id}, {$dept_id}, '{$date}', '{$problem}', 0, '{$tid}', NOW(), NOW())"
                );
                $apt_id = $this->hms_conn->insert_id;

                if ($apt_id) {
                    $this->gn_conn->query(
                        "UPDATE gangnamunni_bookings SET hms_appointment_id = {$apt_id} WHERE id = {$bid}"
                    );
                    $this->stats['bookings_synced']++;
                }
            } else {
                $this->stats['errors']++;
            }
        }

        $this->log("  예약 동기화: {$this->stats['bookings_synced']}개");
    }

    /**
     * HMS에서 환자 사용자 찾기/생성
     */
    private function findOrCreatePatient($name, $phone, $tenant_id)
    {
        // 기존 환자 검색 (전화번호 기준)
        if (!empty($phone)) {
            $result = $this->hms_conn->query(
                "SELECT u.id FROM users u
                 JOIN patients p ON p.user_id = u.id AND p.tenant_id = '{$tenant_id}'
                 WHERE u.phone = '{$phone}' LIMIT 1"
            );
            if ($result && $result->num_rows > 0) {
                return $result->fetch_assoc()['id'];
            }
        }

        // 새 환자 생성
        $email = 'patient_' . time() . '_' . rand(1000, 9999) . '@gangnamunni.local';
        $password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $patient_dept = $this->hms_conn->query("SELECT id FROM departments WHERE name = 'Patient' LIMIT 1");
        $patient_dept_id = ($patient_dept && $patient_dept->num_rows > 0) ? $patient_dept->fetch_assoc()['id'] : 3;

        $this->hms_conn->query(
            "INSERT INTO users (department_id, first_name, email, password, phone, gender, status,
                tenant_id, hospital_name, language, theme_mode, created_at, updated_at)
             VALUES ({$patient_dept_id}, '{$name}', '{$email}', '{$password}', '{$phone}', 0, 1,
                '{$tenant_id}', '', 'en', '0', NOW(), NOW())"
        );
        $user_id = $this->hms_conn->insert_id;
        if (!$user_id) return null;

        $this->hms_conn->query(
            "INSERT INTO patients (user_id, tenant_id, created_at, updated_at)
             VALUES ({$user_id}, '{$tenant_id}', NOW(), NOW())"
        );
        $patient_id = $this->hms_conn->insert_id;

        // owner 업데이트
        $this->hms_conn->query(
            "UPDATE users SET owner_id = {$patient_id}, owner_type = 'App\\\\Models\\\\Patient' WHERE id = {$user_id}"
        );

        return $user_id;
    }

    private function log($msg)
    {
        $line = "[" . date('H:i:s') . "] " . $msg . "\n";
        file_put_contents($this->log_file, $line, FILE_APPEND);
        echo $msg . "\n";
    }
}

// 실행
$sync = new GangnamunniToHmsSync();
$stats = $sync->run();
