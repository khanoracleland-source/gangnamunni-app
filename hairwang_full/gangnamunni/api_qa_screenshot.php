<?php
/**
 * QA 스크린샷 캡처 API
 * Node.js Puppeteer를 호출하여 PC/모바일 스크린샷 생성
 *
 * GET  ?action=status&prefix=F01  → 캡처 이미지 존재 여부
 * POST {action:"capture", items:[{url,prefix},...]}  → 배치 캡처
 * POST {action:"capture_one", url:"...", prefix:"F01"}  → 단일 캡처
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$BASE_DIR = __DIR__;
$SCREENSHOT_DIR = $BASE_DIR . '/qa_screenshots';
$NODE_SCRIPT = $BASE_DIR . '/qa_screenshot.js';

// 스크린샷 디렉토리 생성
if (!is_dir($SCREENSHOT_DIR)) {
    mkdir($SCREENSHOT_DIR, 0755, true);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// GET: 캡처 상태 확인
if ($method === 'GET') {
    if ($action === 'status') {
        $prefix = $_GET['prefix'] ?? '';
        if (empty($prefix)) {
            echo json_encode(['success' => false, 'message' => 'prefix 필수']);
            exit;
        }
        $pc = file_exists("$SCREENSHOT_DIR/{$prefix}_pc.png");
        $mob = file_exists("$SCREENSHOT_DIR/{$prefix}_mob.png");
        $pcTime = $pc ? filemtime("$SCREENSHOT_DIR/{$prefix}_pc.png") : null;
        echo json_encode([
            'success' => true,
            'pc' => $pc ? "qa_screenshots/{$prefix}_pc.png" : null,
            'mob' => $mob ? "qa_screenshots/{$prefix}_mob.png" : null,
            'captured_at' => $pcTime ? date('Y-m-d H:i:s', $pcTime) : null
        ]);
        exit;
    }

    // 전체 목록
    if ($action === 'list') {
        $files = glob("$SCREENSHOT_DIR/*_pc.png");
        $items = [];
        foreach ($files as $f) {
            $name = basename($f, '_pc.png');
            $mob = file_exists("$SCREENSHOT_DIR/{$name}_mob.png");
            $items[] = [
                'prefix' => $name,
                'pc' => "qa_screenshots/{$name}_pc.png",
                'mob' => $mob ? "qa_screenshots/{$name}_mob.png" : null,
                'captured_at' => date('Y-m-d H:i:s', filemtime($f))
            ];
        }
        echo json_encode(['success' => true, 'data' => $items, 'count' => count($items)]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'QA Screenshot API', 'actions' => ['status', 'list', 'capture', 'capture_one']]);
    exit;
}

// POST: 캡처 실행
if ($method === 'POST') {
    // 단일 캡처
    if ($action === 'capture_one') {
        $url = trim($input['url'] ?? '');
        $prefix = trim($input['prefix'] ?? '');

        if (empty($url) || empty($prefix)) {
            echo json_encode(['success' => false, 'message' => 'url, prefix 필수']);
            exit;
        }

        $cmd = "export HOME=/tmp && cd $BASE_DIR && node qa_screenshot.js " . escapeshellarg($url) . " " . escapeshellarg($prefix) . " 2>&1";
        $output = shell_exec($cmd);

        $pc = file_exists("$SCREENSHOT_DIR/{$prefix}_pc.png");
        $mob = file_exists("$SCREENSHOT_DIR/{$prefix}_mob.png");
        $t = time();

        echo json_encode([
            'success' => $pc || $mob,
            'pc' => $pc ? "qa_screenshots/{$prefix}_pc.png?t=$t" : null,
            'mob' => $mob ? "qa_screenshots/{$prefix}_mob.png?t=$t" : null,
            'log' => $output
        ]);
        exit;
    }

    // 배치 캡처
    if ($action === 'capture') {
        $items = $input['items'] ?? [];
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'items 필수']);
            exit;
        }

        // JSON 파일 생성
        $tmpFile = '/tmp/qa_batch_' . uniqid() . '.json';
        file_put_contents($tmpFile, json_encode($items));

        $cmd = "export HOME=/tmp && cd $BASE_DIR && node qa_screenshot.js --batch " . escapeshellarg($tmpFile) . " 2>&1";
        $output = shell_exec($cmd);
        unlink($tmpFile);

        // 결과 수집
        $t = time();
        $results = [];
        foreach ($items as $item) {
            $p = $item['prefix'];
            $results[$p] = [
                'pc' => file_exists("$SCREENSHOT_DIR/{$p}_pc.png") ? "qa_screenshots/{$p}_pc.png?t=$t" : null,
                'mob' => file_exists("$SCREENSHOT_DIR/{$p}_mob.png") ? "qa_screenshots/{$p}_mob.png?t=$t" : null
            ];
        }

        echo json_encode(['success' => true, 'results' => $results, 'count' => count($results), 'log' => $output]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'action 필수 (capture, capture_one)']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);
