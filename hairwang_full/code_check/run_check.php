<?php
/**
 * PHP 코드 문법 검사기 — 메인 실행 파일
 *
 * 사용법:
 *   php run_check.php                          # 기본 (config.php 경로 사용, DB 저장)
 *   php run_check.php --mode=stdout            # 콘솔 출력만 (DB/API 없이 빠른 검사)
 *   php run_check.php --mode=api               # API로 전송 (GitHub Actions용)
 *   php run_check.php --target=/path/to/files  # 경로 오버라이드
 *   php run_check.php --no-ai                  # AI 분석 건너뜀
 *
 * 다른 프로젝트 이식:
 *   1) code_check/ 폴더 통째로 복사
 *   2) config.php 의 CC_SCAN_PATH, CC_DB_* 수정
 *   3) php run_check.php 실행
 */

// ── 부트스트랩 ──────────────────────────────────────────────────
$isCLI = (php_sapi_name() === 'cli');
if (!$isCLI) { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib_scan.php';
require_once __DIR__ . '/lib_ai.php';

// ── 인자 파싱 ───────────────────────────────────────────────────
$opts = getopt('', ['mode:', 'target:', 'no-ai', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php run_check.php [--mode=db|api|stdout] [--target=PATH] [--no-ai]\n";
    exit(0);
}

$mode   = $opts['mode']   ?? CC_MODE_DB;
$target = $opts['target'] ?? CC_SCAN_PATH;
$noAI   = isset($opts['no-ai']);
if (!is_dir($target)) { echo "[ERROR] 경로 없음: $target\n"; exit(1); }

// ── 스캔 실행 ───────────────────────────────────────────────────
echo "[PHP 문법 검사] 시작\n";
echo "대상: $target\n";
echo "모드: $mode\n";
echo str_repeat('-', 60) . "\n";

$result = cc_scan_directory($target, CC_EXCLUDE_PATTERNS);

echo "검사 완료: {$result['total']}개 파일 / 오류: {$result['error_count']}개\n";
echo str_repeat('-', 60) . "\n";

// ── DB 저장 (오류 유무 관계없이 항상 실행 이력 저장) ─────────────
$db = null;
if ($mode === CC_MODE_DB) {
    $db = cc_connect_db();
    if ($db) {
        cc_save_run($db, $result);
    }
} elseif ($mode === CC_MODE_API) {
    cc_send_to_api($result);
}

if ($result['error_count'] === 0) {
    echo "✅ 모든 파일 문법 정상!\n";
    if ($db) { echo "[DB] 실행 이력 저장 완료\n"; $db->close(); }
    exit(0);
}

// ── 오류 출력 ───────────────────────────────────────────────────
foreach ($result['errors'] as $i => $err) {
    echo "\n[오류 " . ($i + 1) . "] " . $err['file'] . "\n";
    echo "  줄: " . ($err['line_no'] ?? '?') . " | " . $err['error_msg'] . "\n";
    if (!empty($err['context'])) {
        echo "  ---\n";
        foreach (explode("\n", $err['context']) as $cl) {
            echo "  $cl\n";
        }
    }
}

// ── 오류 있을 때 AI 분석 ─────────────────────────────────────────
if ($db && !$noAI && $result['error_count'] > 0) {
    echo "\n[AI 분석] 오류 {$result['error_count']}개 분석 시작...\n";
    cc_run_ai_analysis($db, $result['errors']);
}

if ($db) $db->close();
echo "\n완료. DB 저장됨.\n";
exit($result['error_count'] > 0 ? 1 : 0);

// ── DB 함수들 ────────────────────────────────────────────────────
function cc_connect_db(): ?mysqli {
    $db = new mysqli(CC_DB_HOST, CC_DB_USER, CC_DB_PASS, CC_DB_NAME);
    $db->set_charset('utf8mb4');
    if ($db->connect_error) {
        echo "[DB 오류] " . $db->connect_error . "\n";
        return null;
    }
    return $db;
}

function cc_save_run(mysqli $db, array $result): int {
    // 실행 기록
    $stmt = $db->prepare(
        "INSERT INTO gangnamunni_syntax_runs
         (scan_path, total_files, ok_files, error_count, php_version, run_source, scanned_at)
         VALUES (?,?,?,?,?,?,NOW())"
    );
    $source = getenv('GITHUB_ACTIONS') ? 'github_actions' : 'manual';
    $stmt->bind_param('siiiss',
        $result['scan_path'], $result['total'], $result['ok'],
        $result['error_count'], $result['php_version'], $source
    );
    $stmt->execute();
    $run_id = $db->insert_id;
    $stmt->close();

    // 오류 저장
    foreach ($result['errors'] as $err) {
        cc_upsert_error($db, $run_id, $err);
    }

    echo "[DB] 실행 기록 저장 (run_id=$run_id)\n";
    return $run_id;
}

function cc_upsert_error(mysqli $db, int $run_id, array $err): void {
    $stmt = $db->prepare(
        "INSERT INTO gangnamunni_syntax_errors
         (run_id, file_path, file_name, line_no, error_msg, raw_output, context_code, status, created_at)
         VALUES (?,?,?,?,?,?,?,'new',NOW())
         ON DUPLICATE KEY UPDATE
           run_id=VALUES(run_id), line_no=VALUES(line_no),
           error_msg=VALUES(error_msg), raw_output=VALUES(raw_output),
           context_code=VALUES(context_code), status='new', ai_analysis='', ai_fix_suggestion=''
        "
    );
    $fname = basename($err['file']);
    $stmt->bind_param('ississs',
        $run_id, $err['file'], $fname,
        $err['line_no'], $err['error_msg'],
        $err['raw_output'], $err['context']
    );
    $stmt->execute();
    $stmt->close();
}

function cc_run_ai_analysis(mysqli $db, array $errors): void {
    $count = min(count($errors), CC_AI_MAX_ERRORS);
    for ($i = 0; $i < $count; $i++) {
        $err = $errors[$i];
        echo "  AI [{$i}] " . basename($err['file']) . ":{$err['line_no']} 분석 중...\n";
        $ai = cc_ai_analyze_error($err, $db);
        if ($ai['ai_used']) {
            $stmt = $db->prepare(
                "UPDATE gangnamunni_syntax_errors
                 SET ai_analysis=?, ai_fix_suggestion=?, ai_model=?, status='ai_checked', ai_checked_at=NOW()
                 WHERE file_path=? AND status='new'
                 LIMIT 1"
            );
            $model = $ai['ai_model'] ?? '';
            $stmt->bind_param('ssss', $ai['analysis'], $ai['fix_suggestion'], $model, $err['file']);
            $stmt->execute();
            $stmt->close();
            echo "    ✅ 완료: " . mb_substr($ai['analysis'], 0, 60) . "\n";
        }
    }
}

function cc_send_to_api(array $result): void {
    $payload = json_encode($result);
    $ch = curl_init(CC_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-CC-Key: ' . CC_API_KEY,
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "[API] HTTP $code: $resp\n";
}
