<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120);
ob_start();

$results = ['test_date' => date('Y-m-d H:i:s'), 'server' => gethostname(), 'php_version' => PHP_VERSION, 'tests' => [], 'summary' => ['total' => 0, 'pass' => 0, 'fail' => 0]];

