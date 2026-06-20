<?php
/**
 * PHP 코드 문법 검사 시스템 — 설정
 * 다른 프로젝트로 이동 시 이 파일만 수정하면 됨
 */

// ── 검사 대상 경로 ──────────────────────────────────────────────
// 절대경로 또는 상대경로 (run_check.php 기준)
// code_check/ 는 hairwang_full/code_check/, 검사 대상은 hairwang_full/gangnamunni/
define('CC_SCAN_PATH', getenv('CC_SCAN_PATH') ?: dirname(__DIR__) . '/gangnamunni');

// 제외 패턴 (fnmatch 형식)
define('CC_EXCLUDE_PATTERNS', [
    '*/_backup/*',
    '*/_archive*/*',
    '*/node_modules/*',
    '*/.git/*',
    '*/vendor/*',
]);

// ── 서버 API 엔드포인트 ─────────────────────────────────────────
// 오류 결과를 전송할 서버 URL (없으면 DB 직접 저장)
define('CC_API_URL', getenv('CC_API_URL') ?: 'https://hms.seoulbeauty.app/hairwang/gangnamunni/api_syntax_report.php');
define('CC_API_KEY', getenv('CC_API_KEY') ?: 'cc_key_sb2026_xK9mR3');  // GitHub Secret과 동일하게

// ── DB 직접 연결 (로컬/서버 직접 실행 시) ──────────────────────
define('CC_DB_HOST', getenv('CC_DB_HOST') ?: 'localhost');
define('CC_DB_NAME', getenv('CC_DB_NAME') ?: 'hms_saas');
define('CC_DB_USER', getenv('CC_DB_USER') ?: 'root');
define('CC_DB_PASS', getenv('CC_DB_PASS') ?: '<REMOVED_DB_PASSWORD>');

// ── AI 분석 설정 ────────────────────────────────────────────────
define('CC_AI_PROVIDER', 'anthropic');  // anthropic | openai | skip
define('CC_AI_MODEL', 'claude-haiku-4-5-20251001');  // 빠르고 저렴한 모델 사용
define('CC_AI_MAX_ERRORS', 20);  // 한 번에 AI 분석할 최대 오류 수

// ── 실행 모드 ───────────────────────────────────────────────────
define('CC_MODE_API', 'api');      // API로 전송 (GitHub Actions 등 원격 실행)
define('CC_MODE_DB',  'db');       // DB 직접 저장 (서버/로컬 직접 실행)
define('CC_MODE_STDOUT', 'stdout'); // 콘솔 출력만 (DB/API 없이 빠른 검사)

// PHP 실행 경로 (자동 감지, 필요시 수동 설정)
define('CC_PHP_BIN', getenv('CC_PHP_BIN') ?: PHP_BINARY);
