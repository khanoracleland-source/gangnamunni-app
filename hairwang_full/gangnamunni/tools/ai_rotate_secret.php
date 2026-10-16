<?php
/**
 * AI 키 암호화 시크릿 교체 (2026-10-16)
 *
 * data/ai_config.json 의 encryption_secret 이 git 에 커밋되어 노출됐다. 저장소 접근자는 DB 의
 * 암호문(gangnamunni_ai_keys.api_key_encrypted, gangnamunni_hospitals.ai_own_api_key)만 얻으면
 * 원본 API 키를 복호화할 수 있으므로 시크릿을 새로 만들고 모든 암호문을 다시 암호화한다.
 *
 * 실행 (서버에서):
 *   php gangnamunni/tools/ai_rotate_secret.php --dry-run      # 전부 복호화 가능한지 검증만
 *   php gangnamunni/tools/ai_rotate_secret.php                # 실제 교체
 *   php gangnamunni/tools/ai_rotate_secret.php --out=/etc/hms/ai_config.json
 *        └ AI_ENCRYPTION_SECRET 환경변수를 쓰는 경우 새 시크릿을 저장할 파일 (필수)
 *
 * 순서: 검증 → 새 시크릿을 임시 파일에 저장 → DB 트랜잭션으로 일괄 재암호화 → 설정 파일 교체.
 * 하나라도 복호화 실패하면 아무것도 바꾸지 않는다.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// CLI 에서는 PHP-FPM 환경변수가 없으므로 웹과 같은 DB 설정(data/dbconfig.php)을 먼저 로드한다.
require_once dirname(__DIR__) . '/includes/booking_payment_db_config.php';
require_once dirname(__DIR__) . '/includes/ai_proxy.php';
if (empty($connect) || !($connect instanceof mysqli)) { fwrite(STDERR, "DB 연결 실패\n"); exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$opts = getopt('', ['dry-run', 'out::']);
$dry = isset($opts['dry-run']);
$envSecret = getenv('AI_ENCRYPTION_SECRET');
$usingEnv = is_string($envSecret) && strlen($envSecret) >= 32;
$target = $usingEnv ? (string)($opts['out'] ?? '') : (string)($opts['out'] ?? aiConfigFilePath());
if (!$dry && $target === '') {
    fwrite(STDERR, "AI_ENCRYPTION_SECRET 환경변수 사용 중 — 새 시크릿을 저장할 --out=<경로> 를 지정하세요.\n");
    exit(1);
}

$oldSecret = aiGetEncryptionSecret();
if ($oldSecret === '') {
    fwrite(STDERR, "현재 시크릿을 찾을 수 없습니다(" . aiConfigFilePath() . "). 기존 파일을 복구한 뒤 다시 실행하세요.\n");
    exit(1);
}
$newSecret = bin2hex(random_bytes(32));

// API 키는 출력 가능한 ASCII. CBC 는 무결성 검증이 없어 잘못된 키로도 가끔 "복호화"되므로 형태까지 확인한다.
$looksLikeKey = function ($v) { return is_string($v) && preg_match('/^[\x21-\x7E]{8,1024}$/', $v) === 1; };

$sources = [
    ['table' => 'gangnamunni_ai_keys',     'col' => 'api_key_encrypted'],
    ['table' => 'gangnamunni_hospitals',   'col' => 'ai_own_api_key'],
];

$updates = [];
$failed = [];
foreach ($sources as $src) {
    try {
        $res = $connect->query("SELECT id, `{$src['col']}` AS enc FROM `{$src['table']}` WHERE `{$src['col']}` IS NOT NULL AND `{$src['col']}` <> ''");
    } catch (Throwable $e) {
        echo "- {$src['table']}.{$src['col']}: 건너뜀 ({$e->getMessage()})\n";
        continue;
    }
    $n = 0;
    while ($row = $res->fetch_assoc()) {
        $plain = aiDecryptWithSecret($row['enc'], $oldSecret);
        if (!$looksLikeKey($plain)) { $failed[] = "{$src['table']}#{$row['id']}"; continue; }
        $enc = aiEncryptWithSecret($plain, $newSecret);
        if (aiDecryptWithSecret($enc, $newSecret) !== $plain) { $failed[] = "{$src['table']}#{$row['id']} (roundtrip)"; continue; }
        $updates[] = [$src['table'], $src['col'], (int)$row['id'], $enc, $row['enc']];
        $n++;
    }
    echo "- {$src['table']}.{$src['col']}: {$n}건 재암호화 가능\n";
}

if ($failed) {
    fwrite(STDERR, "복호화 실패 " . count($failed) . "건 — 변경하지 않고 중단:\n  " . implode("\n  ", $failed) . "\n");
    fwrite(STDERR, "(해당 행의 키를 관리 화면에서 다시 입력하거나 비운 뒤 재실행하세요.)\n");
    exit(1);
}
if ($dry) { echo "dry-run OK — 총 " . count($updates) . "건\n"; exit(0); }

// 1) 새 시크릿을 임시 파일에 먼저 저장 (DB 커밋 후 파일 쓰기에 실패해 키를 잃는 일이 없도록)
$tmp = $target . '.new';
$dir = dirname($target);
if (!is_dir($dir) && !@mkdir($dir, 0750, true)) { fwrite(STDERR, "디렉터리 생성 실패: $dir\n"); exit(1); }
$um = umask(0077);
$ok = file_put_contents($tmp, json_encode(['encryption_secret' => $newSecret], JSON_PRETTY_PRINT), LOCK_EX);
umask($um);
if ($ok === false) { fwrite(STDERR, "임시 파일 저장 실패: $tmp\n"); exit(1); }
@chmod($tmp, 0600);

// 2) DB 일괄 교체 (낙관적 잠금: 그 사이 값이 바뀐 행이 있으면 롤백)
$connect->begin_transaction();
try {
    foreach ($updates as [$table, $col, $id, $enc, $orig]) {
        $st = $connect->prepare("UPDATE `$table` SET `$col` = ? WHERE id = ? AND `$col` = ?");
        $st->bind_param('sis', $enc, $id, $orig);
        $st->execute();
        if ($st->affected_rows !== 1) throw new RuntimeException("$table#$id 가 실행 중 변경됨");
        $st->close();
    }
    $connect->commit();
} catch (Throwable $e) {
    $connect->rollback();
    @unlink($tmp);
    fwrite(STDERR, "DB 갱신 실패 — 롤백함: {$e->getMessage()}\n");
    exit(1);
}

// 3) 설정 파일 교체 (기존 파일은 확인용으로 보관 — 검증 후 삭제할 것)
$backup = '';
if (file_exists($target)) {
    $backup = $target . '.old-' . date('YmdHis');
    @rename($target, $backup);
    @chmod($backup, 0600);
}
if (!@rename($tmp, $target)) {
    fwrite(STDERR, "!!! DB 는 새 시크릿으로 갱신됐지만 설정 파일 교체 실패. 수동으로 옮기세요: mv $tmp $target\n");
    exit(2);
}

echo "완료: " . count($updates) . "건 재암호화, 새 시크릿 → $target\n";
if ($backup) echo "이전 설정 백업: $backup (AI 기능 확인 후 삭제)\n";
if ($usingEnv) echo "AI_ENCRYPTION_SECRET 환경변수를 새 파일의 값으로 교체하고 PHP-FPM 을 재시작하세요(교체 전까지 AI 키 복호화 실패).\n";
