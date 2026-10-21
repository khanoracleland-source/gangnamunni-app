-- 예약을 누가 취소했는지 기록(노쇼·잦은 취소 제한 집계용). 배포 전에 한 번 실행한다.
-- cancelled_by: 'member' = 회원이 직접 취소, NULL = 병원·관리자 취소 또는 이전 데이터(집계 제외).
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_bookings' AND COLUMN_NAME = 'cancelled_by');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_bookings ADD COLUMN cancelled_by VARCHAR(20) NULL DEFAULT NULL COMMENT ''member=회원 직접 취소''', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
