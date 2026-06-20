<?php
/**
 * ========================================
 * HMS → 강남언니 동기화 스크립트 (양방향)
 * ========================================
 *
 * 1. HMS tenants → gangnamunni_hospitals (이름/주소/전화 동기화)
 * 2. HMS doctors → gangnamunni_doctors
 * 3. HMS doctor_departments → gangnamunni_departments
 * 4. 강남언니 hospitals → HMS tenants (역방향: 이름/주소/전화 동기화)
 *
 * @file    gangnamunni/sync/sync_hms_to_gangnamunni.php
 * @updated 2026-02-10 (Phase 3-2: 양방향 동기화)
 */

require_once __DIR__ . '/db_sync_config.php';

class HmsGangnamunniSync
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
            'hospitals_updated' => 0, 'hospitals_created' => 0,
            'doctors_updated' => 0, 'doctors_created' => 0,
            'departments_updated' => 0, 'departments_created' => 0,
            'hms_hospitals_updated' => 0, // 역방향 (강남언니→HMS)
            'skipped' => 0, 'errors' => 0,
        ];
    }

    public function run()
    {
        $this->log("=== 양방향 동기화 시작: " . date('Y-m-d H:i:s') . " ===");

        $this->syncHospitals();      // HMS → 강남언니
        $this->syncDoctors();        // HMS → 강남언니
        $this->syncDepartments();    // HMS → 강남언니
        $this->syncHospitalsReverse(); // 강남언니 → HMS (이름/주소/전화)

        $this->log("=== 동기화 완료 ===");
        $this->log("통계:");
        foreach ($this->stats as $k => $v) {
            $this->log("  - {$k}: {$v}");
        }

        $this->hms_conn->close();
        $this->gn_conn->close();

        return $this->stats;
    }

    /**
     * HMS tenants → gangnamunni_hospitals
     */
    private function syncHospitals()
    {
        $this->log("\n[1] HMS → 강남언니 병원 동기화");

        $sql = "SELECT t.id as tenant_id, t.hospital_name,
                       u.city as address, u.phone, u.email, u.first_name
                FROM tenants t
                LEFT JOIN users u ON u.tenant_id = t.id AND u.is_admin_default = 1
                ORDER BY t.created_at";

        $result = $this->hms_conn->query($sql);
        if (!$result) {
            $this->log("  [ERR] 쿼리 실패: " . $this->hms_conn->error);
            $this->stats['errors']++;
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $tid = $this->gn_conn->real_escape_string($row['tenant_id']);
            $name = $this->gn_conn->real_escape_string($row['hospital_name']);
            $phone = $this->gn_conn->real_escape_string($row['phone'] ?? '');
            $email = $this->gn_conn->real_escape_string($row['email'] ?? '');
            $address = $this->gn_conn->real_escape_string($row['address'] ?? '');

            // 이미 존재하는지 확인
            $check = $this->gn_conn->query(
                "SELECT id, name, phone, email, address FROM gangnamunni_hospitals WHERE hms_hospital_id = '{$tid}' LIMIT 1"
            );

            if ($check && $check->num_rows > 0) {
                $existing = $check->fetch_assoc();
                // HMS에서 변경된 필드만 업데이트 (강남언니 고유 데이터 보존)
                $updates = [];
                if ($existing['name'] !== $row['hospital_name'] && !empty($row['hospital_name'])) {
                    $updates[] = "name = '{$name}'";
                }
                // HMS에서 phone/email이 있고 강남언니에 없으면 업데이트
                if (empty($existing['phone']) && !empty($phone)) {
                    $updates[] = "phone = '{$phone}'";
                }
                if (empty($existing['email']) && !empty($email)) {
                    $updates[] = "email = '{$email}'";
                }

                if (!empty($updates)) {
                    $updates[] = "synced_at = NOW()";
                    $set = implode(', ', $updates);
                    $this->gn_conn->query(
                        "UPDATE gangnamunni_hospitals SET {$set} WHERE hms_hospital_id = '{$tid}'"
                    );
                    $this->stats['hospitals_updated']++;
                } else {
                    // synced_at만 업데이트
                    $this->gn_conn->query(
                        "UPDATE gangnamunni_hospitals SET synced_at = NOW() WHERE hms_hospital_id = '{$tid}'"
                    );
                    $this->stats['skipped']++;
                }
            } else {
                // 새 병원 생성
                $this->gn_conn->query(
                    "INSERT INTO gangnamunni_hospitals (name, address, phone, email, hms_hospital_id, synced_at, created_at)
                     VALUES ('{$name}', '{$address}', '{$phone}', '{$email}', '{$tid}', NOW(), NOW())"
                );
                $this->stats['hospitals_created']++;
            }
        }

        $this->log("  병원: 생성 {$this->stats['hospitals_created']}, 업데이트 {$this->stats['hospitals_updated']}");
    }

    /**
     * 강남언니 → HMS (역방향: 이름/주소/전화 동기화)
     */
    private function syncHospitalsReverse()
    {
        $this->log("\n[4] 강남언니 → HMS 병원 동기화 (역방향)");

        $sql = "SELECT id, name, address, phone, email, hms_hospital_id
                FROM gangnamunni_hospitals
                WHERE hms_hospital_id IS NOT NULL AND hms_hospital_id != ''
                ORDER BY id";

        $result = $this->gn_conn->query($sql);
        if (!$result) return;

        $updated = 0;
        while ($row = $result->fetch_assoc()) {
            $tid = $this->hms_conn->real_escape_string($row['hms_hospital_id']);
            $name = $this->hms_conn->real_escape_string($row['name']);
            $phone = $this->hms_conn->real_escape_string($row['phone'] ?? '');
            $address = $this->hms_conn->real_escape_string($row['address'] ?? '');

            // HMS tenant hospital_name 업데이트
            $check = $this->hms_conn->query(
                "SELECT hospital_name FROM tenants WHERE id = '{$tid}' LIMIT 1"
            );
            if ($check && $check->num_rows > 0) {
                $existing = $check->fetch_assoc();
                if ($existing['hospital_name'] !== $row['name'] && !empty($row['name'])) {
                    $this->hms_conn->query(
                        "UPDATE tenants SET hospital_name = '{$name}', updated_at = NOW() WHERE id = '{$tid}'"
                    );
                    // admin user도 업데이트
                    $this->hms_conn->query(
                        "UPDATE users SET first_name = '{$name}', hospital_name = '{$name}',
                         city = '{$address}', phone = '{$phone}', updated_at = NOW()
                         WHERE tenant_id = '{$tid}' AND is_admin_default = 1"
                    );
                    $updated++;
                }
            }
        }

        $this->stats['hms_hospitals_updated'] = $updated;
        $this->log("  HMS 병원 업데이트: {$updated}개");
    }

    /**
     * HMS doctors → gangnamunni_doctors
     */
    private function syncDoctors()
    {
        $this->log("\n[2] HMS → 강남언니 의사 동기화");

        $sql = "SELECT d.id as doctor_id, d.tenant_id,
                       CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as name,
                       d.specialist as specialty, u.phone, u.email,
                       u.qualification, d.appointment_charge
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                ORDER BY d.id";

        $result = $this->hms_conn->query($sql);
        if (!$result) return;

        while ($row = $result->fetch_assoc()) {
            $did = (int)$row['doctor_id'];
            $tid = $this->gn_conn->real_escape_string($row['tenant_id']);
            $name = $this->gn_conn->real_escape_string(trim($row['name']));
            $specialty = $this->gn_conn->real_escape_string($row['specialty'] ?? '');
            $phone = $this->gn_conn->real_escape_string($row['phone'] ?? '');
            $email = $this->gn_conn->real_escape_string($row['email'] ?? '');
            $qual = $this->gn_conn->real_escape_string($row['qualification'] ?? '');
            $charge = $this->gn_conn->real_escape_string($row['appointment_charge'] ?? '0');

            $check = $this->gn_conn->query(
                "SELECT id FROM gangnamunni_doctors WHERE hms_doctor_id = {$did} LIMIT 1"
            );

            if ($check && $check->num_rows > 0) {
                $this->gn_conn->query(
                    "UPDATE gangnamunni_doctors SET
                        name = '{$name}', specialty = '{$specialty}',
                        phone = '{$phone}', email = '{$email}',
                        qualification = '{$qual}', appointment_charge = '{$charge}',
                        hms_tenant_id = '{$tid}', synced_at = NOW()
                     WHERE hms_doctor_id = {$did}"
                );
                $this->stats['doctors_updated']++;
            } else {
                $this->gn_conn->query(
                    "INSERT INTO gangnamunni_doctors
                        (name, specialty, phone, email, qualification, appointment_charge,
                         hms_doctor_id, hms_tenant_id, synced_at)
                     VALUES ('{$name}', '{$specialty}', '{$phone}', '{$email}',
                             '{$qual}', '{$charge}', {$did}, '{$tid}', NOW())"
                );
                $this->stats['doctors_created']++;
            }
        }

        $this->log("  의사: 생성 {$this->stats['doctors_created']}, 업데이트 {$this->stats['doctors_updated']}");
    }

    /**
     * HMS doctor_departments → gangnamunni_departments
     */
    private function syncDepartments()
    {
        $query = "SELECT dd.id, dd.title, dd.description, dd.tenant_id
                  FROM doctor_departments dd
                  ORDER BY dd.tenant_id, dd.id";

        $result = $this->hms_conn->query($query);
        if (!$result) {
            $this->log("진료과 조회 실패: " . $this->hms_conn->error);
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $dept_id = $this->gn_conn->real_escape_string($row['id']);
            $tenant_id = $this->gn_conn->real_escape_string($row['tenant_id']);
            $title = $this->gn_conn->real_escape_string($row['title']);
            $desc = $this->gn_conn->real_escape_string($row['description'] ?? '');

            $check = $this->gn_conn->query(
                "SELECT id FROM gangnamunni_departments WHERE hms_department_id = '$dept_id' AND hms_tenant_id = '$tenant_id'"
            );

            if ($check && $check->num_rows > 0) {
                $this->gn_conn->query(
                    "UPDATE gangnamunni_departments SET
                        title = '$title',
                        description = '$desc',
                        synced_at = NOW()
                     WHERE hms_department_id = '$dept_id' AND hms_tenant_id = '$tenant_id'"
                );
                $this->stats['departments_updated']++;
            } else {
                $this->gn_conn->query(
                    "INSERT INTO gangnamunni_departments (hms_department_id, hms_tenant_id, title, description, synced_at)
                     VALUES ('$dept_id', '$tenant_id', '$title', '$desc', NOW())"
                );
                $this->stats['departments_created']++;
            }
        }

        echo "  진료과: 생성 {$this->stats['departments_created']}, 업데이트 {$this->stats['departments_updated']}\n";
    }

    private function log($msg)
    {
        $line = "[" . date('H:i:s') . "] " . $msg . "\n";
        file_put_contents($this->log_file, $line, FILE_APPEND);
        echo $msg . "\n";
    }
}

// 실행
$sync = new HmsGangnamunniSync();
$stats = $sync->run();
