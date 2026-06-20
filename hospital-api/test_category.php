<?php
$db = new PDO(
    'mysql:host=localhost;dbname=kivicare_laravel;charset=utf8mb4',
    'root',
    '<REMOVED_DB_PASSWORD>',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$category = '리프팅';

echo "Testing category filter: $category\n\n";

// Test 1: Using json_encode
echo "Test 1: json_encode(category) - without flag\n";
$encoded = json_encode($category);
echo "Encoded value: $encoded\n";

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE JSON_CONTAINS(categories, ?)");
$stmt->execute([$encoded]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Count: " . $result['cnt'] . "\n\n";

// Test 1b: Using JSON_UNESCAPED_UNICODE
echo "Test 1b: json_encode(category, JSON_UNESCAPED_UNICODE)\n";
$encoded = json_encode($category, JSON_UNESCAPED_UNICODE);
echo "Encoded value: $encoded\n";

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE JSON_CONTAINS(categories, ?)");
$stmt->execute([$encoded]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Count: " . $result['cnt'] . "\n\n";

// Test 2: Sample records
echo "Test 2: Get sample records\n";
$stmt2 = $db->prepare("SELECT id, name, categories FROM gangnamunni_hospitals WHERE JSON_CONTAINS(categories, ?) LIMIT 3");
$stmt2->execute([$encoded]);
$hospitals = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($hospitals as $h) {
    echo "ID: " . $h['id'] . "\n";
    echo "Name: " . $h['name'] . "\n";
    echo "Categories: " . $h['categories'] . "\n";
    echo "---\n";
}

// Test 3: Check first 3 hospitals
echo "\nTest 3: First 3 hospitals (any category)\n";
$stmt3 = $db->query("SELECT id, name, categories FROM gangnamunni_hospitals LIMIT 3");
$allHospitals = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($allHospitals as $h) {
    echo "ID: " . $h['id'] . "\n";
    echo "Name: " . $h['name'] . "\n";
    echo "Categories: " . $h['categories'] . "\n";
    echo "Contains 리프팅? " . (strpos($h['categories'], '리프팅') !== false ? 'YES' : 'NO') . "\n";
    echo "---\n";
}
