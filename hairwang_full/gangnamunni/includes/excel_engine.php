<?php
/**
 * excel_engine.php - 엑셀 Import/Export 공통 엔진
 * PhpSpreadsheet 활용 (HMS vendor에 설치됨)
 *
 * 기능:
 *  - parseExcelFile()     : .xlsx/.csv 파싱 → 2D 배열
 *  - validateRows()       : 엔티티별 유효성 검사
 *  - checkConflicts()     : DB 충돌 3종 검사 (완전중복/부분일치/FK불일치)
 *  - importRows()         : 실제 INSERT/UPDATE 실행
 *  - generateErrorExcel() : 에러 리스트 엑셀 생성
 *  - exportToExcel()      : 데이터를 .xlsx로 생성
 *  - generateTemplate()   : 엔티티별 양식 엑셀 (3시트)
 *
 * @since 2026-02-23
 */

// ── PhpSpreadsheet autoload ──
$__vendorPath = realpath(__DIR__ . '/../../../../vendor/autoload.php');
if (!$__vendorPath) {
    $__vendorPath = realpath(__DIR__ . '/../../../vendor/autoload.php');
}
if (!$__vendorPath) {
    // HMS 프로젝트 루트에서 찾기
    $__vendorPath = '/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/vendor/autoload.php';
}
$HAS_PHPSPREADSHEET = false;
if (file_exists($__vendorPath)) {
    require_once $__vendorPath;
    if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        $HAS_PHPSPREADSHEET = true;
    }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// ── 엔티티 정의 ──
$ENTITY_DEFS = [
    'hospital' => [
        'label' => '병원',
        'icon' => 'bi-hospital',
        'table' => 'tenants',
        'columns' => [
            ['key'=>'hospital_name', 'label'=>'병원명', 'required'=>true, 'type'=>'string', 'unique'=>true],
            ['key'=>'tenant_username', 'label'=>'사용자명', 'required'=>true, 'type'=>'string', 'unique'=>true],
            ['key'=>'hospital_phone', 'label'=>'전화번호', 'required'=>false, 'type'=>'phone'],
            ['key'=>'hospital_email', 'label'=>'이메일', 'required'=>false, 'type'=>'email'],
            ['key'=>'address', 'label'=>'주소', 'required'=>false, 'type'=>'string'],
            ['key'=>'category', 'label'=>'카테고리', 'required'=>false, 'type'=>'fk', 'fk_table'=>'hospital_type', 'fk_col'=>'name'],
        ],
        'conflict_keys' => ['hospital_name'],
        'similar_keys' => ['hospital_name'],
        'owner_type' => null,
        'role_id' => null,
    ],
    'doctor' => [
        'label' => '의사',
        'icon' => 'bi-person-badge',
        'table' => 'users',
        'sub_table' => 'doctors',
        'columns' => [
            ['key'=>'first_name', 'label'=>'이름(First)', 'required'=>true, 'type'=>'string'],
            ['key'=>'last_name', 'label'=>'성(Last)', 'required'=>true, 'type'=>'string'],
            ['key'=>'email', 'label'=>'이메일', 'required'=>true, 'type'=>'email', 'unique'=>true],
            ['key'=>'phone', 'label'=>'전화번호', 'required'=>true, 'type'=>'phone'],
            ['key'=>'gender', 'label'=>'성별', 'required'=>true, 'type'=>'code', 'codes'=>['남성'=>1,'여성'=>2,'Male'=>1,'Female'=>2]],
            ['key'=>'department', 'label'=>'진료과', 'required'=>true, 'type'=>'fk', 'fk_table'=>'doctor_departments', 'fk_col'=>'title'],
            ['key'=>'specialist', 'label'=>'전문분야', 'required'=>true, 'type'=>'string'],
            ['key'=>'qualification', 'label'=>'자격/학력', 'required'=>false, 'type'=>'string'],
            ['key'=>'charge', 'label'=>'진료비', 'required'=>false, 'type'=>'number'],
            ['key'=>'hospital', 'label'=>'소속병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
            ['key'=>'dob', 'label'=>'생년월일', 'required'=>false, 'type'=>'date'],
            ['key'=>'blood_group', 'label'=>'혈액형', 'required'=>false, 'type'=>'code', 'codes'=>['A+'=>'A+','A-'=>'A-','B+'=>'B+','B-'=>'B-','O+'=>'O+','O-'=>'O-','AB+'=>'AB+','AB-'=>'AB-']],
            ['key'=>'description', 'label'=>'소개', 'required'=>false, 'type'=>'text'],
        ],
        'conflict_keys' => ['email'],
        'similar_keys' => ['first_name', 'phone'],
        'owner_type' => 'App\\Models\\Doctor',
        'role_id' => 2,
    ],
    'nurse' => [
        'label' => '간호사',
        'icon' => 'bi-heart-pulse',
        'table' => 'users',
        'sub_table' => 'nurses',
        'columns' => [
            ['key'=>'first_name', 'label'=>'이름(First)', 'required'=>true, 'type'=>'string'],
            ['key'=>'last_name', 'label'=>'성(Last)', 'required'=>true, 'type'=>'string'],
            ['key'=>'email', 'label'=>'이메일', 'required'=>true, 'type'=>'email', 'unique'=>true],
            ['key'=>'phone', 'label'=>'전화번호', 'required'=>true, 'type'=>'phone'],
            ['key'=>'gender', 'label'=>'성별', 'required'=>true, 'type'=>'code', 'codes'=>['남성'=>1,'여성'=>2,'Male'=>1,'Female'=>2]],
            ['key'=>'qualification', 'label'=>'자격/학력', 'required'=>false, 'type'=>'string'],
            ['key'=>'hospital', 'label'=>'소속병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
            ['key'=>'dob', 'label'=>'생년월일', 'required'=>false, 'type'=>'date'],
            ['key'=>'blood_group', 'label'=>'혈액형', 'required'=>false, 'type'=>'code', 'codes'=>['A+'=>'A+','A-'=>'A-','B+'=>'B+','B-'=>'B-','O+'=>'O+','O-'=>'O-','AB+'=>'AB+','AB-'=>'AB-']],
            ['key'=>'description', 'label'=>'소개', 'required'=>false, 'type'=>'text'],
        ],
        'conflict_keys' => ['email'],
        'similar_keys' => ['first_name', 'phone'],
        'owner_type' => 'App\\Models\\Nurse',
        'role_id' => 4,
    ],
    'pharmacist' => [
        'label' => '약사',
        'icon' => 'bi-capsule',
        'table' => 'users',
        'sub_table' => 'pharmacists',
        'columns' => [
            ['key'=>'first_name', 'label'=>'이름(First)', 'required'=>true, 'type'=>'string'],
            ['key'=>'last_name', 'label'=>'성(Last)', 'required'=>true, 'type'=>'string'],
            ['key'=>'email', 'label'=>'이메일', 'required'=>true, 'type'=>'email', 'unique'=>true],
            ['key'=>'phone', 'label'=>'전화번호', 'required'=>true, 'type'=>'phone'],
            ['key'=>'gender', 'label'=>'성별', 'required'=>true, 'type'=>'code', 'codes'=>['남성'=>1,'여성'=>2,'Male'=>1,'Female'=>2]],
            ['key'=>'license_no', 'label'=>'면허번호', 'required'=>false, 'type'=>'string'],
            ['key'=>'qualification', 'label'=>'자격/학력', 'required'=>false, 'type'=>'string'],
            ['key'=>'hospital', 'label'=>'소속병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
            ['key'=>'dob', 'label'=>'생년월일', 'required'=>false, 'type'=>'date'],
            ['key'=>'blood_group', 'label'=>'혈액형', 'required'=>false, 'type'=>'code', 'codes'=>['A+'=>'A+','A-'=>'A-','B+'=>'B+','B-'=>'B-','O+'=>'O+','O-'=>'O-','AB+'=>'AB+','AB-'=>'AB-']],
            ['key'=>'description', 'label'=>'소개', 'required'=>false, 'type'=>'text'],
        ],
        'conflict_keys' => ['email'],
        'similar_keys' => ['first_name', 'phone'],
        'owner_type' => 'App\\Models\\Pharmacist',
        'role_id' => 7,
    ],
    'patient' => [
        'label' => '환자',
        'icon' => 'bi-person-heart',
        'table' => 'users',
        'sub_table' => 'patients',
        'columns' => [
            ['key'=>'first_name', 'label'=>'이름(First)', 'required'=>true, 'type'=>'string'],
            ['key'=>'last_name', 'label'=>'성(Last)', 'required'=>true, 'type'=>'string'],
            ['key'=>'email', 'label'=>'이메일', 'required'=>true, 'type'=>'email', 'unique'=>true],
            ['key'=>'phone', 'label'=>'전화번호', 'required'=>true, 'type'=>'phone'],
            ['key'=>'gender', 'label'=>'성별', 'required'=>true, 'type'=>'code', 'codes'=>['남성'=>1,'여성'=>2,'Male'=>1,'Female'=>2]],
            ['key'=>'dob', 'label'=>'생년월일', 'required'=>false, 'type'=>'date'],
            ['key'=>'blood_group', 'label'=>'혈액형', 'required'=>false, 'type'=>'code', 'codes'=>['A+'=>'A+','A-'=>'A-','B+'=>'B+','B-'=>'B-','O+'=>'O+','O-'=>'O-','AB+'=>'AB+','AB-'=>'AB-']],
            ['key'=>'hospital', 'label'=>'소속병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
            ['key'=>'insurance', 'label'=>'보험정보', 'required'=>false, 'type'=>'string'],
            ['key'=>'allergies', 'label'=>'알레르기', 'required'=>false, 'type'=>'text'],
            ['key'=>'emergency_contact', 'label'=>'긴급연락처', 'required'=>false, 'type'=>'phone'],
            ['key'=>'emergency_name', 'label'=>'긴급연락 이름', 'required'=>false, 'type'=>'string'],
            ['key'=>'address', 'label'=>'주소', 'required'=>false, 'type'=>'string'],
        ],
        'conflict_keys' => ['email'],
        'similar_keys' => ['first_name', 'phone'],
        'owner_type' => 'App\\Models\\Patient',
        'role_id' => 5,
    ],
    'staff' => [
        'label' => '직원',
        'icon' => 'bi-people',
        'table' => 'users',
        'sub_table' => null,
        'columns' => [
            ['key'=>'first_name', 'label'=>'이름(First)', 'required'=>true, 'type'=>'string'],
            ['key'=>'last_name', 'label'=>'성(Last)', 'required'=>true, 'type'=>'string'],
            ['key'=>'email', 'label'=>'이메일', 'required'=>true, 'type'=>'email', 'unique'=>true],
            ['key'=>'phone', 'label'=>'전화번호', 'required'=>true, 'type'=>'phone'],
            ['key'=>'gender', 'label'=>'성별', 'required'=>true, 'type'=>'code', 'codes'=>['남성'=>1,'여성'=>2,'Male'=>1,'Female'=>2]],
            ['key'=>'staff_type', 'label'=>'직종', 'required'=>true, 'type'=>'code', 'codes'=>[
                '접수'=>'Receptionist','회계'=>'Accountant','Lab기사'=>'LabTechnician','사례관리사'=>'CaseHandler',
                'Receptionist'=>'Receptionist','Accountant'=>'Accountant','LabTechnician'=>'LabTechnician','CaseHandler'=>'CaseHandler'
            ]],
            ['key'=>'qualification', 'label'=>'자격/학력', 'required'=>false, 'type'=>'string'],
            ['key'=>'hospital', 'label'=>'소속병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
            ['key'=>'designation', 'label'=>'직위', 'required'=>false, 'type'=>'string'],
            ['key'=>'dob', 'label'=>'생년월일', 'required'=>false, 'type'=>'date'],
        ],
        'conflict_keys' => ['email'],
        'similar_keys' => ['first_name', 'phone'],
        'owner_type' => null, // staff_type에 따라 결정
        'role_id' => null,
    ],
    'medicine' => [
        'label' => '의약품',
        'icon' => 'bi-prescription2',
        'table' => 'medicines',
        'columns' => [
            ['key'=>'name', 'label'=>'약품명', 'required'=>true, 'type'=>'string'],
            ['key'=>'category', 'label'=>'카테고리', 'required'=>false, 'type'=>'fk', 'fk_table'=>'medicine_categories', 'fk_col'=>'name'],
            ['key'=>'brand', 'label'=>'브랜드', 'required'=>false, 'type'=>'fk', 'fk_table'=>'medicine_brands', 'fk_col'=>'name'],
            ['key'=>'selling_price', 'label'=>'판매가', 'required'=>true, 'type'=>'number'],
            ['key'=>'buying_price', 'label'=>'매입가', 'required'=>true, 'type'=>'number'],
            ['key'=>'quantity', 'label'=>'수량', 'required'=>true, 'type'=>'integer'],
            ['key'=>'salt_composition', 'label'=>'성분', 'required'=>true, 'type'=>'string'],
            ['key'=>'description', 'label'=>'설명', 'required'=>false, 'type'=>'text'],
            ['key'=>'side_effects', 'label'=>'부작용', 'required'=>false, 'type'=>'text'],
            ['key'=>'hospital', 'label'=>'소속병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
        ],
        'conflict_keys' => ['name'],
        'similar_keys' => ['name', 'salt_composition'],
        'owner_type' => null,
        'role_id' => null,
    ],
    'appointment' => [
        'label' => '예약',
        'icon' => 'bi-calendar-check',
        'table' => 'appointments',
        'columns' => [
            ['key'=>'patient_email', 'label'=>'환자 이메일', 'required'=>true, 'type'=>'fk', 'fk_table'=>'users', 'fk_col'=>'email', 'fk_where'=>"owner_type='App\\\\Models\\\\Patient'"],
            ['key'=>'doctor_email', 'label'=>'의사 이메일', 'required'=>true, 'type'=>'fk', 'fk_table'=>'users', 'fk_col'=>'email', 'fk_where'=>"owner_type='App\\\\Models\\\\Doctor'"],
            ['key'=>'opd_date', 'label'=>'예약일시', 'required'=>true, 'type'=>'datetime'],
            ['key'=>'problem', 'label'=>'증상/사유', 'required'=>false, 'type'=>'text'],
            ['key'=>'hospital', 'label'=>'병원', 'required'=>false, 'type'=>'fk', 'fk_table'=>'tenants', 'fk_col'=>'hospital_name'],
        ],
        'conflict_keys' => [],
        'similar_keys' => [],
        'owner_type' => null,
        'role_id' => null,
    ],
    'ad' => [
        'label' => '광고',
        'icon' => 'bi-megaphone',
        'table' => 'gangnamunni_ads',
        'columns' => [
            ['key'=>'title', 'label'=>'광고 제목', 'required'=>true, 'type'=>'string'],
            ['key'=>'subtitle', 'label'=>'부제목', 'required'=>false, 'type'=>'string'],
            ['key'=>'link_url', 'label'=>'링크 URL', 'required'=>false, 'type'=>'url'],
            ['key'=>'ad_type', 'label'=>'광고유형', 'required'=>true, 'type'=>'code', 'codes'=>['배너'=>'banner','팝업'=>'popup','피드'=>'feed','banner'=>'banner','popup'=>'popup','feed'=>'feed']],
            ['key'=>'display_order', 'label'=>'노출순서', 'required'=>false, 'type'=>'integer'],
            ['key'=>'is_active', 'label'=>'활성여부', 'required'=>false, 'type'=>'code', 'codes'=>['예'=>1,'아니오'=>0,'Y'=>1,'N'=>0,'1'=>1,'0'=>0]],
        ],
        'conflict_keys' => ['title'],
        'similar_keys' => ['title'],
        'owner_type' => null,
        'role_id' => null,
    ],
];

// ── 역할ID 매핑 ──
$STAFF_ROLE_MAP = [
    'Receptionist' => 3,
    'Accountant' => 6,
    'LabTechnician' => 8,
    'CaseHandler' => 9,
];
$STAFF_OWNER_MAP = [
    'Receptionist' => 'App\\Models\\Receptionist',
    'Accountant' => 'App\\Models\\Accountant',
    'LabTechnician' => 'App\\Models\\LabTechnician',
    'CaseHandler' => 'App\\Models\\CaseHandler',
];

// ────────────────────────────────────────────────────
// Import 기능
// ────────────────────────────────────────────────────

/**
 * 엑셀/CSV 파일 파싱 → 2D 배열 반환
 * @param string $filePath 파일 경로
 * @return array ['headers'=>[], 'rows'=>[]] 또는 ['error'=>'...']
 */
function parseExcelFile($filePath) {
    global $HAS_PHPSPREADSHEET;
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        return parseCsvFile($filePath);
    }

    if ($ext !== 'xlsx' && $ext !== 'xls') {
        return ['error' => '지원하지 않는 형식입니다. .xlsx 또는 .csv 파일만 가능합니다.'];
    }

    if ($HAS_PHPSPREADSHEET) {
        return parseWithPhpSpreadsheet($filePath);
    }

    // fallback: ZIP+XML 직접 파싱
    return parseXlsxNative($filePath);
}

/**
 * PhpSpreadsheet를 이용한 파싱
 */
function parseWithPhpSpreadsheet($filePath) {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (count($data) < 2) {
            return ['error' => '데이터가 없습니다. 헤더 행 + 최소 1개 데이터 행이 필요합니다.'];
        }

        $headers = array_map('trim', $data[0]);
        $rows = [];
        for ($i = 1; $i < count($data); $i++) {
            $row = [];
            $isEmpty = true;
            foreach ($data[$i] as $ci => $val) {
                $key = $headers[$ci] ?? "col_{$ci}";
                $val = trim((string)$val);
                $row[$key] = $val;
                if ($val !== '') $isEmpty = false;
            }
            if (!$isEmpty) $rows[] = $row;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['headers' => $headers, 'rows' => $rows, 'total' => count($rows)];
    } catch (\Exception $e) {
        return ['error' => '엑셀 파싱 실패: ' . $e->getMessage()];
    }
}

/**
 * ZIP+XML로 .xlsx 직접 파싱 (PhpSpreadsheet 없을 때 fallback)
 */
function parseXlsxNative($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['error' => 'XLSX 파일을 열 수 없습니다.'];
    }

    // 공유 문자열 읽기
    $strings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssDom = new DOMDocument();
        $ssDom->loadXML($ssXml);
        $sis = $ssDom->getElementsByTagName('si');
        foreach ($sis as $si) {
            $texts = $si->getElementsByTagName('t');
            $val = '';
            foreach ($texts as $t) $val .= $t->textContent;
            $strings[] = $val;
        }
    }

    // 시트1 읽기
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        $zip->close();
        return ['error' => '시트를 찾을 수 없습니다.'];
    }

    $sheetDom = new DOMDocument();
    $sheetDom->loadXML($sheetXml);
    $rowElements = $sheetDom->getElementsByTagName('row');

    $allRows = [];
    foreach ($rowElements as $rowEl) {
        $rowData = [];
        $cells = $rowEl->getElementsByTagName('c');
        foreach ($cells as $cell) {
            $ref = $cell->getAttribute('r');
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $colLetter = $m[1] ?? 'A';
            $colIndex = _letterToIndex($colLetter);

            $type = $cell->getAttribute('t');
            $vNodes = $cell->getElementsByTagName('v');
            $value = ($vNodes->length > 0) ? $vNodes->item(0)->textContent : '';

            if ($type === 's' && isset($strings[(int)$value])) {
                $value = $strings[(int)$value];
            }

            // 배열 크기 확장
            while (count($rowData) <= $colIndex) $rowData[] = '';
            $rowData[$colIndex] = $value;
        }
        $allRows[] = $rowData;
    }
    $zip->close();

    if (count($allRows) < 2) {
        return ['error' => '데이터가 없습니다. 헤더 행 + 최소 1개 데이터 행이 필요합니다.'];
    }

    $headers = array_map('trim', $allRows[0]);
    $rows = [];
    for ($i = 1; $i < count($allRows); $i++) {
        $row = [];
        $isEmpty = true;
        foreach ($allRows[$i] as $ci => $val) {
            $key = $headers[$ci] ?? "col_{$ci}";
            $val = trim((string)$val);
            $row[$key] = $val;
            if ($val !== '') $isEmpty = false;
        }
        if (!$isEmpty) $rows[] = $row;
    }

    return ['headers' => $headers, 'rows' => $rows, 'total' => count($rows)];
}

/**
 * CSV 파일 파싱
 */
function parseCsvFile($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) return ['error' => 'CSV 파일을 열 수 없습니다.'];

    // BOM 제거
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // 구분자 자동 감지
    $firstLine = fgets($handle);
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);

    $delimiter = ',';
    if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) $delimiter = "\t";
    elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) $delimiter = ';';

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) { fclose($handle); return ['error' => '헤더 행을 읽을 수 없습니다.']; }
    $headers = array_map('trim', $headers);

    $rows = [];
    while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row = [];
        $isEmpty = true;
        foreach ($line as $ci => $val) {
            $key = $headers[$ci] ?? "col_{$ci}";
            $val = trim((string)$val);
            $row[$key] = $val;
            if ($val !== '') $isEmpty = false;
        }
        if (!$isEmpty) $rows[] = $row;
    }
    fclose($handle);

    return ['headers' => $headers, 'rows' => $rows, 'total' => count($rows)];
}

/**
 * 엑셀 열 문자 → 0-based 인덱스 (A=0, B=1, ..., Z=25, AA=26, ...)
 */
function _letterToIndex($letters) {
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}


/**
 * 헤더 매핑: 엑셀 헤더 → 엔티티 key 매핑
 * @param array $excelHeaders 엑셀 파일의 헤더 배열
 * @param string $entityType 엔티티 유형
 * @return array [excelHeader => entityKey, ...]
 */
function mapHeaders($excelHeaders, $entityType) {
    global $ENTITY_DEFS;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return [];

    $mapping = [];
    foreach ($excelHeaders as $header) {
        $header = trim($header);
        $matched = false;
        foreach ($def['columns'] as $col) {
            // key 또는 label로 매칭
            if ($header === $col['key'] || $header === $col['label'] || mb_strtolower($header) === mb_strtolower($col['label'])) {
                $mapping[$header] = $col['key'];
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $mapping[$header] = null; // 매핑 불가
        }
    }
    return $mapping;
}

/**
 * 행 데이터에 매핑 적용
 * @param array $rows 원본 행 배열
 * @param array $headerMapping 헤더 매핑
 * @return array 매핑된 행 배열
 */
function applyMapping($rows, $headerMapping) {
    $mapped = [];
    foreach ($rows as $row) {
        $newRow = [];
        foreach ($row as $header => $val) {
            $key = $headerMapping[$header] ?? null;
            if ($key !== null) {
                $newRow[$key] = $val;
            }
        }
        $mapped[] = $newRow;
    }
    return $mapped;
}


/**
 * 유효성 검사
 * @param array $rows 매핑된 행 배열
 * @param string $entityType 엔티티 유형
 * @return array 행별 에러 배열 [rowIndex => [errors]]
 */
function validateRows($rows, $entityType) {
    global $ENTITY_DEFS;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return [];

    $results = [];
    foreach ($rows as $i => $row) {
        $errors = [];
        foreach ($def['columns'] as $col) {
            $val = trim($row[$col['key']] ?? '');

            // 필수 필드 체크
            if ($col['required'] && $val === '') {
                $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '필수 항목 누락'];
                continue;
            }
            if ($val === '') continue;

            // 타입별 검증
            switch ($col['type']) {
                case 'email':
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '유효하지 않은 이메일'];
                    }
                    break;
                case 'phone':
                    $cleaned = preg_replace('/[\s\-\(\)]/', '', $val);
                    if (!preg_match('/^[\+]?\d{8,15}$/', $cleaned)) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '유효하지 않은 전화번호'];
                    }
                    break;
                case 'number':
                    if (!is_numeric($val)) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '숫자만 입력 가능'];
                    }
                    break;
                case 'integer':
                    if (!ctype_digit(ltrim($val, '-'))) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '정수만 입력 가능'];
                    }
                    break;
                case 'date':
                    if (!preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $val)) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '날짜 형식 오류 (YYYY-MM-DD)'];
                    }
                    break;
                case 'datetime':
                    if (!preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}[\sT]\d{1,2}:\d{2}/', $val)) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '일시 형식 오류 (YYYY-MM-DD HH:MM)'];
                    }
                    break;
                case 'code':
                    if (!isset($col['codes'][$val]) && !in_array($val, array_values($col['codes']))) {
                        $allowed = implode(', ', array_keys($col['codes']));
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => "허용값: {$allowed}"];
                    }
                    break;
                case 'url':
                    if (!filter_var($val, FILTER_VALIDATE_URL) && !preg_match('/^\//', $val)) {
                        $errors[] = ['field' => $col['key'], 'label' => $col['label'], 'msg' => '유효하지 않은 URL'];
                    }
                    break;
            }
        }
        $results[$i] = $errors;
    }
    return $results;
}


/**
 * DB 충돌 검사 (3종: 완전중복, 부분일치, FK불일치)
 * @param array $rows 매핑된 행 배열
 * @param string $entityType 엔티티 유형
 * @param mysqli $connect DB 연결
 * @param int $offset batch offset
 * @return array 행별 결과 [index => {status, details, row}]
 */
function checkConflicts($rows, $entityType, $connect, $offset = 0) {
    global $ENTITY_DEFS;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return [];

    $esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };
    $results = [];

    foreach ($rows as $i => $row) {
        $rowIndex = $offset + $i;
        $status = 'ok';
        $details = [];

        // 1) 유효성 에러 (필수 필드 체크)
        foreach ($def['columns'] as $col) {
            if ($col['required'] && empty(trim($row[$col['key']] ?? ''))) {
                $status = 'error';
                $details[] = ['type' => 'required', 'field' => $col['key'], 'label' => $col['label'], 'msg' => $col['label'] . ' 필수'];
            }
        }

        // 에러가 있으면 DB 검사 스킵
        if ($status === 'error') {
            $results[$rowIndex] = ['status' => $status, 'details' => $details, 'row' => $row];
            continue;
        }

        // 2) 완전 중복 (유니크 키 매칭)
        foreach ($def['conflict_keys'] as $key) {
            $val = $esc(trim($row[$key] ?? ''));
            if ($val === '') continue;

            $table = $def['table'];
            if (strpos($table, ' ') !== false) $table = 'users'; // JOIN 구문이면 users 사용

            if ($key === 'hospital_name') {
                $sql = "SELECT id, hospital_name AS display_name FROM tenants WHERE hospital_name = '{$val}' LIMIT 1";
            } elseif ($key === 'email') {
                $ownerWhere = '';
                if (!empty($def['owner_type'])) {
                    $ownerType = $esc($def['owner_type']);
                    $ownerWhere = " AND owner_type = '{$ownerType}'";
                }
                $sql = "SELECT id, CONCAT(first_name, ' ', COALESCE(last_name,'')) AS display_name FROM users WHERE email = '{$val}'{$ownerWhere} LIMIT 1";
            } elseif ($key === 'name' && $entityType === 'medicine') {
                $sql = "SELECT id, name AS display_name FROM medicines WHERE name = '{$val}' LIMIT 1";
            } elseif ($key === 'title' && $entityType === 'ad') {
                $sql = "SELECT id, title AS display_name FROM gangnamunni_ads WHERE title = '{$val}' LIMIT 1";
            } else {
                $sql = "SELECT id FROM {$table} WHERE `{$key}` = '{$val}' LIMIT 1";
            }

            $res = mysqli_query($connect, $sql);
            if ($res && mysqli_num_rows($res) > 0) {
                $existing = mysqli_fetch_assoc($res);
                $status = 'conflict_exact';
                $details[] = [
                    'type' => 'exact',
                    'field' => $key,
                    'value' => $row[$key],
                    'existing_id' => $existing['id'],
                    'existing_name' => $existing['display_name'] ?? $existing['id'],
                ];
            }
        }

        // 3) 부분 일치 (이름+전화 유사도)
        if ($status === 'ok' && !empty($def['similar_keys'])) {
            $conditions = [];
            foreach ($def['similar_keys'] as $sk) {
                $sv = $esc(trim($row[$sk] ?? ''));
                if ($sv === '') continue;
                if ($sk === 'first_name' || $sk === 'hospital_name' || $sk === 'name' || $sk === 'title') {
                    $conditions[] = "`{$sk}` LIKE '%{$sv}%'";
                } elseif ($sk === 'phone') {
                    $cleanPhone = preg_replace('/[^0-9]/', '', $sv);
                    if (strlen($cleanPhone) >= 4) {
                        $last4 = substr($cleanPhone, -4);
                        $conditions[] = "REPLACE(REPLACE(phone, '-', ''), ' ', '') LIKE '%{$last4}%'";
                    }
                } else {
                    $conditions[] = "`{$sk}` LIKE '%{$sv}%'";
                }
            }

            if (!empty($conditions)) {
                $table = $def['table'];
                if (strpos($table, ' ') !== false) $table = 'users';

                $whereExtra = '';
                if (!empty($def['owner_type'])) {
                    $ownerType = $esc($def['owner_type']);
                    $whereExtra = " AND owner_type = '{$ownerType}'";
                }
                if ($entityType === 'medicine') $table = 'medicines';
                if ($entityType === 'ad') $table = 'gangnamunni_ads';
                if ($entityType === 'hospital') $table = 'tenants';

                $sqlWhere = '(' . implode(' AND ', $conditions) . ')' . $whereExtra;
                $sql = "SELECT id FROM {$table} WHERE {$sqlWhere} LIMIT 3";
                $res = mysqli_query($connect, $sql);
                if ($res && mysqli_num_rows($res) > 0) {
                    $status = 'conflict_similar';
                    while ($sRow = mysqli_fetch_assoc($res)) {
                        $details[] = [
                            'type' => 'similar',
                            'existing_id' => $sRow['id'],
                            'msg' => '유사 데이터 발견 (이름/전화 일치)',
                        ];
                    }
                }
            }
        }

        // 4) FK 유효성 (외래키 존재 확인)
        foreach ($def['columns'] as $col) {
            if ($col['type'] !== 'fk') continue;
            $fkVal = trim($row[$col['key']] ?? '');
            if ($fkVal === '') continue;

            $fkTable = $col['fk_table'];
            $fkCol = $col['fk_col'];
            $fkEsc = $esc($fkVal);
            $extraWhere = isset($col['fk_where']) ? ' AND ' . $col['fk_where'] : '';

            $sql = "SELECT id FROM `{$fkTable}` WHERE `{$fkCol}` = '{$fkEsc}'{$extraWhere} LIMIT 1";
            $res = mysqli_query($connect, $sql);
            if (!$res || mysqli_num_rows($res) === 0) {
                // 유사 검색으로 추천
                $sql2 = "SELECT id, `{$fkCol}` AS val FROM `{$fkTable}` WHERE `{$fkCol}` LIKE '%{$fkEsc}%'{$extraWhere} LIMIT 5";
                $res2 = mysqli_query($connect, $sql2);
                $suggestions = [];
                if ($res2) {
                    while ($sr = mysqli_fetch_assoc($res2)) {
                        $suggestions[] = $sr['val'];
                    }
                }
                if ($status === 'ok') $status = 'fk_missing';
                $details[] = [
                    'type' => 'fk',
                    'field' => $col['key'],
                    'label' => $col['label'],
                    'value' => $fkVal,
                    'fk_table' => $fkTable,
                    'suggestions' => $suggestions,
                ];
            }
        }

        $results[$rowIndex] = ['status' => $status, 'details' => $details, 'row' => $row];
    }

    return $results;
}


/**
 * 행 데이터를 DB에 실제 INSERT/UPDATE
 * @param array $rows 매핑된 행 배열
 * @param string $entityType 엔티티 유형
 * @param array $decisions 행별 처리 결정 [index => 'insert'|'update'|'skip'|'merge']
 * @param mysqli $connect DB 연결
 * @return array 결과 배열
 */
function importRows($rows, $entityType, $decisions, $connect) {
    global $ENTITY_DEFS, $STAFF_ROLE_MAP, $STAFF_OWNER_MAP;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return ['error' => '알 수 없는 엔티티 유형'];

    $esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };
    $results = [];

    foreach ($rows as $i => $row) {
        $decision = $decisions[$i] ?? 'insert';
        if ($decision === 'skip') {
            $results[$i] = ['status' => 'skipped', 'msg' => '건너뜀'];
            continue;
        }

        mysqli_begin_transaction($connect);
        try {
            switch ($entityType) {
                case 'hospital':
                    $results[$i] = _importHospital($row, $decision, $connect, $esc);
                    break;
                case 'doctor':
                    $results[$i] = _importDoctor($row, $decision, $connect, $esc, $def);
                    break;
                case 'nurse':
                    $results[$i] = _importNurse($row, $decision, $connect, $esc, $def);
                    break;
                case 'pharmacist':
                    $results[$i] = _importPharmacist($row, $decision, $connect, $esc, $def);
                    break;
                case 'patient':
                    $results[$i] = _importPatient($row, $decision, $connect, $esc, $def);
                    break;
                case 'staff':
                    $results[$i] = _importStaff($row, $decision, $connect, $esc, $def);
                    break;
                case 'medicine':
                    $results[$i] = _importMedicine($row, $decision, $connect, $esc);
                    break;
                case 'appointment':
                    $results[$i] = _importAppointment($row, $decision, $connect, $esc);
                    break;
                case 'ad':
                    $results[$i] = _importAd($row, $decision, $connect, $esc);
                    break;
                default:
                    throw new Exception('미지원 엔티티');
            }
            mysqli_commit($connect);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $results[$i] = ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }
    return $results;
}

// ── 엔티티별 Import 구현 ──

function _resolveTenantId($hospitalName, $connect, $esc) {
    if (empty($hospitalName)) return null;
    $name = $esc(trim($hospitalName));
    $r = mysqli_query($connect, "SELECT id FROM tenants WHERE hospital_name = '{$name}' LIMIT 1");
    if ($r && ($row = mysqli_fetch_assoc($r))) return $row['id'];
    return null;
}

function _resolveFkId($table, $col, $val, $connect, $esc, $extraWhere = '') {
    if (empty($val)) return null;
    $v = $esc(trim($val));
    $sql = "SELECT id FROM `{$table}` WHERE `{$col}` = '{$v}'{$extraWhere} LIMIT 1";
    $r = mysqli_query($connect, $sql);
    if ($r && ($row = mysqli_fetch_assoc($r))) return $row['id'];
    return null;
}

function _resolveGender($val) {
    $map = ['남성'=>1,'여성'=>2,'Male'=>1,'Female'=>2,'남'=>1,'여'=>2,'M'=>1,'F'=>2,'1'=>1,'2'=>2];
    return $map[$val] ?? 1;
}

function _resolveCode($val, $codes) {
    if (isset($codes[$val])) return $codes[$val];
    if (in_array($val, array_values($codes))) return $val;
    return $val;
}

function _importHospital($row, $decision, $connect, $esc) {
    $name = $esc(trim($row['hospital_name'] ?? ''));
    $username = $esc(trim($row['tenant_username'] ?? ''));
    if (!$name) throw new Exception('병원명 필수');
    if (!$username) $username = $esc(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . '_' . time());

    if ($decision === 'update') {
        $sql = "UPDATE tenants SET hospital_name='{$name}' WHERE hospital_name='{$name}'";
        mysqli_query($connect, $sql);
        return ['status' => 'updated', 'msg' => "병원 '{$name}' 업데이트"];
    }

    $id = uniqid();
    $sql = "INSERT INTO tenants (id, tenant_username, hospital_name, created_at, updated_at) VALUES ('{$id}', '{$username}', '{$name}', NOW(), NOW())";
    if (!mysqli_query($connect, $sql)) throw new Exception('병원 INSERT 실패: ' . mysqli_error($connect));
    return ['status' => 'inserted', 'msg' => "병원 '{$name}' 등록 완료", 'id' => $id];
}

function _importUserBased($row, $decision, $connect, $esc, $def, $subTable, $ownerType, $roleId, $extraInsert = []) {
    $firstName = $esc(trim($row['first_name'] ?? ''));
    $lastName  = $esc(trim($row['last_name'] ?? ''));
    $email     = $esc(trim($row['email'] ?? ''));
    $phone     = $esc(trim($row['phone'] ?? ''));
    $gender    = _resolveGender($row['gender'] ?? '');
    $dob       = $esc(trim($row['dob'] ?? ''));
    $bloodGroup = $esc(trim($row['blood_group'] ?? ''));
    $qualification = $esc(trim($row['qualification'] ?? ''));
    $tenantId  = _resolveTenantId($row['hospital'] ?? '', $connect, $esc);
    $password  = password_hash('password123', PASSWORD_BCRYPT);

    $ownerTypeEsc = str_replace('\\', '\\\\', $ownerType);

    if ($decision === 'update') {
        // 기존 사용자 찾기
        $r = mysqli_query($connect, "SELECT id FROM users WHERE email='{$email}' LIMIT 1");
        if (!$r || mysqli_num_rows($r) === 0) throw new Exception("이메일 '{$email}'에 해당하는 사용자 없음");
        $userId = mysqli_fetch_assoc($r)['id'];

        $sql = "UPDATE users SET first_name='{$firstName}', last_name='{$lastName}', phone='{$phone}', gender={$gender}";
        if ($dob) $sql .= ", dob='{$dob}'";
        if ($bloodGroup) $sql .= ", blood_group='{$bloodGroup}'";
        if ($qualification) $sql .= ", qualification='{$qualification}'";
        if ($tenantId) $sql .= ", tenant_id='{$tenantId}'";
        $sql .= ", updated_at=NOW() WHERE id={$userId}";
        mysqli_query($connect, $sql);
        return ['status' => 'updated', 'msg' => "{$firstName} {$lastName} 업데이트", 'user_id' => $userId];
    }

    // INSERT users
    $deptId = 2; // 기본 department_id
    $sql = "INSERT INTO users (department_id, first_name, last_name, email, password, phone, gender, status, qualification, dob, blood_group, owner_type, tenant_id, created_at, updated_at)
            VALUES ({$deptId}, '{$firstName}', '{$lastName}', '{$email}', '{$password}', '{$phone}', {$gender}, 1, '{$qualification}', "
            . ($dob ? "'{$dob}'" : "NULL") . ", '{$bloodGroup}', '{$ownerTypeEsc}', "
            . ($tenantId ? "'{$tenantId}'" : "NULL") . ", NOW(), NOW())";
    if (!mysqli_query($connect, $sql)) throw new Exception('users INSERT 실패: ' . mysqli_error($connect));
    $userId = mysqli_insert_id($connect);

    // INSERT sub table
    $subId = null;
    if ($subTable) {
        $subCols = "user_id, tenant_id, created_at, updated_at";
        $subVals = "{$userId}, " . ($tenantId ? "'{$tenantId}'" : "NULL") . ", NOW(), NOW()";

        foreach ($extraInsert as $ck => $cv) {
            $subCols .= ", {$ck}";
            $subVals .= ", {$cv}";
        }

        $sql = "INSERT INTO {$subTable} ({$subCols}) VALUES ({$subVals})";
        if (!mysqli_query($connect, $sql)) throw new Exception("{$subTable} INSERT 실패: " . mysqli_error($connect));
        $subId = mysqli_insert_id($connect);

        // owner_id 업데이트
        mysqli_query($connect, "UPDATE users SET owner_id={$subId} WHERE id={$userId}");
    }

    // model_has_roles
    if ($roleId) {
        $modelType = str_replace('\\', '\\\\', 'App\\Models\\User');
        mysqli_query($connect, "INSERT INTO model_has_roles (role_id, model_type, model_id) VALUES ({$roleId}, '{$modelType}', {$userId})");
    }

    return ['status' => 'inserted', 'msg' => "{$firstName} {$lastName} 등록 완료", 'user_id' => $userId, 'sub_id' => $subId];
}

function _importDoctor($row, $decision, $connect, $esc, $def) {
    $deptId = _resolveFkId('doctor_departments', 'title', $row['department'] ?? '', $connect, $esc) ?? 0;
    $specialist = $esc(trim($row['specialist'] ?? ''));
    $charge = floatval($row['charge'] ?? 0);
    $description = $esc(trim($row['description'] ?? ''));

    return _importUserBased($row, $decision, $connect, $esc, $def, 'doctors', 'App\\Models\\Doctor', 2, [
        'doctor_department_id' => $deptId,
        'specialist' => "'{$specialist}'",
        'appointment_charge' => $charge,
        'description' => "'{$description}'",
    ]);
}

function _importNurse($row, $decision, $connect, $esc, $def) {
    $qualification = $esc(trim($row['qualification'] ?? ''));
    $description = $esc(trim($row['description'] ?? ''));

    return _importUserBased($row, $decision, $connect, $esc, $def, 'nurses', 'App\\Models\\Nurse', 4, [
        'qualification' => "'{$qualification}'",
        'description' => "'{$description}'",
    ]);
}

function _importPharmacist($row, $decision, $connect, $esc, $def) {
    $licenseNo = $esc(trim($row['license_no'] ?? ''));
    $qualification = $esc(trim($row['qualification'] ?? ''));
    $description = $esc(trim($row['description'] ?? ''));

    return _importUserBased($row, $decision, $connect, $esc, $def, 'pharmacists', 'App\\Models\\Pharmacist', 7, [
        'license_no' => "'{$licenseNo}'",
        'qualification' => "'{$qualification}'",
        'description' => "'{$description}'",
    ]);
}

function _importPatient($row, $decision, $connect, $esc, $def) {
    $insurance = $esc(trim($row['insurance'] ?? ''));
    $allergies = $esc(trim($row['allergies'] ?? ''));
    $emergencyContact = $esc(trim($row['emergency_contact'] ?? ''));
    $emergencyName = $esc(trim($row['emergency_name'] ?? ''));

    return _importUserBased($row, $decision, $connect, $esc, $def, 'patients', 'App\\Models\\Patient', 5, [
        'insurance' => "'{$insurance}'",
        'allergies' => "'{$allergies}'",
        'emergency_contact' => "'{$emergencyContact}'",
        'emergency_name' => "'{$emergencyName}'",
    ]);
}

function _importStaff($row, $decision, $connect, $esc, $def) {
    global $STAFF_ROLE_MAP, $STAFF_OWNER_MAP;
    $staffType = _resolveCode($row['staff_type'] ?? 'Receptionist', $def['columns'][5]['codes'] ?? []);
    $ownerType = $STAFF_OWNER_MAP[$staffType] ?? 'App\\Models\\Receptionist';
    $roleId = $STAFF_ROLE_MAP[$staffType] ?? 3;

    return _importUserBased($row, $decision, $connect, $esc, $def, null, $ownerType, $roleId);
}

function _importMedicine($row, $decision, $connect, $esc) {
    $name = $esc(trim($row['name'] ?? ''));
    $catId = _resolveFkId('medicine_categories', 'name', $row['category'] ?? '', $connect, $esc);
    $brandId = _resolveFkId('medicine_brands', 'name', $row['brand'] ?? '', $connect, $esc);
    $sellingPrice = floatval($row['selling_price'] ?? 0);
    $buyingPrice = floatval($row['buying_price'] ?? 0);
    $quantity = intval($row['quantity'] ?? 0);
    $salt = $esc(trim($row['salt_composition'] ?? ''));
    $desc = $esc(trim($row['description'] ?? ''));
    $sideEffects = $esc(trim($row['side_effects'] ?? ''));
    $tenantId = _resolveTenantId($row['hospital'] ?? '', $connect, $esc);

    if ($decision === 'update') {
        $r = mysqli_query($connect, "SELECT id FROM medicines WHERE name='{$name}' LIMIT 1");
        if (!$r || mysqli_num_rows($r) === 0) throw new Exception("약품 '{$name}' 없음");
        $id = mysqli_fetch_assoc($r)['id'];

        $sql = "UPDATE medicines SET selling_price={$sellingPrice}, buying_price={$buyingPrice}, quantity={$quantity}, available_quantity={$quantity}, salt_composition='{$salt}', updated_at=NOW() WHERE id={$id}";
        mysqli_query($connect, $sql);
        return ['status' => 'updated', 'msg' => "약품 '{$name}' 업데이트", 'id' => $id];
    }

    $sql = "INSERT INTO medicines (name, category_id, brand_id, selling_price, buying_price, quantity, available_quantity, salt_composition, description, side_effects, tenant_id, created_at, updated_at)
            VALUES ('{$name}', " . ($catId ?? 'NULL') . ", " . ($brandId ?? 'NULL') . ", {$sellingPrice}, {$buyingPrice}, {$quantity}, {$quantity}, '{$salt}', '{$desc}', '{$sideEffects}', "
            . ($tenantId ? "'{$tenantId}'" : "NULL") . ", NOW(), NOW())";
    if (!mysqli_query($connect, $sql)) throw new Exception('medicines INSERT 실패: ' . mysqli_error($connect));
    return ['status' => 'inserted', 'msg' => "약품 '{$name}' 등록 완료", 'id' => mysqli_insert_id($connect)];
}

function _importAppointment($row, $decision, $connect, $esc) {
    $patientEmail = $esc(trim($row['patient_email'] ?? ''));
    $doctorEmail = $esc(trim($row['doctor_email'] ?? ''));
    $opdDate = $esc(trim($row['opd_date'] ?? ''));
    $problem = $esc(trim($row['problem'] ?? ''));
    $tenantId = _resolveTenantId($row['hospital'] ?? '', $connect, $esc);

    // 환자 ID 조회
    $patientOwner = str_replace('\\', '\\\\', 'App\\Models\\Patient');
    $r = mysqli_query($connect, "SELECT u.id AS user_id, p.id AS patient_id, u.department_id FROM users u JOIN patients p ON u.owner_id=p.id WHERE u.email='{$patientEmail}' AND u.owner_type='{$patientOwner}' LIMIT 1");
    if (!$r || mysqli_num_rows($r) === 0) throw new Exception("환자 이메일 '{$patientEmail}' 없음");
    $patientRow = mysqli_fetch_assoc($r);

    // 의사 ID 조회
    $doctorOwner = str_replace('\\', '\\\\', 'App\\Models\\Doctor');
    $r = mysqli_query($connect, "SELECT u.id AS user_id, d.id AS doctor_id, d.doctor_department_id FROM users u JOIN doctors d ON u.owner_id=d.id WHERE u.email='{$doctorEmail}' AND u.owner_type='{$doctorOwner}' LIMIT 1");
    if (!$r || mysqli_num_rows($r) === 0) throw new Exception("의사 이메일 '{$doctorEmail}' 없음");
    $doctorRow = mysqli_fetch_assoc($r);

    $sql = "INSERT INTO appointments (patient_id, doctor_id, department_id, opd_date, problem, is_completed, payment_status, tenant_id, created_at, updated_at)
            VALUES ({$patientRow['patient_id']}, {$doctorRow['doctor_id']}, {$doctorRow['doctor_department_id']}, '{$opdDate}', '{$problem}', 0, 0, "
            . ($tenantId ? "'{$tenantId}'" : "NULL") . ", NOW(), NOW())";
    if (!mysqli_query($connect, $sql)) throw new Exception('appointments INSERT 실패: ' . mysqli_error($connect));
    return ['status' => 'inserted', 'msg' => '예약 등록 완료', 'id' => mysqli_insert_id($connect)];
}

function _importAd($row, $decision, $connect, $esc) {
    $title = $esc(trim($row['title'] ?? ''));
    $subtitle = $esc(trim($row['subtitle'] ?? ''));
    $linkUrl = $esc(trim($row['link_url'] ?? ''));
    $adType = $esc(trim($row['ad_type'] ?? 'banner'));
    if (isset(['배너'=>'banner','팝업'=>'popup','피드'=>'feed'][$adType])) {
        $adType = ['배너'=>'banner','팝업'=>'popup','피드'=>'feed'][$adType];
    }
    $displayOrder = intval($row['display_order'] ?? 0);
    $isActive = isset($row['is_active']) ? intval(_resolveCode($row['is_active'], ['예'=>1,'아니오'=>0,'Y'=>1,'N'=>0,'1'=>1,'0'=>0])) : 1;

    if ($decision === 'update') {
        $r = mysqli_query($connect, "SELECT id FROM gangnamunni_ads WHERE title='{$title}' LIMIT 1");
        if (!$r || mysqli_num_rows($r) === 0) throw new Exception("광고 '{$title}' 없음");
        $id = mysqli_fetch_assoc($r)['id'];

        $sql = "UPDATE gangnamunni_ads SET subtitle='{$subtitle}', link_url='{$linkUrl}', ad_type='{$adType}', display_order={$displayOrder}, is_active={$isActive} WHERE id={$id}";
        mysqli_query($connect, $sql);
        return ['status' => 'updated', 'msg' => "광고 '{$title}' 업데이트", 'id' => $id];
    }

    $sql = "INSERT INTO gangnamunni_ads (title, subtitle, link_url, ad_type, display_order, is_active, created_at, updated_at)
            VALUES ('{$title}', '{$subtitle}', '{$linkUrl}', '{$adType}', {$displayOrder}, {$isActive}, NOW(), NOW())";
    if (!mysqli_query($connect, $sql)) throw new Exception('gangnamunni_ads INSERT 실패: ' . mysqli_error($connect));
    return ['status' => 'inserted', 'msg' => "광고 '{$title}' 등록 완료", 'id' => mysqli_insert_id($connect)];
}


// ────────────────────────────────────────────────────
// Export 기능
// ────────────────────────────────────────────────────

/**
 * 에러 행 목록을 엑셀 파일로 생성
 * @param array $errorRows [{row, errors, rowIndex}, ...]
 * @param string $entityType 엔티티 유형
 * @return string 생성된 파일 경로
 */
function generateErrorExcel($errorRows, $entityType) {
    global $ENTITY_DEFS, $HAS_PHPSPREADSHEET;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return '';

    $dir = sys_get_temp_dir() . '/excel_exports/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $filename = "errors_{$entityType}_" . date('Ymd_His') . '.xlsx';
    $filepath = $dir . $filename;

    if ($HAS_PHPSPREADSHEET) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('에러 목록');

        // 헤더
        $headers = ['행번호', '상태'];
        foreach ($def['columns'] as $col) {
            $headers[] = $col['label'];
        }
        $headers[] = '에러 내용';

        foreach ($headers as $ci => $h) {
            $colLetter = _indexToLetter($ci);
            $sheet->setCellValue("{$colLetter}1", $h);
            $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
            $sheet->getStyle("{$colLetter}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCCCC');
        }

        // 데이터
        $rowNum = 2;
        foreach ($errorRows as $item) {
            $ci = 0;
            $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $item['rowIndex'] + 1);
            $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $item['status'] ?? 'error');
            foreach ($def['columns'] as $col) {
                $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $item['row'][$col['key']] ?? '');
            }
            // 에러 메시지 합치기
            $errMsgs = [];
            foreach (($item['details'] ?? $item['errors'] ?? []) as $d) {
                if (is_array($d)) {
                    $errMsgs[] = ($d['label'] ?? $d['field'] ?? '') . ': ' . ($d['msg'] ?? $d['type'] ?? '');
                } else {
                    $errMsgs[] = (string)$d;
                }
            }
            $sheet->setCellValue(_indexToLetter($ci) . $rowNum, implode(' | ', $errMsgs));
            $rowNum++;
        }

        // 열 너비 자동
        foreach (range(0, count($headers) - 1) as $ci) {
            $sheet->getColumnDimension(_indexToLetter($ci))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    } else {
        // CSV fallback
        $filepath = str_replace('.xlsx', '.csv', $filepath);
        $filename = str_replace('.xlsx', '.csv', $filename);
        $fp = fopen($filepath, 'w');
        fprintf($fp, "\xEF\xBB\xBF"); // BOM
        $headers = ['행번호', '상태'];
        foreach ($def['columns'] as $col) $headers[] = $col['label'];
        $headers[] = '에러 내용';
        fputcsv($fp, $headers);
        foreach ($errorRows as $item) {
            $line = [$item['rowIndex'] + 1, $item['status'] ?? 'error'];
            foreach ($def['columns'] as $col) $line[] = $item['row'][$col['key']] ?? '';
            $errMsgs = [];
            foreach (($item['details'] ?? $item['errors'] ?? []) as $d) {
                $errMsgs[] = is_array($d) ? (($d['label'] ?? $d['field'] ?? '') . ': ' . ($d['msg'] ?? $d['type'] ?? '')) : (string)$d;
            }
            $line[] = implode(' | ', $errMsgs);
            fputcsv($fp, $line);
        }
        fclose($fp);
    }

    return $filepath;
}


/**
 * 데이터를 엑셀 파일로 내보내기
 * @param array $data 2D 배열 (각 행은 연관배열)
 * @param array $headers [{key, label}, ...]
 * @param string $filename 파일명
 * @return string 생성된 파일 경로
 */
function exportToExcel($data, $headers, $filename) {
    global $HAS_PHPSPREADSHEET;

    $dir = sys_get_temp_dir() . '/excel_exports/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $filepath = $dir . $filename;

    if ($HAS_PHPSPREADSHEET) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('데이터');

        // 헤더
        foreach ($headers as $ci => $h) {
            $colLetter = _indexToLetter($ci);
            $sheet->setCellValue("{$colLetter}1", $h['label']);
            $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
            $sheet->getStyle("{$colLetter}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            $sheet->getStyle("{$colLetter}1")->getFont()->getColor()->setRGB('FFFFFF');
        }

        // 데이터
        foreach ($data as $ri => $row) {
            foreach ($headers as $ci => $h) {
                $colLetter = _indexToLetter($ci);
                $sheet->setCellValue("{$colLetter}" . ($ri + 2), $row[$h['key']] ?? '');
            }
            // 줄무늬
            if ($ri % 2 === 1) {
                $range = 'A' . ($ri + 2) . ':' . _indexToLetter(count($headers) - 1) . ($ri + 2);
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');
            }
        }

        // 열 너비 자동
        foreach (range(0, count($headers) - 1) as $ci) {
            $sheet->getColumnDimension(_indexToLetter($ci))->setAutoSize(true);
        }

        // 필터 추가
        $lastCol = _indexToLetter(count($headers) - 1);
        $sheet->setAutoFilter("A1:{$lastCol}1");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    } else {
        // CSV fallback
        $filepath = str_replace('.xlsx', '.csv', $filepath);
        $fp = fopen($filepath, 'w');
        fprintf($fp, "\xEF\xBB\xBF");
        $headerLabels = array_map(function($h) { return $h['label']; }, $headers);
        fputcsv($fp, $headerLabels);
        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $h) $line[] = $row[$h['key']] ?? '';
            fputcsv($fp, $line);
        }
        fclose($fp);
    }

    return $filepath;
}


/**
 * 엔티티별 양식 엑셀 생성 (3시트: 데이터입력/가이드/코드표)
 * @param string $entityType 엔티티 유형
 * @return string 생성된 파일 경로
 */
function generateTemplate($entityType) {
    global $ENTITY_DEFS, $HAS_PHPSPREADSHEET;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return '';

    $dir = sys_get_temp_dir() . '/excel_exports/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $filename = "template_{$entityType}_" . date('Ymd') . '.xlsx';
    $filepath = $dir . $filename;

    if (!$HAS_PHPSPREADSHEET) {
        // CSV fallback (단일 시트)
        $filepath = str_replace('.xlsx', '.csv', $filepath);
        $fp = fopen($filepath, 'w');
        fprintf($fp, "\xEF\xBB\xBF");
        $headers = [];
        foreach ($def['columns'] as $col) {
            $label = $col['label'];
            if ($col['required']) $label .= ' *';
            $headers[] = $label;
        }
        fputcsv($fp, $headers);
        // 예시 행
        $sample = [];
        foreach ($def['columns'] as $col) {
            $sample[] = _getSampleValue($col);
        }
        fputcsv($fp, $sample);
        fclose($fp);
        return $filepath;
    }

    $spreadsheet = new Spreadsheet();

    // ── Sheet 1: 데이터 입력 ──
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('데이터 입력');

    foreach ($def['columns'] as $ci => $col) {
        $colLetter = _indexToLetter($ci);
        $label = $col['label'];
        if ($col['required']) $label .= ' *';
        $sheet1->setCellValue("{$colLetter}1", $label);

        // 헤더 스타일
        $style = $sheet1->getStyle("{$colLetter}1");
        $style->getFont()->setBold(true)->setSize(11);
        if ($col['required']) {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('198754');
            $style->getFont()->getColor()->setRGB('FFFFFF');
        } else {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F5E9');
        }
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 예시 데이터 (2행)
        $sheet1->setCellValue("{$colLetter}2", _getSampleValue($col));
        $sheet1->getStyle("{$colLetter}2")->getFont()->setColor(new Color('808080'));

        // 열 너비
        $sheet1->getColumnDimension($colLetter)->setWidth(max(mb_strlen($label) * 2 + 4, 14));

        // 코드형은 드롭다운 설정
        if ($col['type'] === 'code' && !empty($col['codes'])) {
            $options = '"' . implode(',', array_keys($col['codes'])) . '"';
            $validation = $sheet1->getCell("{$colLetter}2")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                       ->setFormula1($options)
                       ->setAllowBlank(!$col['required'])
                       ->setShowDropDown(true)
                       ->setShowErrorMessage(true)
                       ->setErrorTitle('입력 오류')
                       ->setError('목록에서 선택해주세요.');
            // 1000행까지 적용
            for ($r = 3; $r <= 1000; $r++) {
                $v = $sheet1->getCell("{$colLetter}{$r}")->getDataValidation();
                $v->setType(DataValidation::TYPE_LIST)->setFormula1($options)->setAllowBlank(!$col['required'])->setShowDropDown(true);
            }
        }
    }

    // 행 높이
    $sheet1->getRowDimension(1)->setRowHeight(30);

    // ── Sheet 2: 입력 가이드 ──
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('입력 가이드');

    $guideHeaders = ['컬럼명', '필수여부', '데이터 타입', '설명', '예시'];
    foreach ($guideHeaders as $ci => $h) {
        $colLetter = _indexToLetter($ci);
        $sheet2->setCellValue("{$colLetter}1", $h);
        $sheet2->getStyle("{$colLetter}1")->getFont()->setBold(true);
        $sheet2->getStyle("{$colLetter}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('198754');
        $sheet2->getStyle("{$colLetter}1")->getFont()->getColor()->setRGB('FFFFFF');
    }

    foreach ($def['columns'] as $ri => $col) {
        $r = $ri + 2;
        $sheet2->setCellValue("A{$r}", $col['label']);
        $sheet2->setCellValue("B{$r}", $col['required'] ? '필수' : '선택');
        $sheet2->setCellValue("C{$r}", _getTypeLabel($col['type']));
        $sheet2->setCellValue("D{$r}", _getTypeDesc($col));
        $sheet2->setCellValue("E{$r}", _getSampleValue($col));

        if ($col['required']) {
            $sheet2->getStyle("B{$r}")->getFont()->getColor()->setRGB('DC3545');
            $sheet2->getStyle("B{$r}")->getFont()->setBold(true);
        }
    }

    foreach (range(0, 4) as $ci) {
        $sheet2->getColumnDimension(_indexToLetter($ci))->setAutoSize(true);
    }

    // ── Sheet 3: 코드표 ──
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('코드표');

    $codeHeaders = ['컬럼명', '입력값', '설명/변환값'];
    foreach ($codeHeaders as $ci => $h) {
        $colLetter = _indexToLetter($ci);
        $sheet3->setCellValue("{$colLetter}1", $h);
        $sheet3->getStyle("{$colLetter}1")->getFont()->setBold(true);
        $sheet3->getStyle("{$colLetter}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('198754');
        $sheet3->getStyle("{$colLetter}1")->getFont()->getColor()->setRGB('FFFFFF');
    }

    $codeRow = 2;
    foreach ($def['columns'] as $col) {
        if ($col['type'] === 'code' && !empty($col['codes'])) {
            foreach ($col['codes'] as $label => $val) {
                $sheet3->setCellValue("A{$codeRow}", $col['label']);
                $sheet3->setCellValue("B{$codeRow}", $label);
                $sheet3->setCellValue("C{$codeRow}", (string)$val);
                $codeRow++;
            }
            $codeRow++; // 빈 행 구분
        }
        if ($col['type'] === 'fk') {
            $sheet3->setCellValue("A{$codeRow}", $col['label']);
            $sheet3->setCellValue("B{$codeRow}", "(DB 참조)");
            $sheet3->setCellValue("C{$codeRow}", "{$col['fk_table']}.{$col['fk_col']} 값과 일치해야 합니다");
            $codeRow++;
        }
    }

    foreach (range(0, 2) as $ci) {
        $sheet3->getColumnDimension(_indexToLetter($ci))->setAutoSize(true);
    }

    // 첫 시트로 복귀
    $spreadsheet->setActiveSheetIndex(0);

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $filepath;
}


/**
 * Import 결과를 엑셀로 생성
 * @param array $allResults [index => {status, msg, row, ...}]
 * @param string $entityType
 * @return string 파일 경로
 */
function generateResultsExcel($allResults, $entityType) {
    global $ENTITY_DEFS, $HAS_PHPSPREADSHEET;
    $def = $ENTITY_DEFS[$entityType] ?? null;
    if (!$def) return '';

    $dir = sys_get_temp_dir() . '/excel_exports/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $filename = "results_{$entityType}_" . date('Ymd_His') . '.xlsx';
    $filepath = $dir . $filename;

    if (!$HAS_PHPSPREADSHEET) {
        $filepath = str_replace('.xlsx', '.csv', $filepath);
        $fp = fopen($filepath, 'w');
        fprintf($fp, "\xEF\xBB\xBF");
        $headers = ['행번호', '처리결과', '메시지'];
        foreach ($def['columns'] as $col) $headers[] = $col['label'];
        fputcsv($fp, $headers);
        foreach ($allResults as $idx => $r) {
            $line = [$idx + 1, $r['status'] ?? '', $r['msg'] ?? ''];
            foreach ($def['columns'] as $col) $line[] = $r['row'][$col['key']] ?? '';
            fputcsv($fp, $line);
        }
        fclose($fp);
        return $filepath;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('처리 결과');

    $headers = ['행번호', '처리결과', '메시지'];
    foreach ($def['columns'] as $col) $headers[] = $col['label'];

    foreach ($headers as $ci => $h) {
        $col = _indexToLetter($ci);
        $sheet->setCellValue("{$col}1", $h);
        $sheet->getStyle("{$col}1")->getFont()->setBold(true);
        $sheet->getStyle("{$col}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('198754');
        $sheet->getStyle("{$col}1")->getFont()->getColor()->setRGB('FFFFFF');
    }

    $rowNum = 2;
    foreach ($allResults as $idx => $r) {
        $ci = 0;
        $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $idx + 1);
        $statusVal = $r['status'] ?? '';
        $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $statusVal);
        $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $r['msg'] ?? '');

        foreach ($def['columns'] as $col) {
            $sheet->setCellValue(_indexToLetter($ci++) . $rowNum, $r['row'][$col['key']] ?? '');
        }

        // 상태별 색상
        $statusColor = 'FFFFFF';
        if ($statusVal === 'inserted') $statusColor = 'D4EDDA';
        elseif ($statusVal === 'updated') $statusColor = 'CCE5FF';
        elseif ($statusVal === 'skipped') $statusColor = 'FFF3CD';
        elseif ($statusVal === 'error') $statusColor = 'F8D7DA';
        $range = 'A' . $rowNum . ':' . _indexToLetter(count($headers) - 1) . $rowNum;
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);

        $rowNum++;
    }

    foreach (range(0, count($headers) - 1) as $ci) {
        $sheet->getColumnDimension(_indexToLetter($ci))->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $filepath;
}


// ── 헬퍼 함수들 ──

function _indexToLetter($index) {
    $letter = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = (int)(($index - $mod) / 26);
    }
    return $letter;
}

function _getTypeLabel($type) {
    $map = [
        'string' => '문자열',
        'email' => '이메일',
        'phone' => '전화번호',
        'number' => '숫자 (소수점)',
        'integer' => '정수',
        'date' => '날짜',
        'datetime' => '일시',
        'code' => '코드값',
        'fk' => '참조값',
        'text' => '긴 문자열',
        'url' => 'URL',
    ];
    return $map[$type] ?? $type;
}

function _getTypeDesc($col) {
    switch ($col['type']) {
        case 'email': return '유효한 이메일 주소';
        case 'phone': return '전화번호 (하이픈 포함 가능)';
        case 'date': return 'YYYY-MM-DD 형식';
        case 'datetime': return 'YYYY-MM-DD HH:MM 형식';
        case 'code':
            $keys = array_keys($col['codes'] ?? []);
            return '허용값: ' . implode(', ', $keys);
        case 'fk':
            return ($col['fk_table'] ?? '') . '.' . ($col['fk_col'] ?? '') . ' 값과 일치 필요';
        case 'number': return '숫자 (소수점 가능)';
        case 'integer': return '정수';
        case 'url': return 'URL (https://...)';
        default: return '자유 입력';
    }
}

function _getSampleValue($col) {
    switch ($col['key']) {
        case 'first_name': return '길동';
        case 'last_name': return '홍';
        case 'email': return 'hong@example.com';
        case 'phone': return '010-1234-5678';
        case 'gender': return '남성';
        case 'department': return 'Doctor';
        case 'specialist': return '피부과 전문의';
        case 'qualification': return '서울대 의학과';
        case 'charge': return '50000';
        case 'hospital':
        case 'hospital_name': return '서울미녀 클리닉';
        case 'tenant_username': return 'seoul_clinic';
        case 'dob': return '1985-03-15';
        case 'blood_group': return 'A+';
        case 'description': return '전문 의료 서비스';
        case 'name': return '타이레놀 500mg';
        case 'selling_price': return '5000';
        case 'buying_price': return '3000';
        case 'quantity': return '100';
        case 'salt_composition': return '아세트아미노펜';
        case 'patient_email': return 'patient@example.com';
        case 'doctor_email': return 'doctor@example.com';
        case 'opd_date': return '2026-03-01 10:00';
        case 'problem': return '두통';
        case 'title': return '봄맞이 특별 이벤트';
        case 'subtitle': return '3월 한정';
        case 'link_url': return 'https://example.com/event';
        case 'ad_type': return '배너';
        case 'display_order': return '1';
        case 'is_active': return '예';
        case 'license_no': return 'PH-2024-001';
        case 'insurance': return '국민건강보험';
        case 'allergies': return '페니실린';
        case 'emergency_contact': return '010-9876-5432';
        case 'emergency_name': return '홍어머니';
        case 'address': return '서울시 강남구';
        case 'staff_type': return '접수';
        case 'designation': return '주임';
        case 'category': return '진통제';
        case 'brand': return '한국얀센';
        case 'side_effects': return '졸음, 어지러움';
        case 'hospital_phone': return '02-1234-5678';
        case 'hospital_email': return 'clinic@example.com';
        default: return '';
    }
}
