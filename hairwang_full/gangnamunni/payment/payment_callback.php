<?php
/**
 * Legacy advertising callback is deliberately disabled.
 *
 * KakaoPay/TossPay integration must verify the provider transaction
 * server-to-server and then update gangnamunni_platform_payments atomically.
 */
http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'success' => false,
    'code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED',
    'message' => '결제 제공자 연동이 아직 구성되지 않았습니다.',
], JSON_UNESCAPED_UNICODE);
