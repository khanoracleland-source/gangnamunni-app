<?php
// Uses connection-local temporary tables only; no application records are changed.
require __DIR__ . '/../../hairwang_full/dbconfig.php';
$db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, (int)G5_MYSQL_PORT);
$source = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/index.php');
if (!preg_match('/\$ads_query = "([\s\S]*?)";/', $source, $match)) throw new RuntimeException('Missing query');
$query = str_replace(['gangnamunni_ads', 'gangnamunni_settings'], ['test_home_ads', 'test_home_settings'], $match[1]);
$db->query('CREATE TEMPORARY TABLE test_home_ads (id INT PRIMARY KEY, status VARCHAR(20), display_order INT)');
$db->query('CREATE TEMPORARY TABLE test_home_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(10))');
$db->query("INSERT INTO test_home_ads VALUES (1,'approved',2),(2,'pending',0),(3,'rejected',0),(4,'approved',1)");
$ids = static function () use ($db, $query) { return array_map('intval', array_column($db->query($query)->fetch_all(MYSQLI_ASSOC), 'id')); };
if ($ids() !== [4, 1]) throw new RuntimeException('Approval/order or slot independence failed');
$db->query("INSERT INTO test_home_settings VALUES ('home_banner_enabled_4','0')");
if ($ids() !== [1]) throw new RuntimeException('Hidden banner is exposed');
$db->query("UPDATE test_home_settings SET setting_value='1'");
if ($ids() !== [4, 1]) throw new RuntimeException('Re-enabled banner missing');
$db->query("INSERT INTO test_home_settings VALUES ('home_banner_enabled_2','1')");
if ($ids() !== [4, 1]) throw new RuntimeException('Unapproved banner exposed');
$admin = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/admin_ads.php');
$start = strpos($admin, 'function admin_ads_is_global_action(');
$end = strpos($admin, 'function admin_ads_valid_url(', $start);
eval(substr($admin, $start, $end - $start));
if (!admin_ads_is_global_action('set_home_banner')) throw new RuntimeException('Homepage publication must require super admin');
echo "PASS: approved banners work without slots; order, hide/show, approval and publication permissions remain enforced\n";
$db->close();
