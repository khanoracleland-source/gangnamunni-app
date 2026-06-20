<?php
/**
 * 서울미녀 API 전체 테스트 스크립트
 * 모든 엔드포인트를 테스트하고 결과를 출력합니다
 */

$API_BASE = 'http://localhost:8080/api';
$testResults = [];
$totalTests = 0;
$passedTests = 0;

function testAPI($name, $url, $method = 'GET', $data = null) {
    global $API_BASE, $testResults, $totalTests, $passedTests;

    $totalTests++;
    $fullUrl = $API_BASE . $url;

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Test #{$totalTests}: {$name}\n";
    echo "URL: {$method} {$fullUrl}\n";

    $ch = curl_init($fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $success = false;
    $message = '';

    if ($error) {
        $message = "❌ CURL Error: {$error}";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $success = true;
            $passedTests++;
            $message = "✅ PASS (HTTP {$httpCode})";

            // Response preview
            if (is_array($json)) {
                if (isset($json['data'])) {
                    $message .= " - " . count($json['data']) . " items";
                }
                if (isset($json['pagination'])) {
                    $message .= " - Total: " . ($json['pagination']['total'] ?? 'N/A');
                }
                if (isset($json['total_hospitals'])) {
                    $message .= " - Hospitals: " . $json['total_hospitals'];
                }
                if (isset($json['status'])) {
                    $message .= " - Status: " . $json['status'];
                }
            }
        } else {
            $message = "❌ FAIL - Invalid JSON";
        }
    } else {
        $message = "❌ FAIL (HTTP {$httpCode})";
        if ($response) {
            $json = json_decode($response, true);
            if (isset($json['error'])) {
                $message .= " - " . $json['error'];
            }
        }
    }

    echo "Result: {$message}\n";

    $testResults[] = [
        'name' => $name,
        'success' => $success,
        'message' => $message,
        'http_code' => $httpCode
    ];

    return $success;
}

echo "
╔═══════════════════════════════════════════════════════════╗
║  서울미녀 API 전체 테스트                                  ║
║  Test Date: " . date('Y-m-d H:i:s') . "                            ║
╚═══════════════════════════════════════════════════════════╝
";

// 1. Health Check
testAPI('Health Check', '/health');

// 2. Hospital APIs
testAPI('Get All Hospitals (기본)', '/hospitals');
testAPI('Get Hospitals (페이지 2)', '/hospitals?page=2&per_page=10');
testAPI('Get Hospitals (카테고리: 리프팅)', '/hospitals?category=' . urlencode('리프팅'));
testAPI('Get Hospitals (카테고리: 피부)', '/hospitals?category=' . urlencode('피부'));
testAPI('Get Hospitals (평점 9.0 이상)', '/hospitals?min_rating=9.0');
testAPI('Get Hospitals (정렬: 리뷰 많은순)', '/hospitals?sort_by=review_count&sort_order=desc');
testAPI('Get Hospitals (검색: 강남)', '/hospitals?search=' . urlencode('강남'));
testAPI('Get Single Hospital (ID 15)', '/hospitals/15');

// 3. Settings API
testAPI('Get Settings (새 세션)', '/settings?session_id=test-api-check');
testAPI('Save Settings', '/settings', 'POST', [
    'session_id' => 'test-api-check',
    'settings' => [
        'navigation' => [
            'pc' => ['mode' => 'new-tab'],
            'mobile' => ['mode' => 'popup']
        ],
        'display' => [
            'theme' => 'light',
            'fontSize' => 'medium'
        ]
    ]
]);
testAPI('Get Settings (저장 후)', '/settings?session_id=test-api-check');

// 4. Stats API
testAPI('Get Statistics', '/stats');

// 5. Categories API
testAPI('Get Categories', '/categories');

// Summary
echo "\n\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║  테스트 결과 요약                                         ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "총 테스트: {$totalTests}개\n";
echo "성공: {$passedTests}개 ✅\n";
echo "실패: " . ($totalTests - $passedTests) . "개 ❌\n";
echo "성공률: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
echo "\n";

if ($passedTests === $totalTests) {
    echo "🎉 모든 테스트 통과!\n";
} else {
    echo "⚠️  일부 테스트 실패\n\n";
    echo "실패한 테스트:\n";
    foreach ($testResults as $result) {
        if (!$result['success']) {
            echo "  - {$result['name']}: {$result['message']}\n";
        }
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "테스트 완료\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
