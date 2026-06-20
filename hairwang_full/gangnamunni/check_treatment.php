<?php
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
$result = mysqli_query($connect, "SELECT id, name, treatment_tags FROM gangnamunni_hospitals WHERE id = 5905");
$hospital = mysqli_fetch_assoc($result);
echo "Hospital ID: " . $hospital['id'] . "\n";
echo "Hospital Name: " . $hospital['name'] . "\n";
echo "Treatment Tags: " . $hospital['treatment_tags'] . "\n";
echo "\nParsed tags:\n";
$tags = json_decode($hospital['treatment_tags'] ?? '[]', true);
print_r($tags);
mysqli_close($connect);
