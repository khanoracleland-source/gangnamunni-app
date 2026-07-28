<?php
/**
 * Refunds remain unavailable until the provider adapter performs a verified,
 * idempotent refund against gangnamunni_platform_payments.
 */
http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'success' => false,
    'code' => 'REFUND_PROVIDER_NOT_CONFIGURED',
    'message' => '검증된 환불 제공자 연동이 아직 구성되지 않았습니다.',
], JSON_UNESCAPED_UNICODE);
