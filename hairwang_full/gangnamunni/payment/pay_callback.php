<?php
/**
 * Legacy payment callback.
 *
 * Disabled until a PG-specific signature verification and server-side payment
 * lookup are implemented. Never update order state from request parameters.
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Legacy payment callback is disabled.',
], JSON_UNESCAPED_UNICODE);
