<?php
/**
 * PHP 코드 문법 검사 — MCP API
 * Model Context Protocol 호환 JSON-RPC 2.0 엔드포인트
 *
 * 사용 예시 (Claude MCP 설정):
 *   {
 *     "mcpServers": {
 *       "php-syntax": {
 *         "url": "https://hms.seoulbeauty.app/hairwang/app/api_code_check_mcp.php",
 *         "headers": { "X-CC-Key": "cc_key_sb2026_xK9mR3" }
 *       }
 *     }
 *   }
 *
 * 지원 메서드:
 *   tools/list           — 사용 가능한 툴 목록
 *   tools/call           — 툴 실행
 *     - php_syntax_check : 파일/경로 문법 검사
 *     - get_errors       : 현재 오류 목록 조회
 *     - get_runs         : 최근 실행 이력
 *     - run_full_scan    : 전체 스캔 실행 (서버)
 *     - ai_analyze       : 특정 오류 AI 재분석
 *     - mark_fixed       : 오류 수정 완료 표시
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CC-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── 인증 ─────────────────────────────────────────────────────────
$CC_API_KEY = 'cc_key_sb2026_xK9mR3';
$in_key = $_SERVER['HTTP_X_CC_KEY'] ?? $_GET['key'] ?? '';
if (!hash_equals($CC_API_KEY, $in_key)) {
    mcp_error(-32001, 'Unauthorized', null, 401);
}

// ── DB 연결 ──────────────────────────────────────────────────────
require_once __DIR__ . '/../dbconfig.php';
$db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, (int)G5_MYSQL_PORT);
$db->set_charset('utf8mb4');
if ($db->connect_error) mcp_error(-32002, 'DB error', null, 500);

// ── 요청 파싱 ────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$req  = json_decode($body, true);

// GET으로 간단 테스트 허용
if (!$req && isset($_GET['method'])) {
    $req = ['jsonrpc'=>'2.0','id'=>1,'method'=>$_GET['method'],'params'=>$_GET];
}

if (!$req) mcp_error(-32700, 'Parse error');

$id     = $req['id']     ?? null;
$method = $req['method'] ?? '';
$params = $req['params'] ?? [];

// ── 라우팅 ───────────────────────────────────────────────────────
switch ($method) {

    // MCP 표준: 서버 정보
    case 'initialize':
        mcp_ok($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => ['name' => 'php-syntax-checker', 'version' => '1.0.0'],
        ]);

    // 툴 목록
    case 'tools/list':
        mcp_ok($id, ['tools' => mcp_tool_list()]);

    // 툴 실행
    case 'tools/call':
        $tool_name = $params['name'] ?? '';
        $args      = $params['arguments'] ?? $params ?? [];
        mcp_ok($id, mcp_call_tool($tool_name, $args, $db));

    // 호환성: 직접 메서드명으로도 허용
    case 'php_syntax_check':
    case 'get_errors':
    case 'get_runs':
    case 'run_full_scan':
    case 'ai_analyze':
    case 'mark_fixed':
        mcp_ok($id, mcp_call_tool($method, $params, $db));

    default:
        mcp_error(-32601, "Method not found: $method", $id);
}

// ── 툴 목록 정의 ─────────────────────────────────────────────────
function mcp_tool_list(): array {
    return [
        [
            'name'        => 'php_syntax_check',
            'description' => 'PHP 파일 하나 또는 디렉토리를 php -l로 문법 검사합니다',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'path' => ['type'=>'string','description'=>'검사할 파일 절대경로 또는 디렉토리'],
                ],
                'required'   => ['path'],
            ],
        ],
        [
            'name'        => 'get_errors',
            'description' => '현재 DB에 저장된 PHP 문법 오류 목록을 조회합니다',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'status' => ['type'=>'string','enum'=>['new','ai_checked','fixed','ignored','all'],'description'=>'필터 상태 (기본: new+ai_checked)'],
                    'limit'  => ['type'=>'integer','description'=>'최대 반환 수 (기본 20)'],
                ],
            ],
        ],
        [
            'name'        => 'get_runs',
            'description' => '최근 PHP 문법 검사 실행 이력을 조회합니다',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'limit' => ['type'=>'integer','description'=>'최대 반환 수 (기본 5)'],
                ],
            ],
        ],
        [
            'name'        => 'run_full_scan',
            'description' => '서버 전체 PHP 파일을 지금 즉시 스캔합니다 (419개+ 파일, 수십 초 소요)',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'no_ai' => ['type'=>'boolean','description'=>'AI 분석 건너뜀 (기본 false)'],
                ],
            ],
        ],
        [
            'name'        => 'ai_analyze',
            'description' => '특정 오류 ID에 대해 Claude AI 원인 분석 + 수정 제안을 요청합니다',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'error_id' => ['type'=>'integer','description'=>'gangnamunni_syntax_errors.id'],
                ],
                'required'   => ['error_id'],
            ],
        ],
        [
            'name'        => 'mark_fixed',
            'description' => '오류를 수정 완료(fixed) 또는 무시(ignored) 상태로 변경합니다',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'error_id' => ['type'=>'integer','description'=>'오류 ID'],
                    'status'   => ['type'=>'string','enum'=>['fixed','ignored']],
                    'note'     => ['type'=>'string','description'=>'메모 (선택)'],
                ],
                'required'   => ['error_id','status'],
            ],
        ],
    ];
}

// ── 툴 실행 ──────────────────────────────────────────────────────
function mcp_call_tool(string $name, array $args, mysqli $db): array {
    switch ($name) {

        case 'php_syntax_check': {
            $path = $args['path'] ?? '';
            if (!$path) return mcp_content('오류: path 필수');

            $out = [];
            if (is_file($path)) {
                $cmd = PHP_BINARY . ' -l ' . escapeshellarg($path) . ' 2>&1';
                $r = shell_exec($cmd);
                $ok = strpos($r,'No syntax errors') !== false;
                $out[] = ['file'=>$path,'ok'=>$ok,'output'=>trim($r)];
            } elseif (is_dir($path)) {
                // 디렉토리: 최대 50개만
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
                $n = 0;
                foreach ($it as $f) {
                    if ($f->getExtension() !== 'php') continue;
                    if (++$n > 50) break;
                    $fp = $f->getPathname();
                    $r  = shell_exec(PHP_BINARY . ' -l ' . escapeshellarg($fp) . ' 2>&1');
                    $ok = strpos($r,'No syntax errors') !== false;
                    if (!$ok) $out[] = ['file'=>$fp,'ok'=>false,'output'=>trim($r)];
                }
                if (!$out) return mcp_content("✅ $n 개 파일 모두 정상");
            } else {
                return mcp_content("경로 없음: $path");
            }

            $text = '';
            foreach ($out as $e) {
                $text .= "❌ {$e['file']}\n   {$e['output']}\n\n";
            }
            return mcp_content($text ?: '✅ 정상');
        }

        case 'get_errors': {
            $status = $args['status'] ?? '';
            $limit  = min((int)($args['limit'] ?? 20), 100);
            $where  = $status === 'all' ? '' :
                      ($status ? "WHERE status='".mysqli_real_escape_string($db,$status)."'" :
                                 "WHERE status IN ('new','ai_checked')");
            $rows = [];
            $r = $db->query("SELECT id,file_name,line_no,error_msg,ai_analysis,ai_fix_suggestion,status,created_at
                              FROM gangnamunni_syntax_errors $where ORDER BY created_at DESC LIMIT $limit");
            if ($r) while ($row=$r->fetch_assoc()) $rows[] = $row;

            if (!$rows) return mcp_content('오류 없음 ✅');

            $text = count($rows) . "개 오류:\n\n";
            foreach ($rows as $e) {
                $text .= "#{$e['id']} [{$e['status']}] {$e['file_name']}:{$e['line_no']}\n";
                $text .= "  오류: {$e['error_msg']}\n";
                if ($e['ai_analysis'])      $text .= "  AI원인: {$e['ai_analysis']}\n";
                if ($e['ai_fix_suggestion']) $text .= "  AI수정: {$e['ai_fix_suggestion']}\n";
                $text .= "\n";
            }
            return mcp_content($text);
        }

        case 'get_runs': {
            $limit = min((int)($args['limit'] ?? 5), 20);
            $rows = [];
            $r = $db->query("SELECT id,total_files,ok_files,error_count,run_source,php_version,scanned_at
                              FROM gangnamunni_syntax_runs ORDER BY id DESC LIMIT $limit");
            if ($r) while ($row=$r->fetch_assoc()) $rows[] = $row;
            if (!$rows) return mcp_content('실행 이력 없음');

            $text = '';
            foreach ($rows as $r) {
                $status = $r['error_count'] == 0 ? '✅' : '❌';
                $text .= "$status Run#{$r['id']} | {$r['scanned_at']} | 파일:{$r['total_files']} | 오류:{$r['error_count']} | {$r['run_source']} | PHP{$r['php_version']}\n";
            }
            return mcp_content($text);
        }

        case 'run_full_scan': {
            $noAI   = !empty($args['no_ai']) ? '--no-ai' : '';
            $runner = dirname(dirname(__DIR__)) . '/code_check/run_check.php';
            if (!file_exists($runner)) return mcp_content("run_check.php 없음: $runner");

            $cmd = PHP_BINARY . ' ' . escapeshellarg($runner) . " --mode=db $noAI 2>&1";
            $out = shell_exec("cd " . escapeshellarg(dirname($runner)) . " && $cmd");
            return mcp_content($out ?: '(출력 없음)');
        }

        case 'ai_analyze': {
            $err_id = (int)($args['error_id'] ?? 0);
            if (!$err_id) return mcp_content('error_id 필수');

            $row = $db->query("SELECT * FROM gangnamunni_syntax_errors WHERE id=$err_id LIMIT 1")->fetch_assoc();
            if (!$row) return mcp_content("오류 ID $err_id 없음");

            $lib = dirname(dirname(__DIR__)) . '/code_check/';
            if (!file_exists($lib . 'config.php')) return mcp_content('code_check/config.php 없음');
            require_once $lib . 'config.php';
            require_once $lib . 'lib_ai.php';

            $err = ['file'=>$row['file_path'],'line_no'=>$row['line_no'],
                    'error_msg'=>$row['error_msg'],'context'=>$row['context_code']];
            $ai  = cc_ai_analyze_error($err, $db);

            if ($ai['ai_used']) {
                $stmt = $db->prepare(
                    "UPDATE gangnamunni_syntax_errors SET ai_analysis=?,ai_fix_suggestion=?,ai_model=?,status='ai_checked',ai_checked_at=NOW() WHERE id=? LIMIT 1"
                );
                $m = $ai['ai_model'] ?? '';
                $stmt->bind_param('sssi',$ai['analysis'],$ai['fix_suggestion'],$m,$err_id);
                $stmt->execute(); $stmt->close();
                return mcp_content("【원인】{$ai['analysis']}\n\n【수정】{$ai['fix_suggestion']}");
            }
            return mcp_content('AI 분석 실패 (API 키 확인 필요)');
        }

        case 'mark_fixed': {
            $err_id = (int)($args['error_id'] ?? 0);
            $status = in_array($args['status']??'',['fixed','ignored']) ? $args['status'] : 'fixed';
            $note   = $db->real_escape_string($args['note'] ?? '');
            if (!$err_id) return mcp_content('error_id 필수');

            $db->query("UPDATE gangnamunni_syntax_errors SET status='$status', note='$note' WHERE id=$err_id LIMIT 1");
            return mcp_content("✅ #$err_id 상태 → $status");
        }

        default:
            return mcp_content("알 수 없는 툴: $name");
    }
}

// ── 헬퍼 ─────────────────────────────────────────────────────────
function mcp_content(string $text): array {
    return ['content' => [['type' => 'text', 'text' => $text]]];
}

function mcp_ok($id, array $result): void {
    echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result], JSON_UNESCAPED_UNICODE);
    exit;
}

function mcp_error(int $code, string $msg, $id = null, int $http = 200): void {
    http_response_code($http);
    echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$msg]], JSON_UNESCAPED_UNICODE);
    exit;
}
