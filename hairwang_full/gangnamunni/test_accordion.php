<?php
session_start();
$_SESSION['ad_admin_logged_in'] = true;

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

$hospitals_sql = "SELECT id, name FROM gangnamunni_hospitals ORDER BY name ASC LIMIT 0, 5";
$hospitals_result = mysqli_query($connect, $hospitals_sql);
$hospitals = [];
while ($row = mysqli_fetch_assoc($hospitals_result)) $hospitals[] = $row;

echo "Hospital count: " . count($hospitals) . "\n";

$hospitals_with_images = [];
foreach ($hospitals as $h) {
    $h_id = $h['id'];
    $img_result = mysqli_query($connect, "SELECT * FROM hospital_images WHERE hospital_id = {$h_id} ORDER BY sort_order ASC, id ASC");
    $h['images'] = [];
    if ($img_result) {
        while ($img = mysqli_fetch_assoc($img_result)) {
            $h['images'][] = $img;
        }
    }
    $h['image_count'] = count($h['images']);
    $hospitals_with_images[] = $h;
    echo "Hospital {$h_id}: {$h['name']} - {$h['image_count']} images\n";
}

echo "\nTotal hospitals_with_images: " . count($hospitals_with_images) . "\n";

// Now generate simple accordion HTML
echo "\n=== ACCORDION TEST (5 hospitals) ===\n";
echo '<div class="accordion" id="testAccordion">\n';
$counter = 0;
foreach ($hospitals_with_images as $idx => $h) {
    $counter++;
    echo "<!-- LOOP #{$counter} -->\n";
    echo '<div class="accordion-item" data-id="' . $h['id'] . '">\n';
    echo '  <h2>' . htmlspecialchars($h['name']) . '</h2>\n';

    // The problematic line - JSON in data attribute
    $json = json_encode($h);
    echo '  <button data-hospital=\'' . $json . '\'>' . "Edit</button>\n";
    echo "  <!-- JSON length: " . strlen($json) . " -->\n";

    // Check if JSON contains single quotes
    if (strpos($json, "'") !== false) {
        echo "  <!-- WARNING: JSON contains single quotes! -->\n";
    }

    echo '</div>\n';
}
echo '</div>\n';
echo "\nLoop completed: {$counter} iterations\n";

mysqli_close($connect);
