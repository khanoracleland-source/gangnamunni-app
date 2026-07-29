<?php
/**
 * 의사 상세 페이지
 * - gangnamunni_doctors 테이블 기반
 * - 프로필, 약력, 진료 스케줄, 소속 병원
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/wishlist_button.php';

$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($doctor_id <= 0) {
    header('Location: index.php');
    exit;
}

// 의사 정보 조회
$stmt = $connect->prepare("SELECT d.*, h.name as hospital_name, h.address as hospital_address, h.phone as hospital_phone, COALESCE(NULLIF(h.main_image_url, ''), NULLIF(h.profile_image_url, '')) as hospital_image, h.rating as hospital_rating, h.review_count as hospital_review_count
    FROM gangnamunni_doctors d
    LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
    WHERE d.id = ?");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doctor) {
    die(term("staff") . " 정보를 찾을 수 없습니다.");
}

// 의사 스케줄 파싱
$schedule = json_decode($doctor['schedule'] ?? '{}', true) ?: [];
$day_names = ['월' => '월요일', '화' => '화요일', '수' => '수요일', '목' => '목요일', '금' => '금요일', '토' => '토요일', '일' => '일요일'];

// 오늘 요일 (스케줄 테이블에서 오늘 행 강조에 사용)
$today_idx = date('N'); // 1=월 ~ 7=일
$day_keys = ['월','화','수','목','금','토','일'];
$today_key = $day_keys[$today_idx - 1] ?? '월';

// 주요 진료분야 (의사별 배정 시술)
$doctor_treatments = [];
$dt_stmt = $connect->prepare("SELECT tm.name AS treatment_name, tc.name AS category_name, dt.speciality_level, dt.case_count
    FROM gangnamunni_doctor_treatments dt
    LEFT JOIN gangnamunni_treatment_master tm ON dt.treatment_id = tm.id
    LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
    WHERE dt.doctor_id = ? AND dt.is_active = 1
    ORDER BY tc.sort_order ASC, dt.case_count DESC, tm.name ASC");
if ($dt_stmt) {
    $dt_stmt->bind_param('i', $doctor_id);
    $dt_stmt->execute();
    $dt_res = $dt_stmt->get_result();
    while ($r = $dt_res->fetch_assoc()) {
        if (!empty($r['treatment_name'])) $doctor_treatments[] = $r;
    }
    $dt_stmt->close();
}
// 의사별 배정 시술이 없으면 기본 시술 목록 표시 (추후 의사별 시술 배정 예정)
if (empty($doctor_treatments)) {
    $default_treatments = ['보톡스', '필러', '리프팅', '스킨부스터', '레이저 토닝', '울쎄라', '쌍꺼풀', '코 성형'];
    foreach ($default_treatments as $t) {
        $doctor_treatments[] = ['treatment_name' => $t, 'category_name' => '', 'speciality_level' => '', 'case_count' => 0];
    }
}

$page_title = htmlspecialchars($doctor['name']) . ' - ' . term("staff") . ' 상세';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #AE1932;
            --color-bg: #ffffff;
            --color-bg-light: #f8f9fa;
            --color-text: #222;
            --color-text-secondary: #666;
            --color-border: #eee;
        }
        [data-bs-theme="dark"] {
            --color-bg: #1a1a2e;
            --color-bg-light: #16213e;
            --color-text: #e0e0e0;
            --color-text-secondary: #aaa;
            --color-border: #333;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--color-bg); color: var(--color-text); }
        .container { max-width: 640px; margin: 0 auto; }

        /* 프로필 헤더 — 좌: 사진 / 우: 정보 */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px 20px;
            border-bottom: 1px solid var(--color-border);
        }
        .profile-photo {
            width: 100px; height: 100px; border-radius: 50%;
            background: var(--color-bg-light);
            display: flex; align-items: center; justify-content: center;
            font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1));
            flex-shrink: 0;
            border: 2px solid var(--color-border);
            overflow: hidden;
        }
        .profile-photo img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info { flex: 1; min-width: 0; }
        .profile-name { font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 4px; color: var(--color-text); }
        .profile-specialty { font-size: calc(17px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-primary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .profile-specialty img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }

        /* 섹션 */
        .section { padding: 16px 20px; border-bottom: 1px solid var(--color-border); }
        .section:last-child { border-bottom: none; }
        /* 80% 폭 구분선 */
        .divider-80 { border-bottom: none !important; position: relative; }
        .divider-80::after {
            content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
            width: 95%; height: 1px; background: var(--color-border);
        }
        .section-title { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }

        /* 스케줄 테이블 */
        .schedule-table { width: 100%; border-collapse: collapse; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); }
        .schedule-table td { padding: 8px 10px; border-bottom: 1px solid var(--color-border); }
        .schedule-table .day { font-weight: 600; width: 70px; }
        .schedule-table .today { background: rgba(255, 84, 15, 0.08); }
        .schedule-table .off { color: var(--color-primary, #AE1932); }

        /* 주요 진료분야 */
        .treatment-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .treatment-chip {
            padding: 7px 7px;
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            background: var(--color-bg-light); color: var(--color-text);
            display: inline-flex; align-items: center; gap: 6px;
        }
        .treatment-chip.expert { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
        .treatment-chip .chip-count { font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); opacity: 0.75; }

        /* 약력 */
        .bio-list { list-style: none; }
        .bio-list li {
            position: relative; padding-left: 16px; margin-bottom: 12px;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            line-height: 1.6; color: #000000;
        }
        .bio-list li:last-child { margin-bottom: 0; }
        .bio-list li::before {
            content: ''; position: absolute; left: 0; top: 0.6em;
            width: 5px; height: 5px; border-radius: 50%;
            background: #000000;
        }

        /* 병원 카드 — detail.php event-card 스타일 */
        .hospital-card {
            position: relative;
            overflow: hidden;
        }
        .hospital-card-link { display: flex; align-items: stretch; text-decoration: none; color: inherit; cursor: pointer; }
        .hospital-wishlist { position: absolute; top: 8px; right: 8px; z-index: 2; background: rgba(255,255,255,0.9); border-radius: 50%; display: inline-flex; }
        .hospital-rating { display: flex; align-items: center; gap: 5px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); }
        .hospital-rating img { width: 24px; height: 24px; object-fit: contain; flex-shrink: 0; }
        .hospital-rating .rating-score { font-weight: 700; color: var(--color-text); }
        .hospital-rating .review-count { color: var(--color-text-secondary); display: inline-flex; align-items: center; gap: 4px; }
        .hospital-card-img {
            position: relative; width: 120px; flex-shrink: 0;
            align-self: stretch; min-height: 130px;
            background: var(--color-bg-light);
            display: flex; align-items: center; justify-content: center;
            font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); overflow: hidden;
        }
        .hospital-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .hospital-card-info { flex: 1; min-width: 0; padding: 14px; }
        .hospital-card-name {
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 10px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .hospital-card-addr { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-text-secondary); margin-bottom: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .hospital-card-addr:last-child { margin-bottom: 0; }

        /* 상단 헤더 (detail.php 스타일) */
        .detail-header {
            position: sticky; top: 0;
            width: 100%; max-width: 640px;
            padding: 14px 16px;
            background: var(--color-bg, #fff);
            display: flex; align-items: center; gap: 12px;
            z-index: 99;
        }
        .back-btn {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            border: none; background: none;
            font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
            color: inherit; cursor: pointer; text-decoration: none;
        }
        .doctor-name-header {
            position: absolute; left: 50%; transform: translateX(-50%);
            max-width: 60%; text-align: center;
            font-size: calc(17px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 700;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
    </style>
    <script>
        // 다크모드 anti-flash
        (function(){
            var t = localStorage.getItem('theme') || localStorage.getItem('admin_theme');
            if (t === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="container" style="position:relative;">
    <!-- 상단 헤더 (detail.php 스타일) -->
    <div class="detail-header">
        <button class="back-btn" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
        <div class="doctor-name-header"><?php echo htmlspecialchars($doctor['name']); ?><?php if (!empty($doctor['specialty'])): ?> <?php echo htmlspecialchars($doctor['specialty']); ?><?php endif; ?></div>
    </div>

    <!-- 프로필 헤더 -->
    <div class="profile-header divider-80">
        <div class="profile-photo">
            <?php if (!empty($doctor['photo_url'])): ?>
            <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>">
            <?php else: ?>
            👨‍⚕️
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($doctor['name']); ?></div>
            <div class="profile-specialty"><img src="assets/icons/categories/doctor.jpeg" alt=""><?php echo htmlspecialchars($doctor['specialty'] ?: '전문의'); ?></div>
        </div>
    </div>

    <!-- 주요 진료분야 -->
    <?php if (!empty($doctor_treatments)): ?>
    <div class="section divider-80">
        <div class="section-title"> 주요 진료분야</div>
        <div class="treatment-chips">
            <?php foreach ($doctor_treatments as $dt): ?>
            <span class="treatment-chip <?php echo ($dt['speciality_level'] ?? '') === 'expert' ? 'expert' : ''; ?>">
                <?php echo htmlspecialchars($dt['treatment_name']); ?>
                <?php if (!empty($dt['case_count']) && $dt['case_count'] > 0): ?>
                <span class="chip-count"><?php echo number_format($dt['case_count']); ?>건</span>
                <?php endif; ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 약력 / 소개 -->
    <?php if (!empty($doctor['bio'])): ?>
    <div class="section divider-80">
        <ul class="bio-list">
            <?php foreach (preg_split('/\r\n|\r|\n/', $doctor['bio']) as $bio_line): $bio_line = trim($bio_line); if ($bio_line === '') continue; ?>
            <li><?php echo htmlspecialchars($bio_line); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

   

    <!-- 소속 병원 -->
    <?php if (!empty($doctor['hospital_name'])): ?>
    <?php
    // 주소에서 '구'로 끝나는 토큰(자치구) 추출 — 예: "서울 강남구 …" → "강남구"
    $hospital_gu = '';
    foreach (preg_split('/\s+/', trim($doctor['hospital_address'] ?? '')) as $_ap) {
        if ($_ap !== '' && mb_substr($_ap, -1) === '구') { $hospital_gu = $_ap; break; }
    }
    ?>
    <div class="section">
        <div class="section-title"> 소속 <?php echo term("business"); ?></div>
        <div class="hospital-card">
            <span class="hospital-wishlist"><?php renderWishlistButton('hospital', (int)$doctor['hospital_id'], 'sm', false, 'star'); ?></span>
            <a href="detail.php?id=<?php echo $doctor['hospital_id']; ?>" class="hospital-card-link">
                <div class="hospital-card-img">
                    <img src="<?php echo htmlspecialchars($doctor['hospital_image'] ?? ''); ?>" alt=""
                        onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'120\' height=\'130\'%3E%3Crect fill=\'%23f5f5f5\' width=\'120\' height=\'130\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%23999\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-size=\'14\'%3E병원%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="hospital-card-info">
                    <div class="hospital-card-name"><?php echo htmlspecialchars($doctor['hospital_name']); ?></div>
                    <?php if (!empty($doctor['hospital_address'])): ?>
                    <div class="hospital-card-addr"><?php if (!empty($hospital_gu)): ?><strong style="color:var(--color-text-secondary,#666); font-weight:600;"><?php echo htmlspecialchars($hospital_gu); ?></strong> | <?php endif; ?><?php echo htmlspecialchars($doctor['hospital_address']); ?></div>
                    <?php endif; ?>
                    <div class="hospital-rating">
                        <img src="assets/icons/categories/rating_2.svg" alt="평점">
                        <span class="rating-score"><?php echo number_format(!empty($doctor['hospital_rating']) ? $doctor['hospital_rating'] : 4.8, 1); ?>/5.0</span>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
