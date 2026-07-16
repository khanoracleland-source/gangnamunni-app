<?php
/**
 * ========================================
 * 예약(booking) ↔ 후기(hospital_reviews) 연결 헬퍼
 * ========================================
 * - hospital_reviews.booking_id 컬럼을 자동 보장(self-healing)하여
 *   운영 DB에도 별도 수동 마이그레이션 없이 첫 호출 시 컬럼이 추가된다.
 * - 완료된 예약별로 이미 작성된 후기 상태(작성/승인대기/공개)를 조회한다.
 *
 * @file gangnamunni/includes/review_booking.php
 */

if (!function_exists('ensureReviewBookingColumn')) {

    /** hospital_reviews 에 booking_id 컬럼이 없으면 추가 (요청당 1회만 검사) */
    function ensureReviewBookingColumn($connect)
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $r = @mysqli_query($connect, "SHOW COLUMNS FROM hospital_reviews LIKE 'booking_id'");
        if ($r && mysqli_num_rows($r) === 0) {
            @mysqli_query($connect, "ALTER TABLE hospital_reviews
                ADD COLUMN booking_id INT DEFAULT NULL AFTER event_id,
                ADD INDEX idx_hr_booking (booking_id)");
        }
        if ($r) mysqli_free_result($r);
    }

    /**
     * 특정 예약에 대해 작성된 후기 1건 조회.
     * @return array|null ['id','review_status','is_active'] 또는 null(미작성)
     */
    function getBookingReview($connect, $booking_id)
    {
        ensureReviewBookingColumn($connect);
        $booking_id = (int)$booking_id;
        if ($booking_id <= 0) return null;
        $stmt = mysqli_prepare($connect,
            "SELECT id, review_status, is_active FROM hospital_reviews
             WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
        return $row;
    }

    /**
     * 여러 예약의 후기 상태를 한 번에 조회.
     * @return array [booking_id => ['id','review_status','is_active']]
     */
    function getBookingReviewsMap($connect, array $booking_ids)
    {
        ensureReviewBookingColumn($connect);
        $ids = array_values(array_unique(array_filter(array_map('intval', $booking_ids), function ($v) { return $v > 0; })));
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = mysqli_prepare($connect,
            "SELECT booking_id, id, review_status, is_active FROM hospital_reviews
             WHERE booking_id IN ($ph) ORDER BY id ASC");
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $map = [];
        while ($row = mysqli_fetch_assoc($res)) { $map[(int)$row['booking_id']] = $row; }
        mysqli_stmt_close($stmt);
        return $map;
    }

    /**
     * 후기 상태 → 사용자 노출용 라벨/상태 코드.
     * @return array ['state' => 'none'|'pending'|'published'|'rejected', 'label' => string]
     */
    function bookingReviewState($review)
    {
        if (!$review) return ['state' => 'none', 'label' => ''];
        $status = $review['review_status'] ?? '';
        if ($status === 'published' && (int)($review['is_active'] ?? 0) === 1) {
            return ['state' => 'published', 'label' => '내 후기 보기'];
        }
        if ($status === 'rejected') {
            return ['state' => 'rejected', 'label' => '후기 반려됨'];
        }
        return ['state' => 'pending', 'label' => '승인 대기중'];
    }
}
