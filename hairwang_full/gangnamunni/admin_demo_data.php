<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * 데모 데이터 관리 대시보드
 * 대량 생성 / 삭제 + 개발모드 토글
 * @created 2026-04-05
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '데모 데이터 관리';
$admin_current = 'demo_data';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('super_admin');

$message = '';
$error = '';

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 개발모드 토글
    if ($action === 'toggle_dev_mode') {
        $new_val = ($_POST['dev_mode'] === 'on') ? 'off' : 'on';
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_settings SET setting_value=? WHERE setting_key='dev_mode'");
        mysqli_stmt_bind_param($stmt, 's', $new_val);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "개발모드가 " . ($new_val === 'on' ? 'ON' : 'OFF') . " 되었습니다.";
    }

    // 병원 10개 + 연관 데이터 생성
    if ($action === 'generate_hospitals') {
        $count = intval($_POST['count'] ?? 10);
        if ($count < 1 || $count > 50) $count = 10;

        $surnames = ['김','이','박','최','정','강','조','윤','장','임','한','오','서','신','권','황','안','송','전','홍'];
        $names = ['민수','서연','지훈','하은','준혁','수빈','도윤','예은','시우','다은','현우','지민','태현','소희','우진'];
        $areas = [
            ['강남구','역삼동'],['강남구','삼성동'],['서초구','서초동'],['송파구','잠실동'],
            ['마포구','합정동'],['강남구','청담동'],['강남구','논현동'],['서초구','반포동'],
            ['강남구','신사동'],['용산구','이태원동'],['성동구','성수동'],['영등포구','여의도동']
        ];
        $specialties = ['성형외과','피부과','안과','치과','산부인과','정형외과','내과','비뇨기과','이비인후과','한의원'];
        $treatments_pool = ['쌍꺼풀','코성형','리프팅','보톡스','필러','레이저','지방흡입','가슴성형','눈밑지방','턱보톡스','피부관리','여드름','주름개선','미백','제모','모발이식'];

        $generated = 0;
        for ($i = 0; $i < $count; $i++) {
            $area = $areas[array_rand($areas)];
            $spec = $specialties[array_rand($specialties)];
            $owner = $surnames[array_rand($surnames)] . $names[array_rand($names)];
            $contact = $surnames[array_rand($surnames)] . $names[array_rand($names)];
            $idx = rand(1000, 9999);

            $h_name = '데모 ' . $area[1] . ' ' . $spec . ' #' . $idx;
            $h_email = 'demo_' . $idx . '@test.com';
            $h_phone = '02-' . rand(1000,9999) . '-' . rand(1000,9999);
            $h_address = '서울특별시 ' . $area[0] . ' ' . $area[1] . ' ' . rand(1,300) . '번지';
            $h_biz = rand(100,999) . '-' . rand(10,99) . '-' . rand(10000,99999);
            $h_contact_phone = '010-' . rand(1000,9999) . '-' . rand(1000,9999);
            $h_lat = 37.49 + (rand(0,100)/1000);
            $h_lng = 127.02 + (rand(0,100)/1000);

            // 카테고리/태그 랜덤
            $cats = array_slice($treatments_pool, 0, rand(2,4));
            $tags = array_slice($treatments_pool, rand(0,5), rand(3,6));
            $cats_json = json_encode($cats, JSON_UNESCAPED_UNICODE);
            $tags_json = json_encode($tags, JSON_UNESCAPED_UNICODE);

            $desc = '데모 테스트 병원입니다. ' . $spec . ' 전문으로 ' . rand(5,30) . '년 경력의 의료진이 진료합니다.';
            $bh = json_encode(['mon'=>'09:00-18:00','tue'=>'09:00-18:00','wed'=>'09:00-18:00','thu'=>'09:00-18:00','fri'=>'09:00-18:00','sat'=>'09:00-13:00','sun'=>'휴진'], JSON_UNESCAPED_UNICODE);

            $stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_hospitals (name, owner_name, business_number, phone, email, contact_name, contact_phone, address, latitude, longitude, categories, treatment_tags, business_hours, description, status, is_active, ad_grade, is_demo, rating, review_count, created_at, applied_at, applied_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 1, 'none', 1, ?, ?, NOW(), NOW(), 'demo_generator')"
            );
            $rating = round(rand(35,50)/10, 1);
            $rev_count = rand(5, 50);
            mysqli_stmt_bind_param($stmt, 'ssssssssddssssdi',
                $h_name, $owner, $h_biz, $h_phone, $h_email,
                $contact, $h_contact_phone, $h_address, $h_lat, $h_lng,
                $cats_json, $tags_json, $bh, $desc, $rating, $rev_count
            );
            if (mysqli_stmt_execute($stmt)) {
                $hosp_id = mysqli_insert_id($connect);
                $generated++;

                // 의사 2명 생성
                for ($d = 0; $d < 2; $d++) {
                    $doc_name = $surnames[array_rand($surnames)] . $names[array_rand($names)];
                    $doc_spec = $specialties[array_rand($specialties)];
                    $hms_doc_id = $hosp_id * 100 + $d + 1;
                    $doc_stmt = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_doctors (hms_doctor_id, hospital_id, name, specialty, experience, is_demo, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())"
                    );
                    $exp = rand(3, 25);
                    mysqli_stmt_bind_param($doc_stmt, 'iissi', $hms_doc_id, $hosp_id, $doc_name, $doc_spec, $exp);
                    mysqli_stmt_execute($doc_stmt);
                    mysqli_stmt_close($doc_stmt);
                }

                // 후기 3건 생성
                $review_titles = ['시술 후기 - 매우 만족','상담 후기 - 꼼꼼한 설명','재방문 후기 - 추천합니다','첫 방문 후기 - 괜찮았어요','솔직 후기 - 만족스러운 경험'];
                $review_contents = [
                    '의료진이 매우 친절하고 시술 결과도 만족스럽습니다. 상담부터 시술까지 꼼꼼하게 설명해주셔서 불안감 없이 진행했습니다.',
                    '원장님이 정말 자세하게 설명해주셨습니다. 부작용이나 주의사항까지 솔직하게 말씀해주셔서 신뢰가 갔습니다.',
                    '두 번째 방문입니다. 이전에도 만족했었는데 이번에도 역시 좋았습니다. 시설도 깨끗하고 대기 시간도 짧아서 좋습니다.',
                    '지인 추천으로 방문했습니다. 전반적으로 만족스러운 경험이었고 가격도 합리적이었습니다.',
                    '여러 병원을 비교하다가 선택했는데 잘한 선택이었습니다. 원장님 경력이 풍부하시고 시술도 정확했습니다.'
                ];
                for ($r = 0; $r < 3; $r++) {
                    $rev_title = $review_titles[array_rand($review_titles)];
                    $rev_content = $review_contents[array_rand($review_contents)];
                    $rev_rating = rand(4, 5);
                    $rev_author = $surnames[array_rand($surnames)] . $names[array_rand($names)];
                    $rev_stmt = mysqli_prepare($connect,
                        "INSERT INTO hospital_reviews (hospital_id, user_name, rating, title, content, review_status, is_demo, created_at) VALUES (?, ?, ?, ?, ?, 'published', 1, NOW())"
                    );
                    mysqli_stmt_bind_param($rev_stmt, 'isiss', $hosp_id, $rev_author, $rev_rating, $rev_title, $rev_content);
                    mysqli_stmt_execute($rev_stmt);
                    mysqli_stmt_close($rev_stmt);
                }

                // 이벤트 1건 생성
                $evt_titles = ['봄맞이 특가','여름 할인','가을 이벤트','겨울 프로모션','신규 개원 기념','1주년 감사 이벤트'];
                $evt_stmt = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_events (hospital_id, hospital_name, title, description, original_price, discount_price, discount_rate, start_date, end_date, is_active, is_demo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, 1, NOW())"
                );
                $evt_title = $evt_titles[array_rand($evt_titles)] . ' #' . $idx;
                $evt_desc = '데모 테스트 이벤트입니다. 기간 한정 특별 할인 혜택을 제공합니다.';
                $orig_price = rand(10,100) * 10000;
                $disc_price = intval($orig_price * (1 - rand(20,50)/100));
                $disc_rate = intval((1 - $disc_price/$orig_price) * 100);
                mysqli_stmt_bind_param($evt_stmt, 'isssiis', $hosp_id, $h_name, $evt_title, $evt_desc, $orig_price, $disc_price, $disc_rate);
                mysqli_stmt_execute($evt_stmt);
                mysqli_stmt_close($evt_stmt);

                // 예약 2건 생성
                for ($b = 0; $b < 2; $b++) {
                    $bk_name = $surnames[array_rand($surnames)] . $names[array_rand($names)];
                    $bk_phone = '010-' . rand(1000,9999) . '-' . rand(1000,9999);
                    $bk_treatment = $treatments_pool[array_rand($treatments_pool)];
                    $bk_date = date('Y-m-d', strtotime('+' . rand(1,30) . ' days'));
                    $bk_time = str_pad(rand(9,17), 2, '0', STR_PAD_LEFT) . ':00';
                    $bk_stmt = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_bookings (hospital_id, hospital_name, name, phone, treatment, booking_date, booking_time, status, is_demo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 1, NOW())"
                    );
                    mysqli_stmt_bind_param($bk_stmt, 'issssss', $hosp_id, $h_name, $bk_name, $bk_phone, $bk_treatment, $bk_date, $bk_time);
                    mysqli_stmt_execute($bk_stmt);
                    mysqli_stmt_close($bk_stmt);
                }
            }
            mysqli_stmt_close($stmt);
        }
        $message = "데모 병원 {$generated}개 + 연관 데이터(의사/후기/이벤트/예약) 생성 완료!";
    }

    // 데모 데이터 삭제
    if ($action === 'delete_demo') {
        $target = $_POST['target'] ?? 'all';
        $deleted = [];

        $tables = [
            'all' => [
                'gangnamunni_bookings' => '예약',
                'hospital_reviews' => '후기',
                'gangnamunni_events' => '이벤트',
                'gangnamunni_doctors' => '의사',
                'gangnamunni_ads' => '광고',
                'gangnamunni_consultations' => '상담',
                'gangnamunni_treatments' => '시술',
                'gangnamunni_hospitals' => '병원',  // 마지막에 삭제 (FK 의존)
            ]
        ];

        $target_tables = $tables['all'];
        if ($target !== 'all' && isset($tables['all'][$target])) {
            $target_tables = [$target => $tables['all'][$target]];
        }

        mysqli_begin_transaction($connect);
        try {
            foreach ($target_tables as $tbl => $label) {
                $del = mysqli_query($connect, "DELETE FROM {$tbl} WHERE is_demo=1");
                $cnt = mysqli_affected_rows($connect);
                if ($cnt > 0) $deleted[] = "{$label} {$cnt}건";
            }
            mysqli_commit($connect);
            $message = empty($deleted) ? '삭제할 데모 데이터가 없습니다.' : '삭제 완료: ' . implode(', ', $deleted);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $error = '삭제 중 오류: ' . $e->getMessage();
        }
    }
}

// ===== 현황 조회 =====
$dev_mode = 'off';
$dm_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='dev_mode'"));
if ($dm_row) $dev_mode = $dm_row['setting_value'];

$demo_counts = [];
$count_tables = [
    'gangnamunni_hospitals' => ['label' => '병원', 'icon' => 'bi-building', 'color' => 'primary'],
    'hospital_reviews' => ['label' => '후기', 'icon' => 'bi-chat-dots', 'color' => 'success'],
    'gangnamunni_bookings' => ['label' => '예약', 'icon' => 'bi-calendar-check', 'color' => 'info'],
    'gangnamunni_events' => ['label' => '이벤트', 'icon' => 'bi-megaphone', 'color' => 'warning'],
    'gangnamunni_doctors' => ['label' => '의사', 'icon' => 'bi-person-badge', 'color' => 'danger'],
    'gangnamunni_treatments' => ['label' => '시술', 'icon' => 'bi-scissors', 'color' => 'secondary'],
    'gangnamunni_consultations' => ['label' => '상담', 'icon' => 'bi-headset', 'color' => 'dark'],
    'gangnamunni_ads' => ['label' => '광고', 'icon' => 'bi-badge-ad', 'color' => 'primary'],
];

$total_demo = 0;
foreach ($count_tables as $tbl => $meta) {
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM {$tbl} WHERE is_demo=1");
    $cnt = $r ? mysqli_fetch_assoc($r)['cnt'] : 0;
    $demo_counts[$tbl] = $cnt;
    $total_demo += $cnt;
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- 알림 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- 페이지 헤더 + 개발모드 토글 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1"><i class="bi bi-database-gear me-2"></i>데모 데이터 관리</h5>
        <small class="text-muted">테스트용 데모 데이터 생성 및 삭제</small>
    </div>
    <form method="POST" class="d-inline">
        <input type="hidden" name="action" value="toggle_dev_mode">
        <input type="hidden" name="dev_mode" value="<?= $dev_mode ?>">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-<?= $dev_mode === 'on' ? 'success' : 'secondary' ?> fs-6">
                DEV MODE <?= strtoupper($dev_mode) ?>
            </span>
            <button type="submit" class="btn btn-<?= $dev_mode === 'on' ? 'outline-danger' : 'outline-success' ?> btn-sm">
                <i class="bi bi-<?= $dev_mode === 'on' ? 'toggle-on' : 'toggle-off' ?> me-1"></i>
                <?= $dev_mode === 'on' ? 'OFF' : 'ON' ?>
            </button>
        </div>
    </form>
</div>

<!-- 현황 카드 -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary"><?= $total_demo ?></div>
                <small class="text-muted">총 데모 데이터</small>
            </div>
        </div>
    </div>
    <?php foreach ($count_tables as $tbl => $meta): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <i class="bi <?= $meta['icon'] ?> text-<?= $meta['color'] ?> fs-4"></i>
                <div>
                    <div class="fw-bold"><?= $demo_counts[$tbl] ?>건</div>
                    <small class="text-muted"><?= $meta['label'] ?></small>
                </div>
                <?php if ($demo_counts[$tbl] > 0): ?>
                <form method="POST" class="ms-auto" onsubmit="return confirm('<?= $meta['label'] ?> 데모 데이터 <?= $demo_counts[$tbl] ?>건을 삭제하시겠습니까?')">
                    <input type="hidden" name="action" value="delete_demo">
                    <input type="hidden" name="target" value="<?= $tbl ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="삭제"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 액션 버튼 -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white py-2">
                <h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i>대량 생성</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">병원 + 의사 2명 + 후기 3건 + 이벤트 1건 + 예약 2건 자동 생성</p>
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="action" value="generate_hospitals">
                    <select name="count" class="form-select form-select-sm" style="width:120px">
                        <option value="5">5개</option>
                        <option value="10" selected>10개</option>
                        <option value="20">20개</option>
                        <option value="30">30개</option>
                        <option value="50">50개</option>
                    </select>
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('데모 병원을 생성하시겠습니까?')">
                        <i class="bi bi-lightning-fill me-1"></i>병원 + 연관 데이터 생성
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white py-2">
                <h6 class="mb-0"><i class="bi bi-trash me-1"></i>일괄 삭제</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">is_demo=1인 데이터만 삭제 (실 데이터 안전)</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_demo">
                    <input type="hidden" name="target" value="all">
                    <button type="submit" class="btn btn-danger btn-sm" <?= $total_demo === 0 ? 'disabled' : '' ?>
                        onclick="return confirm('전체 데모 데이터 <?= $total_demo ?>건을 삭제하시겠습니까?\n\n실 데이터(is_demo=0)는 영향 없습니다.')">
                        <i class="bi bi-trash-fill me-1"></i>전체 데모 데이터 삭제 (<?= $total_demo ?>건)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 데모 병원 목록 (최근 20개) -->
<?php
$demo_hospitals = [];
$dh = mysqli_query($connect, "SELECT id, name, status, ad_grade, rating, review_count, created_at FROM gangnamunni_hospitals WHERE is_demo=1 ORDER BY id DESC LIMIT 20");
while ($dh && $row = mysqli_fetch_assoc($dh)) $demo_hospitals[] = $row;
?>
<?php if (!empty($demo_hospitals)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="bi bi-list-ul me-1"></i>데모 병원 목록 (최근 20개)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>병원명</th>
                    <th>상태</th>
                    <th>등급</th>
                    <th>평점</th>
                    <th>후기수</th>
                    <th>생성일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($demo_hospitals as $dh): ?>
                <tr>
                    <td><?= $dh['id'] ?></td>
                    <td><?= htmlspecialchars($dh['name']) ?></td>
                    <td><span class="badge bg-<?= $dh['status']==='approved'?'success':($dh['status']==='pending'?'warning':'danger') ?>"><?= $dh['status'] ?></span></td>
                    <td><?= $dh['ad_grade'] ?></td>
                    <td><?= $dh['rating'] ?></td>
                    <td><?= $dh['review_count'] ?></td>
                    <td><?= substr($dh['created_at'], 0, 16) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
