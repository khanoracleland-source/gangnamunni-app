<?php
/** Load the same DB settings as the booking pages, without sessions or HTML. */
if (!defined('G5_MYSQL_HOST')) {
    $payment_db_root = dirname(__DIR__, 2);
    $payment_web_config = $payment_db_root . '/data/dbconfig.php';
    if (is_file($payment_web_config)) {
        if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
        if (!defined('G5_PATH')) define('G5_PATH', $payment_db_root);
        require_once $payment_web_config;
    } else {
        require_once $payment_db_root . '/dbconfig.php';
    }
    unset($payment_db_root, $payment_web_config);
}
if (!defined('G5_MYSQL_PORT')) define('G5_MYSQL_PORT', 3306);
