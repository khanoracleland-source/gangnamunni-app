<?php
/**
 * 경과 후기(#포인트제도 v2) 사용자 화면 공용: 접근 확인, CSRF, 스타일, 조회 헬퍼.
 */
require_once __DIR__ . '/points_policy_engine.php';

function pr_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function pr_csrf() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function pr_check_csrf() {
    $t = (string)($_POST['csrf_token'] ?? '');
    if ($t === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $t)) {
        http_response_code(403);
        exit('요청이 만료되었습니다. 페이지를 새로고침해주세요.');
    }
}

/** 기능이 꺼져 있거나 스키마가 없으면 안내 화면을 출력하고 종료 */
function pr_require_enabled($connect) {
    if (points_v2_on($connect, 'v2_progress_enabled')) return;
    pr_page_open('경과 후기');
    echo '<div class="pr-empty"><div class="circle"><i class="bi bi-hourglass-split"></i></div><p>경과 후기는 준비 중입니다.</p></div>';
    pr_page_close($connect);
    exit;
}

function pr_kind_label($kind) { return $kind === 'surgery' ? '수술' : '시술'; }
function pr_status_label($st) {
    return ['writing' => '작성 중', 'submitted' => '검수 중', 'approved' => '적립 완료', 'rejected' => '반려', 'deleted' => '삭제'][$st] ?? $st;
}

function pr_load($connect, $id, $member_no) {
    $st = mysqli_prepare($connect, "SELECT p.*, h.name AS hospital_name,
            (SELECT COUNT(*) FROM gangnamunni_progress_entries e WHERE e.progress_id = p.id) AS entry_count
        FROM gangnamunni_progress_reviews p LEFT JOIN gangnamunni_hospitals h ON h.id = p.hospital_id
        WHERE p.id = ? AND p.member_id = ? AND p.status <> 'deleted' LIMIT 1");
    $id = (int)$id; $member_no = (int)$member_no;
    mysqli_stmt_bind_param($st, 'ii', $id, $member_no);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
    mysqli_stmt_close($st);
    return $row;
}

/** 작성/수정 가능 여부: 작성 중이거나, 반려 후 작성일로부터 v2_progress_resubmit_days 이내 */
function pr_editable($connect, array $p) {
    if ($p['status'] === 'writing') return true;
    if ($p['status'] !== 'rejected') return false;
    $days = points_v2_int($connect, 'v2_progress_resubmit_days', 30);
    return strtotime($p['created_at']) >= strtotime("-{$days} days");
}

function pr_page_open($title, $back = 'my_reviews.php') {
    ?><!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title><?php echo pr_h($title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;--bg:#fff;--band:#f2f2f2;--line:#e5e5e5;--line-2:#cfcfcf;--text:#111;--sub:#444;--muted:#9a9a9a;--fs:calc(var(--fs-scale,1) * var(--fs-page,1));}
[data-bs-theme="dark"]{--pri:#e0566c;--bg:#16181b;--band:#0d0e10;--line:#2c2f34;--line-2:#3a3e44;--text:#ececec;--sub:#c4c4c4;--muted:#8a8f96;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo',sans-serif;max-width:640px;margin:0 auto;background:var(--bg);color:var(--text);padding-bottom:96px;-webkit-font-smoothing:antialiased;letter-spacing:-.2px;word-break:keep-all;overflow-wrap:anywhere;}
a,button,input,select,textarea{font-family:inherit;color:inherit;}
.pr-header{position:sticky;top:0;z-index:10;height:56px;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:0 52px;}
.pr-header .back{position:absolute;left:8px;top:8px;width:40px;height:40px;border:none;background:none;font-size:calc(22px * var(--fs));cursor:pointer;}
.pr-header h1{font-size:calc(18px * var(--fs));font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pr-wrap{padding:8px 20px 20px;}
.band{height:12px;background:var(--band);}
.pr-card{border:1px solid var(--line);padding:16px;margin-top:12px;text-decoration:none;display:block;}
.pr-card .t{font-size:calc(16px * var(--fs));font-weight:500;}
.pr-card .m{margin-top:6px;font-size:calc(13px * var(--fs));color:var(--muted);}
.tag{display:inline-flex;align-items:center;height:24px;padding:0 6px;border:1px solid var(--line-2);color:var(--muted);font-size:calc(12px * var(--fs));margin-right:6px;}
.tag.pri{border-color:var(--pri);color:var(--pri);}
.btn{display:flex;align-items:center;justify-content:center;width:100%;height:50px;border:1px solid var(--pri);background:var(--pri);color:#fff;font-size:calc(16px * var(--fs));font-weight:500;cursor:pointer;text-decoration:none;}
.btn.line{background:var(--bg);color:var(--pri);}
.btn[disabled]{opacity:.45;cursor:not-allowed;}
.field{margin-top:16px;}
.field label{display:block;font-size:calc(14px * var(--fs));font-weight:500;margin-bottom:6px;}
.field input,.field select,.field textarea{width:100%;border:1px solid var(--line-2);background:var(--bg);padding:12px;font-size:calc(15px * var(--fs));border-radius:0;}
.field textarea{min-height:120px;resize:vertical;}
.help{margin-top:6px;font-size:calc(12.5px * var(--fs));color:var(--sub);line-height:1.6;}
.alert{margin-top:12px;padding:12px 14px;background:var(--band);font-size:calc(13.5px * var(--fs));line-height:1.6;}
.alert.err{color:var(--pri);}
.pr-empty{text-align:center;padding:64px 20px;color:var(--muted);font-size:calc(14px * var(--fs));}
.pr-empty .circle{width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:var(--band);display:grid;place-items:center;font-size:calc(26px * var(--fs));}
.progress{height:6px;background:var(--band);margin-top:10px;}
.progress i{display:block;height:100%;background:var(--pri);}
.entry{border-top:1px solid var(--line);padding:16px 0;}
.entry .d{font-size:calc(13px * var(--fs));color:var(--muted);}
.entry .c{margin-top:8px;font-size:calc(15px * var(--fs));line-height:1.7;white-space:pre-wrap;}
.entry .ph{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;}
.entry .ph img{width:84px;height:84px;object-fit:cover;border:1px solid var(--line);}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="pr-header">
    <button class="back" onclick="if(history.length>1){history.back();}else{location.href='<?php echo pr_h($back); ?>';}" aria-label="뒤로가기"><i class="bi bi-chevron-left"></i></button>
    <h1><?php echo pr_h($title); ?></h1>
</div>
<?php
}

function pr_page_close($connect) {
    include_once __DIR__ . '/bottom_nav.php';
    echo "\n</body>\n</html>\n";
    if ($connect) @mysqli_close($connect);
}
