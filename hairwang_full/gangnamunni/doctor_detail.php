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
    <?php require_once __DIR__ . "/seo.php"; echo generateSeoulbeautyCanonicalLink(); ?>
    <?php require_once __DIR__ . "/theme_helper.php"; echo generateFaviconLinks(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script>
        // 다크모드 anti-flash
        (function(){
            var t = localStorage.getItem('theme') || localStorage.getItem('admin_theme');
            if (t === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
<style>
:root{--ink:#1A1718;--g700:#4A4446;--g400:#9A9395;--line:#ECE9EA;--pri:#AC1830}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Pretendard,'Noto Sans KR',-apple-system,sans-serif;background:#fff;color:var(--ink);letter-spacing:-0.01em}
a{color:inherit;text-decoration:none}
.container{max-width:640px;margin:0 auto;padding-bottom:83px;background:#fff;min-height:100vh}
.dd-bar{height:52px;display:flex;align-items:center;padding:0 8px}
.dd-back{width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:none;border:0;color:var(--ink);cursor:pointer}
.dd-title{flex:1;text-align:center;font-size:20px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dd-sp{width:44px}
.dd-profile{margin:0 20px;padding:8px 0 18px;display:flex;align-items:center;gap:16px;border-bottom:1px solid var(--line)}
.dd-photo{width:72px;height:72px;border-radius:50%;background:#F4F2F3;object-fit:cover;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;font-size:32px}
.dd-photo img{width:100%;height:100%;object-fit:cover}
.dd-pinfo{display:flex;flex-direction:column;gap:6px}
.dd-name{font-size:20px;font-weight:700}
.dd-spec{display:flex;align-items:center;gap:5px;font-size:15px;color:var(--pri)}
.dd-sec{margin:0 20px;padding:18px 0;border-bottom:1px solid var(--line)}
.dd-h{font-size:18px;font-weight:700;margin-bottom:10px}
.dd-chips{display:flex;gap:6px;flex-wrap:wrap}
.dd-chip{padding:3px 7px;background:#F1EFEF;font-size:14px;font-weight:500}
.dd-list{margin:0 20px;padding:18px 0 18px 16px;border-bottom:1px solid var(--line);font-size:16px;line-height:2;color:var(--g700)}
.dd-hh{padding:20px 20px 12px;font-size:18px;font-weight:700}
.dd-hosp{margin:0 20px;display:flex;gap:12px}
.dd-himg{width:62px;height:62px;border:1px solid var(--line);flex-shrink:0;background:#F4F2F3;object-fit:cover;display:flex;align-items:center;justify-content:center;overflow:hidden}
.dd-himg img{width:100%;height:100%;object-fit:cover}
.dd-hinfo{min-width:0;display:flex;flex-direction:column;gap:4px}
.dd-hname{font-size:19px;font-weight:700}
.dd-haddr{font-size:15px;color:var(--g700)}
.dd-sep{color:#CFC8CB}
.dd-hmeta{display:flex;gap:12px;font-size:14px;align-items:center}
.dd-hmeta span{display:flex;align-items:center;gap:3px}
.dd-hmeta i{font-style:normal;color:var(--g400)}
</style>
</head>
<body>
<div class="container">
    <div class="dd-bar">
        <button class="dd-back" onclick="history.back()" aria-label="뒤로 가기"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></button>
        <div class="dd-title"><?php echo htmlspecialchars($doctor['name']); ?></div>
        <div class="dd-sp"></div>
    </div>

    <div class="dd-profile">
        <div class="dd-photo">
            <?php if (!empty($doctor['photo_url'])): ?><img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>"><?php else: ?>👨‍⚕️<?php endif; ?>
        </div>
        <div class="dd-pinfo">
            <div class="dd-name"><?php echo htmlspecialchars($doctor['name']); ?></div>
            <?php if (trim($doctor['specialty'] ?? '') !== ''): ?>
            <div class="dd-spec"><i class="bi bi-file-medical"></i><?php echo htmlspecialchars(trim($doctor['specialty'])); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($doctor_treatments)): ?>
    <div class="dd-sec">
        <div class="dd-h">주요 진료분야</div>
        <div class="dd-chips">
            <?php foreach ($doctor_treatments as $dt): ?>
            <span class="dd-chip"><?php echo htmlspecialchars($dt['treatment_name']); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($qualification_lines)): ?>
    <ul class="dd-list">
        <?php foreach ($qualification_lines as $l): ?><li><?php echo htmlspecialchars($l); ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($bio_lines)): ?>
    <ul class="dd-list">
        <?php foreach ($bio_lines as $l): ?><li><?php echo htmlspecialchars($l); ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($doctor['hospital_name'])): ?>
    <?php
    $hospital_gu = '';
    foreach (preg_split('/\s+/', trim($doctor['hospital_address'] ?? '')) as $_ap) {
        if ($_ap !== '' && mb_substr($_ap, -1) === '구') { $hospital_gu = $_ap; break; }
    }
    ?>
    <div class="dd-hh">소속 병원</div>
    <a class="dd-hosp" href="detail.php?id=<?php echo (int)$doctor['hospital_id']; ?>">
        <div class="dd-himg"><?php if (!empty($doctor['hospital_image'])): ?><img src="<?php echo htmlspecialchars($doctor['hospital_image']); ?>" alt=""><?php endif; ?></div>
        <div class="dd-hinfo">
            <div class="dd-hname"><?php echo htmlspecialchars($doctor['hospital_name']); ?></div>
            <?php if (!empty($doctor['hospital_address'])): ?>
            <div class="dd-haddr"><?php if ($hospital_gu !== ''): ?><?php echo htmlspecialchars(mb_substr($hospital_gu, 0, -1)); ?> <span class="dd-sep">|</span> <?php endif; ?><?php echo htmlspecialchars($doctor['hospital_address']); ?></div>
            <?php endif; ?>
            <div class="dd-hmeta">
                <span><img src="assets/icons/categories/rating_2.svg" alt="" width="13" height="13"><b><?php echo number_format(!empty($doctor['hospital_rating']) ? (float)$doctor['hospital_rating'] : 0, 1); ?></b><i>(<?php echo number_format((int)($doctor['hospital_review_count'] ?? 0)); ?>)</i></span>
                <?php if (!empty($doctor['hospital_ad_grade']) && $doctor['hospital_ad_grade'] !== 'none'): ?><span style="font-weight:500">인기병원</span><?php endif; ?>
            </div>
        </div>
    </a>
    <?php endif; ?>
</div>

<?php $bottom_nav_active = 'hospital'; include __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
