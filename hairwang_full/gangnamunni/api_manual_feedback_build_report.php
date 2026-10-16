<?php
if (!defined('G5_MYSQL_PASSWORD')) { require_once __DIR__ . '/dbconfig.php'; }
/**
 * 매뉴얼 피드백 — 클라이언트용 변경 리포트 HTML 파일 생성 API
 * POST /api_manual_feedback_build_report.php
 *
 * 파라미터:
 *   ids[]      — 특정 항목 ID 배열 (우선)
 *   all_done=1 — done 전체 포함 (ids 없을 때)
 *   title      — 리포트 제목 (선택)
 *
 * 응답: JSON { success, url, filename, count }
 *
 * @created 2026-04-09
 */

header('Content-Type: application/json; charset=utf-8');

// ─── 세션 + 로그인 체크 ───
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

// ─── 직접 DB 연결 (Gnuboard init.php 미사용) ───
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, 'hms_saas', 3306);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// ─── 파라미터 파싱 ───
$report_title = trim($_POST['title'] ?? '');
if ($report_title === '') {
    $report_title = '매뉴얼 수정 완료 리포트';
}
// XSS 방지
$report_title = htmlspecialchars(strip_tags($report_title), ENT_QUOTES, 'UTF-8');

$raw_ids  = $_POST['ids'] ?? [];
$all_done = !empty($_POST['all_done']) && $_POST['all_done'] === '1';

// ─── 데이터 조회 ───
$rows = [];

if (!empty($raw_ids) && is_array($raw_ids)) {
    // 정수만 허용
    $safe_ids = array_filter(array_map('intval', $raw_ids), fn($v) => $v > 0);
    if (empty($safe_ids)) {
        mysqli_close($connect);
        echo json_encode(['success' => false, 'error' => '유효한 ID가 없습니다.']);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($safe_ids), '?'));
    $types        = str_repeat('i', count($safe_ids));
    $stmt = mysqli_prepare(
        $connect,
        "SELECT id, status, priority, request_type, request_title, request_text,
                progress_note, page_url, page_path, section_title, menu_label,
                source_location_desc, image_path, handled_by, handled_at,
                created_at, updated_at
           FROM manual_feedback_items
          WHERE id IN ($placeholders)
          ORDER BY id ASC"
    );
    if (!$stmt) {
        mysqli_close($connect);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '쿼리 준비 실패: ' . mysqli_error($connect)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$safe_ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

} elseif ($all_done) {
    $stmt = mysqli_prepare(
        $connect,
        "SELECT id, status, priority, request_type, request_title, request_text,
                progress_note, page_url, page_path, section_title, menu_label,
                source_location_desc, image_path, handled_by, handled_at,
                created_at, updated_at
           FROM manual_feedback_items
          WHERE status = 'done'
          ORDER BY handled_at DESC, updated_at DESC"
    );
    if (!$stmt) {
        mysqli_close($connect);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '쿼리 준비 실패: ' . mysqli_error($connect)]);
        exit;
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

} else {
    mysqli_close($connect);
    echo json_encode(['success' => false, 'error' => 'ids[] 또는 all_done=1 파라미터가 필요합니다.']);
    exit;
}

mysqli_close($connect);

if (empty($rows)) {
    echo json_encode(['success' => false, 'error' => '조회된 항목이 없습니다.']);
    exit;
}

// ─── 출력 경로 설정 ───
$gangnamunni_root = '/var/www/html/multi-hospital-management-system-saas-hospital'
                  . '/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni';
$reports_dir      = $gangnamunni_root . '/manual/reports';
$base_url         = 'https://hms.seoulbeauty.app/hairwang/app/manual/reports';

// reports 디렉토리 생성
if (!is_dir($reports_dir)) {
    if (!@mkdir($reports_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'reports 디렉토리 생성 실패']);
        exit;
    }
}

// 파일명: report_{날짜}_{시간}_{랜덤토큰}.html
$date_part  = date('Ymd');
$time_part  = date('His');
$token      = bin2hex(random_bytes(6)); // 12자 랜덤 토큰
$filename   = "report_{$date_part}_{$time_part}_{$token}.html";
$filepath   = $reports_dir . '/' . $filename;
$public_url = $base_url . '/' . $filename;

// ─── 보조 함수 ───
function label_status(string $s): array {
    $map = [
        'requested'    => ['text' => '접수됨',     'class' => 'bg-secondary'],
        'prompt_ready' => ['text' => '프롬프트 준비', 'class' => 'bg-info text-dark'],
        'in_progress'  => ['text' => '진행 중',    'class' => 'bg-warning text-dark'],
        'done'         => ['text' => '완료',        'class' => 'bg-success'],
        'hold'         => ['text' => '보류',        'class' => 'bg-secondary'],
        'rejected'     => ['text' => '거절',        'class' => 'bg-danger'],
    ];
    return $map[$s] ?? ['text' => $s, 'class' => 'bg-secondary'];
}

function label_priority(string $p): array {
    $map = [
        'urgent' => ['text' => '긴급', 'class' => 'bg-danger'],
        'high'   => ['text' => '높음', 'class' => 'bg-warning text-dark'],
        'normal' => ['text' => '보통', 'class' => 'bg-primary'],
        'low'    => ['text' => '낮음', 'class' => 'bg-light text-dark border'],
    ];
    return $map[$p] ?? ['text' => $p, 'class' => 'bg-secondary'];
}

function label_type(string $t): string {
    $map = [
        'bug_fix'      => '버그 수정',
        'feature_add'  => '기능 추가',
        'content_edit' => '내용 수정',
        'update'       => '업데이트',
        'other'        => '기타',
    ];
    return $map[$t] ?? $t;
}

function safe_html(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function build_location(array $row): string {
    $parts = array_filter([
        $row['page_path']     ?? '',
        $row['menu_label']    ?? '',
        $row['section_title'] ?? '',
    ]);
    return implode(' &rsaquo; ', array_map('safe_html', $parts));
}

function format_date(?string $dt): string {
    if (empty($dt) || $dt === '0000-00-00 00:00:00') return '—';
    try {
        $d = new DateTime($dt);
        return $d->format('Y년 m월 d일 H:i');
    } catch (Exception $e) {
        return safe_html($dt);
    }
}

// ─── HTML 리포트 생성 ───
$count         = count($rows);
$generated_at  = date('Y년 m월 d일 H:i');
$title_safe    = safe_html($report_title);

// 카드 HTML 목록 조립
$cards_html = '';
foreach ($rows as $row) {
    $status   = label_status($row['status'] ?? '');
    $priority = label_priority($row['priority'] ?? '');
    $type_lbl = label_type($row['request_type'] ?? '');
    $location = build_location($row);
    $id       = (int)$row['id'];

    $display_date = format_date(
        !empty($row['handled_at']) && $row['handled_at'] !== '0000-00-00 00:00:00'
            ? $row['handled_at']
            : ($row['updated_at'] ?? '')
    );

    $screenshot_rel  = "screenshots/item_{$id}.png";
    $screenshot_full = $reports_dir . '/' . $screenshot_rel;
    $has_screenshot  = file_exists($screenshot_full);
    $screenshot_html = '';
    if ($has_screenshot) {
        $screenshot_html = '<div class="mt-3">'
            . '<p class="text-muted small mb-1">첨부 스크린샷</p>'
            . '<img src="' . safe_html($screenshot_rel) . '" alt="스크린샷" '
            . 'class="img-fluid rounded border" style="max-height:360px;">'
            . '</div>';
    }

    $note_html = '';
    $note_text = trim($row['progress_note'] ?? '');
    if ($note_text !== '') {
        $note_html = '<div class="mt-3 p-3 border-start border-success border-3 '
            . 'bg-success bg-opacity-10 rounded">'
            . '<p class="text-muted small mb-1">처리 결과 / 메모</p>'
            . '<p class="mb-0">' . nl2br(safe_html($note_text)) . '</p>'
            . '</div>';
    }

    $location_block = $location !== ''
        ? '<p class="text-muted small font-monospace mb-0">' . $location . '</p>'
        : '';

    $handled_by = !empty($row['handled_by']) ? safe_html($row['handled_by']) : '—';

    $title_escaped  = safe_html($row['request_title'] ?? '');
    $text_escaped   = safe_html($row['request_text'] ?? '');
    $status_text    = $status['text'];
    $status_class   = $status['class'];
    $priority_text  = $priority['text'];
    $priority_class = $priority['class'];

    $cards_html .= <<<HTML

        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge {$status_class}">{$status_text}</span>
                    <span class="badge {$priority_class}">{$priority_text}</span>
                    <span class="badge bg-light text-dark border">{$type_lbl}</span>
                    <span class="badge bg-light text-dark border ms-auto">#$id</span>
                </div>

                <h5 class="card-title mb-2">{$title_escaped}</h5>

                {$location_block}

                <div class="mt-3 p-3 rounded" style="background:#f8f9fa;">
                    <p class="text-muted small mb-1">요청 내용</p>
                    <p class="mb-0" style="white-space:pre-wrap;">{$text_escaped}</p>
                </div>

                {$note_html}

                {$screenshot_html}

                <div class="mt-3 d-flex flex-wrap gap-3 text-muted small">
                    <span>처리 완료: <strong>{$display_date}</strong></span>
                    <span>처리자: <strong>{$handled_by}</strong></span>
                </div>
            </div>
        </div>

HTML;
}

$html_content = <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title_safe}</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <style>
        body {
            background-color: #f5f7fa;
            color: #212529;
        }
        .report-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
        }
        .report-header .badge-count {
            background: rgba(255,255,255,0.15);
            border-radius: 2rem;
            padding: 0.35rem 1rem;
            font-size: 1rem;
        }
        .card {
            border: none;
            border-radius: 0.75rem;
        }
        .font-monospace {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.8rem;
        }
        .report-footer {
            border-top: 1px solid #dee2e6;
            padding: 1.5rem 0;
            color: #6c757d;
            text-align: center;
            font-size: 0.875rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>

<!-- 리포트 헤더 -->
<div class="report-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col">
                <p class="text-white-50 mb-1 small">Seoul Beauty (서울미녀) — HMS System</p>
                <h1 class="h3 mb-2 fw-bold">{$title_safe}</h1>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <span class="badge-count text-white">총 {$count}건</span>
                    <span class="text-white-50 small">생성일: {$generated_at}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 리포트 본문 -->
<div class="container pb-5">
    {$cards_html}
</div>

<!-- 하단 -->
<div class="report-footer">
    <div class="container">
        &copy; Seoul Beauty HMS System &nbsp;|&nbsp; 본 리포트는 자동 생성된 문서입니다.
    </div>
</div>

</body>
</html>
HTML;

// ─── 파일 저장 ───
$written = @file_put_contents($filepath, $html_content);
if ($written === false) {
    echo json_encode(['success' => false, 'error' => '파일 저장 실패 — 서버 경로 또는 권한을 확인하세요.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'url'      => $public_url,
    'filename' => $filename,
    'count'    => $count,
]);
