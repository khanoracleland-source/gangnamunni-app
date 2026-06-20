<?php
/**
 * Create all required tables for the hospital booking system
 */

try {
    $db = new PDO(
        'mysql:host=localhost;dbname=kivicare_laravel;charset=utf8mb4',
        'root',
        '<REMOVED_DB_PASSWORD>',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "🔧 Creating database tables...\n\n";

    // 1. Users table
    echo "Creating users table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        birth_date DATE,
        gender ENUM('male', 'female', 'other'),
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Users table created\n\n";

    // 2. Bookings table
    echo "Creating bookings table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS bookings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        hospital_id BIGINT UNSIGNED NOT NULL,
        booking_date DATE NOT NULL,
        booking_time TIME NOT NULL,
        category VARCHAR(50),
        service_name VARCHAR(255),
        patient_name VARCHAR(255) NOT NULL,
        patient_phone VARCHAR(20) NOT NULL,
        patient_email VARCHAR(255),
        notes TEXT,
        status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_hospital_id (hospital_id),
        INDEX idx_booking_date (booking_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Bookings table created\n\n";

    // 3. Favorites table
    echo "Creating favorites table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS favorites (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        hospital_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, hospital_id),
        INDEX idx_user_id (user_id),
        INDEX idx_hospital_id (hospital_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Favorites table created\n\n";

    // 4. Community posts table
    echo "Creating community_posts table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS community_posts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        category ENUM('review', 'tip', 'qna') NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        hospital_id BIGINT UNSIGNED,
        images TEXT,
        views INT DEFAULT 0,
        likes INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_category (category),
        INDEX idx_hospital_id (hospital_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Community posts table created\n\n";

    // 5. Community comments table
    echo "Creating community_comments table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS community_comments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_post_id (post_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Community comments table created\n\n";

    // 6. Hospital reviews table (upgrade existing)
    echo "Creating hospital_reviews table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS hospital_reviews (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        hospital_id BIGINT UNSIGNED NOT NULL,
        rating DECIMAL(2,1) NOT NULL,
        comment TEXT,
        images TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_hospital_id (hospital_id),
        INDEX idx_rating (rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Hospital reviews table created\n\n";

    // Verify tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📊 Current tables in database:\n";
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }

    echo "\n✅ All tables created successfully!\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
