<?php

require_once __DIR__ . '/../includes/app_native_auth_http.php';
$input = app_auth_http_input();
$connect = null;

$code = app_auth_input_string($input, 'code');
$device_id = app_auth_input_string($input, 'device_id');

if ($code === '' || !app_native_auth_is_valid_device_id($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'code and device_id are required']);
    exit;
}

$failure_stage = 'connection';
try {
    $connect = app_auth_open_connection();
    // Fail before issuing credentials if the server key is not provisioned.
    $failure_stage = 'encryption_key';
    app_exchange_payload_key();
    // Expired retry data is no longer useful. This contains only ciphertext.
    $failure_stage = 'payload_schema';
    mysqli_query($connect, "UPDATE sso_tokens SET payload = NULL WHERE user_type = 'patient_app_exchange' AND expires_at <= NOW() AND payload IS NOT NULL");
    $response = app_native_auth_transaction($connect, function () use ($connect, $code, $device_id, &$failure_stage) {
        $failure_stage = 'exchange_claim';
        $claim = app_consume_exchange_code($connect, $code, $device_id);
        $mb_id = $claim['mb_id'];
        if ($mb_id === '') throw new DomainException('exchange_invalid', 401);

        $failure_stage = 'member_config';
        $user = app_auth_member($connect, $mb_id);
        if ($claim['payload'] !== null) {
            $failure_stage = 'payload_decryption';
            $payload = app_decrypt_exchange_payload($code, $claim['payload']);
        } else {
            // Keep the WebView's existing remember credential valid until
            // native storage has received this response. Killing the app
            // between server commit and secure-storage commit must not lose
            // BOTH the cookie fallback and the newly-issued native token.
            // Expired rows are pruned by create_remember_login_token; explicit
            // logout revokes ALL credentials bound to this account/device.
            $failure_stage = 'token_issuance';
            $refresh = create_remember_login_token(['mb_id' => $mb_id], APP_REFRESH_TOKEN_LIFETIME,
                ['is_patient_app' => true, 'device_id' => $device_id]);
            $access = app_issue_access_token($connect, $mb_id, $device_id);
            $bridge = app_issue_session_bridge_token($connect, $mb_id, $device_id);
            if ($access['token'] === '' || $refresh === '' || $bridge === '') {
                throw new RuntimeException('Token issuance failed');
            }
            $payload = ['access_token' => $access['token'], 'access_expires_at' => $access['expires_at'],
                'refresh_token' => $refresh, 'refresh_expires_at' => G5_SERVER_TIME + APP_REFRESH_TOKEN_LIFETIME,
                'session_bridge_token' => $bridge];
            $failure_stage = 'payload_storage';
            app_store_exchange_payload($connect, $code, $payload);
        }
        $failure_stage = 'commit';
        return array_merge(['success' => true], $payload, ['user' => $user]);
    });
    $failure_stage = 'response_encoding';
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    // Never log exception messages: SQL/payload errors may contain credentials.
    error_log('[app-native-auth] exchange failed stage=' . $failure_stage
        . ' exception=' . get_class($e) . ' code=' . (int)$e->getCode());
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'exchange_unavailable']);
}
if ($connect) mysqli_close($connect);
