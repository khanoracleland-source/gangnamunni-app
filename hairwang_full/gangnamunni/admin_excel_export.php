<?php
/**
 * admin_excel_export.php - 데이터 검색/다운로드 (통합 엑셀 Export)
 * 11종 엔티티별 검색/필터 + AJAX 페이지네이션 + 엑셀 다운로드
 *
 * @since 2026-02-23
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('excel_export');
require_once __DIR__ . '/includes/excel_engine.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// ── 검색/필터 설정 ──
$searchConfigs = [
    'hospital' => [
        'label' => '병원',
        'icon' => 'bi-hospital',
        'searchCols' => ['hospital_name', 'tenant_username'],
        'filters' => [
            ['key'=>'category', 'label'=>'카테고리', 'type'=>'select', 'sql'=>"SELECT DISTINCT name AS val, name AS label FROM hospital_type ORDER BY name"],
        ],
        'query' => "SELECT t.id, t.hospital_name, t.tenant_username, t.created_at,
                    (SELECT COUNT(*) FROM users u WHERE u.tenant_id=t.id AND u.owner_type LIKE '%Doctor') AS doctor_count,
                    (SELECT COUNT(*) FROM users u WHERE u.tenant_id=t.id AND u.owner_type LIKE '%Patient') AS patient_count
                    FROM tenants t",
        'headers' => [
            ['key'=>'id','label'=>'ID'],
            ['key'=>'hospital_name','label'=>'병원명'],
            ['key'=>'tenant_username','label'=>'사용자명'],
            ['key'=>'doctor_count','label'=>'의사 수'],
            ['key'=>'patient_count','label'=>'환자 수'],
            ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(t.hospital_name LIKE '%{Q}%' OR t.tenant_username LIKE '%{Q}%')",
    ],
    'doctor' => [
        'label' => '의사',
        'icon' => 'bi-person-badge',
        'searchCols' => ['first_name', 'last_name', 'email', 'specialist'],
        'filters' => [
            ['key'=>'hospital', 'label'=>'병원', 'type'=>'select', 'sql'=>"SELECT id AS val, hospital_name AS label FROM tenants ORDER BY hospital_name"],
            ['key'=>'department', 'label'=>'진료과', 'type'=>'select', 'sql'=>"SELECT DISTINCT id AS val, title AS label FROM doctor_departments ORDER BY title"],
            ['key'=>'status', 'label'=>'상태', 'type'=>'select', 'options'=>[['val'=>'1','label'=>'활성'],['val'=>'0','label'=>'비활성']]],
        ],
        'query' => "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender,
                    d.specialist, d.appointment_charge, dd.title AS department,
                    t.hospital_name, u.status, u.created_at
                    FROM users u
                    LEFT JOIN doctors d ON u.owner_id=d.id AND u.owner_type='App\\\\Models\\\\Doctor'
                    LEFT JOIN doctor_departments dd ON d.doctor_department_id=dd.id
                    LEFT JOIN tenants t ON u.tenant_id=t.id
                    WHERE u.owner_type='App\\\\Models\\\\Doctor'",
        'headers' => [
            ['key'=>'id','label'=>'ID'],
            ['key'=>'first_name','label'=>'이름'],
            ['key'=>'last_name','label'=>'성'],
            ['key'=>'email','label'=>'이메일'],
            ['key'=>'phone','label'=>'전화번호'],
            ['key'=>'gender','label'=>'성별'],
            ['key'=>'specialist','label'=>'전문분야'],
            ['key'=>'department','label'=>'진료과'],
            ['key'=>'appointment_charge','label'=>'진료비'],
            ['key'=>'hospital_name','label'=>'소속병원'],
            ['key'=>'status','label'=>'상태'],
            ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(u.first_name LIKE '%{Q}%' OR u.last_name LIKE '%{Q}%' OR u.email LIKE '%{Q}%' OR d.specialist LIKE '%{Q}%')",
        'filterWhere' => ['hospital'=>"u.tenant_id='{V}'", 'department'=>"d.doctor_department_id='{V}'", 'status'=>"u.status='{V}'"],
    ],
    'nurse' => [
        'label' => '간호사',
        'icon' => 'bi-heart-pulse',
        'searchCols' => ['first_name', 'last_name', 'email'],
        'filters' => [
            ['key'=>'hospital', 'label'=>'병원', 'type'=>'select', 'sql'=>"SELECT id AS val, hospital_name AS label FROM tenants ORDER BY hospital_name"],
        ],
        'query' => "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender,
                    u.qualification, t.hospital_name, u.status, u.created_at
                    FROM users u
                    LEFT JOIN tenants t ON u.tenant_id=t.id
                    WHERE u.owner_type='App\\\\Models\\\\Nurse'",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'first_name','label'=>'이름'], ['key'=>'last_name','label'=>'성'],
            ['key'=>'email','label'=>'이메일'], ['key'=>'phone','label'=>'전화번호'], ['key'=>'gender','label'=>'성별'],
            ['key'=>'qualification','label'=>'자격'], ['key'=>'hospital_name','label'=>'소속병원'],
            ['key'=>'status','label'=>'상태'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(u.first_name LIKE '%{Q}%' OR u.last_name LIKE '%{Q}%' OR u.email LIKE '%{Q}%')",
        'filterWhere' => ['hospital'=>"u.tenant_id='{V}'"],
    ],
    'pharmacist' => [
        'label' => '약사',
        'icon' => 'bi-capsule',
        'searchCols' => ['first_name', 'last_name', 'email'],
        'filters' => [
            ['key'=>'hospital', 'label'=>'병원', 'type'=>'select', 'sql'=>"SELECT id AS val, hospital_name AS label FROM tenants ORDER BY hospital_name"],
        ],
        'query' => "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender,
                    p.license_no, u.qualification, t.hospital_name, u.status, u.created_at
                    FROM users u
                    LEFT JOIN pharmacists p ON u.owner_id=p.id
                    LEFT JOIN tenants t ON u.tenant_id=t.id
                    WHERE u.owner_type='App\\\\Models\\\\Pharmacist'",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'first_name','label'=>'이름'], ['key'=>'last_name','label'=>'성'],
            ['key'=>'email','label'=>'이메일'], ['key'=>'phone','label'=>'전화번호'], ['key'=>'license_no','label'=>'면허번호'],
            ['key'=>'hospital_name','label'=>'소속병원'], ['key'=>'status','label'=>'상태'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(u.first_name LIKE '%{Q}%' OR u.last_name LIKE '%{Q}%' OR u.email LIKE '%{Q}%')",
        'filterWhere' => ['hospital'=>"u.tenant_id='{V}'"],
    ],
    'patient' => [
        'label' => '환자',
        'icon' => 'bi-person-heart',
        'searchCols' => ['first_name', 'last_name', 'email', 'phone'],
        'filters' => [
            ['key'=>'hospital', 'label'=>'병원', 'type'=>'select', 'sql'=>"SELECT id AS val, hospital_name AS label FROM tenants ORDER BY hospital_name"],
            ['key'=>'gender', 'label'=>'성별', 'type'=>'select', 'options'=>[['val'=>'1','label'=>'남성'],['val'=>'2','label'=>'여성']]],
            ['key'=>'blood_group', 'label'=>'혈액형', 'type'=>'select', 'options'=>[['val'=>'A+','label'=>'A+'],['val'=>'A-','label'=>'A-'],['val'=>'B+','label'=>'B+'],['val'=>'B-','label'=>'B-'],['val'=>'O+','label'=>'O+'],['val'=>'O-','label'=>'O-'],['val'=>'AB+','label'=>'AB+'],['val'=>'AB-','label'=>'AB-']]],
            ['key'=>'date_from', 'label'=>'등록일(부터)', 'type'=>'date'],
            ['key'=>'date_to', 'label'=>'등록일(까지)', 'type'=>'date'],
        ],
        'query' => "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.dob, u.blood_group,
                    p.insurance, p.patient_unique_id, t.hospital_name, u.status, u.created_at
                    FROM users u
                    LEFT JOIN patients p ON u.owner_id=p.id
                    LEFT JOIN tenants t ON u.tenant_id=t.id
                    WHERE u.owner_type='App\\\\Models\\\\Patient'",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'patient_unique_id','label'=>'환자번호'],
            ['key'=>'first_name','label'=>'이름'], ['key'=>'last_name','label'=>'성'],
            ['key'=>'email','label'=>'이메일'], ['key'=>'phone','label'=>'전화번호'],
            ['key'=>'gender','label'=>'성별'], ['key'=>'dob','label'=>'생년월일'],
            ['key'=>'blood_group','label'=>'혈액형'], ['key'=>'insurance','label'=>'보험'],
            ['key'=>'hospital_name','label'=>'소속병원'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(u.first_name LIKE '%{Q}%' OR u.last_name LIKE '%{Q}%' OR u.email LIKE '%{Q}%' OR u.phone LIKE '%{Q}%')",
        'filterWhere' => ['hospital'=>"u.tenant_id='{V}'", 'gender'=>"u.gender='{V}'", 'blood_group'=>"u.blood_group='{V}'", 'date_from'=>"u.created_at >= '{V}'", 'date_to'=>"u.created_at <= '{V} 23:59:59'"],
    ],
    'staff' => [
        'label' => '직원',
        'icon' => 'bi-people',
        'searchCols' => ['first_name', 'last_name', 'email'],
        'filters' => [
            ['key'=>'staff_type', 'label'=>'직종', 'type'=>'select', 'options'=>[['val'=>'Receptionist','label'=>'접수'],['val'=>'Accountant','label'=>'회계'],['val'=>'LabTechnician','label'=>'Lab기사'],['val'=>'CaseHandler','label'=>'사례관리사']]],
            ['key'=>'hospital', 'label'=>'병원', 'type'=>'select', 'sql'=>"SELECT id AS val, hospital_name AS label FROM tenants ORDER BY hospital_name"],
        ],
        'query' => "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender,
                    u.owner_type, u.designation, t.hospital_name, u.status, u.created_at
                    FROM users u
                    LEFT JOIN tenants t ON u.tenant_id=t.id
                    WHERE u.owner_type IN ('App\\\\Models\\\\Receptionist','App\\\\Models\\\\Accountant','App\\\\Models\\\\LabTechnician','App\\\\Models\\\\CaseHandler')",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'first_name','label'=>'이름'], ['key'=>'last_name','label'=>'성'],
            ['key'=>'email','label'=>'이메일'], ['key'=>'phone','label'=>'전화번호'],
            ['key'=>'owner_type','label'=>'직종'], ['key'=>'designation','label'=>'직위'],
            ['key'=>'hospital_name','label'=>'소속병원'], ['key'=>'status','label'=>'상태'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(u.first_name LIKE '%{Q}%' OR u.last_name LIKE '%{Q}%' OR u.email LIKE '%{Q}%')",
        'filterWhere' => ['staff_type'=>"u.owner_type LIKE '%{V}'", 'hospital'=>"u.tenant_id='{V}'"],
    ],
    'medicine' => [
        'label' => '의약품',
        'icon' => 'bi-prescription2',
        'searchCols' => ['name', 'salt_composition'],
        'filters' => [
            ['key'=>'category', 'label'=>'카테고리', 'type'=>'select', 'sql'=>"SELECT id AS val, name AS label FROM medicine_categories ORDER BY name"],
            ['key'=>'brand', 'label'=>'브랜드', 'type'=>'select', 'sql'=>"SELECT id AS val, name AS label FROM medicine_brands ORDER BY name"],
            ['key'=>'stock', 'label'=>'재고', 'type'=>'select', 'options'=>[['val'=>'in','label'=>'재고 있음'],['val'=>'out','label'=>'품절'],['val'=>'low','label'=>'부족(10개 이하)']]],
        ],
        'query' => "SELECT m.id, m.name, mc.name AS category_name, mb.name AS brand_name,
                    m.selling_price, m.buying_price, m.quantity, m.available_quantity,
                    m.salt_composition, t.hospital_name, m.created_at
                    FROM medicines m
                    LEFT JOIN medicine_categories mc ON m.category_id=mc.id
                    LEFT JOIN medicine_brands mb ON m.brand_id=mb.id
                    LEFT JOIN tenants t ON m.tenant_id=t.id
                    WHERE 1=1",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'name','label'=>'약품명'],
            ['key'=>'category_name','label'=>'카테고리'], ['key'=>'brand_name','label'=>'브랜드'],
            ['key'=>'selling_price','label'=>'판매가'], ['key'=>'buying_price','label'=>'매입가'],
            ['key'=>'quantity','label'=>'수량'], ['key'=>'available_quantity','label'=>'가용수량'],
            ['key'=>'salt_composition','label'=>'성분'], ['key'=>'hospital_name','label'=>'병원'],
            ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(m.name LIKE '%{Q}%' OR m.salt_composition LIKE '%{Q}%')",
        'filterWhere' => ['category'=>"m.category_id='{V}'", 'brand'=>"m.brand_id='{V}'", 'stock'=>"CASE WHEN '{V}'='in' THEN m.available_quantity > 0 WHEN '{V}'='out' THEN m.available_quantity = 0 WHEN '{V}'='low' THEN m.available_quantity BETWEEN 1 AND 10 END"],
    ],
    'appointment' => [
        'label' => '예약',
        'icon' => 'bi-calendar-check',
        'searchCols' => [],
        'filters' => [
            ['key'=>'status', 'label'=>'상태', 'type'=>'select', 'options'=>[['val'=>'0','label'=>'대기'],['val'=>'1','label'=>'완료']]],
            ['key'=>'date_from', 'label'=>'예약일(부터)', 'type'=>'date'],
            ['key'=>'date_to', 'label'=>'예약일(까지)', 'type'=>'date'],
            ['key'=>'hospital', 'label'=>'병원', 'type'=>'select', 'sql'=>"SELECT id AS val, hospital_name AS label FROM tenants ORDER BY hospital_name"],
        ],
        'query' => "SELECT a.id, a.opd_date, a.problem, a.is_completed, a.payment_status,
                    CONCAT(pu.first_name, ' ', COALESCE(pu.last_name,'')) AS patient_name,
                    CONCAT(du.first_name, ' ', COALESCE(du.last_name,'')) AS doctor_name,
                    dd.title AS department, t.hospital_name, a.created_at
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id=p.id
                    LEFT JOIN users pu ON p.user_id=pu.id
                    LEFT JOIN doctors d ON a.doctor_id=d.id
                    LEFT JOIN users du ON d.user_id=du.id
                    LEFT JOIN doctor_departments dd ON a.department_id=dd.id
                    LEFT JOIN tenants t ON a.tenant_id=t.id
                    WHERE 1=1",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'patient_name','label'=>'환자'],
            ['key'=>'doctor_name','label'=>'의사'], ['key'=>'department','label'=>'진료과'],
            ['key'=>'opd_date','label'=>'예약일시'], ['key'=>'problem','label'=>'증상'],
            ['key'=>'is_completed','label'=>'완료여부'], ['key'=>'payment_status','label'=>'결제'],
            ['key'=>'hospital_name','label'=>'병원'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(pu.first_name LIKE '%{Q}%' OR du.first_name LIKE '%{Q}%' OR a.problem LIKE '%{Q}%')",
        'filterWhere' => ['status'=>"a.is_completed='{V}'", 'date_from'=>"a.opd_date >= '{V}'", 'date_to'=>"a.opd_date <= '{V} 23:59:59'", 'hospital'=>"a.tenant_id='{V}'"],
    ],
    'ad' => [
        'label' => '광고',
        'icon' => 'bi-megaphone',
        'searchCols' => ['title', 'subtitle'],
        'filters' => [
            ['key'=>'ad_type', 'label'=>'유형', 'type'=>'select', 'options'=>[['val'=>'banner','label'=>'배너'],['val'=>'popup','label'=>'팝업'],['val'=>'feed','label'=>'피드']]],
            ['key'=>'status', 'label'=>'상태', 'type'=>'select', 'options'=>[['val'=>'1','label'=>'활성'],['val'=>'0','label'=>'비활성']]],
        ],
        'query' => "SELECT id, title, subtitle, ad_type, link_url, display_order, is_active, created_at
                    FROM gangnamunni_ads WHERE 1=1",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'title','label'=>'제목'],
            ['key'=>'subtitle','label'=>'부제목'], ['key'=>'ad_type','label'=>'유형'],
            ['key'=>'link_url','label'=>'링크'], ['key'=>'display_order','label'=>'순서'],
            ['key'=>'is_active','label'=>'활성'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(title LIKE '%{Q}%' OR subtitle LIKE '%{Q}%')",
        'filterWhere' => ['ad_type'=>"ad_type='{V}'", 'status'=>"is_active='{V}'"],
    ],
    'review' => [
        'label' => '후기',
        'icon' => 'bi-star',
        'searchCols' => ['content'],
        'filters' => [
            ['key'=>'rating_min', 'label'=>'최소 별점', 'type'=>'select', 'options'=>[['val'=>'1','label'=>'1점'],['val'=>'2','label'=>'2점'],['val'=>'3','label'=>'3점'],['val'=>'4','label'=>'4점'],['val'=>'5','label'=>'5점']]],
            ['key'=>'status', 'label'=>'상태', 'type'=>'select', 'options'=>[['val'=>'1','label'=>'활성'],['val'=>'0','label'=>'비활성']]],
        ],
        'query' => "SELECT r.id, r.rating, r.content, r.is_active, r.created_at,
                    h.hospital_name
                    FROM hospital_reviews r
                    LEFT JOIN gangnamunni_hospitals gh ON r.hospital_id=gh.id
                    LEFT JOIN tenants h ON gh.hms_hospital_id=h.id
                    WHERE 1=1",
        'headers' => [
            ['key'=>'id','label'=>'ID'], ['key'=>'hospital_name','label'=>'병원'],
            ['key'=>'rating','label'=>'별점'], ['key'=>'content','label'=>'내용'],
            ['key'=>'is_active','label'=>'상태'], ['key'=>'created_at','label'=>'등록일'],
        ],
        'searchWhere' => "(r.content LIKE '%{Q}%' OR h.hospital_name LIKE '%{Q}%')",
        'filterWhere' => ['rating_min'=>"r.rating >= '{V}'", 'status'=>"r.is_active='{V}'"],
    ],
    'member' => [
        'label' => '회원',
        'icon' => 'bi-person-circle',
        'searchCols' => ['mb_id', 'mb_nick', 'mb_email'],
        'filters' => [
            ['key'=>'mb_level', 'label'=>'등급', 'type'=>'select', 'options'=>[['val'=>'1','label'=>'1'],['val'=>'2','label'=>'2'],['val'=>'3','label'=>'3'],['val'=>'5','label'=>'5'],['val'=>'10','label'=>'10']]],
            ['key'=>'date_from', 'label'=>'가입일(부터)', 'type'=>'date'],
            ['key'=>'date_to', 'label'=>'가입일(까지)', 'type'=>'date'],
        ],
        'query' => "SELECT mb_no, mb_id, mb_nick, mb_name, mb_email, mb_hp, mb_level, mb_point, mb_datetime, mb_last_login
                    FROM g5_member WHERE 1=1",
        'headers' => [
            ['key'=>'mb_no','label'=>'번호'], ['key'=>'mb_id','label'=>'아이디'],
            ['key'=>'mb_nick','label'=>'닉네임'], ['key'=>'mb_name','label'=>'이름'],
            ['key'=>'mb_email','label'=>'이메일'], ['key'=>'mb_hp','label'=>'전화번호'],
            ['key'=>'mb_level','label'=>'등급'], ['key'=>'mb_point','label'=>'포인트'],
            ['key'=>'mb_datetime','label'=>'가입일'], ['key'=>'mb_last_login','label'=>'최근접속'],
        ],
        'searchWhere' => "(mb_id LIKE '%{Q}%' OR mb_nick LIKE '%{Q}%' OR mb_email LIKE '%{Q}%' OR mb_name LIKE '%{Q}%')",
        'filterWhere' => ['mb_level'=>"mb_level='{V}'", 'date_from'=>"mb_datetime >= '{V}'", 'date_to'=>"mb_datetime <= '{V} 23:59:59'"],
    ],
];

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // 필터 옵션 로드
    if ($action === 'load_filters') {
        $entity = $_POST['entity'] ?? '';
        $config = $searchConfigs[$entity] ?? null;
        if (!$config) { echo json_encode(['success'=>false,'error'=>'알 수 없는 엔티티']); exit; }

        $filters = [];
        foreach ($config['filters'] as $f) {
            $filterData = ['key'=>$f['key'], 'label'=>$f['label'], 'type'=>$f['type']];
            if ($f['type'] === 'select') {
                if (isset($f['options'])) {
                    $filterData['options'] = $f['options'];
                } elseif (isset($f['sql'])) {
                    $r = mysqli_query($connect, $f['sql']);
                    $opts = [];
                    if ($r) { while ($row = mysqli_fetch_assoc($r)) $opts[] = $row; }
                    $filterData['options'] = $opts;
                }
            }
            $filters[] = $filterData;
        }
        echo json_encode(['success'=>true, 'filters'=>$filters, 'headers'=>$config['headers']]);
        exit;
    }

    // 검색
    if ($action === 'search') {
        $entity = $_POST['entity'] ?? '';
        $query = trim($_POST['query'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        $perPage = max(10, min(100, intval($_POST['per_page'] ?? 20)));
        $filtersJson = $_POST['filters'] ?? '{}';
        $filterValues = json_decode($filtersJson, true) ?: [];

        $config = $searchConfigs[$entity] ?? null;
        if (!$config) { echo json_encode(['success'=>false,'error'=>'알 수 없는 엔티티']); exit; }

        $sql = $config['query'];
        $conditions = [];

        // 검색어
        if ($query !== '' && !empty($config['searchWhere'])) {
            $q = $esc($query);
            $conditions[] = str_replace('{Q}', $q, $config['searchWhere']);
        }

        // 필터
        if (!empty($filterValues) && !empty($config['filterWhere'])) {
            foreach ($filterValues as $fk => $fv) {
                if ($fv === '' || $fv === null) continue;
                if (isset($config['filterWhere'][$fk])) {
                    $v = $esc($fv);
                    $conditions[] = str_replace('{V}', $v, $config['filterWhere'][$fk]);
                }
            }
        }

        if (!empty($conditions)) {
            // 기존 WHERE 유무 확인
            if (stripos($sql, 'WHERE') !== false) {
                $sql .= ' AND ' . implode(' AND ', $conditions);
            } else {
                $sql .= ' WHERE ' . implode(' AND ', $conditions);
            }
        }

        // 전체 건수
        $countSql = "SELECT COUNT(*) AS cnt FROM ({$sql}) AS sub";
        $countRes = mysqli_query($connect, $countSql);
        $totalCount = 0;
        if ($countRes) {
            $totalCount = intval(mysqli_fetch_assoc($countRes)['cnt']);
        }

        // 페이지네이션
        $totalPages = max(1, ceil($totalCount / $perPage));
        $offset = ($page - 1) * $perPage;
        $sql .= " ORDER BY 1 DESC LIMIT {$offset}, {$perPage}";

        $result = mysqli_query($connect, $sql);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                // 성별 변환
                if (isset($row['gender'])) {
                    $row['gender'] = $row['gender'] == 1 ? '남성' : ($row['gender'] == 2 ? '여성' : $row['gender']);
                }
                // owner_type 변환
                if (isset($row['owner_type'])) {
                    $row['owner_type'] = str_replace('App\\Models\\', '', $row['owner_type']);
                }
                // 상태 변환
                if (isset($row['status']) && is_numeric($row['status'])) {
                    $row['status'] = $row['status'] == 1 ? '활성' : '비활성';
                }
                if (isset($row['is_completed'])) {
                    $row['is_completed'] = $row['is_completed'] == 1 ? '완료' : '대기';
                }
                if (isset($row['payment_status'])) {
                    $row['payment_status'] = $row['payment_status'] == 1 ? '결제' : '미결제';
                }
                if (isset($row['is_active'])) {
                    $row['is_active'] = $row['is_active'] == 1 ? '활성' : '비활성';
                }
                $rows[] = $row;
            }
        }

        echo json_encode([
            'success' => true,
            'rows' => $rows,
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ]);
        exit;
    }

    // 엑셀 다운로드
    if ($action === 'export') {
        $entity = $_POST['entity'] ?? '';
        $query = trim($_POST['query'] ?? '');
        $filtersJson = $_POST['filters'] ?? '{}';
        $filterValues = json_decode($filtersJson, true) ?: [];

        $config = $searchConfigs[$entity] ?? null;
        if (!$config) { echo json_encode(['success'=>false,'error'=>'알 수 없는 엔티티']); exit; }

        $sql = $config['query'];
        $conditions = [];

        if ($query !== '' && !empty($config['searchWhere'])) {
            $q = $esc($query);
            $conditions[] = str_replace('{Q}', $q, $config['searchWhere']);
        }

        if (!empty($filterValues) && !empty($config['filterWhere'])) {
            foreach ($filterValues as $fk => $fv) {
                if ($fv === '' || $fv === null) continue;
                if (isset($config['filterWhere'][$fk])) {
                    $v = $esc($fv);
                    $conditions[] = str_replace('{V}', $v, $config['filterWhere'][$fk]);
                }
            }
        }

        if (!empty($conditions)) {
            if (stripos($sql, 'WHERE') !== false) {
                $sql .= ' AND ' . implode(' AND ', $conditions);
            } else {
                $sql .= ' WHERE ' . implode(' AND ', $conditions);
            }
        }

        // 최대 10000행
        $sql .= " ORDER BY 1 DESC LIMIT 10000";
        $result = mysqli_query($connect, $sql);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (isset($row['gender'])) $row['gender'] = $row['gender'] == 1 ? '남성' : ($row['gender'] == 2 ? '여성' : $row['gender']);
                if (isset($row['owner_type'])) $row['owner_type'] = str_replace('App\\Models\\', '', $row['owner_type']);
                if (isset($row['status']) && is_numeric($row['status'])) $row['status'] = $row['status'] == 1 ? '활성' : '비활성';
                if (isset($row['is_completed'])) $row['is_completed'] = $row['is_completed'] == 1 ? '완료' : '대기';
                if (isset($row['payment_status'])) $row['payment_status'] = $row['payment_status'] == 1 ? '결제' : '미결제';
                if (isset($row['is_active'])) $row['is_active'] = $row['is_active'] == 1 ? '활성' : '비활성';
                $data[] = $row;
            }
        }

        $filename = "export_{$entity}_" . date('Ymd_His') . '.xlsx';
        $filepath = exportToExcel($data, $config['headers'], $filename);

        if (!$filepath || !file_exists($filepath)) {
            echo json_encode(['success'=>false,'error'=>'엑셀 생성 실패']);
            exit;
        }

        $webDir = __DIR__ . '/uploads/excel_exports/';
        if (!is_dir($webDir)) mkdir($webDir, 0775, true);
        $webFile = basename($filepath);
        copy($filepath, $webDir . $webFile);
        echo json_encode(['success'=>true, 'url'=>'uploads/excel_exports/' . $webFile, 'filename'=>$webFile, 'count'=>count($data)]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

// ── GET 다운로드 ──
if (isset($_GET['download'])) {
    $file = __DIR__ . '/uploads/excel_exports/' . basename($_GET['download']);
    if (file_exists($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = $ext === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo '파일을 찾을 수 없습니다.';
    exit;
}

$admin_page_title = '데이터 검색/다운로드';
$admin_current = 'excel_export';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 엔티티 탭 ── */
.entity-tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    padding: 4px 0 12px;
    border-bottom: 2px solid var(--bs-border-color);
    margin-bottom: 20px;
}
.entity-tab {
    padding: 8px 14px;
    border-radius: 8px 8px 0 0;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    color: var(--bs-secondary-color);
    transition: all 0.2s;
    border: 1px solid transparent;
    border-bottom: none;
}
.entity-tab:hover { color: #198754; background: rgba(25,135,84,0.04); }
.entity-tab.active {
    color: #198754;
    background: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    border-bottom-color: var(--bs-body-bg);
    margin-bottom: -2px;
    padding-bottom: 10px;
}
.entity-tab i { margin-right: 4px; }

/* ── 검색/필터 바 ── */
.search-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
    margin-bottom: 16px;
}
.search-filter-bar .search-input-wrap {
    flex: 1 1 280px;
    min-width: 200px;
}
.search-filter-bar .filter-item { flex: 0 0 auto; }
.search-filter-bar .filter-item label { font-size: 11px; color: var(--bs-secondary-color); display: block; margin-bottom: 2px; }
.search-filter-bar .filter-item select, .search-filter-bar .filter-item input { font-size: 13px; }

/* ── 결과 테이블 ── */
.result-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    margin-bottom: 16px;
}
.result-table { font-size: 12px; margin: 0; }
.result-table thead th {
    position: sticky; top: 0; z-index: 2;
    background: #198754; color: #fff;
    font-size: 11px; white-space: nowrap;
}
.result-table td { white-space: nowrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
.result-table tbody tr:hover { background: rgba(25,135,84,0.04); }

/* ── 페이지네이션 ── */
.pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
}
.pagination-bar .page-info { color: var(--bs-secondary-color); }
.pagination-bar .page-nav { display: flex; gap: 4px; }
.pagination-bar .page-btn {
    padding: 4px 10px;
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    background: var(--bs-body-bg);
}
.pagination-bar .page-btn:hover { border-color: #198754; color: #198754; }
.pagination-bar .page-btn.active { background: #198754; color: #fff; border-color: #198754; }
.pagination-bar .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── 빈 상태 ── */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--bs-secondary-color);
}
.empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }

/* ── 하단 액션 바 ── */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
}
</style>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="card-title mb-0">
                <i class="bi bi-search text-success me-2"></i>데이터 검색 / 엑셀 다운로드
            </h5>
            <span class="badge bg-secondary" id="totalBadge" style="display:none;"></span>
        </div>

        <!-- 엔티티 탭 -->
        <div class="entity-tabs" id="entityTabs"></div>

        <!-- 검색/필터 바 -->
        <div class="search-filter-bar" id="searchFilterBar">
            <div class="search-input-wrap">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" placeholder="검색어 입력..." onkeydown="if(event.key==='Enter')doSearch()">
                    <button class="btn btn-success" onclick="doSearch()">검색</button>
                </div>
            </div>
            <div id="filterContainer"></div>
        </div>

        <!-- 결과 테이블 -->
        <div id="resultArea">
            <div class="empty-state" id="emptyState">
                <i class="bi bi-table"></i>
                <div>데이터 유형을 선택하고 검색하세요</div>
            </div>

            <div id="tableArea" style="display:none;">
                <div class="result-table-wrap">
                    <table class="table table-sm table-hover result-table" id="resultTable">
                        <thead id="resultThead"></thead>
                        <tbody id="resultTbody"></tbody>
                    </table>
                </div>

                <!-- 페이지네이션 -->
                <div class="pagination-bar">
                    <div class="page-info" id="pageInfo">0건</div>
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm" style="width:80px;" id="perPageSelect" onchange="doSearch(1)">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <div class="page-nav" id="pageNav"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 하단 액션 바 -->
        <div class="action-bar">
            <div>
                <button class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>필터 초기화
                </button>
            </div>
            <div>
                <button class="btn btn-success btn-sm" id="btnExport" onclick="exportExcel()" disabled>
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>엑셀 다운로드
                </button>
                <a href="admin_excel_import_wizard.php" class="btn btn-outline-success btn-sm ms-2">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i>대량 등록
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// ── 클라이언트 설정 ──
const SEARCH_CONFIGS = <?php
    $clientConfigs = [];
    foreach ($searchConfigs as $key => $cfg) {
        $clientConfigs[$key] = [
            'key' => $key,
            'label' => $cfg['label'],
            'icon' => $cfg['icon'],
        ];
    }
    echo json_encode($clientConfigs, JSON_UNESCAPED_UNICODE);
?>;

let currentEntity = null;
let currentFilters = {};
let currentHeaders = [];
let currentPage = 1;
let searchDebounce = null;

// ── 엔티티 탭 렌더링 ──
function renderTabs() {
    const tabs = document.getElementById('entityTabs');
    let html = '';
    for (const [key, cfg] of Object.entries(SEARCH_CONFIGS)) {
        html += `<div class="entity-tab" data-entity="${key}" onclick="selectTab('${key}')">
            <i class="bi ${cfg.icon}"></i>${cfg.label}
        </div>`;
    }
    tabs.innerHTML = html;
}

function selectTab(entity) {
    currentEntity = entity;
    currentPage = 1;
    currentFilters = {};

    document.querySelectorAll('.entity-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.entity-tab[data-entity="${entity}"]`).classList.add('active');

    // 필터 로드
    loadFilters(entity);
}

// ── 필터 로드 ──
function loadFilters(entity) {
    const fd = new FormData();
    fd.append('action', 'load_filters');
    fd.append('entity', entity);

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            currentHeaders = d.headers;
            renderFilters(d.filters);
            doSearch(1);
        });
}

function renderFilters(filters) {
    const container = document.getElementById('filterContainer');
    let html = '';
    filters.forEach(f => {
        html += `<div class="filter-item">`;
        html += `<label>${f.label}</label>`;
        if (f.type === 'select') {
            html += `<select class="form-select form-select-sm" data-filter="${f.key}" onchange="updateFilter('${f.key}', this.value)">`;
            html += `<option value="">전체</option>`;
            (f.options || []).forEach(o => {
                html += `<option value="${o.val}">${o.label}</option>`;
            });
            html += `</select>`;
        } else if (f.type === 'date') {
            html += `<input type="date" class="form-control form-control-sm" data-filter="${f.key}" onchange="updateFilter('${f.key}', this.value)">`;
        }
        html += `</div>`;
    });
    container.innerHTML = html;
}

function updateFilter(key, value) {
    if (value) currentFilters[key] = value;
    else delete currentFilters[key];
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => doSearch(1), 300);
}

function resetFilters() {
    currentFilters = {};
    document.getElementById('searchInput').value = '';
    document.querySelectorAll('#filterContainer select, #filterContainer input').forEach(el => el.value = '');
    if (currentEntity) doSearch(1);
}

// ── 검색 ──
function doSearch(page) {
    if (!currentEntity) return;
    currentPage = page || currentPage;

    const query = document.getElementById('searchInput').value.trim();
    const perPage = document.getElementById('perPageSelect').value;

    const fd = new FormData();
    fd.append('action', 'search');
    fd.append('entity', currentEntity);
    fd.append('query', query);
    fd.append('page', currentPage);
    fd.append('per_page', perPage);
    fd.append('filters', JSON.stringify(currentFilters));

    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('tableArea').style.display = '';
    document.getElementById('resultTbody').innerHTML = '<tr><td colspan="20" class="text-center py-4"><span class="spinner-border spinner-border-sm text-success me-2"></span>로딩 중...</td></tr>';

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert(d.error); return; }
            renderResults(d);
        })
        .catch(e => {
            document.getElementById('resultTbody').innerHTML = `<tr><td colspan="20" class="text-center text-danger py-4">오류: ${e.message}</td></tr>`;
        });
}

function renderResults(data) {
    // 헤더
    const thead = document.getElementById('resultThead');
    let thHtml = '<tr>';
    currentHeaders.forEach(h => { thHtml += `<th>${h.label}</th>`; });
    thHtml += '</tr>';
    thead.innerHTML = thHtml;

    // 데이터
    const tbody = document.getElementById('resultTbody');
    if (data.rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="' + currentHeaders.length + '" class="text-center py-4 text-muted">검색 결과가 없습니다</td></tr>';
    } else {
        let trHtml = '';
        data.rows.forEach(row => {
            trHtml += '<tr>';
            currentHeaders.forEach(h => {
                let val = row[h.key] ?? '';
                // 긴 텍스트 줄이기
                if (typeof val === 'string' && val.length > 50) val = val.substring(0, 50) + '...';
                trHtml += `<td title="${(row[h.key] || '').toString().replace(/"/g, '&quot;')}">${val}</td>`;
            });
            trHtml += '</tr>';
        });
        tbody.innerHTML = trHtml;
    }

    // 페이지 정보
    const start = (data.page - 1) * data.per_page + 1;
    const end = Math.min(data.page * data.per_page, data.total);
    document.getElementById('pageInfo').textContent = data.total > 0 ? `${start}-${end} / 총 ${data.total}건` : '0건';
    document.getElementById('totalBadge').style.display = '';
    document.getElementById('totalBadge').textContent = `${data.total}건`;

    // 페이지네이션
    renderPagination(data.page, data.total_pages);

    // 다운로드 버튼 활성화
    document.getElementById('btnExport').disabled = data.total === 0;
}

function renderPagination(current, total) {
    const nav = document.getElementById('pageNav');
    if (total <= 1) { nav.innerHTML = ''; return; }

    let html = '';
    html += `<button class="page-btn" ${current <= 1 ? 'disabled' : ''} onclick="doSearch(${current - 1})"><i class="bi bi-chevron-left"></i></button>`;

    // 최대 7페이지 표시
    let startPage = Math.max(1, current - 3);
    let endPage = Math.min(total, startPage + 6);
    if (endPage - startPage < 6) startPage = Math.max(1, endPage - 6);

    if (startPage > 1) {
        html += `<button class="page-btn" onclick="doSearch(1)">1</button>`;
        if (startPage > 2) html += `<span class="px-1">...</span>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="doSearch(${i})">${i}</button>`;
    }

    if (endPage < total) {
        if (endPage < total - 1) html += `<span class="px-1">...</span>`;
        html += `<button class="page-btn" onclick="doSearch(${total})">${total}</button>`;
    }

    html += `<button class="page-btn" ${current >= total ? 'disabled' : ''} onclick="doSearch(${current + 1})"><i class="bi bi-chevron-right"></i></button>`;
    nav.innerHTML = html;
}

// ── 엑셀 다운로드 ──
function exportExcel() {
    if (!currentEntity) return;
    const btn = document.getElementById('btnExport');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>생성 중...';

    const fd = new FormData();
    fd.append('action', 'export');
    fd.append('entity', currentEntity);
    fd.append('query', document.getElementById('searchInput').value.trim());
    fd.append('filters', JSON.stringify(currentFilters));

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                window.location.href = '?download=' + encodeURIComponent(d.filename);
                alert(`${d.count}건 엑셀 다운로드 완료`);
            } else {
                alert(d.error || '다운로드 실패');
            }
        })
        .catch(e => alert('오류: ' + e.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-earmark-arrow-down me-1"></i>엑셀 다운로드';
        });
}

// ── 초기화 ──
renderTabs();

// URL 파라미터로 자동 선택
const urlParams = new URLSearchParams(window.location.search);
const autoEntity = urlParams.get('entity');
if (autoEntity && SEARCH_CONFIGS[autoEntity]) {
    selectTab(autoEntity);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
