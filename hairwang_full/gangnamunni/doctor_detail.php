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
$stmt = $connect->prepare("SELECT d.*, h.name as hospital_name, h.address as hospital_address, h.phone as hospital_phone,
    COALESCE(
        (SELECT NULLIF(hi.image_url, '')
         FROM hospital_images hi
         WHERE hi.hospital_id = h.id
           AND hi.is_active = 1
           AND hi.image_type = 'main'
         ORDER BY hi.sort_order ASC, hi.id ASC
         LIMIT 1),
        NULLIF(h.main_image_url, ''),
        NULLIF(h.profile_image_url, '')
    ) as hospital_image,
    h.rating as hospital_rating, h.review_count as hospital_review_count, h.ad_grade as hospital_ad_grade
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
$page_title = htmlspecialchars($doctor['name']) . ' - ' . term("staff") . ' 상세';

// 관리자 입력 필드 구조를 그대로 유지한다: 자격/학회와 소개/약력을 섞지 않는다.
function doctor_detail_lines($text) {
    $lines = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $line = trim(preg_replace('/^[\s•·\-*]+/u', '', $line));
        if ($line !== '' && !in_array($line, $lines, true)) $lines[] = $line;
    }
    return $lines;
}
$qualification_lines = doctor_detail_lines($doctor['qualification'] ?? '');
$bio_lines = doctor_detail_lines($doctor['bio'] ?? '');
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
            --color-primary: #c81935;
            --color-bg: #ffffff;
            --color-page: #404040;
            --color-bg-light: #f4f4f4;
            --color-text: #111111;
            --color-text-secondary: #6f6f6f;
            --color-border: #dedede;
        }
        [data-bs-theme="dark"] {
            --color-bg: #202124;
            --color-page: #121212;
            --color-bg-light: #303134;
            --color-text: #f2f2f2;
            --color-text-secondary: #b7b7b7;
            --color-border: #464646;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            padding: 8px 20px 20px;
            font-family: Pretendard, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--color-page);
            color: var(--color-text);
        }
        .container {
            width: 100%;
            max-width: 1030px;
            min-height: calc(100vh - 28px);
            margin: 0 auto;
            background: var(--color-bg);
            border: 0;
            box-shadow: none;
        }
        .detail-header {
            position: relative;
            height: 95px;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-bg);
        }
        .back-btn {
            position: absolute;
            left: 24px;
            width: 46px;
            height: 46px;
            border: 0;
            background: transparent;
            color: var(--color-text);
            font-size: 38px;
            line-height: 1;
            cursor: pointer;
        }
        .doctor-name-header {
            max-width: 75%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: center;
            font-size: clamp(24px, 2.4vw, 34px);
            font-weight: 600;
            letter-spacing: -.03em;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 40px;
            min-height: 212px;
            margin: 0 32px;
            padding: 18px 0 30px;
            border-bottom: 1px solid var(--color-border);
        }
        .profile-photo {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: var(--color-bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 76px;
            flex-shrink: 0;
            border: 1px solid var(--color-border);
            overflow: hidden;
        }
        .profile-photo img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info { flex: 1; min-width: 0; }
        .profile-name {
            margin-bottom: 20px;
            color: var(--color-text);
            font-size: clamp(27px, 3vw, 35px);
            font-weight: 700;
            letter-spacing: -.04em;
        }
        .profile-specialty {
            display: flex;
            align-items: center;
            gap: 13px;
            color: var(--color-primary);
            font-size: clamp(18px, 2vw, 24px);
            font-weight: 600;
        }
        .profile-specialty i { font-size: 34px; font-weight: 400; }
        .section {
            margin: 0 32px;
            padding: 36px 0;
            border-bottom: 1px solid var(--color-border);
        }
        .section:last-child { border-bottom: 0; }
        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            font-size: clamp(24px, 2.5vw, 32px);
            font-weight: 700;
            letter-spacing: -.04em;
        }
        .treatment-chips { display: flex; flex-wrap: wrap; gap: 12px 20px; }
        .treatment-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 13px;
            background: var(--color-bg-light);
            color: var(--color-text);
            font-size: clamp(16px, 1.7vw, 21px);
            font-weight: 600;
        }
        .treatment-chip.expert { background: var(--color-bg-light); color: var(--color-text); }
        .treatment-chip .chip-count { font-size: .72em; opacity: .75; }
        .bio-list { list-style: none; padding: 0 10px; }
        .bio-list li {
            position: relative;
            padding-left: 27px;
            margin-bottom: 18px;
            color: var(--color-text);
            font-size: clamp(17px, 1.8vw, 22px);
            line-height: 1.55;
        }
        .bio-list li:last-child { margin-bottom: 0; }
        .bio-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            top: -.05em;
            color: var(--color-text);
            font-size: 1.25em;
        }
        .hospital-card { position: relative; overflow: hidden; }
        .hospital-card-link {
            display: flex;
            align-items: center;
            min-height: 160px;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        .hospital-wishlist { display: inline-flex; align-items: center; }
        .hospital-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: clamp(15px, 1.55vw, 20px);
        }
        .hospital-rating img { width: 36px; height: 36px; object-fit: contain; flex-shrink: 0; }
        .hospital-rating .rating-score { font-weight: 700; color: var(--color-text); }
        .hospital-rating .review-count { color: var(--color-text-secondary); display: inline-flex; align-items: center; gap: 4px; }
        .hospital-card-img {
            position: relative;
            width: 160px;
            height: 160px;
            flex-shrink: 0;
            background: var(--color-bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--color-border);
        }
        .hospital-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .hospital-card-info { flex: 1; min-width: 0; padding: 8px 28px; }
        .hospital-card-name {
            margin-bottom: 14px;
            font-size: clamp(23px, 2.4vw, 31px);
            font-weight: 700;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .hospital-card-addr {
            margin-bottom: 18px;
            color: var(--color-text-secondary);
            font-size: clamp(15px, 1.55vw, 20px);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .hospital-card-addr:last-child { margin-bottom: 0; }
        .hospital-meta { display:flex; align-items:center; flex-wrap:wrap; gap:14px 26px; }
        .popular-hospital { display:inline-flex; align-items:center; gap:7px; color:#202020; font-size:clamp(14px,1.4vw,18px); font-weight:600; }
        .popular-hospital i { color:#ff9f1a; font-size:1.35em; }
        @media (max-width: 700px) {
            body { padding: 0; }
            .container { min-height: 100vh; border: 0; box-shadow: none; }
            .detail-header { height: 68px; padding: 0 16px; }
            .back-btn { left: 8px; width: 44px; height: 44px; font-size: 30px; }
            .doctor-name-header { max-width: 72%; font-size: 20px; }
            .profile-header {
                gap: 20px;
                min-height: 0;
                margin: 0 20px;
                padding: 28px 0 32px;
            }
            .profile-photo { width: 112px; height: 112px; font-size: 48px; }
            .profile-name { margin-bottom: 14px; font-size: 25px; }
            .profile-specialty { gap: 8px; font-size: 17px; }
            .profile-specialty i { font-size: 26px; }
            .section { margin: 0 20px; padding: 28px 0; border-bottom-width: 1px; }
            .section-title { margin-bottom: 18px; font-size: 24px; }
            .treatment-chip { padding: 8px 10px; font-size: 16px; }
            .bio-list { padding: 0; }
            .bio-list li { padding-left: 20px; margin-bottom: 14px; font-size: 16px; }
            .hospital-card-link { min-height: 110px; align-items: stretch; }
            .hospital-card-img { width: 112px; height: 112px; }
            .hospital-card-info { padding: 8px 14px; }
            .hospital-card-name { margin-bottom: 8px; font-size: 20px; }
            .hospital-card-addr { margin-bottom: 9px; font-size: 13px; white-space: normal; line-height: 1.35; }
            .hospital-rating { gap: 6px; font-size: 14px; }
            .hospital-rating img { width: 25px; height: 25px; }
            .hospital-wishlist { right: 4px; bottom: 4px; }
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
        <div class="doctor-name-header"><?php echo htmlspecialchars($doctor['name']); ?></div>
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
            <?php if (trim($doctor['specialty'] ?? '') !== ''): ?>
            <div class="profile-specialty"><i class="bi bi-file-medical"></i><?php echo htmlspecialchars(trim($doctor['specialty'])); ?></div>
            <?php endif; ?>
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

    <!-- 자격 및 소속 학회 -->
    <?php if (!empty($qualification_lines)): ?>
    <div class="section divider-80">
        <div class="section-title">자격 및 소속 학회</div>
        <ul class="bio-list">
            <?php foreach ($qualification_lines as $qualification_line): ?>
            <li><?php echo htmlspecialchars($qualification_line); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- 소개 및 약력 -->
    <?php if (!empty($bio_lines)): ?>
    <div class="section divider-80">
        <div class="section-title">소개 및 약력</div>
        <ul class="bio-list">
            <?php foreach ($bio_lines as $bio_line): ?>
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
            <a href="detail.php?id=<?php echo $doctor['hospital_id']; ?>" class="hospital-card-link">
                <div class="hospital-card-img">
                    <?php if (!empty($doctor['hospital_image'])): ?>
                    <img src="<?php echo htmlspecialchars($doctor['hospital_image'] ?? ''); ?>" alt=""
                        onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'120\' height=\'130\'%3E%3Crect fill=\'%23f5f5f5\' width=\'120\' height=\'130\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%23999\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-size=\'14\'%3E병원%3C/text%3E%3C/svg%3E'">
                    <?php else: ?>
                    <i class="bi bi-hospital" style="font-size:44px;color:var(--color-text-secondary);"></i>
                    <?php endif; ?>
                </div>
                <div class="hospital-card-info">
                    <div class="hospital-card-name"><?php echo htmlspecialchars($doctor['hospital_name']); ?></div>
                    <?php if (!empty($doctor['hospital_address'])): ?>
                    <div class="hospital-card-addr"><?php if (!empty($hospital_gu)): ?><strong style="color:var(--color-text-secondary,#666); font-weight:600;"><?php echo htmlspecialchars($hospital_gu); ?></strong> | <?php endif; ?><?php echo htmlspecialchars($doctor['hospital_address']); ?></div>
                    <?php endif; ?>
                    <div class="hospital-meta">
                        <div class="hospital-rating">
                            <span class="hospital-wishlist"><?php renderWishlistButton('hospital', (int)$doctor['hospital_id'], 'sm', false, 'star'); ?></span>
                            <span class="rating-score"><?php echo number_format(!empty($doctor['hospital_rating']) ? $doctor['hospital_rating'] : 0, 1); ?></span>
                            <span class="review-count">(<?php echo number_format((int)($doctor['hospital_review_count'] ?? 0)); ?>)</span>
                        </div>
                        <?php if (!empty($doctor['hospital_ad_grade']) && $doctor['hospital_ad_grade'] !== 'none'): ?>
                        <span class="popular-hospital"><i class="bi bi-trophy"></i>인기병원</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
