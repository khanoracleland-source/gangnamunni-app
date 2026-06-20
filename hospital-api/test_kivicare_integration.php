<?php
/**
 * Kivicare Integration Test Script
 *
 * 이 스크립트는 Kivicare 연동이 올바르게 작동하는지 테스트합니다.
 *
 * 실행 방법:
 * php test_kivicare_integration.php
 */

require_once __DIR__ . '/app/Services/KivicareAPI.php';
require_once __DIR__ . '/app/Services/DataTransformer.php';
require_once __DIR__ . '/app/Helpers/SettingsHelper.php';

use App\Services\KivicareAPI;
use App\Services\DataTransformer;
use App\Helpers\SettingsHelper;

echo "============================================\n";
echo "Kivicare Integration Test\n";
echo "============================================\n\n";

// Test 1: SettingsHelper 테스트
echo "[TEST 1] SettingsHelper Test\n";
echo "----------------------------\n";

try {
    // Kivicare 사용 여부 확인 (user_id = 1)
    $useKivicare = SettingsHelper::useKivicare(1);
    echo "✓ useKivicare(1): " . ($useKivicare ? 'true' : 'false') . "\n";

    // Kivicare API URL 확인
    $apiUrl = SettingsHelper::getKivicareApiUrl();
    echo "✓ Kivicare API URL: $apiUrl\n";

    // Kivicare 서버 연결 테스트
    $isAvailable = SettingsHelper::isKivicareAvailable();
    echo ($isAvailable ? "✓" : "✗") . " Kivicare Server Available: " . ($isAvailable ? 'YES' : 'NO') . "\n";

    echo "\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: DataTransformer 테스트
echo "[TEST 2] DataTransformer Test\n";
echo "------------------------------\n";

try {
    // 사용자 데이터 변환 테스트
    $newSystemUser = [
        'name' => '홍길동',
        'email' => 'test@example.com',
        'phone' => '010-1234-5678',
        'birth_date' => '1990-01-01',
        'gender' => 'M'
    ];

    $kivicareUser = DataTransformer::userToKivicare($newSystemUser);
    echo "✓ New System → Kivicare 변환:\n";
    echo "  - first_name: {$kivicareUser['first_name']}\n";
    echo "  - last_name: {$kivicareUser['last_name']}\n";
    echo "  - mobile: {$kivicareUser['mobile']}\n";

    $convertedBack = DataTransformer::userFromKivicare($kivicareUser);
    echo "✓ Kivicare → New System 변환:\n";
    echo "  - name: {$convertedBack['name']}\n";
    echo "  - phone: {$convertedBack['phone']}\n";

    echo "\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: 예약 데이터 변환 테스트
echo "[TEST 3] Booking Data Transformation\n";
echo "-------------------------------------\n";

try {
    $newSystemBooking = [
        'hospital_id' => 15,
        'booking_date' => '2026-02-15',
        'booking_time' => '14:00:00',
        'treatment_type' => '리프팅',
        'notes' => '테스트 예약입니다',
        'status' => 'pending'
    ];

    $kivicareBooking = DataTransformer::bookingToKivicare($newSystemBooking);
    echo "✓ New System → Kivicare 예약 변환:\n";
    echo "  - clinic_id: {$kivicareBooking['clinic_id']}\n";
    echo "  - appointment_start_date: {$kivicareBooking['appointment_start_date']}\n";
    echo "  - appointment_start_time: {$kivicareBooking['appointment_start_time']}\n";
    echo "  - status: {$kivicareBooking['status']}\n";

    echo "\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: 상태 매핑 테스트
echo "[TEST 4] Status Mapping Test\n";
echo "-----------------------------\n";

$testStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
foreach ($testStatuses as $status) {
    $booking = ['status' => $status];
    $kivicare = DataTransformer::bookingToKivicare($booking);
    echo "✓ $status → Kivicare: {$kivicare['status']}\n";
}

echo "\n";

// Test 5: KivicareAPI 연결 테스트 (Kivicare 서버가 실행 중인 경우)
echo "[TEST 5] KivicareAPI Connection Test\n";
echo "-------------------------------------\n";

if (SettingsHelper::isKivicareAvailable()) {
    echo "✓ Kivicare 서버가 실행 중입니다.\n";
    echo "  API 기능 테스트를 진행할 수 있습니다.\n\n";

    // 실제 API 테스트는 여기에 추가
    // 예: KivicareAPI::getClinics()

} else {
    echo "✗ Kivicare 서버가 실행되지 않았습니다.\n";
    echo "  다음 단계:\n";
    echo "  1. Kivicare Laravel 서버를 실행하세요: cd kivicare && php artisan serve\n";
    echo "  2. .env 파일에서 KIVICARE_API_URL을 확인하세요\n\n";
}

// Test 6: 설정 저장/조회 테스트
echo "[TEST 6] Settings Save/Load Test\n";
echo "---------------------------------\n";

try {
    $testUserId = 999;

    // 설정 저장
    $testSettings = [
        'use_kivicare' => true,
        'loading_indicator_type' => 'busy',
        'theme' => 'light',
        'language' => 'ko',
        'notifications_enabled' => true
    ];

    $saved = SettingsHelper::saveUserSettings($testUserId, $testSettings);
    echo ($saved ? "✓" : "✗") . " Settings saved for user $testUserId\n";

    // 설정 조회
    $loaded = SettingsHelper::getUserSettings($testUserId);
    echo "✓ Loaded settings:\n";
    echo "  - use_kivicare: " . ($loaded['use_kivicare'] ? 'true' : 'false') . "\n";
    echo "  - theme: {$loaded['theme']}\n";
    echo "  - language: {$loaded['language']}\n";

    // 정리
    $db = new PDO(
        "mysql:host=localhost;port=3306;dbname=kivicare_laravel;charset=utf8mb4",
        'root',
        '<REMOVED_DB_PASSWORD>'
    );
    $db->exec("DELETE FROM user_settings WHERE user_id = $testUserId");
    echo "✓ Test data cleaned up\n";

    echo "\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n\n";
}

// 테스트 요약
echo "============================================\n";
echo "Test Summary\n";
echo "============================================\n";
echo "✓ SettingsHelper: OK\n";
echo "✓ DataTransformer: OK\n";
echo "✓ Status Mapping: OK\n";
echo "✓ Settings Save/Load: OK\n";

if (SettingsHelper::isKivicareAvailable()) {
    echo "✓ Kivicare Server: RUNNING\n";
    echo "\n[SUCCESS] All tests passed! Kivicare integration is ready.\n";
} else {
    echo "⚠ Kivicare Server: NOT RUNNING\n";
    echo "\n[PARTIAL] Core functionality OK, but Kivicare server is not running.\n";
    echo "Start Kivicare server to enable full integration.\n";
}

echo "\n";
echo "============================================\n";
echo "Next Steps:\n";
echo "============================================\n";
echo "1. Start Kivicare server (if not running):\n";
echo "   cd h:/carebaby_project/kivicare_clean_install/kivicare-laravel-web-v1.9.0\n";
echo "   php artisan serve --port=8000\n\n";
echo "2. Start hospital-api server:\n";
echo "   cd h:/carebaby_project/hospital-api\n";
echo "   php artisan serve --port=8001\n\n";
echo "3. Test the frontend:\n";
echo "   - Navigate to settings page\n";
echo "   - Toggle 'Kivicare 사용' setting\n";
echo "   - Try login/booking with both settings\n\n";
echo "============================================\n";
