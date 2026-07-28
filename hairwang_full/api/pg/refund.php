<?php
/**
 * Refund endpoint placeholder.
 *
 * Refunds remain fail-closed until a real provider adapter with verified
 * cancellation responses is installed.
 */
include_once dirname(__FILE__) . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Only POST method is allowed', 405, 'METHOD_NOT_ALLOWED');
}

$auth = ApiAuth::getAuthFromHeader();
$api_key_info = $auth ? ApiAuth::verifyApiKey($auth['api_key'], $auth['api_secret']) : false;
if (!$api_key_info) {
    ApiResponse::error('API authentication required', 401, 'AUTH_REQUIRED');
}

if ($api_key_info['ak_type'] !== 'pg' && $api_key_info['ak_type'] !== 'all') {
    ApiResponse::error('This API key is not authorized for PG operations', 403, 'FORBIDDEN');
}

$request_ip = ApiAuth::getRequestIp();
if (!ApiAuth::verifyIpWhitelist($api_key_info['ak_id'], $request_ip)) {
    ApiResponse::error('Your IP address is not authorized', 403, 'IP_NOT_ALLOWED');
}
if (!ApiAuth::enforceRateLimit($api_key_info['ak_id'], 'pg-refund', 15)) {
    ApiResponse::error('Too many requests', 429, 'RATE_LIMITED');
}

ApiResponse::error(
    'Refund provider is not configured. No payment state was changed.',
    503,
    'REFUND_PROVIDER_NOT_CONFIGURED'
);
