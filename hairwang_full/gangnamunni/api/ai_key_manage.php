<?php
/**
 * AI API 키 관리 API
 *
 * AJAX 엔드포인트:
 * - action=list        : 키 목록 (마스킹)
 * - action=store       : 키 등록 (암호화)
 * - action=update      : 키 수정
 * - action=delete      : 키 삭제
 * - action=test        : 키 유효성 테스트
 * - action=reorder     : 우선순위 재정렬
 * - action=stats       : 사용 통계
 * - action=test_call   : AI 테스트 호출 (Hello)
 */
session_start();
require_once dirname(__DIR__) . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단

// 인증 체크
if (empty($_SESSION['ad_admin_logged_in'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// super_admin만 키 관리 가능
$role = $_SESSION['ad_admin_role'] ?? '';
if ($role !== 'super_admin') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Super admin only']);
    exit;
}

require_once __DIR__ . '/../includes/ai_proxy.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ========================================================================
    // 키 목록 (마스킹)
    // ========================================================================
    case 'list':
        $provider = $_GET['provider'] ?? '';
        $where = "WHERE 1=1";
        if (!empty($provider)) {
            $where .= " AND provider = '" . mysqli_real_escape_string($connect, $provider) . "'";
        }
        $sql = "SELECT id, provider, model_name, priority, is_active,
                       api_key_encrypted, usage_count, daily_usage_count,
                       rate_limited_until, config_json, created_at, updated_at
                FROM gangnamunni_ai_keys
                {$where}
                ORDER BY provider ASC, priority ASC";
        $result = mysqli_query($connect, $sql);
        $keys = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                // 키 마스킹
                $decrypted = aiDecryptKey($row['api_key_encrypted']);
                $row['masked_key'] = $decrypted ? aiMaskKey($decrypted) : '****';
                unset($row['api_key_encrypted']);

                // rate limit 상태
                $row['is_rate_limited'] = !empty($row['rate_limited_until'])
                    && strtotime($row['rate_limited_until']) > time();

                // config 디코딩
                if (!empty($row['config_json'])) {
                    $row['config_json'] = json_decode($row['config_json'], true);
                }

                $keys[] = $row;
            }
        }
        echo json_encode(['success' => true, 'keys' => $keys]);
        break;

    // ========================================================================
    // 키 등록
    // ========================================================================
    case 'store':
        $provider = trim($_POST['provider'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        $modelName = trim($_POST['model_name'] ?? '');

        $validProviders = ['anthropic', 'openai', 'google', 'deepseek']; // [추가 2026-06-19] deepseek
        if (empty($provider) || !in_array($provider, $validProviders)) {
            echo json_encode(['success' => false, 'error' => 'Invalid provider']);
            break;
        }
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'API key is required']);
            break;
        }

        // 우선순위 자동: 같은 provider 중 max + 1
        $sql = "SELECT COALESCE(MAX(priority), 0) + 1 as next_p
                FROM gangnamunni_ai_keys
                WHERE provider = '" . mysqli_real_escape_string($connect, $provider) . "'";
        $r = mysqli_query($connect, $sql);
        $nextP = ($r && $row = mysqli_fetch_assoc($r)) ? (int)$row['next_p'] : 1;

        // 암호화
        $encrypted = aiEncryptKey($apiKey);

        $providerEsc = mysqli_real_escape_string($connect, $provider);
        $modelEsc = mysqli_real_escape_string($connect, $modelName);
        $encEsc = mysqli_real_escape_string($connect, $encrypted);

        $sql = "INSERT INTO gangnamunni_ai_keys
                (provider, api_key_encrypted, model_name, priority, is_active, created_at, updated_at)
                VALUES ('{$providerEsc}', '{$encEsc}', '{$modelEsc}', {$nextP}, 1, NOW(), NOW())";

        if (mysqli_query($connect, $sql)) {
            $newId = mysqli_insert_id($connect);
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'API key registered']);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . mysqli_error($connect)]);
        }
        break;

    // ========================================================================
    // 키 수정
    // ========================================================================
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            break;
        }

        $updates = [];
        if (isset($_POST['is_active'])) {
            $updates[] = "is_active = " . ((int)$_POST['is_active']);
        }
        if (isset($_POST['model_name'])) {
            $updates[] = "model_name = '" . mysqli_real_escape_string($connect, $_POST['model_name']) . "'";
        }
        if (isset($_POST['priority'])) {
            $updates[] = "priority = " . (int)$_POST['priority'];
        }
        if (!empty($_POST['api_key'])) {
            $encrypted = aiEncryptKey(trim($_POST['api_key']));
            $updates[] = "api_key_encrypted = '" . mysqli_real_escape_string($connect, $encrypted) . "'";
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'Nothing to update']);
            break;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE gangnamunni_ai_keys SET " . implode(', ', $updates) . " WHERE id = {$id}";

        if (mysqli_query($connect, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Key updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . mysqli_error($connect)]);
        }
        break;

    // ========================================================================
    // 키 삭제
    // ========================================================================
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            break;
        }

        $sql = "DELETE FROM gangnamunni_ai_keys WHERE id = {$id}";
        if (mysqli_query($connect, $sql) && mysqli_affected_rows($connect) > 0) {
            echo json_encode(['success' => true, 'message' => 'Key deleted']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Key not found or delete failed']);
        }
        break;

    // ========================================================================
    // 키 테스트
    // ========================================================================
    case 'test':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            break;
        }

        $sql = "SELECT provider, api_key_encrypted FROM gangnamunni_ai_keys WHERE id = {$id}";
        $r = mysqli_query($connect, $sql);
        if (!$r || !($row = mysqli_fetch_assoc($r))) {
            echo json_encode(['success' => false, 'error' => 'Key not found']);
            break;
        }

        $decrypted = aiDecryptKey($row['api_key_encrypted']);
        if (!$decrypted) {
            echo json_encode(['success' => false, 'error' => 'Failed to decrypt key']);
            break;
        }

        $testResult = aiTestKey($row['provider'], $decrypted);
        echo json_encode(array_merge(['success' => true], $testResult));
        break;

    // ========================================================================
    // 우선순위 재정렬
    // ========================================================================
    case 'reorder':
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (empty($items)) {
            echo json_encode(['success' => false, 'error' => 'Items required']);
            break;
        }

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $priority = (int)($item['priority'] ?? 0);
            if ($id > 0) {
                mysqli_query($connect, "UPDATE gangnamunni_ai_keys SET priority = {$priority}, updated_at = NOW() WHERE id = {$id}");
            }
        }
        echo json_encode(['success' => true, 'message' => 'Reordered']);
        break;

    // ========================================================================
    // 사용 통계
    // ========================================================================
    case 'stats':
        // 프로바이더별 집계
        $sql = "SELECT provider,
                       COUNT(*) as key_count,
                       SUM(is_active) as active_count,
                       SUM(usage_count) as total_usage,
                       SUM(daily_usage_count) as today_usage
                FROM gangnamunni_ai_keys
                GROUP BY provider";
        $r = mysqli_query($connect, $sql);
        $byProvider = [];
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $byProvider[] = $row;
            }
        }

        // 최근 7일 사용 로그
        $sql = "SELECT DATE(created_at) as log_date,
                       action_type,
                       COUNT(*) as count,
                       SUM(input_tokens) as input_tokens,
                       SUM(output_tokens) as output_tokens
                FROM gangnamunni_ai_usage_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY log_date, action_type
                ORDER BY log_date DESC";
        $r = mysqli_query($connect, $sql);
        $dailyUsage = [];
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $dailyUsage[] = $row;
            }
        }

        echo json_encode(['success' => true, 'by_provider' => $byProvider, 'daily_usage' => $dailyUsage]);
        break;

    // ========================================================================
    // AI 테스트 호출
    // ========================================================================
    case 'test_call':
        $provider = $_POST['provider'] ?? 'anthropic';
        $testPrompt = $_POST['prompt'] ?? '안녕하세요. 간단한 테스트입니다. 한 문장으로 응답해주세요.';

        $result = callAI('test', $testPrompt, [
            'provider'   => $provider,
            'max_tokens' => 100,
        ]);

        echo json_encode([
            'success' => $result['success'],
            'content' => $result['content'],
            'model'   => $result['model'],
            'tokens'  => $result['tokens'],
            'error'   => $result['error'],
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}
