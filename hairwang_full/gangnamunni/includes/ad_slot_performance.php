<?php

function adSlotNormalizeKeyword($keyword)
{
    $keyword = trim((string) $keyword);
    $keyword = preg_replace('/\s+/u', ' ', $keyword);

    return mb_strtolower($keyword, 'UTF-8');
}

function adSlotResolveTreatment($connect, $keyword)
{
    $keyword = adSlotNormalizeKeyword($keyword);
    if ($keyword === '') {
        return null;
    }

    $sql = "
        SELECT matched.id, matched.name, matched.hashtag
        FROM (
            SELECT tm.id, tm.name, tm.hashtag,
                   MIN(CASE
                       WHEN tm.name = ? THEN 0
                       WHEN tm.name LIKE CONCAT('%', ?, '%') THEN 1
                       WHEN ta.alias = ? THEN 2
                       WHEN tm.hashtag = ? THEN 3
                       WHEN ta.alias LIKE CONCAT('%', ?, '%') THEN 4
                       ELSE 5
                   END) AS match_rank
            FROM gangnamunni_treatment_master tm
            LEFT JOIN gangnamunni_treatment_aliases ta ON ta.treatment_id = tm.id
            WHERE tm.is_active = 1
              AND (
                  tm.name = ? OR ta.alias = ? OR tm.hashtag = ?
                  OR tm.name LIKE CONCAT('%', ?, '%')
                  OR ta.alias LIKE CONCAT('%', ?, '%')
                  OR tm.hashtag LIKE CONCAT('%', ?, '%')
              )
            GROUP BY tm.id, tm.name, tm.hashtag
        ) matched
        ORDER BY matched.match_rank ASC, CHAR_LENGTH(matched.name) ASC
        LIMIT 1
    ";
    $stmt = mysqli_prepare($connect, $sql);
    if (! $stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'sssssssssss',
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function adSlotGetSetting($connect, $key, $default = null)
{
    $stmt = mysqli_prepare($connect, 'SELECT setting_value FROM gangnamunni_cpv_settings WHERE setting_key = ? LIMIT 1');
    if (! $stmt) {
        return $default;
    }
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ? $row['setting_value'] : $default;
}

function adSlotVisitorIp()
{
    // 위조 가능한 프록시 헤더는 신뢰 프록시 경유 시에만 사용 (includes/client_ip.php)
    require_once __DIR__ . '/client_ip.php';
    return seoulbeauty_client_ip();
}

function adSlotVisitorId()
{
    $cookieName = 'ad_slot_vid';
    if (! empty($_COOKIE[$cookieName]) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE[$cookieName])) {
        return $_COOKIE[$cookieName];
    }

    try {
        $visitorId = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $visitorId = md5(uniqid('', true));
    }

    $secure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($cookieName, $visitorId, [
        'expires' => time() + (86400 * 365),
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$cookieName] = $visitorId;

    return $visitorId;
}

function adSlotIsBot($userAgent)
{
    return (bool) preg_match('/bot|crawl|spider|slurp|headless|preview/i', (string) $userAgent);
}

function adSlotProcessEvent($connect, array $input)
{
    $eventType = ($input['event_type'] ?? '') === 'click' ? 'click' : 'impression';
    $bookingId = (int) ($input['booking_id'] ?? 0);
    if ($bookingId <= 0) {
        return ['success' => false, 'charged' => false, 'reason' => 'invalid_booking'];
    }

    $keyword = adSlotNormalizeKeyword($input['keyword'] ?? '');
    $pageUrl = mb_substr((string) ($input['page_url'] ?? ''), 0, 2000, 'UTF-8');
    $targetUrl = mb_substr((string) ($input['target_url'] ?? ''), 0, 2000, 'UTF-8');
    $userAgent = mb_substr((string) ($input['user_agent'] ?? ''), 0, 500, 'UTF-8');
    $visitorIpHash = hash('sha256', adSlotVisitorIp());
    $visitorIdHash = hash('sha256', adSlotVisitorId());
    $dedupeSeconds = $eventType === 'click' ? 300 : 300;
    $timeBucket = (int) floor(time() / $dedupeSeconds);
    $eventKey = hash('sha256', implode('|', [$bookingId, $eventType, $visitorIdHash, $visitorIpHash, $timeBucket]));

    mysqli_begin_transaction($connect);

    try {
        $stmt = mysqli_prepare($connect, "
            SELECT b.id AS booking_id, b.hospital_id, b.daily_spent, b.total_spent, b.budget_date,
                   p.id AS product_id, p.treatment_id, p.rank_position, p.billing_model,
                   p.cpc_price, p.cpm_price, p.daily_budget, p.total_budget
            FROM gangnamunni_ad_slot_bookings b
            INNER JOIN gangnamunni_ad_slot_products p ON p.id = b.product_id
            WHERE b.id = ?
              AND b.status = 'active'
              AND p.is_active = 1
              AND b.start_date <= CURDATE()
              AND (b.end_date IS NULL OR b.end_date >= CURDATE())
            LIMIT 1
            FOR UPDATE
        ");
        if (! $stmt) {
            throw new RuntimeException('booking_prepare_failed');
        }
        mysqli_stmt_bind_param($stmt, 'i', $bookingId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $slot = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (! $slot) {
            mysqli_rollback($connect);
            return ['success' => true, 'charged' => false, 'reason' => 'inactive_booking'];
        }

        $billingModel = in_array($slot['billing_model'], ['monthly', 'cpc', 'cpm'], true)
            ? $slot['billing_model']
            : 'monthly';
        $unitPrice = $billingModel === 'cpc' ? (float) $slot['cpc_price'] : (float) $slot['cpm_price'];
        $chargeAmount = $billingModel === 'cpc' ? $unitPrice : round($unitPrice / 1000, 2);
        $isBillable = ($billingModel === 'cpc' && $eventType === 'click')
            || ($billingModel === 'cpm' && $eventType === 'impression');
        $skipReason = null;

        if ($billingModel === 'monthly') {
            $isBillable = false;
            $chargeAmount = 0;
            $skipReason = 'legacy_monthly';
        } elseif (! $isBillable) {
            $chargeAmount = 0;
            $skipReason = 'billing_event_mismatch';
        } elseif ($unitPrice <= 0 || $chargeAmount <= 0) {
            $isBillable = false;
            $chargeAmount = 0;
            $skipReason = 'zero_price';
        }

        $treatmentId = $slot['treatment_id'] !== null ? (int) $slot['treatment_id'] : null;
        $rankPosition = (int) $slot['rank_position'];
        $productId = (int) $slot['product_id'];
        $hospitalId = (int) $slot['hospital_id'];
        $isBillableInt = $isBillable ? 1 : 0;
        $isChargedInt = 0;

        $stmt = mysqli_prepare($connect, "
            INSERT INTO gangnamunni_ad_slot_events
                (event_key, event_type, booking_id, product_id, hospital_id, treatment_id,
                 keyword, rank_position, billing_model, unit_price, charge_amount,
                 is_billable, is_charged, skip_reason, page_url, target_url,
                 visitor_ip_hash, visitor_id_hash, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (! $stmt) {
            throw new RuntimeException('event_prepare_failed');
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssiiiisisddiissssss',
            $eventKey,
            $eventType,
            $bookingId,
            $productId,
            $hospitalId,
            $treatmentId,
            $keyword,
            $rankPosition,
            $billingModel,
            $unitPrice,
            $chargeAmount,
            $isBillableInt,
            $isChargedInt,
            $skipReason,
            $pageUrl,
            $targetUrl,
            $visitorIpHash,
            $visitorIdHash,
            $userAgent
        );
        if (! mysqli_stmt_execute($stmt)) {
            $errno = mysqli_stmt_errno($stmt);
            mysqli_stmt_close($stmt);
            mysqli_rollback($connect);
            if ($errno === 1062) {
                return ['success' => true, 'charged' => false, 'reason' => 'duplicate'];
            }
            throw new RuntimeException('event_insert_failed');
        }
        $eventId = mysqli_insert_id($connect);
        mysqli_stmt_close($stmt);

        $testMode = adSlotGetSetting($connect, 'ad_performance_test_mode', '1') === '1';
        if ($isBillable && $testMode) {
            $skipReason = 'test_mode';
        }

        if ($isBillable && ! $testMode) {
            $today = date('Y-m-d');
            $dailySpent = $slot['budget_date'] === $today ? (float) $slot['daily_spent'] : 0.0;
            $totalSpent = (float) $slot['total_spent'];
            $dailyBudget = $slot['daily_budget'] !== null ? (float) $slot['daily_budget'] : 0.0;
            $totalBudget = $slot['total_budget'] !== null ? (float) $slot['total_budget'] : 0.0;

            if ($dailyBudget > 0 && $dailySpent + $chargeAmount > $dailyBudget) {
                $skipReason = 'daily_budget_exceeded';
            } elseif ($totalBudget > 0 && $totalSpent + $chargeAmount > $totalBudget) {
                $skipReason = 'total_budget_exceeded';
            } else {
                $stmt = mysqli_prepare($connect, "
                    SELECT id, balance, daily_cap, is_active, is_paused
                    FROM gangnamunni_cpv_wallets
                    WHERE hospital_id = ? LIMIT 1 FOR UPDATE
                ");
                mysqli_stmt_bind_param($stmt, 'i', $hospitalId);
                mysqli_stmt_execute($stmt);
                $walletResult = mysqli_stmt_get_result($stmt);
                $wallet = $walletResult ? mysqli_fetch_assoc($walletResult) : null;
                mysqli_stmt_close($stmt);

                if (! $wallet) {
                    $skipReason = 'wallet_missing';
                } elseif (! (int) $wallet['is_active'] || (int) $wallet['is_paused']) {
                    $skipReason = 'wallet_inactive';
                } elseif ((float) $wallet['balance'] < $chargeAmount) {
                    $skipReason = 'insufficient_balance';
                } else {
                    $walletDailyCap = (float) $wallet['daily_cap'];
                    $stmt = mysqli_prepare($connect, "
                        SELECT COALESCE(SUM(charge_amount), 0) AS spent
                        FROM gangnamunni_ad_slot_events
                        WHERE hospital_id = ? AND is_charged = 1 AND DATE(created_at) = CURDATE()
                    ");
                    mysqli_stmt_bind_param($stmt, 'i', $hospitalId);
                    mysqli_stmt_execute($stmt);
                    $spentResult = mysqli_stmt_get_result($stmt);
                    $spentRow = $spentResult ? mysqli_fetch_assoc($spentResult) : null;
                    mysqli_stmt_close($stmt);
                    $walletDailySpent = $spentRow ? (float) $spentRow['spent'] : 0.0;

                    if ($walletDailyCap > 0 && $walletDailySpent + $chargeAmount > $walletDailyCap) {
                        $skipReason = 'wallet_daily_cap';
                    } else {
                        $stmt = mysqli_prepare($connect, "
                            UPDATE gangnamunni_cpv_wallets
                            SET balance = balance - ?, total_spent = total_spent + ?, updated_at = NOW()
                            WHERE id = ? AND balance >= ?
                        ");
                        $walletId = (int) $wallet['id'];
                        mysqli_stmt_bind_param($stmt, 'ddid', $chargeAmount, $chargeAmount, $walletId, $chargeAmount);
                        mysqli_stmt_execute($stmt);
                        $updated = mysqli_stmt_affected_rows($stmt);
                        mysqli_stmt_close($stmt);

                        if ($updated !== 1) {
                            $skipReason = 'wallet_update_conflict';
                        } else {
                            $newBalance = (float) $wallet['balance'] - $chargeAmount;
                            $description = sprintf('검색 광고 %s 과금 (booking#%d, event#%d)', strtoupper($billingModel), $bookingId, $eventId);
                            $referenceType = 'ad_slot_event';
                            $transactionType = 'deduct';
                            $stmt = mysqli_prepare($connect, "
                                INSERT INTO gangnamunni_cpv_transactions
                                    (hospital_id, type, amount, balance_after, description, reference_type, reference_id, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            mysqli_stmt_bind_param(
                                $stmt,
                                'isddssi',
                                $hospitalId,
                                $transactionType,
                                $chargeAmount,
                                $newBalance,
                                $description,
                                $referenceType,
                                $eventId
                            );
                            if (! mysqli_stmt_execute($stmt)) {
                                mysqli_stmt_close($stmt);
                                throw new RuntimeException('transaction_insert_failed');
                            }
                            mysqli_stmt_close($stmt);

                            $stmt = mysqli_prepare($connect, "
                                UPDATE gangnamunni_ad_slot_bookings
                                SET daily_spent = CASE WHEN budget_date = CURDATE() THEN daily_spent + ? ELSE ? END,
                                    total_spent = total_spent + ?, budget_date = CURDATE(),
                                    last_charged_at = NOW(), pause_reason = NULL
                                WHERE id = ?
                            ");
                            mysqli_stmt_bind_param($stmt, 'dddi', $chargeAmount, $chargeAmount, $chargeAmount, $bookingId);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);

                            $isChargedInt = 1;
                            $skipReason = null;
                        }
                    }
                }
            }
        }

        $stmt = mysqli_prepare($connect, "
            UPDATE gangnamunni_ad_slot_events
            SET is_charged = ?, skip_reason = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'isi', $isChargedInt, $skipReason, $eventId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($connect);

        return [
            'success' => true,
            'charged' => $isChargedInt === 1,
            'reason' => $skipReason,
            'event_id' => $eventId,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($connect);
        if ((int) $e->getCode() === 1062) {
            return ['success' => true, 'charged' => false, 'reason' => 'duplicate'];
        }
        error_log('[ad-slot] ' . $e->getMessage());

        return ['success' => false, 'charged' => false, 'reason' => 'internal_error'];
    }
}
