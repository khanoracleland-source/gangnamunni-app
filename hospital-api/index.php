<?php
/**
 * Hospital Booking API
 * Standalone PHP REST API for hospital data
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
$db = new PDO(
    'mysql:host=localhost;dbname=kivicare_laravel;charset=utf8mb4',
    'root',
    '<REMOVED_DB_PASSWORD>',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Get request path and method
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$query = $_GET;

// Router
try {
    // Health check
    if ($path === '/api/health') {
        echo json_encode(['status' => 'OK', 'timestamp' => date('c')]);
        exit;
    }

    // Get all hospitals
    if ($path === '/api/hospitals' && $method === 'GET') {
        $sql = "SELECT * FROM gangnamunni_hospitals WHERE 1=1";
        $params = [];

        // Search filter
        if (isset($query['search'])) {
            $sql .= " AND name LIKE ?";
            $params[] = "%{$query['search']}%";
        }

        // Category filter
        if (isset($query['category'])) {
            $sql .= " AND JSON_CONTAINS(categories, ?)";
            $params[] = json_encode($query['category'], JSON_UNESCAPED_UNICODE);
        }

        // Min rating filter
        if (isset($query['min_rating'])) {
            $sql .= " AND rating >= ?";
            $params[] = $query['min_rating'];
        }

        // Sorting
        $sortBy = $query['sort_by'] ?? 'rating';
        $sortOrder = $query['sort_order'] ?? 'DESC';
        $sql .= " ORDER BY {$sortBy} {$sortOrder}";

        // Pagination
        $page = (int)($query['page'] ?? 1);
        $perPage = (int)($query['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM gangnamunni_hospitals WHERE 1=1";
        $countParams = [];
        if (isset($query['search'])) {
            $countSql .= " AND name LIKE ?";
            $countParams[] = "%{$query['search']}%";
        }
        if (isset($query['category'])) {
            $countSql .= " AND JSON_CONTAINS(categories, ?)";
            $countParams[] = json_encode($query['category'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($query['min_rating'])) {
            $countSql .= " AND rating >= ?";
            $countParams[] = $query['min_rating'];
        }

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'data' => $hospitals,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'last_page' => ceil($total / $perPage),
            ]
        ]);
        exit;
    }

    // Get single hospital by ID
    if (preg_match('/^\/api\/hospitals\/(\d+)$/', $path, $matches) && $method === 'GET') {
        $id = $matches[1];
        $stmt = $db->prepare("SELECT * FROM gangnamunni_hospitals WHERE id = ?");
        $stmt->execute([$id]);
        $hospital = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hospital) {
            http_response_code(404);
            echo json_encode(['error' => 'Hospital not found']);
            exit;
        }

        // Parse JSON fields
        $hospital['categories'] = json_decode($hospital['categories']);
        $hospital['badges'] = json_decode($hospital['badges']);
        $hospital['treatment_tags'] = json_decode($hospital['treatment_tags']);
        $hospital['subway_names'] = json_decode($hospital['subway_names']);
        $hospital['business_hours'] = json_decode($hospital['business_hours']);

        echo json_encode($hospital);
        exit;
    }

    // Get nearby hospitals
    if ($path === '/api/hospitals/nearby' && $method === 'GET') {
        $lat = $query['lat'] ?? null;
        $lng = $query['lng'] ?? null;
        $radius = $query['radius'] ?? 5;

        if (!$lat || !$lng) {
            http_response_code(400);
            echo json_encode(['error' => 'lat and lng parameters required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT *,
            (6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )) AS distance
            FROM gangnamunni_hospitals
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            HAVING distance < ?
            ORDER BY distance
            LIMIT 50
        ");
        $stmt->execute([$lat, $lng, $lat, $radius]);
        $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($hospitals);
        exit;
    }

    // Get all categories
    if ($path === '/api/categories' && $method === 'GET') {
        $stmt = $db->query("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(categories, CONCAT('$[', numbers.idx, ']'))) as name,
                COUNT(*) as hospital_count
            FROM gangnamunni_hospitals
            CROSS JOIN (
                SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
            ) as numbers
            WHERE JSON_EXTRACT(categories, CONCAT('$[', numbers.idx, ']')) IS NOT NULL
            GROUP BY name
            ORDER BY hospital_count DESC
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($categories);
        exit;
    }

    // Get statistics
    if ($path === '/api/stats' && $method === 'GET') {
        // Total hospitals
        $total = $db->query("SELECT COUNT(*) as count FROM gangnamunni_hospitals")->fetch()['count'];

        // Average rating
        $avgRating = $db->query("SELECT AVG(rating) as avg FROM gangnamunni_hospitals")->fetch()['avg'];

        // Total reviews
        $totalReviews = $db->query("SELECT SUM(review_count) as sum FROM gangnamunni_hospitals")->fetch()['sum'];

        // Top rated
        $topRated = $db->query("
            SELECT id, name, rating, review_count, address
            FROM gangnamunni_hospitals
            WHERE rating IS NOT NULL
            ORDER BY rating DESC, review_count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Most reviewed
        $mostReviewed = $db->query("
            SELECT id, name, rating, review_count, address
            FROM gangnamunni_hospitals
            WHERE review_count IS NOT NULL
            ORDER BY review_count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'total_hospitals' => (int)$total,
            'avg_rating' => round($avgRating, 2),
            'total_reviews' => (int)$totalReviews,
            'top_rated' => $topRated,
            'most_reviewed' => $mostReviewed,
        ]);
        exit;
    }

    // ============================================
    // BOOKING ENDPOINTS
    // ============================================

    // Get all bookings
    if ($path === '/api/bookings' && $method === 'GET') {
        $sql = "SELECT b.*, h.name as hospital_name, h.address as hospital_address
                FROM bookings b
                LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
                WHERE 1=1";
        $params = [];

        // Filter by hospital_id
        if (isset($query['hospital_id'])) {
            $sql .= " AND b.hospital_id = ?";
            $params[] = $query['hospital_id'];
        }

        // Filter by status
        if (isset($query['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $query['status'];
        }

        // Filter by date
        if (isset($query['date'])) {
            $sql .= " AND b.booking_date = ?";
            $params[] = $query['date'];
        }

        // Filter by patient phone
        if (isset($query['phone'])) {
            $sql .= " AND b.patient_phone = ?";
            $params[] = $query['phone'];
        }

        $sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

        // Pagination
        $page = (int)($query['page'] ?? 1);
        $perPage = (int)($query['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM bookings b WHERE 1=1";
        $countParams = [];
        if (isset($query['hospital_id'])) {
            $countSql .= " AND b.hospital_id = ?";
            $countParams[] = $query['hospital_id'];
        }
        if (isset($query['status'])) {
            $countSql .= " AND b.status = ?";
            $countParams[] = $query['status'];
        }
        if (isset($query['date'])) {
            $countSql .= " AND b.booking_date = ?";
            $countParams[] = $query['date'];
        }
        if (isset($query['phone'])) {
            $countSql .= " AND b.patient_phone = ?";
            $countParams[] = $query['phone'];
        }

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'data' => $bookings,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'last_page' => ceil($total / $perPage),
            ]
        ]);
        exit;
    }

    // Get single booking by ID
    if (preg_match('/^\/api\/bookings\/(\d+)$/', $path, $matches) && $method === 'GET') {
        $id = $matches[1];
        $stmt = $db->prepare("
            SELECT b.*, h.name as hospital_name, h.address as hospital_address, h.phone as hospital_phone
            FROM bookings b
            LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            http_response_code(404);
            echo json_encode(['error' => 'Booking not found']);
            exit;
        }

        echo json_encode($booking);
        exit;
    }

    // Create new booking
    if ($path === '/api/bookings' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['hospital_id', 'patient_name', 'patient_phone', 'booking_date', 'booking_time'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: {$field}"]);
                exit;
            }
        }

        // Check if hospital exists
        $stmt = $db->prepare("SELECT id FROM gangnamunni_hospitals WHERE id = ?");
        $stmt->execute([$input['hospital_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Hospital not found']);
            exit;
        }

        // Check for duplicate booking (same hospital, date, time)
        $stmt = $db->prepare("
            SELECT id FROM bookings
            WHERE hospital_id = ? AND booking_date = ? AND booking_time = ? AND status != 'cancelled'
        ");
        $stmt->execute([$input['hospital_id'], $input['booking_date'], $input['booking_time']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'This time slot is already booked']);
            exit;
        }

        // Insert booking
        $stmt = $db->prepare("
            INSERT INTO bookings (hospital_id, patient_name, patient_phone, patient_email,
                                  booking_date, booking_time, treatment_type, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $input['hospital_id'],
            $input['patient_name'],
            $input['patient_phone'],
            $input['patient_email'] ?? null,
            $input['booking_date'],
            $input['booking_time'],
            $input['treatment_type'] ?? null,
            $input['notes'] ?? null,
        ]);

        $bookingId = $db->lastInsertId();

        // Get the created booking
        $stmt = $db->prepare("
            SELECT b.*, h.name as hospital_name, h.address as hospital_address
            FROM bookings b
            LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(201);
        echo json_encode($booking);
        exit;
    }

    // Update booking status
    if (preg_match('/^\/api\/bookings\/(\d+)$/', $path, $matches) && $method === 'PUT') {
        $id = $matches[1];
        $input = json_decode(file_get_contents('php://input'), true);

        // Check if booking exists
        $stmt = $db->prepare("SELECT id FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Booking not found']);
            exit;
        }

        // Update booking
        $allowedStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (isset($input['status']) && !in_array($input['status'], $allowedStatuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status value']);
            exit;
        }

        $updateFields = [];
        $updateParams = [];

        if (isset($input['status'])) {
            $updateFields[] = "status = ?";
            $updateParams[] = $input['status'];
        }
        if (isset($input['notes'])) {
            $updateFields[] = "notes = ?";
            $updateParams[] = $input['notes'];
        }
        if (isset($input['booking_date'])) {
            $updateFields[] = "booking_date = ?";
            $updateParams[] = $input['booking_date'];
        }
        if (isset($input['booking_time'])) {
            $updateFields[] = "booking_time = ?";
            $updateParams[] = $input['booking_time'];
        }

        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            exit;
        }

        $updateParams[] = $id;
        $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($updateParams);

        // Get updated booking
        $stmt = $db->prepare("
            SELECT b.*, h.name as hospital_name, h.address as hospital_address
            FROM bookings b
            LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($booking);
        exit;
    }

    // Delete/Cancel booking
    if (preg_match('/^\/api\/bookings\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
        $id = $matches[1];

        // Check if booking exists
        $stmt = $db->prepare("SELECT id FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Booking not found']);
            exit;
        }

        // Soft delete - just update status to cancelled
        $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['message' => 'Booking cancelled successfully']);
        exit;
    }

    // Get bookings for a specific hospital
    if (preg_match('/^\/api\/hospitals\/(\d+)\/bookings$/', $path, $matches) && $method === 'GET') {
        $hospitalId = $matches[1];

        $sql = "SELECT b.* FROM bookings b WHERE b.hospital_id = ?";
        $params = [$hospitalId];

        // Filter by status
        if (isset($query['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $query['status'];
        }

        // Filter by date
        if (isset($query['date'])) {
            $sql .= " AND b.booking_date = ?";
            $params[] = $query['date'];
        }

        $sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($bookings);
        exit;
    }

    // Get available time slots for a hospital on a specific date
    if (preg_match('/^\/api\/hospitals\/(\d+)\/available-slots$/', $path, $matches) && $method === 'GET') {
        $hospitalId = $matches[1];
        $date = $query['date'] ?? date('Y-m-d');

        // Get all bookings for this hospital on this date
        $stmt = $db->prepare("
            SELECT booking_time FROM bookings
            WHERE hospital_id = ? AND booking_date = ? AND status != 'cancelled'
        ");
        $stmt->execute([$hospitalId, $date]);
        $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Generate available slots (9:00 - 18:00, 30-minute intervals)
        $allSlots = [];
        $start = strtotime('09:00');
        $end = strtotime('18:00');
        while ($start <= $end) {
            $timeSlot = date('H:i:s', $start);
            $allSlots[] = [
                'time' => $timeSlot,
                'available' => !in_array($timeSlot, $bookedTimes)
            ];
            $start += 1800; // 30 minutes
        }

        echo json_encode([
            'date' => $date,
            'slots' => $allSlots
        ]);
        exit;
    }

    // ============================================
    // REVIEW ENDPOINTS
    // ============================================

    // Get all reviews
    if ($path === '/api/reviews' && $method === 'GET') {
        $sql = "SELECT r.*, h.name as hospital_name
                FROM reviews r
                LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
                WHERE 1=1";
        $params = [];

        // Filter by hospital_id
        if (isset($query['hospital_id'])) {
            $sql .= " AND r.hospital_id = ?";
            $params[] = $query['hospital_id'];
        }

        // Filter by status
        if (isset($query['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $query['status'];
        } else {
            // Default to approved reviews only for public access
            $sql .= " AND r.status = 'approved'";
        }

        // Filter by minimum rating
        if (isset($query['min_rating'])) {
            $sql .= " AND r.rating >= ?";
            $params[] = $query['min_rating'];
        }

        $sql .= " ORDER BY r.created_at DESC";

        // Pagination
        $page = (int)($query['page'] ?? 1);
        $perPage = (int)($query['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM reviews r WHERE 1=1";
        $countParams = [];
        if (isset($query['hospital_id'])) {
            $countSql .= " AND r.hospital_id = ?";
            $countParams[] = $query['hospital_id'];
        }
        if (isset($query['status'])) {
            $countSql .= " AND r.status = ?";
            $countParams[] = $query['status'];
        } else {
            $countSql .= " AND r.status = 'approved'";
        }
        if (isset($query['min_rating'])) {
            $countSql .= " AND r.rating >= ?";
            $countParams[] = $query['min_rating'];
        }

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'data' => $reviews,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'last_page' => ceil($total / $perPage),
            ]
        ]);
        exit;
    }

    // Get reviews for a specific hospital
    if (preg_match('/^\/api\/hospitals\/(\d+)\/reviews$/', $path, $matches) && $method === 'GET') {
        $hospitalId = $matches[1];

        // Pagination
        $page = (int)($query['page'] ?? 1);
        $perPage = (int)($query['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM reviews WHERE hospital_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $params = [$hospitalId];

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get average rating and total count for this hospital
        $statsStmt = $db->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
            FROM reviews
            WHERE hospital_id = ? AND status = 'approved'
        ");
        $statsStmt->execute([$hospitalId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'data' => $reviews,
            'stats' => [
                'avg_rating' => round($stats['avg_rating'], 1),
                'total_reviews' => (int)$stats['total_reviews']
            ],
            'pagination' => [
                'total' => (int)$stats['total_reviews'],
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'last_page' => ceil($stats['total_reviews'] / $perPage),
            ]
        ]);
        exit;
    }

    // Get single review
    if (preg_match('/^\/api\/reviews\/(\d+)$/', $path, $matches) && $method === 'GET') {
        $id = $matches[1];
        $stmt = $db->prepare("
            SELECT r.*, h.name as hospital_name, h.address as hospital_address
            FROM reviews r
            LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            http_response_code(404);
            echo json_encode(['error' => 'Review not found']);
            exit;
        }

        echo json_encode($review);
        exit;
    }

    // Create new review
    if ($path === '/api/reviews' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['hospital_id', 'patient_name', 'rating', 'content'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: {$field}"]);
                exit;
            }
        }

        // Validate rating range
        if ($input['rating'] < 0 || $input['rating'] > 10) {
            http_response_code(400);
            echo json_encode(['error' => 'Rating must be between 0 and 10']);
            exit;
        }

        // Check if hospital exists
        $stmt = $db->prepare("SELECT id FROM gangnamunni_hospitals WHERE id = ?");
        $stmt->execute([$input['hospital_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Hospital not found']);
            exit;
        }

        // Insert review
        $stmt = $db->prepare("
            INSERT INTO reviews (hospital_id, patient_name, patient_phone, rating, title, content,
                                treatment_type, visit_date, images, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $input['hospital_id'],
            $input['patient_name'],
            $input['patient_phone'] ?? null,
            $input['rating'],
            $input['title'] ?? null,
            $input['content'],
            $input['treatment_type'] ?? null,
            $input['visit_date'] ?? null,
            isset($input['images']) ? json_encode($input['images']) : null
        ]);

        $reviewId = $db->lastInsertId();

        // Get the created review
        $stmt = $db->prepare("SELECT * FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(201);
        echo json_encode($review);
        exit;
    }

    // Update review (for moderation)
    if (preg_match('/^\/api\/reviews\/(\d+)$/', $path, $matches) && $method === 'PUT') {
        $id = $matches[1];
        $input = json_decode(file_get_contents('php://input'), true);

        // Check if review exists
        $stmt = $db->prepare("SELECT id FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Review not found']);
            exit;
        }

        // Update review
        $updateFields = [];
        $updateParams = [];

        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (isset($input['status'])) {
            if (!in_array($input['status'], $allowedStatuses)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value']);
                exit;
            }
            $updateFields[] = "status = ?";
            $updateParams[] = $input['status'];
        }

        if (isset($input['helpful_count'])) {
            $updateFields[] = "helpful_count = ?";
            $updateParams[] = $input['helpful_count'];
        }

        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            exit;
        }

        $updateParams[] = $id;
        $sql = "UPDATE reviews SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($updateParams);

        // Get updated review
        $stmt = $db->prepare("SELECT * FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($review);
        exit;
    }

    // Delete review
    if (preg_match('/^\/api\/reviews\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
        $id = $matches[1];

        // Check if review exists
        $stmt = $db->prepare("SELECT id FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Review not found']);
            exit;
        }

        // Delete review
        $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['message' => 'Review deleted successfully']);
        exit;
    }

    // Get user settings
    if ($path === '/api/settings' && $method === 'GET') {
        $sessionId = $query['session_id'] ?? null;

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'session_id is required']);
            exit;
        }

        // Get settings from database
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no settings found, return defaults
        if (!$settings) {
            echo json_encode([
                'navigation' => [
                    'pc' => ['mode' => 'same-tab'],
                    'mobile' => ['mode' => 'same-page']
                ],
                'display' => [
                    'theme' => 'light',
                    'fontSize' => 'medium'
                ]
            ]);
            exit;
        }

        // Convert database format to frontend format
        echo json_encode([
            'navigation' => [
                'pc' => ['mode' => $settings['navigation_pc_mode']],
                'mobile' => ['mode' => $settings['navigation_mobile_mode']]
            ],
            'display' => [
                'theme' => $settings['theme'],
                'fontSize' => $settings['font_size']
            ]
        ]);
        exit;
    }

    // Save user settings
    if ($path === '/api/settings' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $sessionId = $input['session_id'] ?? null;
        $settings = $input['settings'] ?? null;

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'session_id is required']);
            exit;
        }

        if (!$settings) {
            http_response_code(400);
            echo json_encode(['error' => 'settings data is required']);
            exit;
        }

        // Extract settings from frontend format
        $navigationPcMode = $settings['navigation']['pc']['mode'] ?? 'same-tab';
        $navigationMobileMode = $settings['navigation']['mobile']['mode'] ?? 'same-page';
        $theme = $settings['display']['theme'] ?? 'light';
        $fontSize = $settings['display']['fontSize'] ?? 'medium';

        // Check if settings already exist
        $stmt = $db->prepare("SELECT id FROM user_settings WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing settings
            $stmt = $db->prepare("
                UPDATE user_settings
                SET navigation_pc_mode = ?,
                    navigation_mobile_mode = ?,
                    theme = ?,
                    font_size = ?,
                    updated_at = NOW()
                WHERE session_id = ?
            ");
            $stmt->execute([
                $navigationPcMode,
                $navigationMobileMode,
                $theme,
                $fontSize,
                $sessionId
            ]);
        } else {
            // Insert new settings
            $stmt = $db->prepare("
                INSERT INTO user_settings
                (session_id, navigation_pc_mode, navigation_mobile_mode, theme, font_size, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $sessionId,
                $navigationPcMode,
                $navigationMobileMode,
                $theme,
                $fontSize
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
        exit;
    }

    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
