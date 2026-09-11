<?php
// CLI only: php hairwang_full/gangnamunni/migrations/20260911_community_moderation.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/dbconfig.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
mysqli_set_charset($db, 'utf8mb4');
mysqli_query($db, "ALTER TABLE gangnamunni_community_posts MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending'");
echo "Community moderation migration applied. Existing post statuses preserved.\n";
