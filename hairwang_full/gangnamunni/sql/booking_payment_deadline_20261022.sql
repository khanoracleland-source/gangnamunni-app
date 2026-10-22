-- [05-37] 결제 기한 자동 취소. 배포 전에 한 번 실행한다. (여러 번 실행해도 안전)
-- confirmed_at          : 병원이 예약을 확정한 시각(결제 기한 시작점)
-- expire_notice_seen_at : 자동 취소 안내 모달을 회원이 확인한 시각
-- 사이트·cron 과 같은 한국 시간으로 기록한다(서버 시간대가 UTC 여도 기한이 어긋나지 않도록).
SET time_zone = '+09:00';
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_bookings' AND COLUMN_NAME = 'confirmed_at');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_bookings ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_bookings' AND COLUMN_NAME = 'expire_notice_seen_at');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_bookings ADD COLUMN expire_notice_seen_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
-- 이미 확정된 예약은 지금부터 기한을 센다(배포 직후 한꺼번에 취소되지 않도록).
UPDATE gangnamunni_bookings SET confirmed_at = NOW() WHERE status = 'confirmed' AND confirmed_at IS NULL;
