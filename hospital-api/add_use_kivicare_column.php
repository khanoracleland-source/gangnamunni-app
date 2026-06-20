<?php
/**
 * Add use_kivicare column to user_settings table
 */

try {
    $db = new PDO(
        'mysql:host=localhost;dbname=kivicare_laravel;charset=utf8mb4',
        'root',
        '<REMOVED_DB_PASSWORD>',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Checking if use_kivicare column exists...\n";

    $stmt = $db->query("SHOW COLUMNS FROM user_settings LIKE 'use_kivicare'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "✅ Column 'use_kivicare' already exists!\n";
    } else {
        echo "Adding 'use_kivicare' column...\n";

        $sql = "ALTER TABLE user_settings ADD COLUMN use_kivicare TINYINT(1) DEFAULT 0 AFTER category_type";
        $db->exec($sql);

        echo "✅ Column 'use_kivicare' added successfully!\n";
    }

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
