<?php

if (!function_exists('seoulbeauty_normalize_push_token_input')) {
    function seoulbeauty_normalize_push_token_input(array $input) {
        $token = trim((string) (
            $input['token']
            ?? $input['fcm_token']
            ?? $input['regid']
            ?? $input['reg_id']
            ?? $input['registration_id']
            ?? $input['user_token']
            ?? ''
        ));

        $platform = strtolower(trim((string) ($input['platform'] ?? $input['app'] ?? 'android')));
        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        $deviceInfoInput = $input['device_info'] ?? [];
        if (!is_array($deviceInfoInput)) {
            $deviceInfoInput = [];
        }

        $deviceId = trim((string) ($input['device_id'] ?? $input['uuid'] ?? ''));
        $deviceInfo = json_encode([
            'device_id' => $deviceId,
            'app_version' => substr((string) ($input['app_version'] ?? $input['version'] ?? ($deviceInfoInput['app_version'] ?? '')), 0, 100),
            'platform' => $platform,
            'user_agent' => substr((string) ($deviceInfoInput['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'token' => $token,
            'platform' => $platform,
            'device_id' => $deviceId,
            'device_info' => $deviceInfo,
        ];
    }
}

if (!function_exists('seoulbeauty_register_push_token')) {
    function seoulbeauty_register_push_token($connect, $memberId, array $input, array $options = []) {
        $normalized = seoulbeauty_normalize_push_token_input($input);
        $token = $normalized['token'];
        $platform = $normalized['platform'];
        $deviceId = $normalized['device_id'];
        $deviceInfo = $normalized['device_info'];
        $memberId = trim((string) $memberId);
        $allowEmptyMember = !empty($options['allow_empty_member']);

        if (!$connect) {
            return ['success' => false, 'http_code' => 500, 'message' => 'Database unavailable'];
        }
        if ($memberId === '' && !$allowEmptyMember) {
            return ['success' => false, 'http_code' => 200, 'message' => '로그인 필요', 'debug' => 'no_member'];
        }
        if ($token === '' || strlen($token) < 20 || strlen($token) > 500) {
            return ['success' => false, 'http_code' => 422, 'message' => 'Invalid FCM token'];
        }

        @mysqli_query($connect, "ALTER TABLE g5_push_tokens MODIFY pt_token VARCHAR(500) NOT NULL");

        $escapedToken = mysqli_real_escape_string($connect, $token);
        if ($deviceId !== '') {
            $escapedDeviceId = mysqli_real_escape_string($connect, $deviceId);
            @mysqli_query(
                $connect,
                "UPDATE g5_push_tokens
                 SET pt_use = 0
                 WHERE pt_token <> '{$escapedToken}'
                   AND pt_device_info LIKE '%\"device_id\":\"{$escapedDeviceId}\"%'"
            );
        }

        if ($memberId !== '') {
            $escapedMemberId = mysqli_real_escape_string($connect, $memberId);
            $escapedPlatform = mysqli_real_escape_string($connect, $platform);
            @mysqli_query(
                $connect,
                "UPDATE g5_push_tokens
                 SET pt_use = 0
                 WHERE pt_token <> '{$escapedToken}'
                   AND mb_id = '{$escapedMemberId}'
                   AND pt_platform = '{$escapedPlatform}'
                   AND (pt_device_info IS NULL
                        OR pt_device_info = ''
                        OR pt_device_info LIKE '%\"device_id\":\"\"%')"
            );
        }

        $stmt = mysqli_prepare(
            $connect,
            "INSERT INTO g5_push_tokens
                (mb_id, pt_token, pt_platform, pt_device_info, pt_datetime, pt_use)
             VALUES (?, ?, ?, ?, NOW(), 1)
             ON DUPLICATE KEY UPDATE
                mb_id = VALUES(mb_id),
                pt_platform = VALUES(pt_platform),
                pt_device_info = VALUES(pt_device_info),
                pt_datetime = NOW(),
                pt_use = 1"
        );

        if (!$stmt) {
            return ['success' => false, 'http_code' => 500, 'message' => 'Token registration failed'];
        }

        mysqli_stmt_bind_param($stmt, 'ssss', $memberId, $token, $platform, $deviceInfo);
        $registered = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$registered) {
            return ['success' => false, 'http_code' => 500, 'message' => 'Token registration failed'];
        }

        return [
            'success' => true,
            'message' => '토큰 저장 완료',
            'linked' => $memberId !== '',
            'mb_id' => $memberId,
            'platform' => $platform,
        ];
    }
}
