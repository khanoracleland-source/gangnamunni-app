# Server deploy (Rocky Linux 9, systemd)

## Database user (least privilege)
Read everywhere, write only where the API mutates. Run in `mysql -u root -p`:
```sql
GRANT SELECT ON hms_saas.* TO 'sb_api_ro'@'127.0.0.1';
GRANT SELECT ON hms_saas.* TO 'sb_api_ro'@'localhost';
-- 찜 (POST /v1/wishlist/toggle)
GRANT INSERT, DELETE ON hms_saas.gangnamunni_wishlist TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_wishlist TO 'sb_api_ro'@'localhost';
-- 상담 신청 (POST /v1/consultations)
GRANT INSERT, UPDATE ON hms_saas.gangnamunni_consultations TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, UPDATE ON hms_saas.gangnamunni_consultations TO 'sb_api_ro'@'localhost';
-- 이벤트 응모 (POST /v1/events/:id/entries) + 응모 알림(회원/관리자 인앱 행; FCM 푸시는 포팅 안 됨)
GRANT INSERT ON hms_saas.gangnamunni_event_entries TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_event_entries TO 'sb_api_ro'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_user_notifications TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_user_notifications TO 'sb_api_ro'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_notifications TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_notifications TO 'sb_api_ro'@'localhost';
-- 커뮤니티: 글 좋아요/댓글/댓글 좋아요 (POST /v1/community/:id/like|comments|comments/:cid/like) + 조회수. posts 는 카운터 3개 컬럼만 UPDATE
GRANT INSERT, DELETE ON hms_saas.gangnamunni_community_likes TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_community_likes TO 'sb_api_ro'@'localhost';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_community_comment_likes TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_community_comment_likes TO 'sb_api_ro'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_community_comments TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_community_comments TO 'sb_api_ro'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_community_comment_threads TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_community_comment_threads TO 'sb_api_ro'@'localhost';
GRANT UPDATE (like_count, comment_count, view_count) ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (like_count, comment_count, view_count) ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'localhost';
-- 커뮤니티 쓰기: 글 작성/수정/삭제(소프트), 신고, 차단, 후기 좋아요 (POST/PUT/DELETE /v1/community..., /reports, /blocks, /reviews/:id/like)
GRANT INSERT ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'localhost';
GRANT UPDATE (category, title, content, photos, status) ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (category, title, content, photos, status) ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_content_reports TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_content_reports TO 'sb_api_ro'@'localhost';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_blocked_users TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_blocked_users TO 'sb_api_ro'@'localhost';
GRANT INSERT, DELETE ON hms_saas.review_likes TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE ON hms_saas.review_likes TO 'sb_api_ro'@'localhost';
GRANT UPDATE (like_count) ON hms_saas.hospital_reviews TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (like_count) ON hms_saas.hospital_reviews TO 'sb_api_ro'@'localhost';
-- 상담 다시 요청 (POST /v1/mypage/consultations/:id/rerequest): the UPDATE on consultations is covered above
GRANT INSERT ON hms_saas.gangnamunni_consultation_events TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_consultation_events TO 'sb_api_ro'@'localhost';
-- 내 예약: 결제 기한 자동 취소 안내를 확인함으로 표시 (POST /v1/mypage/bookings/:id/expire-seen)
GRANT UPDATE (expire_notice_seen_at) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (expire_notice_seen_at) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'localhost';
-- 내 알림 (read / read-all / delete)
GRANT UPDATE (is_read), DELETE ON hms_saas.gangnamunni_user_notifications TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (is_read), DELETE ON hms_saas.gangnamunni_user_notifications TO 'sb_api_ro'@'localhost';
-- 내 정보 수정 (POST /v1/mypage/profile): only these four member columns
GRANT UPDATE (mb_nick, mb_name, mb_hp, mb_birth) ON hms_saas.g5_member TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (mb_nick, mb_name, mb_hp, mb_birth) ON hms_saas.g5_member TO 'sb_api_ro'@'localhost';
-- 생년월일 변경 이력 (PHP creates it on the first birth change; create it here too so the 30-day limit works from day one)
CREATE TABLE IF NOT EXISTS hms_saas.gangnamunni_member_birth_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, mb_id VARCHAR(60) NOT NULL,
  old_birth VARCHAR(10) NOT NULL DEFAULT '', new_birth VARCHAR(10) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_mb_created (mb_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
GRANT INSERT ON hms_saas.gangnamunni_member_birth_log TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_member_birth_log TO 'sb_api_ro'@'localhost';
-- 알림 설정 (POST /v1/mypage/notification-settings): one toggle per request
GRANT INSERT (member_id), UPDATE (noti_general, noti_booking, noti_event, noti_promo, noti_push, noti_message, mkt_push, mkt_sms, mkt_email, mkt_night_push) ON hms_saas.gangnamunni_notification_settings TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT (member_id), UPDATE (noti_general, noti_booking, noti_event, noti_promo, noti_push, noti_message, mkt_push, mkt_sms, mkt_email, mkt_night_push) ON hms_saas.gangnamunni_notification_settings TO 'sb_api_ro'@'localhost';
-- 후기 작성/수정 (POST /v1/mypage/reviews)
GRANT INSERT, UPDATE (hospital_id, title, content, rating, treatments, doctor_id, doctor_name, review_status, is_active, updated_at) ON hms_saas.hospital_reviews TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, UPDATE (hospital_id, title, content, rating, treatments, doctor_id, doctor_name, review_status, is_active, updated_at) ON hms_saas.hospital_reviews TO 'sb_api_ro'@'localhost';
GRANT INSERT, DELETE ON hms_saas.review_images TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE ON hms_saas.review_images TO 'sb_api_ro'@'localhost';
GRANT UPDATE (review_count, rating) ON hms_saas.gangnamunni_hospitals TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (review_count, rating) ON hms_saas.gangnamunni_hospitals TO 'sb_api_ro'@'localhost';
-- 관리자 알림 (new review): send log + dead FCM tokens switched off (gangnamunni_user_notifications INSERT is granted above)
GRANT INSERT ON hms_saas.gangnamunni_notification_sends TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_notification_sends TO 'sb_api_ro'@'localhost';
GRANT UPDATE (is_active) ON hms_saas.gangnamunni_admin_fcm_tokens TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (is_active) ON hms_saas.gangnamunni_admin_fcm_tokens TO 'sb_api_ro'@'localhost';
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'sb_api_ro'@'127.0.0.1';
```
Every new writing endpoint must add its table here (and nothing broader).

Review photos and admin push also need files/dirs (not SQL):
- `uploads/reviews` (= `REVIEW_UPLOAD_DIR`): `chgrp sbapi` + `chmod 2775`, and add it to the `ReadWritePaths=` drop-in (`ProtectSystem=strict`).
- Firebase service account JSON (= `FCM_SERVICE_ACCOUNT_PATH`): `chgrp sbapi`, `chmod 640`. Its `project_id` must equal `FCM_EXPECTED_PROJECT_ID`.

## Service
Run once as root, from the `seoulbeauty-api/` directory:

```bash
# 1. dedicated system user (no shell, no home)
useradd --system --no-create-home --shell /sbin/nologin sbapi || true

# 2. build artefacts readable by sbapi — EXCLUDING .env (the recursive chmod must never touch it)
find . -path ./node_modules -prune -o -name .env -prune -o -print0 | xargs -0 chmod o+rX
chmod -R o+rX node_modules

# 3. .env readable by the service only — always LAST, after any recursive chmod
chown root:sbapi .env && chmod 640 .env
stat -c '%a %U:%G %n' .env        # expect: 640 root:sbapi .env

# 4. install + start
cp deploy/seoulbeauty-api.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now seoulbeauty-api
systemctl status --no-pager seoulbeauty-api
journalctl -u seoulbeauty-api -n 30 --no-pager
curl -s http://127.0.0.1:3110/health
```

After each `git pull`:
```bash
npm ci && npm run build && systemctl restart seoulbeauty-api
stat -c '%a' .env   # must still be 640
```
