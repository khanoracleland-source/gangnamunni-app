<?php
/**
 * AI 분석 백그라운드 실행기
 * api_syntax_report.php 에서 nohup으로 호출
 * Usage: php api_syntax_ai_run.php <run_id>
 */

if (php_sapi_name() !== 'cli') exit;

$run_id = (int)($argv[1] ?? 0);
if (!$run_id) exit;

require_once __DIR__ . '/../dbconfig.php';

// code_check 라이브러리 로드
$lib = dirname(__DIR__) . '/code_check/';
if (!file_exists($lib . 'config.php')) exit;
require_once $lib . 'config.php';
require_once $lib . 'lib_ai.php';

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$db->set_charset('utf8mb4');
if ($db->connect_error) exit;

// 이번 run의 'new' 상태 오류 가져오기
$stmt = $db->prepare(
    "SELECT id, file_path, line_no, error_msg, context_code FROM gangnamunni_syntax_errors
     WHERE run_id=? AND status='new' LIMIT " . CC_AI_MAX_ERRORS
);
$stmt->bind_param('i', $run_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $err = [
        'file'      => $row['file_path'],
        'line_no'   => $row['line_no'],
        'error_msg' => $row['error_msg'],
        'context'   => $row['context_code'],
    ];
    $ai = cc_ai_analyze_error($err, $db);
    if ($ai['ai_used']) {
        $upd = $db->prepare(
            "UPDATE gangnamunni_syntax_errors
             SET ai_analysis=?, ai_fix_suggestion=?, ai_model=?, status='ai_checked', ai_checked_at=NOW()
             WHERE id=? LIMIT 1"
        );
        $model = $ai['ai_model'] ?? '';
        $upd->bind_param('sssi', $ai['analysis'], $ai['fix_suggestion'], $model, $row['id']);
        $upd->execute();
        $upd->close();
    }
}
$stmt->close();
$db->close();
