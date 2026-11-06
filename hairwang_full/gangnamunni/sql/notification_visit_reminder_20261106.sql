-- 설정 > 방문 전 알림 (+ 커뮤니티 / 포인트 · 후기 switches). 배포 전에 한 번 실행한다. (여러 번 실행해도 안전; MariaDB 와 MySQL 8 둘 다 동작)
-- gangnamunni_notification_settings.noti_visit / noti_community / noti_review : the 설정 page switches (default on)
-- gangnamunni_bookings.visit_reminder_sent_at : the day-before reminder was sent (one per booking; seoulbeauty-api VisitReminderService claims it)
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_notification_settings' AND COLUMN_NAME = 'noti_visit');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_notification_settings ADD COLUMN noti_visit TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_notification_settings' AND COLUMN_NAME = 'noti_community');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_notification_settings ADD COLUMN noti_community TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_notification_settings' AND COLUMN_NAME = 'noti_review');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_notification_settings ADD COLUMN noti_review TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
SET @h := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_bookings' AND COLUMN_NAME = 'visit_reminder_sent_at');
SET @d := IF(@h = 0, 'ALTER TABLE gangnamunni_bookings ADD COLUMN visit_reminder_sent_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @d; EXECUTE s; DEALLOCATE PREPARE s;
