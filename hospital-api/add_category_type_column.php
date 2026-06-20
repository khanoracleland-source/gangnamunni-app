<?php
/**
 * Add category_type column to user_settings table
 */

try {
    $db = new PDO(
        'mysql:host=localhost;dbname=kivicare_laravel;charset=utf8mb4',
        'root',
        '<REMOVED_DB_PASSWORD>',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Checking if category_type column exists...\n";

    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM user_settings LIKE 'category_type'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "✅ Column 'category_type' already exists!\n";
    } else {
        echo "Adding 'category_type' column...\n";

        $sql = "ALTER TABLE user_settings ADD COLUMN category_type VARCHAR(20) DEFAULT 'checkbox' AFTER loading_type";
        $db->exec($sql);

        echo "✅ Column 'category_type' added successfully!\n";
    }

    // Verify
    $stmt = $db->query("DESC user_settings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "\nCurrent columns in user_settings:\n";
    foreach ($columns as $column) {
        echo "  - $column\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Migration completed!\n";
