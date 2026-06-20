<?php
/**
 * HMS ↔ 서울미녀 DB 동기화 설정
 *
 * 동기화 전략:
 * - HMS: 병원 관리 시스템 (의사, 환자, 예약, 진료)
 * - 서울미녀: 병원 리뷰 플랫폼 (병원 정보, 리뷰, 이벤트)
 * - 단방향 동기화: HMS → 서울미녀 (병원/의사 정보)
 * - 양방향 동기화: 상담 신청 (서울미녀 → HMS appointments)
 */

// HMS DB 설정
define('HMS_DB_HOST', 'localhost');
define('HMS_DB_USER', 'root');
define('HMS_DB_PASS', '<REMOVED_DB_PASSWORD>');
define('HMS_DB_NAME', 'hms_saas');

// 서울미녀 DB 설정 (Gnuboard hairwang DB)
define('GANGNAMUNNI_DB_HOST', 'localhost');
define('GANGNAMUNNI_DB_USER', 'root');
define('GANGNAMUNNI_DB_PASS', '<REMOVED_DB_PASSWORD>');
define('GANGNAMUNNI_DB_NAME', 'hms_saas');

// 동기화 설정
define('SYNC_LOG_PATH', __DIR__ . '/logs/');
define('SYNC_INTERVAL', 300); // 5분마다 동기화

// 동기화 매핑 설정
$SYNC_MAPPINGS = [
    // HMS → 서울미녀: 병원 정보
    'hospitals' => [
        'enabled' => true,
        'direction' => 'hms_to_gangnamunni',
        'hms_table' => 'hospitals', // HMS hospitals 테이블
        'gangnamunni_table' => 'gangnamunni_hospitals',
        'field_mapping' => [
            'id' => 'hms_hospital_id', // HMS ID를 별도 컬럼에 저장
            'name' => 'name',
            'address' => 'address',
            'phone' => 'phone',
            'description' => 'description',
        ],
    ],

    // HMS → 서울미녀: 의사 정보
    'doctors' => [
        'enabled' => true,
        'direction' => 'hms_to_gangnamunni',
        'hms_table' => 'doctors',
        'gangnamunni_table' => 'gangnamunni_doctors',
        'field_mapping' => [
            'id' => 'hms_doctor_id',
            'name' => 'name',
            'specialization' => 'specialty',
            'experience_years' => 'experience',
        ],
    ],

    // 서울미녀 → HMS: 상담 신청
    'consultations' => [
        'enabled' => true,
        'direction' => 'gangnamunni_to_hms',
        'gangnamunni_table' => 'gangnamunni_consultations',
        'hms_table' => 'appointments',
        'field_mapping' => [
            'id' => 'gangnamunni_consultation_id',
            'hospital_id' => 'hospital_id',
            'name' => 'patient_name',
            'phone' => 'patient_phone',
            'message' => 'notes',
            'created_at' => 'appointment_date',
        ],
    ],
];

// 동기화 제외 조건
$SYNC_EXCLUSIONS = [
    // 테스트 데이터 제외
    'test_hospitals' => "name LIKE '%테스트%' OR name LIKE '%test%'",

    // 삭제된 데이터 제외
    'deleted' => "deleted_at IS NOT NULL",
];

/**
 * DB 연결 헬퍼
 */
function getHMSConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = mysqli_connect(HMS_DB_HOST, HMS_DB_USER, HMS_DB_PASS, HMS_DB_NAME);
        if (!$conn) {
            throw new Exception('HMS DB 연결 실패: ' . mysqli_connect_error());
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

function getGangnamunniConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = mysqli_connect(GANGNAMUNNI_DB_HOST, GANGNAMUNNI_DB_USER, GANGNAMUNNI_DB_PASS, GANGNAMUNNI_DB_NAME);
        if (!$conn) {
            throw new Exception('Gangnamunni DB 연결 실패: ' . mysqli_connect_error());
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

/**
 * 동기화 로그 기록
 */
function logSync($message, $level = 'INFO') {
    if (!is_dir(SYNC_LOG_PATH)) {
        mkdir(SYNC_LOG_PATH, 0755, true);
    }

    $log_file = SYNC_LOG_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);

    if ($level === 'ERROR') {
        error_log($log_entry);
    }
}
