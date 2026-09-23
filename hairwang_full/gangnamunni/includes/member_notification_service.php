<?php
/**
 * Member notification service for admin-sent user notifications.
 *
 * Keeps recipient selection, preference filtering, in-app creation, push sending,
 * preview counts, and send logging in one place.
 */

if (!function_exists('create_user_notifications_batch')) {
    require_once __DIR__ . '/user_notifications.php';
}
if (!function_exists('send_push_to_members')) {
    require_once __DIR__ . '/admin_fcm.php';
}

function member_notification_allowed_types() {
    return ['general', 'booking', 'event', 'raffle', 'system', 'promotion'];
}

function member_notification_type_labels() {
    return [
        'general' => '일반 알림',
        'booking' => '예약 알림',
        'event' => '이벤트',
        'raffle' => '응모/추첨',
        'system' => '시스템',
        'promotion' => '프로모션',
    ];
}

function member_notification_normalize_type($type) {
    $type = trim((string)$type);
    return in_array($type, member_notification_allowed_types(), true) ? $type : 'general';
}

function member_notification_type_preference_column($type) {
    switch (member_notification_normalize_type($type)) {
        case 'booking':
            return 'noti_booking';
        case 'event':
        case 'raffle':
            return 'noti_event';
        case 'promotion':
            return 'noti_promo';
        case 'general':
        case 'system':
        default:
            return 'noti_general';
    }
}

function member_notification_ensure_tables($connect) {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    @mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_sends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        admin_username VARCHAR(50),
        target_type VARCHAR(50),
        target_count INT DEFAULT 0,
        notification_type VARCHAR(50),
        title VARCHAR(255),
        message TEXT,
        link VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connect, "ALTER TABLE gangnamunni_notification_sends
        ADD COLUMN IF NOT EXISTS target_count INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS notification_type VARCHAR(50),
        ADD COLUMN IF NOT EXISTS link VARCHAR(500),
        ADD COLUMN IF NOT EXISTS resolved_count INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS in_app_count INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS push_sent INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS push_failed INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS push_skipped INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS push_error TEXT,
        ADD COLUMN IF NOT EXISTS push_details LONGTEXT");

    @mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id VARCHAR(100) NOT NULL UNIQUE,
        noti_booking TINYINT(1) NOT NULL DEFAULT 1,
        noti_chat TINYINT(1) NOT NULL DEFAULT 1,
        noti_event TINYINT(1) NOT NULL DEFAULT 1,
        noti_promo TINYINT(1) NOT NULL DEFAULT 1,
        noti_review TINYINT(1) NOT NULL DEFAULT 1,
        noti_push TINYINT(1) NOT NULL DEFAULT 1,
        noti_general TINYINT(1) NOT NULL DEFAULT 1,
        noti_message TINYINT(1) NOT NULL DEFAULT 1,
        mkt_push TINYINT(1) NOT NULL DEFAULT 0,
        mkt_sms TINYINT(1) NOT NULL DEFAULT 0,
        mkt_email TINYINT(1) NOT NULL DEFAULT 0,
        mkt_night_push TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($connect, "ALTER TABLE gangnamunni_notification_settings
        ADD COLUMN IF NOT EXISTS noti_booking TINYINT(1) NOT NULL DEFAULT 1,
        ADD COLUMN IF NOT EXISTS noti_event TINYINT(1) NOT NULL DEFAULT 1,
        ADD COLUMN IF NOT EXISTS noti_promo TINYINT(1) NOT NULL DEFAULT 1,
        ADD COLUMN IF NOT EXISTS noti_push TINYINT(1) NOT NULL DEFAULT 1,
        ADD COLUMN IF NOT EXISTS noti_general TINYINT(1) NOT NULL DEFAULT 1,
        ADD COLUMN IF NOT EXISTS mkt_push TINYINT(1) NOT NULL DEFAULT 0");
}

function member_notification_bind_params($stmt, $types, array $params) {
    if ($types === '') return true;

    $bind = [$stmt, $types];
    foreach ($params as $key => $value) {
        $bind[$key + 2] = $value;
    }
    foreach ($bind as $key => &$value) {
        $bind[$key] = &$value;
    }
    unset($value);

    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function member_notification_fetch_column_values($connect, $sql, $types = '', array $params = []) {
    $values = [];
    if ($types === '') {
        $result = mysqli_query($connect, $sql);
        while ($result && $row = mysqli_fetch_row($result)) {
            if (trim((string)($row[0] ?? '')) !== '') $values[] = (string)$row[0];
        }
        return array_values(array_unique($values));
    }

    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) return [];
    member_notification_bind_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && $row = mysqli_fetch_row($result)) {
        if (trim((string)($row[0] ?? '')) !== '') $values[] = (string)$row[0];
    }
    mysqli_stmt_close($stmt);
    return array_values(array_unique($values));
}

function member_notification_event_entries_have_member_id($connect) {
    $check = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'");
    return $check && mysqli_num_rows($check) > 0;
}

function member_notification_resolve_recipients($connect, $target, $eventId = 0, $memberId = '', $memberIds = []) {
    $target = trim((string)$target);
    $eventId = (int)$eventId;
    $memberId = trim((string)$memberId);
    if (!is_array($memberIds)) $memberIds = [$memberIds];
    $memberIds = array_values(array_unique(array_filter(array_map('trim', $memberIds), 'strlen')));

    switch ($target) {
        case 'all':
            return member_notification_fetch_column_values(
                $connect,
                "SELECT mb_id FROM g5_member WHERE mb_id IS NOT NULL AND mb_id <> ''"
            );

        case 'recent_7days':
            return member_notification_fetch_column_values(
                $connect,
                "SELECT mb_id FROM g5_member
                 WHERE mb_id IS NOT NULL AND mb_id <> ''
                   AND mb_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );

        case 'event_entrants':
            if (member_notification_event_entries_have_member_id($connect)) {
                return member_notification_fetch_column_values(
                    $connect,
                    "SELECT DISTINCT member_id FROM gangnamunni_event_entries WHERE member_id IS NOT NULL AND member_id <> ''"
                );
            }
            return member_notification_fetch_column_values(
                $connect,
                "SELECT DISTINCT m.mb_id
                 FROM gangnamunni_event_entries e
                 INNER JOIN g5_member m ON e.user_email = m.mb_email
                 WHERE e.user_email IS NOT NULL AND e.user_email <> ''"
            );

        case 'specific_event':
            if ($eventId <= 0) return [];
            if (member_notification_event_entries_have_member_id($connect)) {
                return member_notification_fetch_column_values(
                    $connect,
                    "SELECT DISTINCT member_id FROM gangnamunni_event_entries
                     WHERE event_id = ? AND member_id IS NOT NULL AND member_id <> ''",
                    'i',
                    [$eventId]
                );
            }
            return member_notification_fetch_column_values(
                $connect,
                "SELECT DISTINCT m.mb_id
                 FROM gangnamunni_event_entries e
                 INNER JOIN g5_member m ON e.user_email = m.mb_email
                 WHERE e.event_id = ? AND e.user_email IS NOT NULL AND e.user_email <> ''",
                'i',
                [$eventId]
            );

        case 'individual':
            if ($memberId === '') return [];
            return member_notification_fetch_column_values(
                $connect,
                "SELECT mb_id FROM g5_member WHERE mb_id = ? LIMIT 1",
                's',
                [$memberId]
            );

        case 'selected':
            if (empty($memberIds)) return [];
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            return member_notification_fetch_column_values(
                $connect,
                "SELECT mb_id FROM g5_member
                 WHERE mb_id IS NOT NULL AND mb_id <> ''
                   AND mb_id IN ({$placeholders})",
                str_repeat('s', count($memberIds)),
                $memberIds
            );

        default:
            return [];
    }
}

function member_notification_default_preferences() {
    return [
        'noti_booking' => 1,
        'noti_event' => 1,
        'noti_promo' => 1,
        'noti_push' => 1,
        'noti_general' => 1,
        'mkt_push' => 0,
    ];
}

function member_notification_fetch_preferences($connect, array $memberIds) {
    $memberIds = array_values(array_unique(array_filter(array_map('strval', $memberIds), 'strlen')));
    $preferences = [];
    foreach ($memberIds as $memberId) {
        $preferences[$memberId] = member_notification_default_preferences();
    }
    if (empty($memberIds)) return $preferences;

    member_notification_ensure_tables($connect);
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $sql = "SELECT member_id, noti_booking, noti_event, noti_promo, noti_push, noti_general, mkt_push
            FROM gangnamunni_notification_settings
            WHERE member_id IN ({$placeholders})";
    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) return $preferences;

    member_notification_bind_params($stmt, str_repeat('s', count($memberIds)), $memberIds);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $id = (string)$row['member_id'];
        $preferences[$id] = [
            'noti_booking' => (int)($row['noti_booking'] ?? 1),
            'noti_event' => (int)($row['noti_event'] ?? 1),
            'noti_promo' => (int)($row['noti_promo'] ?? 1),
            'noti_push' => (int)($row['noti_push'] ?? 1),
            'noti_general' => (int)($row['noti_general'] ?? 1),
            'mkt_push' => (int)($row['mkt_push'] ?? 0),
        ];
    }
    mysqli_stmt_close($stmt);

    return $preferences;
}

function member_notification_filter_recipients($connect, array $memberIds, $type) {
    $type = member_notification_normalize_type($type);
    $preferenceColumn = member_notification_type_preference_column($type);
    $preferences = member_notification_fetch_preferences($connect, $memberIds);
    $inApp = [];
    $push = [];
    $skippedInApp = 0;
    $skippedPush = 0;

    foreach ($memberIds as $memberId) {
        $memberId = (string)$memberId;
        $pref = $preferences[$memberId] ?? member_notification_default_preferences();
        $typeAllowed = (int)($pref[$preferenceColumn] ?? 1) === 1;

        if ($typeAllowed) {
            $inApp[] = $memberId;
        } else {
            $skippedInApp++;
        }

        $marketingAllowed = $type !== 'promotion' || (int)($pref['mkt_push'] ?? 0) === 1;
        if ($typeAllowed && $marketingAllowed && (int)($pref['noti_push'] ?? 1) === 1) {
            $push[] = $memberId;
        } else {
            $skippedPush++;
        }
    }

    return [
        'in_app' => array_values(array_unique($inApp)),
        'push' => array_values(array_unique($push)),
        'skipped_in_app' => $skippedInApp,
        'skipped_push' => $skippedPush,
    ];
}

function member_notification_count_push_tokens($connect, array $memberIds) {
    $memberIds = array_values(array_unique(array_filter(array_map('strval', $memberIds), 'strlen')));
    if (empty($memberIds)) {
        return ['members_with_tokens' => 0, 'tokens' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $stmt = mysqli_prepare(
        $connect,
        "SELECT COUNT(DISTINCT mb_id) AS members_with_tokens, COUNT(DISTINCT pt_token) AS tokens
         FROM g5_push_tokens
         WHERE pt_use = 1 AND pt_token <> '' AND mb_id IN ({$placeholders})"
    );
    if (!$stmt) {
        return ['members_with_tokens' => 0, 'tokens' => 0];
    }
    member_notification_bind_params($stmt, str_repeat('s', count($memberIds)), $memberIds);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return [
        'members_with_tokens' => (int)($row['members_with_tokens'] ?? 0),
        'tokens' => (int)($row['tokens'] ?? 0),
    ];
}

function member_notification_target_label($target, $eventId, $memberId, array $members) {
    $target = trim((string)$target);
    $labels = [
        'all' => '전체 회원',
        'recent_7days' => '최근 7일 가입자',
        'event_entrants' => '이벤트 응모자(전체)',
        'specific_event' => '특정 이벤트(#' . (int)$eventId . ')',
        'individual' => '개별(' . trim((string)$memberId) . ')',
        'selected' => '선택 회원(' . count($members) . '명)',
    ];
    return $labels[$target] ?? $target;
}

function member_notification_validate_link($link) {
    $link = trim((string)$link);
    if ($link === '') return true;

    $scheme = strtolower((string)parse_url($link, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) return false;

    $host = strtolower((string)parse_url($link, PHP_URL_HOST));
    if ($host === '') return false;

    $allowedHosts = [
        'hms.seoulbeauty.app',
        'seoulbeauty.app',
        'www.seoulbeauty.app',
    ];
    if (in_array($host, $allowedHosts, true)) return true;
    foreach ($allowedHosts as $allowedHost) {
        if (substr($host, -strlen('.' . $allowedHost)) === '.' . $allowedHost) return true;
    }
    return false;
}

function member_notification_preview($connect, $target, $eventId, $memberId, $memberIds, $type) {
    $type = member_notification_normalize_type($type);
    $members = member_notification_resolve_recipients($connect, $target, $eventId, $memberId, $memberIds);
    $filtered = member_notification_filter_recipients($connect, $members, $type);
    $pushTokens = member_notification_count_push_tokens($connect, $filtered['push']);

    return [
        'success' => true,
        'count' => count($filtered['in_app']),
        'resolved_count' => count($members),
        'in_app_count' => count($filtered['in_app']),
        'push_member_count' => $pushTokens['members_with_tokens'],
        'push_token_count' => $pushTokens['tokens'],
        'skipped_in_app' => $filtered['skipped_in_app'],
        'skipped_push' => $filtered['skipped_push'],
    ];
}

function member_notification_log_send($connect, array $logData) {
    member_notification_ensure_tables($connect);

    $stmt = mysqli_prepare(
        $connect,
        "INSERT INTO gangnamunni_notification_sends
            (admin_id, admin_username, target_type, target_count, notification_type, title, message, link,
             resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, push_details)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return false;

    $pushDetails = $logData['push_details'] ?? '';
    if (is_array($pushDetails)) {
        $pushDetails = json_encode($pushDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ississssiiiiiss',
        $logData['admin_id'],
        $logData['admin_username'],
        $logData['target_type'],
        $logData['target_count'],
        $logData['notification_type'],
        $logData['title'],
        $logData['message'],
        $logData['link'],
        $logData['resolved_count'],
        $logData['in_app_count'],
        $logData['push_sent'],
        $logData['push_failed'],
        $logData['push_skipped'],
        $logData['push_error'],
        $pushDetails
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function member_notification_send($connect, array $input, array $adminContext = []) {
    member_notification_ensure_tables($connect);

    $target = $input['target'] ?? 'all';
    $eventId = (int)($input['event_id'] ?? 0);
    $memberId = trim((string)($input['member_id'] ?? ''));
    $memberIds = $input['member_ids'] ?? [];
    $type = member_notification_normalize_type($input['type'] ?? 'general');
    $title = trim((string)($input['title'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    $link = trim((string)($input['link'] ?? ''));

    if ($title === '') {
        return ['success' => false, 'error' => '제목을 입력해 주세요.'];
    }
    if (mb_strlen($title) > 200 || mb_strlen($message) > 1000) {
        return ['success' => false, 'error' => '제목 또는 내용이 너무 깁니다.'];
    }
    if (!member_notification_validate_link($link)) {
        return ['success' => false, 'error' => '링크는 서울미녀 도메인의 http/https 주소만 사용할 수 있습니다.'];
    }

    $members = member_notification_resolve_recipients($connect, $target, $eventId, $memberId, $memberIds);
    if (empty($members)) {
        return ['success' => false, 'error' => '발송 대상이 없습니다.'];
    }

    $filtered = member_notification_filter_recipients($connect, $members, $type);
    if (empty($filtered['in_app']) && empty($filtered['push'])) {
        return ['success' => false, 'error' => '알림 수신 설정이 켜진 대상이 없습니다.'];
    }

    $inAppCount = 0;
    if (!empty($filtered['in_app'])) {
        $inAppCount = create_user_notifications_batch(
            $connect,
            $filtered['in_app'],
            $type,
            $title,
            $message !== '' ? $message : null,
            $link !== '' ? $link : null
        );
    }

    $pushResult = ['sent' => 0, 'failed' => 0, 'reason' => 'No push recipients'];
    if (!empty($filtered['push'])) {
        $directPushPlatforms = $target === 'all' ? ['ios', 'web'] : null;
        $pushResult = send_push_to_members(
            $connect,
            $filtered['push'],
            $title,
            $message !== '' ? $message : $title,
            $link !== '' ? $link : null,
            $directPushPlatforms
        );
    }

    if ($target === 'all' && !empty($filtered['push'])) {
        $androidBroadcastResult = send_push_to_android_broadcast(
            $connect,
            $title,
            $message !== '' ? $message : $title,
            $link !== '' ? $link : null
        );
        $pushResult['sent'] = (int)($pushResult['sent'] ?? 0) + (int)($androidBroadcastResult['sent'] ?? 0);
        $pushResult['failed'] = (int)($pushResult['failed'] ?? 0) + (int)($androidBroadcastResult['failed'] ?? 0);
        if (empty($pushResult['error']) && !empty($androidBroadcastResult['error'])) {
            $pushResult['error'] = $androidBroadcastResult['error'];
            $pushResult['error_code'] = $androidBroadcastResult['error_code'] ?? '';
            $pushResult['http_code'] = $androidBroadcastResult['http_code'] ?? 0;
        }
        $pushResult['android_broadcast'] = $androidBroadcastResult;
    }

    $pushSent = (int)($pushResult['sent'] ?? 0);
    $pushFailed = (int)($pushResult['failed'] ?? 0);
    $pushError = trim((string)($pushResult['error'] ?? $pushResult['reason'] ?? ''));
    $pushErrorCode = trim((string)($pushResult['error_code'] ?? ''));
    $pushHttpCode = (int)($pushResult['http_code'] ?? 0);
    if ($pushError !== '' && ($pushErrorCode !== '' || $pushHttpCode > 0)) {
        $pushError .= ' [' . trim($pushErrorCode . ' HTTP ' . ($pushHttpCode ?: '')) . ']';
    }
    $pushSkipped = (int)$filtered['skipped_push'];
    $androidBroadcast = $pushResult['android_broadcast'] ?? null;
    $androidTopicSent = is_array($androidBroadcast) ? (int)($androidBroadcast['sent'] ?? 0) : 0;
    $androidTopicFailed = is_array($androidBroadcast) ? (int)($androidBroadcast['failed'] ?? 0) : 0;
    $directSent = max(0, $pushSent - $androidTopicSent);
    $directFailed = max(0, $pushFailed - $androidTopicFailed);
    $pushDetails = [
        'target' => $target,
        'resolved_users' => count($members),
        'in_app_saved' => $inAppCount,
        'push_enabled_users' => count($filtered['push']),
        'push_skipped_users' => $pushSkipped,
        'direct' => [
            'sent' => $directSent,
            'failed' => $directFailed,
            'platform_stats' => $pushResult['platform_stats'] ?? [],
        ],
        'android_topic' => $androidBroadcast ? [
            'sent' => $androidTopicSent,
            'failed' => $androidTopicFailed,
            'topic' => $androidBroadcast['topic'] ?? '',
            'error' => $androidBroadcast['error'] ?? '',
        ] : null,
        'error' => $pushError,
    ];

    $targetLabel = member_notification_target_label($target, $eventId, $memberId, $members);
    member_notification_log_send($connect, [
        'admin_id' => (int)($adminContext['id'] ?? 0),
        'admin_username' => (string)($adminContext['username'] ?? ''),
        'target_type' => $targetLabel,
        'target_count' => $inAppCount,
        'notification_type' => $type,
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'resolved_count' => count($members),
        'in_app_count' => $inAppCount,
        'push_sent' => $pushSent,
        'push_failed' => $pushFailed,
        'push_skipped' => $pushSkipped,
        'push_error' => $pushError,
        'push_details' => $pushDetails,
    ]);

    $pushWarning = $pushFailed > 0 || (!empty($filtered['push']) && $pushSent === 0);
    $platformSummary = '';
    if ($target === 'all') {
        $platformSummary = ' (Android topic '
            . $androidTopicSent . '건'
            . ($directSent > 0 ? ', iOS/web direct ' . $directSent . '건' : '')
            . ')';
    }
    return [
        'success' => true,
        'sent' => $inAppCount,
        'resolved_count' => count($members),
        'in_app_count' => $inAppCount,
        'push_sent' => $pushSent,
        'push_failed' => $pushFailed,
        'push_skipped' => $pushSkipped,
        'push_details' => $pushDetails,
        'push_success' => !$pushWarning,
        'push_error' => $pushError,
        'message' => $inAppCount . '명에게 알림 저장, 푸시 '
            . $pushSent . '건 성공, '
            . $pushFailed . '건 실패'
            . $platformSummary
            . ($pushSkipped > 0 ? ', 설정 제외 ' . $pushSkipped . '명' : '')
            . ($pushError !== '' ? ' (' . $pushError . ')' : ''),
    ];
}
