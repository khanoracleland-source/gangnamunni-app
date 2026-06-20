<?php
/**
 * PHP 문법 검사 수신 API
 * GitHub Actions 또는 run_check.php --mode=api 에서 POST로 전송받아 DB 저장 + AI 분석 큐
 *
 * POST /api_syntax_report.php
 * Header: X-CC-Key: <api_key>
 * Body: JSON (run_check.php 결과 형식)
 */

header('Content-Type: application/json; charset=utf-8');

// ── 인증 ─────────────────────────────────────────────────────────
$CC_API_KEY = 'cc_key_sb2026_xK9mR3';  // GitHub Secret CC_API_KEY 와 동일
$in_key = $_SERVER['HTTP_X_CC_KEY'] ?? '';
if (!hash_equals($CC_API_KEY, $in_key)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only']);
    exit;
}

// ── DB 연결 ──────────────────────────────────────────────────────
require_once __DIR__ . '/../dbconfig.php';
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$db->set_charset('utf8mb4');
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'DB error']);
    exit;
}

// ── 입력 파싱 ────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data || !isset($data['total'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid payload']);
    exit;
}

// ── 실행 기록 저장 ────────────────────────────────────────────────
$scan_path   = $data['scan_path']   ?? '';
$total       = (int)($data['total'] ?? 0);
$ok          = (int)($data['ok']    ?? 0);
$error_count = (int)($data['error_count'] ?? 0);
$php_ver     = $data['php_version'] ?? '';
$source      = $data['source']      ?? 'api';
$branch      = $data['git_branch']  ?? '';
$commit      = $data['git_commit']  ?? '';
$triggered   = $data['triggered_by'] ?? '';

$stmt = $db->prepare(
    "INSERT INTO gangnamunni_syntax_runs
     (scan_path,total_files,ok_files,error_count,php_version,run_source,git_branch,git_commit,triggered_by,scanned_at)
     VALUES (?,?,?,?,?,?,?,?,?,NOW())"
);
$stmt->bind_param('siiisssss', $scan_path, $total, $ok, $error_count, $php_ver, $source, $branch, $commit, $triggered);
$stmt->execute();
$run_id = $db->insert_id;
$stmt->close();

// ── 오류 저장 ─────────────────────────────────────────────────────
$errors = $data['errors'] ?? [];
foreach ($errors as $err) {
    $fpath  = $err['file']      ?? '';
    $fname  = basename($fpath);
    $lineno = $err['line_no']   ?? null;
    $emsg   = $err['error_msg'] ?? '';
    $raw    = $err['raw_output'] ?? '';
    $ctx    = $err['context']    ?? '';

    $stmt = $db->prepare(
        "INSERT INTO gangnamunni_syntax_errors
         (run_id,file_path,file_name,line_no,error_msg,raw_output,context_code,status,created_at)
         VALUES (?,?,?,?,?,?,?,'new',NOW())
         ON DUPLICATE KEY UPDATE
           run_id=VALUES(run_id), line_no=VALUES(line_no),
           error_msg=VALUES(error_msg), raw_output=VALUES(raw_output),
           context_code=VALUES(context_code), status='new',
           ai_analysis='', ai_fix_suggestion='', ai_checked_at=NULL"
    );
    $stmt->bind_param('ississ s', $run_id, $fpath, $fname, $lineno, $emsg, $raw, $ctx);
    $stmt->execute();
    $stmt->close();
}

// ── AI 분석 큐 (최대 20개 비동기 처리 — nohup) ─────────────────
if ($error_count > 0) {
    $runner = dirname(__DIR__) . '/code_check/run_check.php';
    if (file_exists($runner)) {
        // DB에 이미 저장됨, AI만 실행 (별도 스크립트)
        $ai_cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/api_syntax_ai_run.php')
                . ' ' . escapeshellarg($run_id) . ' > /dev/null 2>&1 &';
        shell_exec('nohup ' . $ai_cmd);
    }
}

echo json_encode([
    'ok'      => true,
    'run_id'  => $run_id,
    'saved'   => count($errors),
    'ai_queue'=> $error_count > 0 ? 'queued' : 'none',
]);
$db->close();
