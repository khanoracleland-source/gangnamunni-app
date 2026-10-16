<?php
/**
 * CPV 과금 시스템 — 헬퍼 함수
 * 모든 CPV 기능에서 공통으로 사용하는 설정 로딩 + 유틸리티
 */

/**
 * CPV 설정값 전체 로드 (1회 캐시)
 */
function loadCpvSettings() {
    global $connect;
    $settings = [];
    if (!$connect) return $settings;

    // 테이블 존재 확인
    $check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_cpv_settings'");
    if (!$check || mysqli_num_rows($check) === 0) return $settings;

    $r = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_cpv_settings");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

/**
 * 개별 설정값 조회 (static 캐시)
 */
function getCpvSetting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $settings = loadCpvSettings();
    }
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * ON/OFF 토글 체크 (마스터 스위치 포함)
 */
function isCpvEnabled($key) {
    // 마스터 스위치 먼저 체크
    if (getCpvSetting('cpv_master_switch', '0') !== '1') return false;
    return getCpvSetting($key, '0') === '1';
}

/**
 * 테스트 모드 여부
 */
function isCpvTestMode() {
    return getCpvSetting('cpv_test_mode', '1') === '1';
}

/**
 * CPV 단가 계산 (모든 가중치 적용)
 */
function calculateCpvRate($hospital_id, $keyword = null, $procedure_type = null, $event_active = false) {
    $base = (float) getCpvSetting('base_cpv_rate', 200);
    $rate = $base;

    // 키워드 가중치 — 호출자는 서버에서 정한 값만 넘겨야 한다(방문자 입력 금지).
    // 설정 키 접미사로 쓰이므로 형식도 제한한다.
    if (isCpvEnabled('keyword_pricing_enabled') && is_string($keyword) && preg_match('/^[a-z0-9_]{1,32}$/', $keyword)) {
        $rate *= cpvClampWeight(getCpvSetting('weight_keyword_' . $keyword, 1.0));
    }

    // 시술 가중치 — 키워드와 동일한 규칙
    if (isCpvEnabled('procedure_pricing_enabled') && is_string($procedure_type) && preg_match('/^[a-z0-9_]{1,32}$/', $procedure_type)) {
        $rate *= cpvClampWeight(getCpvSetting('weight_proc_' . $procedure_type, 1.0));
    }

    // 이벤트 가중치
    if (isCpvEnabled('event_weight_enabled')) {
        $w = $event_active ? 'weight_event_active' : 'weight_event_ended';
        $rate *= (float) getCpvSetting($w, 1.0);
    }

    // 평점 가중치
    if (isCpvEnabled('rating_weight_enabled')) {
        $rating = getHospitalRatingForCpv($hospital_id);
        if ($rating >= 4.5) {
            $rate *= (float) getCpvSetting('weight_rating_high', 1.3);
        } elseif ($rating < 3.0) {
            $rate *= (float) getCpvSetting('weight_rating_low', 0.6);
        }
    }

    // 상한/하한 적용
    $max = (float) getCpvSetting('max_cpv_rate', 5000);
    $min = (float) getCpvSetting('min_cpv_rate', 50);
    $rate = max($min, min($max, $rate));

    // 소수점 처리
    $rounding = getCpvSetting('decimal_rounding', 'round');
    if ($rounding === 'ceil') return (int) ceil($rate);
    if ($rounding === 'floor') return (int) floor($rate);
    return (int) round($rate);
}

/**
 * 가중치 배수 정규화 — 관리자 설정 범위(0.1~10)를 벗어난 값/비숫자는 보정
 */
function cpvClampWeight($value) {
    if (!is_numeric($value)) return 1.0;
    return max(0.1, min(10.0, (float) $value));
}

/**
 * 병원 평점 조회 (CPV 계산용)
 */
function getHospitalRatingForCpv($hospital_id) {
    global $connect;
    if (!$connect) return 0;
    $stmt = mysqli_prepare($connect, "SELECT AVG(rating) as avg_rating FROM hospital_reviews WHERE hospital_id = ? AND status = 'approved'");
    if (!$stmt) return 0;
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? (float) $row['avg_rating'] : 0;
}

/**
 * 충전 상품 목록 조회
 */
function getCpvChargeProducts() {
    $products = [];
    $names = [
        1 => '라이트',
        2 => '베이직',
        3 => '스탠다드',
        4 => '프리미엄',
        5 => '엔터프라이즈'
    ];
    for ($i = 1; $i <= 5; $i++) {
        $price = (int) getCpvSetting("charge_product_{$i}_price", 0);
        $bonus = (float) getCpvSetting("charge_product_{$i}_bonus", 0);
        if ($price > 0) {
            $products[] = [
                'id' => $i,
                'name' => $names[$i] . ' 충전',
                'price' => $price,
                'bonus_rate' => $bonus,
                'bonus_amount' => (int) round($price * $bonus / 100),
                'total' => $price + (int) round($price * $bonus / 100)
            ];
        }
    }
    return $products;
}

/**
 * 숫자 포맷 (통화)
 */
function formatCpvCurrency($amount) {
    $unit = getCpvSetting('currency_unit', 'KRW');
    if ($unit === 'KRW') {
        return number_format($amount) . '원';
    }
    return number_format($amount, 2);
}

/**
 * CPV 설정 카테고리 정의
 */
function getCpvSettingCategories() {
    return [
        'phase6' => ['label' => '시스템 전역 설정', 'icon' => 'bi-gear-wide-connected', 'color' => '#6b7280'],
        'phase1' => ['label' => '포인트 지갑 시스템', 'icon' => 'bi-wallet2', 'color' => '#2563eb'],
        'phase2' => ['label' => 'CPV 과금 엔진', 'icon' => 'bi-cpu', 'color' => '#dc2626'],
        'phase3' => ['label' => '단가 정책', 'icon' => 'bi-currency-dollar', 'color' => '#ea580c'],
        'phase4' => ['label' => '예산 관리', 'icon' => 'bi-piggy-bank', 'color' => '#16a34a'],
        'phase5' => ['label' => '충전 상품 & 대시보드', 'icon' => 'bi-graph-up-arrow', 'color' => '#AE1932'],
        'phase7' => ['label' => 'AI 과금 설정', 'icon' => 'bi-robot', 'color' => '#0891b2'],
    ];
}

/**
 * AI 비용을 병원 CPV 지갑에서 차감
 *
 * @param int    $hospital_id  병원 ID
 * @param string $action_type  AI 액션 유형 (ai_base, ai_review, ai_ad, ai_suggest, ai_image)
 * @param int|null $cost       비용 (null이면 설정에서 자동 로드)
 * @return array ['success'=>bool, 'charged'=>int, 'balance'=>int, 'message'=>string]
 */
function chargeAiFromWallet($hospital_id, $action_type = 'ai_base', $cost = null) {
    global $connect;
    if (!$connect) return ['success' => false, 'charged' => 0, 'balance' => 0, 'message' => 'DB 연결 없음'];

    // AI 과금 마스터 스위치 체크
    if (getCpvSetting('ai_billing_enabled', '0') !== '1') {
        return ['success' => true, 'charged' => 0, 'balance' => 0, 'message' => 'AI 과금 비활성 (무료)'];
    }

    // AI 테스트 모드 — 차감하지 않고 성공 반환
    if (getCpvSetting('ai_test_mode', '1') === '1') {
        return ['success' => true, 'charged' => 0, 'balance' => 0, 'message' => 'AI 테스트 모드 (무과금)'];
    }

    // 비용 결정
    if ($cost === null) {
        $cost_map = [
            'ai_base'    => 'ai_base_cost',
            'ai_review'  => 'ai_review_cost',
            'ai_ad'      => 'ai_ad_cost',
            'ai_suggest' => 'ai_suggest_cost',
            'ai_image'   => 'ai_image_cost',
        ];
        $cost_key = $cost_map[$action_type] ?? 'ai_base_cost';
        $cost = (int) getCpvSetting($cost_key, 50);
    }

    if ($cost <= 0) {
        return ['success' => true, 'charged' => 0, 'balance' => 0, 'message' => '비용 0원 (무과금)'];
    }

    // 지갑 잔액 조회
    $stmt = mysqli_prepare($connect, "SELECT id, balance FROM gangnamunni_cpv_wallets WHERE hospital_id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'charged' => 0, 'balance' => 0, 'message' => 'DB 오류'];
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wallet = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$wallet) {
        return ['success' => false, 'charged' => 0, 'balance' => 0, 'message' => '지갑 없음 — 충전 필요'];
    }

    $balance = (int) $wallet['balance'];
    if ($balance < $cost) {
        return ['success' => false, 'charged' => 0, 'balance' => $balance, 'message' => '잔액 부족 (잔액: ' . number_format($balance) . '원, 필요: ' . number_format($cost) . '원)'];
    }

    // 잔액 차감
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_wallets SET balance = balance - ?, updated_at = NOW() WHERE id = ? AND balance >= ?");
    mysqli_stmt_bind_param($stmt, 'iii', $cost, $wallet['id'], $cost);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok || $affected === 0) {
        return ['success' => false, 'charged' => 0, 'balance' => $balance, 'message' => '차감 실패 (동시 요청 충돌)'];
    }

    $new_balance = $balance - $cost;

    // 거래 기록
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_cpv_transactions (hospital_id, type, amount, balance_after, description, created_at)
         VALUES (?, 'ai_usage', ?, ?, ?, NOW())"
    );
    $desc = 'AI ' . $action_type . ' 비용 차감';
    mysqli_stmt_bind_param($stmt, 'iiis', $hospital_id, $cost, $new_balance, $desc);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'charged' => $cost, 'balance' => $new_balance, 'message' => number_format($cost) . '원 차감 완료'];
}

/**
 * 병원 AI 설정 조회
 */
function getHospitalAiSettings($hospital_id) {
    global $connect;
    if (!$connect) return null;

    $stmt = mysqli_prepare($connect,
        "SELECT ai_enabled, ai_use_own_key, ai_own_api_key, ai_own_provider, ai_cost_from_wallet
         FROM gangnamunni_hospitals WHERE id = ? LIMIT 1"
    );
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row;
}
