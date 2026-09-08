<?php
// Exercise the actual page connection setup against an isolated MySQL socket.
$socket = getenv('APP_AUTH_TEST_SOCKET');
if (!$socket) throw new RuntimeException('APP_AUTH_TEST_SOCKET is required');
ini_set('mysqli.default_socket', $socket);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('G5_MYSQL_HOST', 'localhost');
define('G5_MYSQL_USER', 'root');
define('G5_MYSQL_PASSWORD', '');
define('G5_MYSQL_DB', 'mysql');
$source = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/includes/init.php');
$start = strpos($source, '// Pages may close');
$end = strpos($source, "if (isset(\$connect) && \$connect && file_exists", $start);
if ($start === false || $end === false) throw new RuntimeException('Setup block not found');
$setup = substr($source, $start, $end - $start);
$g5 = ['connect_db' => mysqli_connect('localhost', 'root', '', 'mysql')];
$connect = $g5['connect_db'];
eval($setup);
if ($connect === $g5['connect_db']) throw new RuntimeException('Page still owns shared connection');
if (mysqli_fetch_row(mysqli_query($connect, 'SELECT @@session.time_zone'))[0] !== '+09:00') {
    throw new RuntimeException('Wrong token timezone');
}
mysqli_close($connect);
if (mysqli_fetch_row(mysqli_query($g5['connect_db'], 'SELECT 1'))[0] != 1) {
    throw new RuntimeException('Shared connection was closed');
}
echo "PASS: page close preserves Gnuboard connection; page timezone is +09:00\n";
$connect = mysqli_connect('localhost', 'root', '', 'mysql');
$owned = $connect;
eval($setup);
if ($connect !== $owned) throw new RuntimeException('Existing independent connection replaced');
mysqli_close($connect);
mysqli_close($g5['connect_db']);
echo "PASS: existing independent page connection is preserved\n";
