<?php
/**
 * 진료과/의사 AJAX API 엔드포인트
 *
 * GET ?action=departments&hospital_id=X  → 병원별 진료과 목록
 * GET ?action=doctors&department_id=X    → 진료과별 의사 목록
 * GET ?action=doctors&hospital_id=X      → 병원별 전체 의사 목록
 *
 * @file    gangnamunni/api_departments.php
 * @created 2026-02-10 (Phase 2-1)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/init.php';

$action = $_GET['action'] ?? '';
$hospital_id = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;

switch ($action) {
    case 'departments':
        // 병원별 진료과 목록 조회
        if ($hospital_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'hospital_id required']);
            exit;
        }

        // gangnamunni_hospitals에서 hms_tenant_id 가져오기
        $h_result = mysqli_query($connect,
            "SELECT hms_hospital_id FROM gangnamunni_hospitals WHERE id = {$hospital_id}");
        $h_row = $h_result ? mysqli_fetch_assoc($h_result) : null;
        $tenant_id = $h_row['hms_hospital_id'] ?? '';

        if (empty($tenant_id)) {
            // HMS 비연동 병원: 기본 진료과 목록 반환
            echo json_encode([
                'success' => true,
                'data' => [
                    ['id' => 0, 'title' => '일반진료', 'doctor_count' => 0],
                    ['id' => 0, 'title' => '피부과', 'doctor_count' => 0],
                    ['id' => 0, 'title' => '성형외과', 'doctor_count' => 0],
                ]
            ]);
            exit;
        }

        $tenant_esc = mysqli_real_escape_string($connect, $tenant_id);
        $result = mysqli_query($connect,
            "SELECT id, hms_department_id, title, description, doctor_count
             FROM gangnamunni_departments
             WHERE hms_tenant_id = '{$tenant_esc}'
             ORDER BY title");

        $departments = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $departments], JSON_UNESCAPED_UNICODE);
        break;

    case 'doctors':
        // 진료과별 또는 병원별 의사 목록 조회
        $where = "1=1";

        if ($department_id > 0) {
            // gangnamunni_departments의 hms_department_id로 매핑
            $dept_result = mysqli_query($connect,
                "SELECT hms_department_id, hms_tenant_id FROM gangnamunni_departments WHERE id = {$department_id}");
            $dept_row = $dept_result ? mysqli_fetch_assoc($dept_result) : null;

            if ($dept_row) {
                // HMS API로 해당 진료과 의사 조회
                require_once __DIR__ . '/includes/infyhms_api.php';
// HMS 독립 모드: 자체 DB에서 진료과 조회if (function_exists("is_hms_standalone") && is_hms_standalone()) {    $dept_q = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_departments WHERE hospital_id = ? AND is_active = 1 ORDER BY sort_order");    mysqli_stmt_bind_param($dept_q, "i", $hospital_id);    mysqli_stmt_execute($dept_q);    $dept_r = mysqli_stmt_get_result($dept_q);    $departments = [];    while ($d = mysqli_fetch_assoc($dept_r)) $departments[] = $d;    echo json_encode(["success" => true, "departments" => $departments]);    exit;}
                $api = get_infyhms_api();
                $api_result = $api->getDoctorsByDepartment($dept_row['hms_department_id']);

                if (!empty($api_result['success']) && !empty($api_result['data'])) {
                    echo json_encode(['success' => true, 'data' => $api_result['data']], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            // 폴백: 로컬 DB 조회
            $where .= " AND d.department_id = {$department_id}";
        }

        if ($hospital_id > 0) {
            $where .= " AND d.hospital_id = {$hospital_id}";
        }

        $result = mysqli_query($connect,
            "SELECT d.id, d.hms_doctor_id, d.name, d.specialty, d.phone, d.qualification, d.appointment_charge
             FROM gangnamunni_doctors d
             WHERE {$where}
             ORDER BY d.name");

        $doctors = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $doctors[] = $row;
            }
        }

        echo json_encode(['success' => true, 'data' => $doctors], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use: departments, doctors']);
}
