<?php

/**
 * 병원 입점 안내 + 입점 신청서 (about-apply.php)
 * - 공개 페이지(비로그인). 신청은 gangnamunni_hospitals 에 status='pending' 으로 접수된다.
 * - 심사: admin_requested_hospitals.php (최고관리자 전용)
 *   승인되면 status='approved', is_active=1 이 되고 그때부터 admin_hospitals.php 와 앱에 나타난다.
 * @created 2026-10-13
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ilova WebView eski nusxani ko'rsatmasligi uchun sahifa keshlanmaydi.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/includes/client_ip.php';

// PHP 8 부터 mysqli 는 기본적으로 예외를 던진다. 공개 페이지가 흰 화면이 되지 않도록
// 이 페이지에서는 예외를 끄고, DB 가 없으면 안내 섹션만이라도 보여준다.
mysqli_report(MYSQLI_REPORT_OFF);
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
} else {
    error_log('[about-apply] DB 연결 실패: ' . mysqli_connect_error());
}

// CSRF 토큰
if (empty($_SESSION['apply_lead_csrf'])) $_SESSION['apply_lead_csrf'] = bin2hex(random_bytes(32));
$apply_csrf = $_SESSION['apply_lead_csrf'];

$apply_error   = '';
$apply_error_kind = 'input'; // 'input' = 폼 안내 문구, 'system' = 전송 실패 시트
$apply_success = isset($_GET['sent']) && $_GET['sent'] === '1';

// 완료 화면 요약. 직접 ?sent=1 로 들어온 경우엔 비어 있을 수 있다.
$apply_done = [];
if ($apply_success && !empty($_SESSION['apply_done'])) {
    $apply_done = (array)$_SESSION['apply_done'];
    unset($_SESSION['apply_done']);
}

// 진료과목: 폼에 있는 값만 허용
$APPLY_DEPARTMENTS = ['피부과', '성형외과', '피부과·성형외과', '치과', '기타'];
$APPLY_SIDO = [
    '서울특별시',
    '부산광역시',
    '대구광역시',
    '인천광역시',
    '광주광역시',
    '대전광역시',
    '울산광역시',
    '세종특별자치시',
    '경기도',
    '강원특별자치도',
    '충청북도',
    '충청남도',
    '전북특별자치도',
    '전라남도',
    '경상북도',
    '경상남도',
    '제주특별자치도',
];

// 다시 채워줄 입력값 (오류 시)
$old = [
    'hospital_name' => '',
    'department' => '',
    'sido' => '',
    'address_detail' => '',
    'manager' => '',
    'phone' => '',
    'email' => '',
    'memo' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? ''));

        $client_ip = function_exists('seoulbeauty_client_ip') ? seoulbeauty_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!$connect) {
            $apply_error = '일시적인 오류로 신청을 접수하지 못했어요. 잠시 후 다시 시도해 주세요.'; $apply_error_kind = 'system';
        } elseif (empty($_POST['csrf_token']) || !hash_equals($apply_csrf, (string)$_POST['csrf_token'])) {
            $apply_error = '세션이 만료되었어요. 페이지를 새로고침한 뒤 다시 신청해 주세요.';
        } elseif (trim((string)($_POST['website'] ?? '')) !== '') {
            // 허니팟: 사람이라면 비어 있어야 한다. 봇에게는 성공처럼 보이게 한다.
            header('Location: about-apply.php?sent=1#apply-form-title');
            exit;
        } elseif ($old['hospital_name'] === '' || mb_strlen($old['hospital_name']) > 100) {
            $apply_error = '병원명을 100자 이내로 입력해 주세요.';
        } elseif (!in_array($old['department'], $APPLY_DEPARTMENTS, true)) {
            $apply_error = '진료과목을 선택해 주세요.';
        } elseif (!in_array($old['sido'], $APPLY_SIDO, true)) {
            $apply_error = '병원 지역(시/도)을 선택해 주세요.';
        } elseif (mb_strlen($old['address_detail']) > 200) {
            $apply_error = '상세 주소는 200자 이내로 입력해 주세요.';
        } elseif ($old['manager'] === '' || mb_strlen($old['manager']) > 50) {
            $apply_error = '담당자 성함을 50자 이내로 입력해 주세요.';
        } elseif (!preg_match('/^01[016789]-?\d{3,4}-?\d{4}$/', str_replace(' ', '', $old['phone']))) {
            $apply_error = '연락처를 010-0000-0000 형식으로 입력해 주세요.';
        } elseif ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
            // 승인 시 이 이메일로 병원 관리자 계정이 발급되므로 필수다.
            $apply_error = '이메일을 정확히 입력해 주세요. 승인 시 이 주소로 관리자 계정이 발급돼요.';
        } elseif (mb_strlen($old['memo']) > 2000) {
            $apply_error = '문의 내용은 2000자 이내로 입력해 주세요.';
        } elseif (empty($_POST['privacy_agree'])) {
            $apply_error = '개인정보 수집·이용에 동의해 주세요.';
        } else {
            // 같은 IP 에서 10분 내 3건 이상이면 차단
            $rate_limited = false;
            $ip_pattern = 'about_' . $client_ip . '%';
            if ($stmt = mysqli_prepare($connect, "SELECT COUNT(*) FROM gangnamunni_hospitals WHERE applied_by LIKE ? AND applied_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)")) {
                mysqli_stmt_bind_param($stmt, 's', $ip_pattern);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $recent_cnt);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);
                $rate_limited = ((int)$recent_cnt >= 3);
            }

            // 하나의 이메일 = 하나의 병원: 이미 심사 중이거나 승인된 이메일은 다시 신청할 수 없다.
            $dup_status = '';
            if (!$rate_limited && ($stmt = mysqli_prepare($connect, "SELECT status FROM gangnamunni_hospitals WHERE LOWER(email) = LOWER(?) AND status IN ('pending','approved') LIMIT 1"))) {
                mysqli_stmt_bind_param($stmt, 's', $old['email']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $dup_status);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);
            }

            if ($rate_limited) {
                $apply_error = '잠시 후 다시 신청해 주세요. (짧은 시간에 너무 많이 신청했어요)';
            } elseif ($dup_status === 'pending') {
                $apply_error = '이미 심사 중인 이메일이에요. 담당자 연락을 기다려 주세요.';
            } elseif ($dup_status === 'approved') {
                $apply_error = '이미 입점이 완료된 이메일이에요. 문의가 있으시면 담당자에게 연락해 주세요.';
            } else {
                $phone_norm = preg_replace('/[^0-9]/', '', $old['phone']);
                if (strlen($phone_norm) === 11) {
                    $phone_norm = substr($phone_norm, 0, 3) . '-' . substr($phone_norm, 3, 4) . '-' . substr($phone_norm, 7);
                } elseif (strlen($phone_norm) === 10) {
                    $phone_norm = substr($phone_norm, 0, 3) . '-' . substr($phone_norm, 3, 3) . '-' . substr($phone_norm, 6);
                }

                $full_address = trim($old['sido'] . ' ' . $old['address_detail']);
                // categories ustuni JSON massiv bo'lishi shart (jadvalda json_valid CHECK bor).
                // "피부과·성형외과" kabi qoʻshma tanlov ikkita elementga boʻlinadi.
                $category_list = array_values(array_filter(array_map('trim', explode('·', $old['department'])), function ($v) { return $v !== ''; }));
                $categories_json = json_encode($category_list, JSON_UNESCAPED_UNICODE);
                $applied_by   = 'about_' . $client_ip . '_' . time();
                // 문의 내용은 심사 화면(admin_requested_hospitals.php)에서 바로 보이도록 description 에 담는다.
                $description  = $old['memo'];

                $sql = "INSERT INTO gangnamunni_hospitals
                        (name, phone, contact_phone, email, contact_name, address, categories, description,
                         applied_by, applied_at, status, is_active, ad_grade, is_demo, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 0, 'none', 0, NOW())";
                if ($stmt = mysqli_prepare($connect, $sql)) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        'sssssssss',
                        $old['hospital_name'],
                        $phone_norm,
                        $phone_norm,
                        $old['email'],
                        $old['manager'],
                        $full_address,
                        $categories_json,
                        $description,
                        $applied_by
                    );
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    if ($ok) {
                        // 완료 화면에 보여줄 신청 요약 (1회용 — 새로고침하면 사라진다)
                        $_SESSION['apply_done'] = [
                            'hospital_name' => $old['hospital_name'],
                            'department'    => $old['department'],
                            'sido'          => $old['sido'],
                            'manager'       => $old['manager'],
                            'phone'         => $phone_norm,
                        ];
                        // PRG: 새로고침 시 중복 접수 방지
                        header('Location: about-apply.php?sent=1');
                        exit;
                    }
                    error_log('[about-apply] INSERT 실패: ' . mysqli_error($connect));
                } else {
                    error_log('[about-apply] prepare 실패: ' . mysqli_error($connect));
                }
                $apply_error = '일시적인 오류로 신청을 접수하지 못했어요. 잠시 후 다시 시도해 주세요.'; $apply_error_kind = 'system';
            }
        }
    } catch (Throwable $e) {
        error_log('[about-apply] 예외: ' . $e->getMessage());
        $apply_error = '일시적인 오류로 신청을 접수하지 못했어요. 잠시 후 다시 시도해 주세요.'; $apply_error_kind = 'system';
    }
}

/** @param mixed $v */
function apply_h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">

<head>
    <?php require_once __DIR__ . '/../lib/site_favicon.php';
    echo seoulBeautyFaviconLinks(); ?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>병원 입점 안내 | 서울미녀</title>
    <meta name="description" content="피부과·성형외과 미용시술 가격 비교 플랫폼 서울미녀에 병원을 등록하고 시술·지역으로 병원을 찾는 고객을 만나보세요. 2027년 6월까지 입점비 0원.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <script>
        // Keep downward gestures available for normal page scrolling in the app.
        window._pullToRefreshInstalled = true;
    </script>
    <style>
        :root {
            --brand: #AC1830;
            --ink: #171717;
            --text: #444;
            --muted: #8a8a8a;
        }

        * {
            box-sizing: border-box;
        }

        html {
            overscroll-behavior-y: none;
        }

        body {
            margin: 0;
            background: #fff;
            color: var(--ink);
            font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", sans-serif;
            -webkit-font-smoothing: antialiased;
            word-break: keep-all;
        }

        .about-page {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }

        /* ---------- Header (about_project.php bilan bir xil) ---------- */
        .about-header {
            height: auto;
            aspect-ratio: 640 / 70;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 3.2%;
            background: #ffffff;
        }

        /* 로고는 헤더 폭에 비례해 커진다 (작은 폰 70px ~ 큰 화면 128px) */
        .about-brand {
            width: 19%;
            min-width: 72px;
            max-width: 128px;
            display: block;
        }

        .about-brand img {
            display: block;
            width: 100%;
            height: auto;
        }

        .about-share {
            display: flex;
            align-items: center;
            gap: 6px;
            border: 0;
            padding: 6px 0 6px 10px;
            color: #333;
            background: transparent;
            font: inherit;
            font-size: clamp(12px, 5.1vw, 33px);
            cursor: pointer;
        }

        .about-share svg {
            width: 1em;
            height: 1em;
        }

        /* ---------- 한 화면 = 한 섹션 (세로 스냅) ----------
           스와이프 한 번 = 한 화면. 스크롤 컨테이너는 .about-page 이고,
           화면보다 긴 섹션(신청서)은 그 섹션 안에서만 스크롤된다. */
        html, body { height: 100%; }
        body { overflow: hidden; }

        .about-page {
            height: 100vh;
            height: 100dvh;
            overflow-y: scroll;
            scroll-behavior: smooth;
            /* mandatory emas, proximity: oxirgi (신청서) bo'limi ekrandan baland,
               mandatory bo'lganda iOS uni pastga yetganda majburan yuqoriga tortib yuboradi */
            scroll-snap-type: none; /* snap yo'q — oddiy, erkin skroll */
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .about-page::-webkit-scrollbar { width: 0; height: 0; }

        .page {
            /* height 가 아니라 min-height: 내용이 길면 섹션이 그만큼 늘어나고
               스크롤은 바깥 컨테이너(.about-page) 하나만 담당한다.
               (섹션마다 overflow 를 주면 내용이 딱 맞는 섹션이 '스크롤 안 되는 스크롤 영역'이 되어
                휠·터치가 부모로 전달되지 않고 페이지 전체가 멈춰버린다) */
            min-height: 100vh;
            min-height: 100dvh;
            /* snap o'chirilgan: bo'limlar oddiy skroll bilan ketma-ket ochiladi */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        /* 완료 화면은 스냅 없이 평범하게 스크롤된다 */
        .about-page--plain { scroll-snap-type: none; }

        /* 모달이 열리면 뒤 화면도 잠근다 */
        body.pv-locked .about-page { overflow: hidden; }

        /* 1번 화면: 헤더 → (위쪽에서 시작하는) 히어로 → 바닥에 붙는 입점비 띠.
           시안 비율: 히어로 텍스트는 위에서 약 8% 지점, 빨간 띠는 화면 아래 약 30%. */
        .page--hero { padding: 0; }
        .page--hero .about-header { flex: none; }
        /* 히어로 텍스트는 남는 공간의 가운데보다 살짝 위에 놓인다.
           (16 Pro Max 처럼 긴 화면에서 텍스트 아래 여백만 커지지 않도록) */
        .page--hero .apply-hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-top: clamp(20px, 5vh, 56px);
            padding-bottom: clamp(18px, 4vh, 48px);
        }
        .page--hero .apply-offer {
            flex: none;
            min-height: 55vh;
            min-height: 55dvh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* 세로로 짧은 화면(가로 모드 등)에서는 빨간 띠가 화면을 잡아먹지 않게 */
        @media (max-height: 560px) {
            .page--hero .apply-offer { min-height: 0; }
            .page--hero .apply-hero { padding-top: clamp(16px, 5vh, 40px); padding-bottom: clamp(14px, 4vh, 32px); }
        }

        /* 내용이 화면보다 길면 섹션이 늘어나고 평소처럼 스크롤된다 */
        .apply-why, .apply-process, .apply-check {
            padding-top: clamp(36px, 9vw, 60px);
            padding-bottom: clamp(36px, 9vw, 60px);
            /* 내용이 화면보다 짧으면 세로 가운데 정렬 — 아래에 큰 여백이 남지 않는다 */
            justify-content: center;
        }
        /* uzun forma bo'limi snap nuqtasi bo'lmasin — ichida erkin skroll qilinadi */
        .apply-form-sec { justify-content: flex-start; }

        /* CHECKLIST bo'limi to'liq ekran emas, 75dvh */
        .apply-check {
            min-height: 75vh;
            min-height: 75dvh;
        }

        @media (prefers-reduced-motion: reduce) {
            .about-page { scroll-behavior: auto; }
        }

        /* ---------- Hero ---------- */
        .apply-hero {
            padding: clamp(42px, 11vw, 70px) clamp(20px, 5.6vw, 36px) clamp(40px, 10vw, 64px);
        }

        .apply-eyebrow {
            margin: 0 0 clamp(14px, max(3.4vw, 1.9vh), 26px);
            color: var(--brand);
            font-size: clamp(17px, calc(max(3vw, 1.7vh) + 5px), 25px);
            font-weight: 500;
            letter-spacing: .42em;
        }

        /* sarlavha va qo'l ishorasi bir qatorda: qo'l o'ngda, pastki chekkaga tekislangan */
        .apply-title-row {
            display: flex;
            align-items: flex-end;
            gap: clamp(8px, 2.4vw, 18px);
            margin: 0 0 clamp(22px, max(5.6vw, 3.1vh), 44px);
        }

        .apply-title {
            flex: 1 1 auto;
            min-width: 0;
            margin: 0;
            font-size: clamp(26px, max(7vw, 4.6vh), 52px);
            font-weight: 800;
            line-height: 1.32;
            letter-spacing: -.03em;
        }

        .apply-lead {
            margin: 0;
            color: var(--text);
            font-size: clamp(13px, max(3.4vw, 2.1vh), 24px);
            font-weight: 400;
            line-height: 1.85;
        }

        /* Pastga surish ishorasi: about_project1.php dagi qo'l rasmi.
           Harakat vertikal: pastdan yuqoriga ko'tariladi, yuqorida so'nadi va
           yana pastdan paydo bo'ladi (orqaga tushib kelmaydi).
           Rasm atrofidagi bo'sh joy kesilgan — shuning uchun konteyner overflow: hidden. */
        .scroll-hint {
            align-self: flex-end;
            flex: 0 0 auto;
            display: block;
            width: clamp(32px, 8.2vw, 48px);
            margin: 0 0 clamp(4px, 1.2vw, 10px) 0;
            animation: hint-swipe-up 2.4s linear infinite;
        }

        .scroll-hint img {
            display: block;
            width: 100%;
            height: auto;
            transform: rotate(-15deg);
        }

        @keyframes hint-swipe-up {
            /* har bir segmentga ease-in-out alohida qo'llanmasligi uchun linear:
               aks holda qo'l o'rtada sekinlashib "to'xtaganday" ko'rinadi */
            0%        { transform: translateY(12px); opacity: 0; animation-timing-function: ease-out; }
            12%       { transform: translateY(6px); opacity: 1; animation-timing-function: linear; }
            78%       { transform: translateY(-88px); opacity: 1; animation-timing-function: linear; }
            92%       { transform: translateY(-112px); opacity: 0; }
            93%, 100% { transform: translateY(12px); opacity: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            .scroll-hint { animation: none; }
        }

        /* ---------- Qizil lenta: 모든 광고비 0원 ---------- */
        .apply-offer {
            background: var(--brand);
            color: #fff;
            text-align: center;
            padding: clamp(28px, 7.4vw, 48px) clamp(20px, 5.6vw, 36px) clamp(30px, 8vw, 52px);
        }

        /* lead matni qizil lenta ichida — oq rangda */
        .apply-offer .apply-lead {
            color: #fff;
            margin: 0 0 clamp(18px, 4.6vw, 32px);
        }

        /* oq, yumaloq karta: ichidagi matn brend rangida */
        .offer-card {
            background: #fff;
            color: var(--brand);
            border-radius: clamp(22px, 6.5vw, 44px);
            padding: clamp(20px, 5.4vw, 36px) clamp(16px, 4.4vw, 30px) clamp(22px, 5.8vw, 38px);
            margin: 0 clamp(0px, 1.6vw, 14px);
        }

        .apply-offer-main .offer-free {
            color: var(--brand);
        }

        .apply-offer-top {
            margin: 0 0 clamp(10px, max(2.6vw, 1.5vh), 20px);
            font-size: clamp(18px, calc(max(3.4vw, 2.1vh) + 5px), 29px);
            font-weight: 500;
        }

        .apply-offer-main {
            color: #1a1a1a;
            margin: 0 0 clamp(10px, max(2.6vw, 1.5vh), 20px);
            font-size: clamp(30px, calc(max(6.9vw, 4.2vh) + 5px), 56px);
            font-weight: 900;
            letter-spacing: -.03em;
            line-height: 1.1;
        }

        .apply-offer-sub {
            margin: 0;
            font-size: clamp(18px, calc(max(3.4vw, 2.1vh) + 5px), 29px);
            font-weight: 500;
        }


        /* ---------- WHY SEOUL BEAUTY ---------- */
        .apply-why {
            padding: clamp(28px, 7vw, 52px) clamp(20px, 5.6vw, 36px) clamp(26px, 6.5vw, 46px);
        }

        .apply-h2 {
            margin: 0 0 clamp(18px, 4.6vw, 34px);
            font-size: clamp(29px, calc(6.4vw + 5px), 46px);
            font-weight: 800;
            line-height: 1.34;
            letter-spacing: -.03em;
        }

        /* WHY sarlavhasi +5px siz, asl o'lchamda */
        #apply-why-title {
            font-size: clamp(24px, 6.4vw, 41px);
        }

        .why-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: clamp(9px, 2.4vw, 16px);
        }

        .why-card {
            display: flex;
            align-items: center;
            gap: clamp(11px, 2.9vw, 19px);
            background: #fcfafa;
            border: 1px solid #f0eaea;
            border-radius: clamp(14px, 3.6vw, 22px);
            padding: clamp(13px, 3.4vw, 23px) clamp(14px, 3.8vw, 25px);
        }

        .why-icon {
            flex: none;
            width: clamp(38px, 9.6vw, 62px);
            height: clamp(38px, 9.6vw, 62px);
            border-radius: 50%;
            background: #f8e7ea;
            color: var(--brand);
            display: grid;
            place-items: center;
        }

        .why-icon img {
            width: 48%;
            height: 48%;
            object-fit: contain;
        }

        .why-body {
            min-width: 0;
        }

        .why-title {
            margin: 0 0 clamp(5px, 1.4vw, 10px);
            font-size: clamp(19px, calc(3.7vw + 5px), 29px);
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .why-desc {
            margin: 0;
            color: var(--text);
            font-size: clamp(17px, calc(3.1vw + 5px), 25px);
            line-height: 1.55;
        }





        /* ---------- APPLY (입점 신청서) ---------- */
        .apply-form-sec {
            background: #f4f3f3;
            padding: clamp(42px, 11vw, 72px) clamp(20px, 5.6vw, 36px) clamp(46px, 12vw, 78px);
        }

        .req-mark {
            color: var(--brand);
        }

        .form-lead {
            margin: clamp(16px, 4.2vw, 26px) 0 clamp(26px, 6.8vw, 44px);
            color: var(--text);
            font-size: clamp(13px, 3.4vw, 21px);
            line-height: 1.75;
        }

        .form-card {
            background: #fff;
            border-radius: clamp(16px, 4.2vw, 26px);
            padding: clamp(22px, 5.6vw, 38px) clamp(18px, 4.6vw, 30px) clamp(24px, 6vw, 38px);
        }

        .field {
            border: 0;
            margin: 0 0 clamp(26px, 6.8vw, 44px);
            padding: 0;
        }

        .field-label {
            display: block;
            margin: 0 0 clamp(10px, 2.6vw, 16px);
            padding: 0;
            font-size: clamp(13.5px, 3.5vw, 21px);
            font-weight: 500;
            color: var(--ink);
        }

        .input {
            display: block;
            width: 100%;
            background: #fff;
            color: var(--ink);
            border: 1px solid #e3e0e0;
            border-radius: clamp(8px, 2vw, 12px);
            padding: clamp(13px, 3.4vw, 21px) clamp(13px, 3.4vw, 20px);
            font-family: inherit;
            font-size: clamp(13.5px, 3.5vw, 21px);
            line-height: 1.4;
            -webkit-appearance: none;
            appearance: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .input::placeholder {
            color: #b5b0b0;
        }

        .input:focus {
            outline: 0;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(172, 24, 48, .1);
        }

        .input.mt-sm {
            margin-top: clamp(8px, 2vw, 13px);
        }

        .textarea {
            min-height: clamp(110px, 29vw, 185px);
            resize: vertical;
            line-height: 1.6;
        }

        .select-wrap {
            position: relative;
        }

        .select {
            padding-right: clamp(38px, 9.5vw, 58px);
            cursor: pointer;
        }

        .select:invalid,
        .select option[value=""] {
            color: var(--ink);
        }

        .select-wrap::after {
            content: "";
            position: absolute;
            right: clamp(14px, 3.6vw, 22px);
            top: 50%;
            pointer-events: none;
            width: clamp(11px, 2.8vw, 17px);
            height: clamp(11px, 2.8vw, 17px);
            transform: translateY(-62%) rotate(45deg);
            border-right: 2px solid #222;
            border-bottom: 2px solid #222;
            border-radius: 0 0 3px 0;
        }

        /* 진료과목 chip */
        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(8px, 2.2vw, 14px);
        }

        .chip {
            position: relative;
        }

        .chip input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
        }

        .chip span {
            display: block;
            border: 1px solid #e3e0e0;
            border-radius: clamp(8px, 2vw, 12px);
            padding: clamp(12px, 3.1vw, 19px) clamp(16px, 4.2vw, 26px);
            font-size: clamp(13px, 3.4vw, 21px);
            color: #b0abab;
            text-align: center;
            transition: .15s;
        }

        .chip input:checked+span {
            border-color: var(--brand);
            background: #fbeef0;
            color: var(--brand);
            font-weight: 500;
        }

        .chip input:focus-visible+span {
            outline: 3px solid var(--brand);
            outline-offset: 2px;
        }

        .form-hr {
            border: 0;
            border-top: 1px solid #ececec;
            margin: clamp(24px, 6.2vw, 40px) 0 clamp(16px, 4.2vw, 26px);
        }

        .agree-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .agree {
            display: flex;
            align-items: center;
            gap: clamp(9px, 2.4vw, 15px);
            cursor: pointer;
        }

        .agree input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .agree-box {
            flex: none;
            width: clamp(17px, 4.4vw, 27px);
            height: clamp(17px, 4.4vw, 27px);
            border: 1px solid #c9c4c4;
            border-radius: 3px;
            background: #fff;
            position: relative;
            transition: .15s;
        }

        .agree input:checked+.agree-box {
            background: var(--brand);
            border-color: var(--brand);
        }

        .agree input:checked+.agree-box::after {
            content: "";
            position: absolute;
            left: 32%;
            top: 14%;
            width: 26%;
            height: 52%;
            border-right: 2px solid #fff;
            border-bottom: 2px solid #fff;
            transform: rotate(42deg);
        }

        .agree input:focus-visible+.agree-box {
            outline: 3px solid var(--brand);
            outline-offset: 2px;
        }

        .agree-text {
            font-size: clamp(12.5px, 3.2vw, 20px);
            color: var(--ink);
            line-height: 1.4;
        }

        .agree-toggle {
            flex: none;
            background: none;
            border: 0;
            padding: 4px 0;
            color: #8a8a8a;
            font: inherit;
            font-size: clamp(12px, 3vw, 18px);
            text-decoration: underline;
            cursor: pointer;
        }

        .privacy-box {
            margin-top: clamp(12px, 3.1vw, 19px);
            background: #f4f3f3;
            border-radius: clamp(8px, 2vw, 12px);
            padding: clamp(14px, 3.6vw, 23px) clamp(14px, 3.6vw, 22px);
        }

        .privacy-box[hidden] {
            display: none;
        }

        .privacy-box p {
            margin: 0;
            color: #4a4a4a;
            font-size: clamp(12.5px, 3.2vw, 20px);
            line-height: 1.75;
        }

        .form-error {
            margin: clamp(14px, 3.6vw, 22px) 0 0;
            color: var(--brand);
            font-size: clamp(12.5px, 3.2vw, 19px);
            line-height: 1.6;
        }

        .form-error[hidden] {
            display: none;
        }

        .submit-btn {
            display: block;
            width: 100%;
            margin-top: clamp(20px, 5.2vw, 34px);
            background: var(--brand);
            color: #fff;
            border: 0;
            border-radius: clamp(6px, 1.5vw, 9px);
            padding: clamp(15px, 4vw, 25px);
            font-family: inherit;
            font-size: clamp(15px, 3.9vw, 24px);
            font-weight: 700;
            letter-spacing: -.01em;
            cursor: pointer;
            transition: filter .15s;
        }

        .submit-btn:hover {
            filter: brightness(1.08);
        }

        .submit-btn:active {
            filter: brightness(.92);
        }

        .hp {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .form-error--server {
            margin: 0 0 clamp(18px, 4.6vw, 28px);
            padding: clamp(12px, 3.1vw, 19px) clamp(13px, 3.4vw, 20px);
            background: #fbeef0;
            border-radius: clamp(8px, 2vw, 12px);
        }

        .form-done {
            text-align: center;
            padding-top: clamp(34px, 8.8vw, 56px);
            padding-bottom: clamp(34px, 8.8vw, 56px);
        }

        .done-title {
            margin: 0 0 clamp(10px, 2.6vw, 16px);
            color: var(--brand);
            font-size: clamp(18px, 4.8vw, 30px);
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .done-desc {
            margin: 0 0 clamp(20px, 5.2vw, 32px);
            color: var(--text);
            font-size: clamp(13px, 3.4vw, 21px);
            line-height: 1.75;
        }

        .done-link {
            display: inline-block;
            color: #8a8a8a;
            font-size: clamp(12.5px, 3.2vw, 19px);
            text-decoration: underline;
        }

        /* ---------- CHECKLIST ---------- */
        .apply-check {
            padding: clamp(24px, 6vw, 44px) clamp(20px, 5.6vw, 36px) clamp(24px, 6vw, 44px);
        }

        .check-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: clamp(16px, 4.2vw, 26px);
            padding: clamp(15px, 3.9vw, 26px) clamp(16px, 4.2vw, 27px) clamp(15px, 3.9vw, 26px);
            box-shadow: 0 1px 2px rgba(0, 0, 0, .02);
        }

        .check-card+.check-card {
            margin-top: clamp(11px, 2.9vw, 19px);
        }

        .check-title {
            margin: 0 0 clamp(10px, 2.6vw, 18px);
            font-size: clamp(21px, calc(4.2vw + 5px), 32px);
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .check-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: clamp(6px, 1.6vw, 11px);
        }

        .check-list li {
            position: relative;
            padding-left: clamp(19px, 4.8vw, 30px);
            font-size: clamp(18.5px, calc(3.5vw + 5px), 27px);
            line-height: 1.45;
            color: var(--ink);
        }

        .check-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 0;
            color: var(--brand);
            font-weight: 500;
        }

        .asset-grid {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: clamp(8px, 2.2vw, 14px);
        }

        .asset-grid li {
            background: #f4f3f3;
            border-radius: clamp(7px, 1.8vw, 11px);
            padding: clamp(9px, 2.3vw, 15px) clamp(8px, 2vw, 14px);
            text-align: center;
            font-size: clamp(18px, calc(3.4vw + 5px), 26px);
            line-height: 1.4;
            color: var(--ink);
        }

        .check-note {
            display: flex;
            gap: clamp(6px, 1.6vw, 11px);
            margin: clamp(9px, 2.3vw, 15px) 0 0;
            color: var(--brand);
            font-size: clamp(12px, 3.1vw, 19px);
            line-height: 1.65;
        }

        .check-note>span {
            flex: none;
        }

        /* ---------- PROCESS (timeline) ---------- */
        .apply-process {
            background: #f4f3f3;
            padding: clamp(28px, 7vw, 52px) clamp(20px, 5.6vw, 36px) clamp(28px, 7vw, 52px);
            --dot: clamp(14px, 3.5vw, 22px);
            --gap: clamp(24px, 6.4vw, 44px);
            /* nuqta sarlavha qatorining markaziga tushsin (sarlavha o'lchami bilan bir xil formula) */
            --dot-top: calc((clamp(20px, calc(3.9vw + 5px), 30px) * 1.4 - var(--dot)) / 2);
        }

        .step-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: var(--gap);
        }

        .step {
            position: relative;
            display: grid;
            grid-template-columns: var(--dot) 1fr;
            column-gap: clamp(14px, 3.8vw, 26px);
            align-items: start;
        }

        .step-dot {
            width: var(--dot);
            height: var(--dot);
            margin-top: var(--dot-top);
            border-radius: 50%;
            background: var(--brand);
        }

        /* bo'g'inlar: har bir qadamdan keyingisiga tushadigan chiziq */
        .step:not(:last-child)::before {
            content: "";
            position: absolute;
            width: 1.5px;
            background: var(--brand);
            left: calc(var(--dot) / 2 - .75px);
            top: calc(var(--dot-top) + var(--dot) / 2);
            bottom: calc(-1 * var(--gap) - var(--dot-top));
        }

        .step-title {
            margin: 0 0 clamp(5px, 1.4vw, 10px);
            color: var(--brand);
            font-size: clamp(20px, calc(3.9vw + 5px), 30px);
            font-weight: 700;
            letter-spacing: -.02em;
            line-height: 1.4;
        }

        .step-desc {
            margin: 0;
            color: #3a3a3a;
            font-size: clamp(17.5px, calc(3.2vw + 5px), 26px);
            line-height: 1.7;
        }

        /* timeline animatsiyasi */
        .step-dot {
            transform: scale(0);
            transition: transform .45s cubic-bezier(.34, 1.56, .64, 1);
        }

        .step.is-in .step-dot {
            transform: scale(1);
        }

        .step-body {
            opacity: 0;
            transform: translateY(14px);
            transition: opacity .55s ease-out .12s, transform .55s cubic-bezier(.22, .61, .36, 1) .12s;
        }

        .step.is-in .step-body {
            opacity: 1;
            transform: none;
        }

        .step:not(:last-child)::before {
            transform: scaleY(0);
            transform-origin: top;
            transition: transform .55s linear .25s;
        }

        .step.is-in::before {
            transform: scaleY(1);
        }



        /* ---------- 신청 완료 화면 ---------- */
        .done-hero {
            text-align: center;
            padding: clamp(40px, 10.5vw, 68px) clamp(20px, 5.6vw, 36px) clamp(34px, 8.8vw, 56px);
        }

        .done-check {
            display: inline-grid;
            place-items: center;
            width: clamp(40px, 10vw, 64px);
            height: clamp(40px, 10vw, 64px);
            border-radius: 50%;
            background: var(--brand);
            color: #fff;
        }

        .done-check svg {
            width: 56%;
            height: 56%;
        }

        .done-h1 {
            margin: clamp(16px, 4.2vw, 26px) 0 clamp(12px, 3.1vw, 19px);
            font-size: clamp(19px, 5vw, 31px);
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.35;
        }

        .done-sub {
            margin: 0 0 clamp(18px, 4.6vw, 28px);
            color: var(--text);
            font-size: clamp(13px, 3.4vw, 21px);
            line-height: 1.7;
        }

        .done-pill {
            display: inline-block;
            margin: 0;
            background: #fbeef0;
            color: var(--brand);
            border-radius: 999px;
            padding: clamp(7px, 1.8vw, 11px) clamp(14px, 3.6vw, 22px);
            font-size: clamp(11.5px, 3vw, 18px);
            font-weight: 700;
        }

        .done-summary {
            margin-top: clamp(24px, 6.2vw, 38px);
            border: 1px solid #ececec;
            border-radius: clamp(12px, 3.1vw, 19px);
            padding: clamp(18px, 4.6vw, 30px) clamp(16px, 4.2vw, 28px);
            text-align: left;
        }

        .done-summary-title {
            margin: 0 0 clamp(14px, 3.6vw, 22px);
            font-size: clamp(14px, 3.7vw, 23px);
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .done-dl {
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 2.6vw, 16px);
        }

        .done-dl>div {
            display: grid;
            grid-template-columns: clamp(66px, 18vw, 108px) 1fr;
            column-gap: clamp(10px, 2.6vw, 16px);
            align-items: baseline;
        }

        .done-dl dt {
            margin: 0;
            color: var(--muted);
            font-size: clamp(12.5px, 3.2vw, 20px);
        }

        .done-dl dd {
            margin: 0;
            color: var(--ink);
            font-size: clamp(12.5px, 3.2vw, 20px);
            line-height: 1.5;
            word-break: break-all;
        }

        /* 진행 안내 — PROCESS 타임라인 재사용 */
        .done-flow {
            background: #f4f3f3;
            padding: clamp(34px, 8.8vw, 56px) clamp(20px, 5.6vw, 36px) clamp(38px, 10vw, 62px);
            --dot: clamp(13px, 3.3vw, 21px);
            --gap: clamp(28px, 7.4vw, 48px);
            --dot-top: calc((clamp(14px, 3.7vw, 23px) * 1.4 - var(--dot)) / 2);
        }

        .done-h2 {
            margin: 0 0 clamp(22px, 5.6vw, 36px);
            font-size: clamp(17px, 4.5vw, 28px);
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .done-flow .step-title {
            color: var(--ink);
            font-size: clamp(14px, 3.7vw, 23px);
        }

        .done-flow .step--now .step-title {
            color: var(--ink);
        }

        .done-flow .step--next .step-dot {
            background: #b9b4b4;
        }

        .done-flow .step--next:not(:last-child)::before {
            background: #cfcaca;
        }

        .step-now-badge {
            display: inline-block;
            margin-left: clamp(5px, 1.4vw, 9px);
            vertical-align: middle;
            background: #fbeef0;
            color: var(--brand);
            border-radius: 999px;
            padding: clamp(2px, .7vw, 4px) clamp(7px, 1.9vw, 12px);
            font-size: clamp(10px, 2.6vw, 16px);
            font-weight: 700;
        }

        .done-prep {
            padding: clamp(34px, 8.8vw, 56px) clamp(20px, 5.6vw, 36px) clamp(40px, 10.5vw, 68px);
        }

        .done-prep .check-list li {
            font-size: clamp(13px, 3.4vw, 21px);
        }

        .done-btn {
            margin-top: clamp(28px, 7.4vw, 46px);
            text-align: center;
            text-decoration: none;
        }

        /* ---------- 개인정보 동의 전문 모달 ---------- */
        .pv-overlay {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(12px, 3.5vw, 28px);
            background: rgba(0, 0, 0, .45);
            opacity: 0;
            transition: opacity .2s ease;
        }

        .pv-overlay[hidden] {
            display: none;
        }

        .pv-overlay.is-open {
            opacity: 1;
        }

        .pv-dialog {
            width: 100%;
            max-width: 680px;
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: clamp(10px, 2.4vw, 14px);
            box-shadow: 0 18px 50px rgba(0, 0, 0, .25);
            transform: translateY(14px) scale(.98);
            transition: transform .22s cubic-bezier(.22, .61, .36, 1);
        }

        .pv-overlay.is-open .pv-dialog {
            transform: none;
        }

        .pv-head {
            flex: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: clamp(18px, 4.6vw, 28px) clamp(18px, 4.6vw, 30px) clamp(10px, 2.6vw, 16px);
        }

        .pv-title {
            margin: 0;
            font-size: clamp(16px, 4.2vw, 23px);
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .pv-close {
            flex: none;
            width: clamp(30px, 7.6vw, 40px);
            height: clamp(30px, 7.6vw, 40px);
            display: grid;
            place-items: center;
            border: 0;
            background: none;
            color: #222;
            cursor: pointer;
            border-radius: 50%;
        }

        .pv-close svg {
            width: 60%;
            height: 60%;
        }

        .pv-close:hover {
            background: #f2f0f0;
        }

        .pv-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0 clamp(18px, 4.6vw, 30px) clamp(22px, 5.6vw, 34px);
        }

        .pv-intro {
            margin: 0 0 clamp(16px, 4.2vw, 24px);
            color: var(--text);
            font-size: clamp(12.5px, 3.2vw, 17px);
            line-height: 1.75;
        }

        .pv-table {
            margin: 0 0 clamp(20px, 5.2vw, 32px);
            border-top: 1.5px solid #222;
        }

        .pv-row {
            display: grid;
            grid-template-columns: clamp(78px, 22vw, 132px) 1fr;
            border-bottom: 1px solid #e6e4e4;
        }

        .pv-row:last-child {
            border-bottom: 1.5px solid #222;
        }

        .pv-row dt {
            margin: 0;
            padding: clamp(13px, 3.4vw, 20px) clamp(8px, 2.2vw, 14px);
            background: #f6f5f5;
            font-size: clamp(12.5px, 3.2vw, 17px);
            font-weight: 700;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pv-row dd {
            margin: 0;
            padding: clamp(13px, 3.4vw, 20px) clamp(12px, 3.1vw, 20px);
            font-size: clamp(12.5px, 3.2vw, 17px);
            line-height: 1.7;
            color: var(--ink);
        }

        .pv-h3 {
            margin: 0 0 clamp(8px, 2.2vw, 13px);
            font-size: clamp(14.5px, 3.8vw, 20px);
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .pv-p {
            margin: 0 0 clamp(18px, 4.6vw, 28px);
            color: var(--text);
            font-size: clamp(12.5px, 3.2vw, 17px);
            line-height: 1.75;
        }

        .pv-p:last-child {
            margin-bottom: 0;
        }

        .pv-p a {
            color: var(--ink);
        }

        /* 담당자 지정 후 실제 연락처로 교체할 자리 */
        .pv-todo {
            color: #e83e8c;
        }

        body.pv-locked {
            overflow: hidden;
        }

        /* ---------- 전송 실패 시트 (bottom sheet) ---------- */
        .sheet-overlay { position: fixed; inset: 0; z-index: 70; display: flex; align-items: flex-end; justify-content: center; background: rgba(0,0,0,.45); opacity: 0; transition: opacity .25s ease; }
        .sheet-overlay[hidden] { display: none; }
        .sheet-overlay.is-open { opacity: 1; }

        .sheet {
            width: 100%; max-width: 640px; max-height: 92vh; overflow-y: auto;
            background: #fff; border-radius: clamp(16px, 4.2vw, 24px) clamp(16px, 4.2vw, 24px) 0 0;
            padding: clamp(10px, 2.6vw, 16px) 0 calc(clamp(22px, 5.6vw, 34px) + env(safe-area-inset-bottom, 0px));
            transform: translateY(100%); transition: transform .32s cubic-bezier(.22,.61,.36,1);
        }
        .sheet-overlay.is-open .sheet { transform: none; }

        .sheet-handle { display: block; width: clamp(38px, 10vw, 62px); height: 4px; margin: 0 auto clamp(20px, 5.2vw, 32px); border-radius: 999px; background: var(--brand); }
        .sheet-body { padding: 0 clamp(20px, 5.2vw, 34px); text-align: center; }

        .sheet-icon { display: inline-grid; place-items: center; width: clamp(40px, 10vw, 62px); height: clamp(40px, 10vw, 62px); border-radius: 50%; background: var(--brand); color: #fff; }
        .sheet-icon svg { width: 60%; height: 60%; }
        .sheet-title { margin: clamp(14px, 3.6vw, 24px) 0 clamp(10px, 2.6vw, 16px); font-size: clamp(18px, 4.8vw, 29px); font-weight: 800; letter-spacing: -.03em; }
        .sheet-desc { margin: 0 0 clamp(22px, 5.6vw, 36px); color: var(--text); font-size: clamp(13px, 3.4vw, 21px); line-height: 1.7; }

        .sheet-contact { background: #fbeef0; margin: 0 calc(-1 * clamp(20px, 5.2vw, 34px)) clamp(22px, 5.6vw, 36px); padding: clamp(18px, 4.6vw, 30px) clamp(16px, 4.2vw, 26px); }
        .sheet-contact-title { margin: 0 0 clamp(6px, 1.6vw, 10px); font-size: clamp(13px, 3.4vw, 21px); font-weight: 700; color: var(--ink); }
        .sheet-contact-mail { margin: 0; font-size: clamp(12.5px, 3.2vw, 20px); }
        .sheet-contact-mail a { color: var(--ink); text-decoration: none; }

        .sheet-actions { display: flex; gap: clamp(10px, 2.6vw, 16px); }
        .sheet-btn { flex: 1; border-radius: 0; padding: clamp(14px, 3.6vw, 22px) clamp(8px, 2vw, 14px); font-family: inherit; font-size: clamp(13.5px, 3.5vw, 22px); font-weight: 700; letter-spacing: -.01em; cursor: pointer; transition: filter .15s, background .15s; }
        .sheet-btn--primary { border: 1px solid var(--brand); background: var(--brand); color: #fff; }
        .sheet-btn--primary:hover { filter: brightness(1.08); }
        .sheet-btn--ghost { border: 1px solid var(--brand); background: #fff; color: var(--brand); }
        .sheet-btn--ghost:hover { background: #fbeef0; }

        @media (prefers-reduced-motion: reduce) {
            .sheet-overlay, .sheet { transition: none; }
        }

        /* ---------- Scroll reveal ---------- */
        .reveal {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity .6s cubic-bezier(.22, .61, .36, 1), transform .6s cubic-bezier(.22, .61, .36, 1);
            will-change: opacity, transform;
        }

        .reveal.is-in {
            opacity: 1;
            transform: none;
        }

        /* bir bo'lim ichidagi elementlar ketma-ket chiqadi */
        .apply-hero .reveal:nth-child(2) {
            transition-delay: .08s;
        }

        .apply-hero .reveal:nth-child(3) {
            transition-delay: .16s;
        }

        .apply-offer .reveal:nth-child(2) {
            transition-delay: .08s;
        }

        .apply-offer .reveal:nth-child(3) {
            transition-delay: .16s;
        }

        .apply-offer-main {
            transform: translateY(22px) scale(.94);
        }

        .apply-offer-main.is-in {
            transform: none;
        }

        .why-card:nth-child(2) {
            transition-delay: .09s;
        }

        .why-card:nth-child(3) {
            transition-delay: .18s;
        }

        .why-card:nth-child(4) {
            transition-delay: .27s;
        }

        @media (prefers-reduced-motion: reduce) {

            .reveal,
            .apply-offer-main,
            .step-dot,
            .step-body {
                opacity: 1;
                transform: none;
                transition: none;
            }

            .step:not(:last-child)::before {
                transform: none;
                transition: none;
            }
        }

        :focus-visible {
            outline: 3px solid var(--brand);
            outline-offset: 3px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .share-copy-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 30;
            background: #222;
            color: #fff;
            border-radius: 8px;
            padding: 12px 18px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s;
        }

        .share-copy-toast.is-visible {
            opacity: 1;
        }
        /* 세로가 짧은 기기(구형 폰, 가로 모드): 한 화면에 들어오도록 여백을 줄인다 */
        /* 시안 기준: 각 화면의 내용은 위에서 약 8% 지점부터 시작 */
        .apply-why, .apply-process, .apply-check, .apply-form-sec {
            padding-top: clamp(26px, 8vh, 86px);
            padding-bottom: clamp(26px, 5vh, 60px);
        }

        @media (max-height: 740px) {
            .apply-why, .apply-process, .apply-check, .apply-form-sec { padding-top: 22px; padding-bottom: 22px; }
            .page--hero .apply-hero { padding-top: clamp(14px, 3.5vh, 30px); padding-bottom: clamp(12px, 3vh, 26px); }
            /* kichik ekranda matn +5px emas, +1px — bo'lim bitta ekranga sig'sin */
            .apply-eyebrow { font-size: clamp(13px, calc(3vw + 1px), 21px); }
            .apply-h2      { font-size: clamp(25px, calc(6.4vw + 1px), 42px); }
            .why-title     { font-size: clamp(15px, calc(3.7vw + 1px), 25px); }
            .why-desc      { font-size: clamp(13px, calc(3.1vw + 1px), 21px); }
            .step-title    { font-size: clamp(16px, calc(3.9vw + 1px), 26px); }
            .step-desc     { font-size: clamp(13.5px, calc(3.2vw + 1px), 22px); }
            .check-title   { font-size: clamp(17px, calc(4.2vw + 1px), 28px); }
            .check-list li { font-size: clamp(14.5px, calc(3.5vw + 1px), 23px); }
            .asset-grid li { font-size: clamp(14px, calc(3.4vw + 1px), 22px); }
            .apply-h2 { margin-bottom: 18px; }
            .why-list { gap: 9px; }
            .why-card { padding: 12px 14px; }
            .why-desc { line-height: 1.55; }
            .apply-process { --gap: 22px; }
            .check-card { padding: 14px 14px 14px; }
            .check-card + .check-card { margin-top: 10px; }
            .check-title { margin-bottom: 10px; }
            .check-list { gap: 7px; }
            .check-note { margin-top: 10px; }
            .asset-grid { gap: 6px; }
            .asset-grid li { padding: 9px 6px; }
        }
    </style>
</head>

<body>
    <div class="about-page<?= $apply_success ? ' about-page--plain' : '' ?>">
        <?php if ($apply_success): ?>
            <main class="done-main" aria-labelledby="done-title">
                <header class="about-header">
                    <a class="about-brand" href="index.php" aria-label="서울미녀 홈"><img src="assets/images/logo_129.jpg" alt="서울미녀"></a>
                    <button type="button" class="about-share" id="pageShareCopyBtn" aria-label="공유하기"><span>공유하기</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                            <path d="M14 3h7v7M21 3 10 14M9 3H3v18h18v-6" />
                        </svg></button>
                </header>
                <section class="done-hero">
                    <span class="done-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12.5 4.5 4.5L19 7.5" />
                        </svg>
                    </span>
                    <h1 class="done-h1" id="done-title">입점 신청이 접수되었어요!</h1>
                    <p class="done-sub">남겨주신 연락처로 담당자가<br>영업일 이내에 연락드려요.</p>
                    <p class="done-pill">2027년 6월까지 입점비 0원 적용</p>

                    <?php if ($apply_done): ?>
                        <div class="done-summary">
                            <h2 class="done-summary-title">신청 내용</h2>
                            <dl class="done-dl">
                                <div>
                                    <dt>병원명</dt>
                                    <dd><?= apply_h($apply_done['hospital_name'] ?? '') ?></dd>
                                </div>
                                <div>
                                    <dt>진료과목</dt>
                                    <dd><?= apply_h($apply_done['department'] ?? '') ?></dd>
                                </div>
                                <div>
                                    <dt>병원 지역</dt>
                                    <dd><?= apply_h($apply_done['sido'] ?? '') ?></dd>
                                </div>
                                <div>
                                    <dt>담당자</dt>
                                    <dd><?= apply_h($apply_done['manager'] ?? '') ?></dd>
                                </div>
                                <div>
                                    <dt>연락처</dt>
                                    <dd><?= apply_h($apply_done['phone'] ?? '') ?></dd>
                                </div>
                            </dl>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="done-flow" aria-labelledby="done-flow-title">
                    <h2 class="done-h2" id="done-flow-title">앞으로 이렇게 진행돼요</h2>
                    <ol class="step-list">
                        <li class="step step--now">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">담당자 상담 <span class="step-now-badge">지금 단계</span></h3>
                                <p class="step-desc">담당자가 연락드려 입점 조건과 진행 방법을 안내해요.</p>
                            </div>
                        </li>
                        <li class="step step--next">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">PDF 가이드 전달</h3>
                                <p class="step-desc">이미지 규격과 등록 방법을 정리한 PDF 가이드를 보내드려요.</p>
                            </div>
                        </li>
                        <li class="step step--next">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">계약·서류 제출</h3>
                                <p class="step-desc">계약서와 필요 서류를 제출해 주세요.</p>
                            </div>
                        </li>
                        <li class="step step--next">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">병원 정보 등록 · 오픈</h3>
                                <p class="step-desc">가이드에 맞춰 정보를 등록하면 검수 후 서울미녀 앱에 노출돼요.</p>
                            </div>
                        </li>
                    </ol>
                </section>

                <section class="done-prep" aria-labelledby="done-prep-title">
                    <h2 class="done-h2" id="done-prep-title">미리 준비하면 더 빨라요</h2>
                    <ul class="check-list">
                        <li>사업자등록증</li>
                        <li>의료기관 개설신고증명서<br>(또는 개설허가증)</li>
                        <li>정산용 통장 사본</li>
                    </ul>
                    <a class="submit-btn done-btn" href="index.php">확인했어요</a>
                </section>
            </main>
        <?php else: ?>
            <main aria-labelledby="apply-title">
                <!-- 1) Hero -->
                <section class="page page--hero">
                    <header class="about-header">
                        <a class="about-brand" href="index.php" aria-label="서울미녀 홈"><img src="assets/images/logo_129.jpg" alt="서울미녀"></a>
                        <button type="button" class="about-share" id="pageShareCopyBtn" aria-label="공유하기"><span>공유하기</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                                <path d="M14 3h7v7M21 3 10 14M9 3H3v18h18v-6" />
                            </svg></button>
                    </header>
                    <div class="apply-hero">
                    <p class="apply-eyebrow reveal">FOR CLINICS</p>
                    <div class="apply-title-row">
                        <h1 id="apply-title" class="apply-title reveal">우리 병원 시술을<br>찾는 고객과<br>바로 연결하세요</h1>
                        <span class="scroll-hint" aria-hidden="true"><img src="assets/media/about-apply-hand.png?v=<?= (int)@filemtime(__DIR__ . '/assets/media/about-apply-hand.png') ?>" alt="" width="184" height="268"></span>
                    </div>
                    </div>

                    <!-- 2) 모든 광고비 0원 -->
                    <div class="apply-offer" aria-label="광고비 혜택 안내">
                    <p class="apply-lead reveal">피부과·성형외과 미용시술 가격 비교 플랫폼 서울미녀에 병원을 등록하고, 시술·지역으로 병원을 찾는 고객을 만나보세요.</p>
                    <div class="offer-card">
                        <p class="apply-offer-top reveal">지금 신청하면</p>
                        <p class="apply-offer-main reveal">모든 광고비 <span class="offer-free">0원</span></p>
                        <p class="apply-offer-sub reveal">2027년 6월까지 입점비 없이 시작하세요</p>
                    </div>
                    </div>
                </section>

                <!-- 3) WHY SEOUL BEAUTY -->
                <section class="page apply-why" aria-labelledby="apply-why-title">
                    <p class="apply-eyebrow reveal">WHY SEOUL BEAUTY</p>
                    <h2 id="apply-why-title" class="apply-h2 reveal">서울미녀와 함께하면<br>이런 점이 좋아요</h2>

                    <ul class="why-list">
                        <li class="why-card reveal">
                            <span class="why-icon" aria-hidden="true">
                                <img src="assets/icons/apply-search.svg" alt="">
                            </span>
                            <div class="why-body">
                                <h3 class="why-title">시술·지역 검색에 노출</h3>
                                <p class="why-desc">고객이 원하는 시술과 지역으로 검색할 때 우리 병원이 함께 보여요.</p>
                            </div>
                        </li>
                        <li class="why-card reveal">
                            <span class="why-icon" aria-hidden="true">
                                <img src="assets/icons/apply-price.svg" alt="">
                            </span>
                            <div class="why-body">
                                <h3 class="why-title">시술 가격 직접 등록</h3>
                                <p class="why-desc">시술별 가격을 등록하고, 낮은가격·평점·인기순 비교 목록에 노출돼요.</p>
                            </div>
                        </li>
                        <li class="why-card reveal">
                            <span class="why-icon" aria-hidden="true">
                                <img src="assets/icons/apply-booking.svg" alt="">
                            </span>
                            <div class="why-body">
                                <h3 class="why-title">병원이 확인하고 확정하는 예약</h3>
                                <p class="why-desc">예약 요청을 병원이 먼저 확인하고, 고객 결제가 끝나면 예약이 확정돼요.</p>
                            </div>
                        </li>
                        <li class="why-card reveal">
                            <span class="why-icon" aria-hidden="true">
                                <img src="assets/icons/apply-clinic.svg" alt="">
                            </span>
                            <div class="why-body">
                                <h3 class="why-title">병원 소개 페이지 제공</h3>
                                <p class="why-desc">의료진, 병원 내부, 시술 정보, 시술후기를 한 페이지에서 보여줘요.</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- 4) PROCESS -->
                <section class="page apply-process" aria-labelledby="apply-process-title">
                    <p class="apply-eyebrow reveal">PROCESS</p>
                    <h2 id="apply-process-title" class="apply-h2 reveal">입점은 이렇게 진행돼요</h2>

                    <ol class="step-list">
                        <li class="step">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">입점 신청</h3>
                                <p class="step-desc">아래 신청서를 작성해 주세요.</p>
                            </div>
                        </li>
                        <li class="step">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">담당자 상담</h3>
                                <p class="step-desc">담당자가 연락드려 입점 조건과 진행 방법을 안내해요.</p>
                            </div>
                        </li>
                        <li class="step">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">계약·서류 제출</h3>
                                <p class="step-desc">계약서와 필요 서류를 제출해 주세요.</p>
                            </div>
                        </li>
                        <li class="step">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">병원 정보 등록</h3>
                                <p class="step-desc">담당자가 보내드리는 PDF 가이드에 맞춰 로고, 병원 사진, 의료진, 시술·이벤트 상세페이지 정보를 등록해요.</p>
                            </div>
                        </li>
                        <li class="step">
                            <span class="step-dot" aria-hidden="true"></span>
                            <div class="step-body">
                                <h3 class="step-title">서비스 오픈</h3>
                                <p class="step-desc">검수가 끝나면 서울미녀 앱에 병원이 노출돼요.</p>
                            </div>
                        </li>
                    </ol>
                </section>

                <!-- 5) CHECKLIST -->
                <section class="page apply-check" aria-labelledby="apply-check-title">
                    <p class="apply-eyebrow reveal">CHECKLIST</p>
                    <h2 id="apply-check-title" class="apply-h2 reveal">미리 준비해 주세요</h2>

                    <div class="check-card reveal">
                        <h3 class="check-title">계약 서류</h3>
                        <ul class="check-list">
                            <li>사업자등록증</li>
                            <li>의료기관 개설신고증명서<br>(또는 개설허가증)</li>
                            <li>정산용 통장 사본</li>
                        </ul>
                        <p class="check-note"><span aria-hidden="true">※</span>세부 서류는 상담 시 다시 안내드려요.</p>
                    </div>

                    <div class="check-card reveal">
                        <h3 class="check-title">병원 페이지 등록 자료</h3>
                        <ul class="asset-grid">
                            <li>병원 로고</li>
                            <li>대표 썸네일</li>
                            <li>병원 내부 사진</li>
                            <li>의료진 사진·소개</li>
                            <li>시술 상세페이지</li>
                        </ul>
                        <p class="check-note"><span aria-hidden="true">※</span>필요한 이미지 규격과 등록 방법은 입점 신청 후 담당자가 PDF 가이드로 보내드려요. 시술 상세페이지는 의료광고 관련 법령을 지켜 작성해 주세요.</p>
                    </div>
                </section>

                <!-- 6) APPLY — 입점 신청서 -->
                <section class="page apply-form-sec" aria-labelledby="apply-form-title">
                    <p class="apply-eyebrow reveal">APPLY</p>
                    <h2 id="apply-form-title" class="apply-h2 reveal">입점 신청서</h2>
                    <p class="form-lead reveal">
                        신청 후 담당자가 영업일 이내에 연락드려요.<br>
                        [운영시간 월 ~ 금 10:00 ~ 18:00, 공휴일 휴무]<br>
                        <span class="req-mark">*</span> 표시는 필수 항목이에요.
                    </p>

                    <form class="form-card reveal" id="applyForm" method="post" action="about-apply.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= apply_h($apply_csrf) ?>">
                        <!-- 봇 차단용(사람에게는 보이지 않음) -->
                        <div class="hp" aria-hidden="true"><label>홈페이지<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <?php if ($apply_error !== ''): ?>
                            <p class="form-error form-error--server" role="alert"><?= apply_h($apply_error) ?></p>
                        <?php endif; ?>
                        <div class="field">
                            <label class="field-label" for="af-name">병원명 <span class="req-mark">*</span></label>
                            <input class="input" type="text" id="af-name" name="hospital_name" value="<?= apply_h($old['hospital_name']) ?>" placeholder="예) 서울미녀피부과의원" maxlength="100" required>
                        </div>

                        <fieldset class="field">
                            <legend class="field-label">진료과목 <span class="req-mark">*</span></legend>
                            <div class="chip-group">
                                <label class="chip"><input type="radio" name="department" value="피부과" <?= $old['department'] === '피부과' ? ' checked' : '' ?> required><span>피부과</span></label>
                                <label class="chip"><input type="radio" name="department" value="성형외과" <?= $old['department'] === '성형외과' ? ' checked' : '' ?>><span>성형외과</span></label>
                                <label class="chip"><input type="radio" name="department" value="피부과·성형외과" <?= $old['department'] === '피부과·성형외과' ? ' checked' : '' ?>><span>피부과·성형외과</span></label>
                                <label class="chip"><input type="radio" name="department" value="치과" <?= $old['department'] === '치과' ? ' checked' : '' ?>><span>치과</span></label>
                                <label class="chip"><input type="radio" name="department" value="기타" <?= $old['department'] === '기타' ? ' checked' : '' ?>><span>기타</span></label>
                            </div>
                        </fieldset>

                        <div class="field">
                            <label class="field-label" for="af-sido">병원 지역 <span class="req-mark">*</span></label>
                            <div class="select-wrap">
                                <select class="input select" id="af-sido" name="sido" required>
                                    <option value="">시/도 선택</option>
                                    <?php foreach ($APPLY_SIDO as $sido_opt): ?>
                                        <option<?= $old['sido'] === $sido_opt ? ' selected' : '' ?>><?= apply_h($sido_opt) ?></option>
                                        <?php endforeach; ?>
                                </select>
                            </div>
                            <input class="input mt-sm" type="text" name="address_detail" value="<?= apply_h($old['address_detail']) ?>" placeholder="상세 주소" maxlength="200" aria-label="상세 주소">
                        </div>

                        <div class="field">
                            <label class="field-label" for="af-manager">담당자 성함 <span class="req-mark">*</span></label>
                            <input class="input" type="text" id="af-manager" name="manager" value="<?= apply_h($old['manager']) ?>" placeholder="성함 / 직책" maxlength="50" required>
                        </div>

                        <div class="field">
                            <label class="field-label" for="af-phone">연락처 <span class="req-mark">*</span></label>
                            <input class="input" type="tel" id="af-phone" name="phone" inputmode="numeric" maxlength="13" value="<?= apply_h($old['phone']) ?>" placeholder="010-0000-0000" required>
                        </div>

                        <div class="field">
                            <label class="field-label" for="af-email">이메일 <span class="req-mark">*</span></label>
                            <input class="input" type="email" id="af-email" name="email" value="<?= apply_h($old['email']) ?>" placeholder="clinic@example.com" maxlength="150" required>
                        </div>

                        <div class="field">
                            <label class="field-label" for="af-memo">문의 내용</label>
                            <textarea class="input textarea" id="af-memo" name="memo" rows="5" maxlength="2000" placeholder="궁금한 점이나 상담 희망 시간을 남겨주세요."><?= apply_h($old['memo']) ?></textarea>
                        </div>

                        <hr class="form-hr">

                        <div class="agree-row">
                            <label class="agree">
                                <input type="checkbox" name="privacy_agree" id="af-agree" value="1" required>
                                <span class="agree-box" aria-hidden="true"></span>
                                <span class="agree-text">[필수] 개인정보 수집·이용에 동의합니다.</span>
                            </label>
                            <button type="button" class="agree-toggle" id="privacyToggle" aria-haspopup="dialog">보기</button>
                        </div>
                        <div class="privacy-box" id="privacyBox">
                            <p>수집 항목: 병원명, 담당자 성함, 연락처, 이메일</p>
                            <p>이용 목적: 입점 상담 및 안내</p>
                            <p>보유 기간: 입점 상담 완료 후 1년</p>
                        </div>

                        <p class="form-error" id="formError" role="alert" hidden></p>
                        <button type="submit" class="submit-btn">입점 신청하기</button>
                    </form>
                </section>

                <!-- 개인정보 수집·이용 동의 전문 (보기 버튼으로 열림) -->
                <div class="pv-overlay" id="privacyModal" hidden>
                    <div class="pv-dialog" role="dialog" aria-modal="true" aria-labelledby="pvTitle">
                        <div class="pv-head">
                            <h2 class="pv-title" id="pvTitle">개인정보 수집·이용 동의</h2>
                            <button type="button" class="pv-close" id="privacyClose" aria-label="닫기">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                                    <path d="M5 5l14 14M19 5L5 19" />
                                </svg>
                            </button>
                        </div>
                        <div class="pv-body">
                            <p class="pv-intro">셀럽몰 주식회사(이하 '회사')는 서울미녀 입점 상담을 위해 아래와 같이 개인정보를 수집·이용합니다. 내용을 확인하신 후 동의 여부를 결정해 주세요.</p>

                            <dl class="pv-table">
                                <div class="pv-row">
                                    <dt>수집 목적</dt>
                                    <dd>입점 신청 접수, 입점 상담 및 진행 안내</dd>
                                </div>
                                <div class="pv-row">
                                    <dt>수집 항목</dt>
                                    <dd>
                                        (필수) 병원명, 진료과목, 병원 지역, 담당자 성함, 연락처, 이메일<br>
                                        (선택) 상세 주소, 문의 내용
                                    </dd>
                                </div>
                                <div class="pv-row">
                                    <dt>보유 기간</dt>
                                    <dd><strong>입점 상담 완료 후 1년</strong><br>보유 기간이 지나면 지체 없이 파기합니다.</dd>
                                </div>
                            </dl>

                            <h3 class="pv-h3">동의를 거부할 권리</h3>
                            <p class="pv-p">개인정보 수집·이용에 동의하지 않으실 수 있어요. 다만 필수 항목에 동의하지 않으면 입점 신청 접수와 상담 안내가 어려워요.</p>

                            <h3 class="pv-h3">문의</h3>
                            <p class="pv-p">개인정보와 관련한 문의는 <span class="pv-todo">[개인정보 보호책임자 연락처 담당자지정]</span>로 보내주세요. 자세한 내용은 <a href="privacy_policy.php" target="_blank" rel="noopener">서울미녀 개인정보 처리방침</a>에서 확인할 수 있어요.</p>
                        </div>
                    </div>
                </div>
                <!-- 전송 실패 시트 (아래에서 올라옴) -->
                <div class="sheet-overlay" id="failSheet" hidden data-open="<?= ($apply_error !== '' && $apply_error_kind === 'system') ? '1' : '0' ?>">
                    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="failTitle" aria-describedby="failDesc">
                        <span class="sheet-handle" aria-hidden="true"></span>
                        <div class="sheet-body">
                            <span class="sheet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 7v6.5"/><path d="M12 17h.01"/></svg>
                            </span>
                            <h2 class="sheet-title" id="failTitle">신청서를 보내지 못했어요</h2>
                            <p class="sheet-desc" id="failDesc">일시적인 네트워크 문제일 수 있어요.<br>잠시 후 다시 시도해 주세요.</p>
                            <div class="sheet-contact">
                                <p class="sheet-contact-title">계속 실패한다면 아래로 연락 주세요.</p>
                                <p class="sheet-contact-mail"><a href="mailto:info@seoulbeauty.app">info@seoulbeauty.app</a></p>
                            </div>
                            <div class="sheet-actions">
                                <button type="button" class="sheet-btn sheet-btn--primary" id="failRetry">다시 시도하기</button>
                                <button type="button" class="sheet-btn sheet-btn--ghost" id="failBack">신청서로 돌아가기</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 다음 섹션은 디자인 전달 후 이어서 추가 -->
            </main>
        <?php endif; ?>
    </div>
    <div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite"></div>
    <script>
        (function() {
            var toastTimer = null;

            function getShareText() {
                return '우리 병원도 서울미녀에 등록해보세요!\n서울미녀 병원 입점 안내\n' + window.location.href;
            }

            function fallbackCopy(text) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                var copied = document.execCommand('copy');
                document.body.removeChild(textarea);
                return copied;
            }

            function showToast(message) {
                var toast = document.getElementById('pageShareCopyToast');
                if (!toast) return;
                toast.textContent = message;
                toast.classList.add('is-visible');
                window.clearTimeout(toastTimer);
                toastTimer = window.setTimeout(function() {
                    toast.classList.remove('is-visible');
                }, 1800);
            }

            function copyShareText() {
                var shareText = getShareText();
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(shareText).then(function() {
                        showToast('링크가 복사되었습니다.');
                    }).catch(function() {
                        showToast(fallbackCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
                    });
                    return;
                }
                showToast(fallbackCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
            }


            // ---- Scroll reveal: element ekranga kirganda ko'rinadi ----
            function initReveal() {
                var items = [].slice.call(document.querySelectorAll('.reveal, .step'));
                if (!items.length) return;

                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (reduce || !('IntersectionObserver' in window)) {
                    items.forEach(function(el) {
                        el.classList.add('is-in');
                    });
                    return;
                }

                var io = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) return;
                        entry.target.classList.add('is-in');
                        io.unobserve(entry.target);
                    });
                }, {
                    threshold: 0.08,
                    rootMargin: '0px 0px -4% 0px'
                });

                items.forEach(function(el) {
                    // 첫 화면 안에 이미 들어와 있는 요소는 바로 보여준다.
                    // (섹션 맨 아래 줄처럼 화면 경계에 걸친 요소가 끝까지 안 나타나던 문제)
                    var rect = el.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        el.classList.add('is-in');
                        return;
                    }
                    io.observe(el);
                });
            }


            // ---- 개인정보 수집·이용 동의 전문 모달 ----

            // ---- 전송 실패 시트 (아래에서 올라옴) ----
            function initFailSheet() {
                var overlay = document.getElementById('failSheet');
                if (!overlay) return;

                var sheet = overlay.querySelector('.sheet');
                var retry = document.getElementById('failRetry');
                var back = document.getElementById('failBack');
                var lastFocus = null;

                function open() {
                    lastFocus = document.activeElement;
                    overlay.hidden = false;
                    document.body.classList.add('pv-locked');
                    requestAnimationFrame(function() {
                        overlay.classList.add('is-open');
                    });
                    if (retry) retry.focus();
                }

                function close() {
                    overlay.classList.remove('is-open');
                    document.body.classList.remove('pv-locked');
                    window.setTimeout(function() {
                        overlay.hidden = true;
                    }, 320);
                    if (lastFocus && lastFocus.focus) lastFocus.focus();
                }

                // 바깥 영역 클릭 / ESC 로 닫기
                overlay.addEventListener('click', function(e) {
                    if (sheet && !sheet.contains(e.target)) close();
                });
                document.addEventListener('keydown', function(e) {
                    if (!overlay.hidden && e.key === 'Escape') close();
                });

                // 다시 시도하기: 폼을 그대로 다시 제출한다.
                if (retry) retry.addEventListener('click', function() {
                    var form = document.getElementById('applyForm');
                    close();
                    if (!form) { window.location.reload(); return; }
                    var btn = form.querySelector('.submit-btn');
                    if (btn) { btn.disabled = false; btn.textContent = '입점 신청하기'; }
                    window.setTimeout(function() {
                        if (form.requestSubmit) form.requestSubmit(); else form.submit();
                    }, 260);
                });

                // 신청서로 돌아가기: 시트를 닫고 폼으로 이동
                if (back) back.addEventListener('click', function() {
                    close();
                    var form = document.getElementById('applyForm');
                    if (form) window.setTimeout(function() {
                        form.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    }, 260);
                });

                // 서버가 전송 실패를 알려준 경우 바로 올라온다.
                if (overlay.getAttribute('data-open') === '1') open();

                window.seoulbeautyOpenFailSheet = open;
            }

            function initPrivacyModal() {
                var btn = document.getElementById('privacyToggle');
                var modal = document.getElementById('privacyModal');
                if (!btn || !modal) return;

                var closeBtn = document.getElementById('privacyClose');
                var dialog = modal.querySelector('.pv-dialog');
                var lastFocus = null;

                function open() {
                    lastFocus = document.activeElement;
                    modal.hidden = false;
                    document.body.classList.add('pv-locked');
                    // hidden 해제 직후 트랜지션이 걸리도록 한 프레임 쉰다
                    requestAnimationFrame(function() {
                        modal.classList.add('is-open');
                    });
                    if (closeBtn) closeBtn.focus();
                }

                function close() {
                    modal.classList.remove('is-open');
                    document.body.classList.remove('pv-locked');
                    window.setTimeout(function() {
                        modal.hidden = true;
                    }, 200);
                    if (lastFocus && lastFocus.focus) lastFocus.focus();
                }

                btn.addEventListener('click', open);
                if (closeBtn) closeBtn.addEventListener('click', close);

                // 바깥 영역 클릭으로 닫기
                modal.addEventListener('click', function(e) {
                    if (dialog && !dialog.contains(e.target)) close();
                });

                // ESC 로 닫기 + 모달 안에서만 Tab 이동
                document.addEventListener('keydown', function(e) {
                    if (modal.hidden) return;
                    if (e.key === 'Escape') {
                        close();
                        return;
                    }
                    if (e.key !== 'Tab' || !dialog) return;

                    var focusables = dialog.querySelectorAll('a[href], button, [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0],
                        last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                });
            }

            // ---- 연락처 자동 하이픈 ----
            function initPhoneFormat() {
                var el = document.getElementById('af-phone');
                if (!el) return;
                el.addEventListener('input', function() {
                    var d = el.value.replace(/[^0-9]/g, '').slice(0, 11);
                    var out = d;
                    if (d.length > 7) out = d.slice(0, 3) + '-' + d.slice(3, 7) + '-' + d.slice(7);
                    else if (d.length > 3) out = d.slice(0, 3) + '-' + d.slice(3);
                    el.value = out;
                });
            }

            // ---- 제출 전 확인 ----
            function initFormValidate() {
                var form = document.getElementById('applyForm');
                var errBox = document.getElementById('formError');
                if (!form) return;

                function fail(message, el) {
                    if (errBox) {
                        errBox.textContent = message;
                        errBox.hidden = false;
                    }
                    if (el) {
                        el.scrollIntoView({
                            block: 'center',
                            behavior: 'smooth'
                        });
                        if (el.focus) el.focus({
                            preventScroll: true
                        });
                    }
                }

                form.addEventListener('submit', function(e) {
                    if (errBox) errBox.hidden = true;

                    var name = form.elements['hospital_name'];
                    if (!name.value.trim()) {
                        e.preventDefault();
                        return fail('병원명을 입력해 주세요.', name);
                    }

                    var dept = form.querySelector('input[name="department"]:checked');
                    if (!dept) {
                        e.preventDefault();
                        return fail('진료과목을 선택해 주세요.', form.querySelector('.chip input'));
                    }

                    var sido = form.elements['sido'];
                    if (!sido.value) {
                        e.preventDefault();
                        return fail('병원 지역(시/도)을 선택해 주세요.', sido);
                    }

                    var manager = form.elements['manager'];
                    if (!manager.value.trim()) {
                        e.preventDefault();
                        return fail('담당자 성함을 입력해 주세요.', manager);
                    }

                    var phone = form.elements['phone'];
                    if (!/^01[016789]-?\d{3,4}-?\d{4}$/.test(phone.value.replace(/\s/g, ''))) {
                        e.preventDefault();
                        return fail('연락처를 010-0000-0000 형식으로 입력해 주세요.', phone);
                    }

                    var email = form.elements['email'];
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                        e.preventDefault();
                        return fail('이메일을 정확히 입력해 주세요. 승인 시 이 주소로 관리자 계정이 발급돼요.', email);
                    }

                    // form.elements[] bir nechta element bo'lsa RadioNodeList qaytaradi (.checked = undefined),
                    // shuning uchun to'g'ridan-to'g'ri input ni olamiz
                    var agree = form.querySelector('input[name="privacy_agree"]');
                    if (!agree || !agree.checked) {
                        e.preventDefault();
                        return fail('개인정보 수집·이용에 동의해 주세요.', agree);
                    }

                    // 중복 제출 방지
                    var btn = form.querySelector('.submit-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = '신청 중...';
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                var button = document.getElementById('pageShareCopyBtn');
                if (button) button.addEventListener('click', copyShareText);
                initReveal();
                initPrivacyModal();
                initFailSheet();

                // 입력 오류로 돌아온 경우 신청서 화면에서 시작한다.
                var serverErr = document.querySelector('.form-error--server');
                if (serverErr) {
                    var formPage = document.querySelector('.apply-form-sec');
                    if (formPage) formPage.scrollIntoView({ block: 'start' });
                }
                initPhoneFormat();
                initFormValidate();
            });
        }());
    </script>
</body>

</html>
