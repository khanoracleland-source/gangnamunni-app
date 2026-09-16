<?php
/** Isolated config selection checks. No database or provider connection. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dir = sys_get_temp_dir() . '/payment_config_' . bin2hex(random_bytes(6));
mkdir($dir . '/gangnamunni/includes', 0700, true);
mkdir($dir . '/data', 0700);
$dir = realpath($dir);
$loader = $dir . '/gangnamunni/includes/booking_payment_db_config.php';
copy(__DIR__ . '/../../hairwang_full/gangnamunni/includes/booking_payment_db_config.php', $loader);
$web = "<?php if (!defined('_GNUBOARD_')) exit(9); define('G5_MYSQL_HOST','web-host'); define('G5_MYSQL_DB','web-db');";
$fallback = "<?php define('G5_MYSQL_HOST','fallback-host'); define('G5_MYSQL_DB','fallback-db'); define('G5_MYSQL_PORT',3308);";
$count = 0;
$run = function (string $label, string $before, string $assertion, int $expected = 0) use ($dir, $loader, &$count): void {
    file_put_contents($dir . '/run.php', '<?php ' . $before . ' require ' . var_export($loader, true) . '; exit((' . $assertion . ') ? 0 : 1);');
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -n -d display_errors=0 -d log_errors=0 ' . escapeshellarg($dir . '/run.php'), $output, $status);
    if ($status !== $expected) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . "\n";
    $count++;
};
try {
    file_put_contents($dir . '/data/dbconfig.php', $web);
    file_put_contents($dir . '/dbconfig.php', $fallback);
    $run('web database takes precedence over generic defaults', '', "G5_MYSQL_HOST === 'web-host' && G5_MYSQL_DB === 'web-db' && G5_PATH === " . var_export($dir, true));
    $run('legacy web configuration gets a default port', '', 'G5_MYSQL_PORT === 3306');
    $run('explicit test configuration remains isolated', "define('G5_MYSQL_HOST','fixture'); define('G5_MYSQL_DB','fixture-db'); define('G5_MYSQL_PORT',3399);", "G5_MYSQL_HOST === 'fixture' && G5_MYSQL_DB === 'fixture-db' && G5_MYSQL_PORT === 3399");
    unlink($dir . '/data/dbconfig.php');
    $run('generic config is used when no web config exists', '', "G5_MYSQL_HOST === 'fallback-host' && G5_MYSQL_DB === 'fallback-db' && G5_MYSQL_PORT === 3308");
    file_put_contents($dir . '/data/dbconfig.php', "<?php throw new RuntimeException('fixture broken config');");
    $run('broken web config does not silently select another database', '', 'true', 255);
    echo "All $count configuration checks passed.\n";
} finally {
    foreach ([$dir . '/run.php', $dir . '/data/dbconfig.php', $dir . '/dbconfig.php', $loader] as $file) if (is_file($file)) unlink($file);
    rmdir($dir . '/data'); rmdir($dir . '/gangnamunni/includes'); rmdir($dir . '/gangnamunni'); rmdir($dir);
}
