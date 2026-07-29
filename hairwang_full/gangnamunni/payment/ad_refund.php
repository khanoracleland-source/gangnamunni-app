<?php
/**
 * Retired legacy ad-package refund endpoint.
 *
 * Ending an advertisement stops future daily wallet charges. The remaining
 * advertising-wallet balance stays on the hospital account and is not
 * automatically refunded.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'success' => false,
    'code' => 'AD_PACKAGE_BILLING_RETIRED',
    'message' => '광고 종료 시 이후 일일 차감만 중단되며 광고 지갑 잔액은 유지됩니다.',
], JSON_UNESCAPED_UNICODE);
