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
-- 후기 삭제 (DELETE /v1/mypage/reviews/:id): soft delete + 포인트 회수 (earn_cancel row, lots consumed) — only when v2 policy is on
GRANT INSERT ON hms_saas.gangnamunni_points TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_points TO 'sb_api_ro'@'localhost';
GRANT UPDATE (remaining) ON hms_saas.gangnamunni_point_lots TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (remaining) ON hms_saas.gangnamunni_point_lots TO 'sb_api_ro'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_point_lot_allocs TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_point_lot_allocs TO 'sb_api_ro'@'localhost';
-- 경과 후기 (포인트제도 v2; /v1/mypage/progress*) — the feature stays off until v2_progress_enabled is on
GRANT INSERT, UPDATE (status, submitted_at, reject_reason) ON hms_saas.gangnamunni_progress_reviews TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, UPDATE (status, submitted_at, reject_reason) ON hms_saas.gangnamunni_progress_reviews TO 'sb_api_ro'@'localhost';
GRANT INSERT, DELETE, UPDATE (photo_urls, photo_consent) ON hms_saas.gangnamunni_progress_entries TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT, DELETE, UPDATE (photo_urls, photo_consent) ON hms_saas.gangnamunni_progress_entries TO 'sb_api_ro'@'localhost';
-- 약관 동의 (POST /v1/legal/agree)
GRANT INSERT ON hms_saas.gangnamunni_user_agreements TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_user_agreements TO 'sb_api_ro'@'localhost';
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'sb_api_ro'@'127.0.0.1';
```
Every new writing endpoint must add its table here (and nothing broader).

Review photos and admin push also need files/dirs (not SQL):
- `uploads/reviews` (= `REVIEW_UPLOAD_DIR`): `chgrp sbapi` + `chmod 2775`, and add it to the `ReadWritePaths=` drop-in (`ProtectSystem=strict`).
- `uploads/progress` (= `PROGRESS_UPLOAD_DIR`): same as `uploads/reviews` (`chgrp sbapi`, `chmod 2775`, `ReadWritePaths=`).
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
-- 예약하기 (POST /v1/booking, /v1/booking/precheck): the booking row, the answers to the clinic's questions, the HMS appointment id
GRANT INSERT (hospital_id, hospital_name, name, phone, mb_id, treatment, treatments_json, quantity, booking_date, booking_time, memo, price, status, created_at) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT (hospital_id, hospital_name, name, phone, mb_id, treatment, treatments_json, quantity, booking_date, booking_time, memo, price, status, created_at) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'localhost';
GRANT UPDATE (hms_appointment_id) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (hms_appointment_id) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'localhost';
GRANT INSERT (booking_id, question, answer) ON hms_saas.gangnamunni_booking_answers TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT (booking_id, question, answer) ON hms_saas.gangnamunni_booking_answers TO 'sb_api_ro'@'localhost';
-- Also add to seoulbeauty-api/.env (same values as hairwang_full/gangnamunni/includes/infyhms_api.php): HMS_API_URL=http://127.0.0.1:8000/api/external  HMS_API_KEY=<the key in infyhms_api.php>
-- (without them the booking is still saved; it is just not sent to HMS and the log says so)
-- 예약 취소 / 변경 (POST /v1/mypage/bookings/:id/cancel, /:id/change): the booking row, the HMS appointment, a pending order's point hold
GRANT UPDATE (status, cancel_reason, cancelled_at, cancelled_by, updated_at, refund_status, booking_date, booking_time, treatment, edit_count, memo) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (status, cancel_reason, cancelled_at, cancelled_by, updated_at, refund_status, booking_date, booking_time, treatment, edit_count, memo) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'localhost';
GRANT SELECT, UPDATE (is_completed, updated_at, opd_date) ON hms_saas.appointments TO 'sb_api_ro'@'127.0.0.1';
GRANT SELECT, UPDATE (is_completed, updated_at, opd_date) ON hms_saas.appointments TO 'sb_api_ro'@'localhost';
-- (only once sql/points_v2.sql exists) GRANT UPDATE (status) ON hms_saas.gangnamunni_point_payment_holds ...; GRANT UPDATE (amount, points_used) ON hms_saas.gangnamunni_platform_payments ...
-- 예약 결제 (GET /v1/mypage/bookings/:id/payment, POST /:id/payment/preview): the pending order and the "결제 없이 계속하기" record
GRANT INSERT (payment_type, funds_recipient, booking_id, hospital_id, hospital_name, patient_mb_id, patient_name, patient_phone, treatment_name, amount, currency, merchant_uid, status) ON hms_saas.gangnamunni_platform_payments TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT (payment_type, funds_recipient, booking_id, hospital_id, hospital_name, patient_mb_id, patient_name, patient_phone, treatment_name, amount, currency, merchant_uid, status) ON hms_saas.gangnamunni_platform_payments TO 'sb_api_ro'@'localhost';
GRANT INSERT (booking_id), UPDATE (booking_id) ON hms_saas.gangnamunni_booking_payment_previews TO 'sb_api_ro'@'127.0.0.1';  -- UPDATE: ON DUPLICATE KEY UPDATE needs it
GRANT INSERT (booking_id), UPDATE (booking_id) ON hms_saas.gangnamunni_booking_payment_previews TO 'sb_api_ro'@'localhost';  -- UPDATE: ON DUPLICATE KEY UPDATE needs it
-- .env (same meaning as hairwang_full/data/dbconfig.php): PG_PAYMENTS_ENABLED=0  BOOKING_PAYMENT_METHODS=  BOOKING_PAYMENT_PREVIEW_ENABLED=1  (TOSS_CLIENT_KEY/TOSS_SECRET_KEY only once the PG contract exists; the Toss checkout itself still runs in PHP)

### D1 비밀번호 찾기 (password_lost → Nest, 2026-10-30)
Nest endi o'zi parolni almashtiradi va tokenlarni bekor qiladi (PHP `password_lost.php` + `revoke_all_auth_credentials`). Column-level GRANT:
```
GRANT UPDATE (mb_password, mb_lost_certify, mb_cred_epoch) ON hms_saas.g5_member TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (mb_password, mb_lost_certify, mb_cred_epoch) ON hms_saas.g5_member TO 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.sso_tokens TO 'sb_api_ro'@'127.0.0.1';
GRANT DELETE ON hms_saas.sso_tokens TO 'sb_api_ro'@'localhost';
GRANT UPDATE (password, updated_at) ON hms_saas.users TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (password, updated_at) ON hms_saas.users TO 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.personal_access_tokens TO 'sb_api_ro'@'127.0.0.1';
GRANT DELETE ON hms_saas.personal_access_tokens TO 'sb_api_ro'@'localhost';
FLUSH PRIVILEGES;
```
`.env`: `SMTP_PASSWORD=<admin@seoulbeauty.app parol>` (kerak; yo'q bo'lsa email ketmaydi va parol O'ZGARMAYDI). Ixtiyoriy: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_FROM_NAME`. Yangi npm paketlar (nodemailer, bcryptjs): deploy'da `npm ci` kerak.

### D2 회원가입 (register → Nest, 2026-10-30)
Nest endi a'zoni o'zi yaratadi (`POST /v1/auth/register/code`, `POST /v1/auth/register`); Gnuboard sessiyasini PHP ko'prigi `gangnamunni/api/session_login.php` yaratadi (faqat loopback + HMAC).

1. Column-level GRANT (ikkala host uchun, `sb_api_ro`):
```sql
GRANT INSERT (mb_id, mb_password, mb_name, mb_nick, mb_nick_date, mb_email, mb_level, mb_datetime, mb_today_login, mb_ip, mb_login_ip, mb_email_certify, mb_open_date, mb_1, mb_recommend, mb_mailling, mb_sms, mb_hp, mb_birth, mb_2) ON hms_saas.g5_member TO 'sb_api_ro'@'localhost';
GRANT INSERT (mb_id, mb_password, mb_name, mb_nick, mb_nick_date, mb_email, mb_level, mb_datetime, mb_today_login, mb_ip, mb_login_ip, mb_email_certify, mb_open_date, mb_1, mb_recommend, mb_mailling, mb_sms, mb_hp, mb_birth, mb_2) ON hms_saas.g5_member TO 'sb_api_ro'@'127.0.0.1';
FLUSH PRIVILEGES;
```
2. Maxfiy kalit (kamida 32 belgi) — bir xil qiymat ikkala `.env` ga: `seoulbeauty-api/.env` va `hairwang_full/gangnamunni/.env`:
```
SESSION_BRIDGE_SECRET=<openssl rand -hex 32>
```
3. `seoulbeauty-api/.env`: `PHP_SESSION_URL` loopback bo'lishi shart (masalan `http://127.0.0.1/api/session_me.php`, `PHP_SESSION_HOST=hms.seoulbeauty.app`) — ko'prik shu URLdan `session_login.php` ni oladi; PHP tomoni loopbackdan boshqa chaqiruvni rad etadi.
4. OTP kodlari Nest xotirasida saqlanadi (restartda yo'qoladi — foydalanuvchi qayta yuboradi).

### D3 email login (login → Nest, 2026-10-30)
`POST /v1/auth/login` (+ `GET /v1/auth/login-options`). Yangi GRANT kerak emas (faqat SELECT). Sessiya D2 dagi `session_login.php` ko'prigi orqali (`mode=login`); `SESSION_BRIDGE_SECRET` va loopback `PHP_SESSION_URL` shart. Hisoblagichlar Nest xotirasida (restartda nol; `app_login.php` fayl hisoblagichlari alohida). PBKDF2 bo'lmagan hash'li akkauntlar (91 bcrypt + 1 md5) PHP loginda ham kira olmaydi: Nest ularga oddiy 401 qaytaradi.
