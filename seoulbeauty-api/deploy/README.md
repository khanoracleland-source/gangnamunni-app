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
-- 알림 설정 (POST /v1/mypage/notification-settings): one toggle per request. RUN `hairwang_full/gangnamunni/sql/notification_visit_reminder_20261106.sql` BEFORE deploying the API (the SELECT names noti_visit / noti_community; the same file adds gangnamunni_bookings.visit_reminder_sent_at).
-- 방문 전 알림 (설정 > noti_visit): the API itself reminds confirmed bookings the day before (from 10:00 KST, every VISIT_REMINDER_INTERVAL_MIN=15 min; VISIT_REMINDER_ENABLED=0 on any second instance). No PHP cron. By hand: `npm run visit-reminder -- --dry-run`.
GRANT UPDATE (visit_reminder_sent_at) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (visit_reminder_sent_at) ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'localhost';
GRANT INSERT (member_id), UPDATE (noti_booking, noti_visit, noti_community, noti_review, mkt_push, mkt_sms, mkt_email, mkt_night_push) ON hms_saas.gangnamunni_notification_settings TO 'sb_api_ro'@'127.0.0.1';
GRANT INSERT (member_id), UPDATE (noti_booking, noti_visit, noti_community, noti_review, mkt_push, mkt_sms, mkt_email, mkt_night_push) ON hms_saas.gangnamunni_notification_settings TO 'sb_api_ro'@'localhost';
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
-- 예약 · 결제 알림 to the member (예약 접수 / 결제 완료 / 본인 취소 / 변경 요청 from the API; the other events stay in PHP): dead member device tokens switched off
-- (user_notifications + notification_sends INSERT are granted above). Links use PHP_PUBLIC_SITE_URL (.env; default https://seoulbeauty.app/).
GRANT UPDATE (pt_use) ON hms_saas.g5_push_tokens TO 'sb_api_ro'@'127.0.0.1';
GRANT UPDATE (pt_use) ON hms_saas.g5_push_tokens TO 'sb_api_ro'@'localhost';
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
-- .env (same meaning as hairwang_full/data/dbconfig.php): PG_PAYMENTS_ENABLED=0  BOOKING_PAYMENT_METHODS=  BOOKING_PAYMENT_PREVIEW_ENABLED=1  (no PG is connected yet)

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
`.env`: `SMTP_PASSWORD=<admin@seoulbeauty.app parol>` (kerak; yo'q bo'lsa email ketmaydi va parol O'ZGARMAYDI). Ixtiyoriy: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_FROM_NAME`, `PUBLIC_SITE_URL` (xatdagi "비밀번호 재설정하기" havolasi uchun veb-ilova manzili, `/next` bilan; default `https://hms.seoulbeauty.app/next`). Yangi npm paketlar (nodemailer, bcryptjs): deploy'da `npm ci` kerak.

**Test bayrog'i `PASSWORD_LOST_RATE_LIMIT=off` (XAVFLI, faqat sinov):** 비밀번호 찾기 xat limitlari va vaqtinchalik parol tekshiruvi (`verify`) limitlarini o'chiradi. Bayroq faqat `NODE_ENV=development` yoki `test` bo'lgandagina ishlaydi; `production`, boshqa qiymat yoki `NODE_ENV` umuman yo'q bo'lsa (pm2, qo'lda `node dist/main.js`) **e'tiborsiz qoldiriladi**. Ishga tushishda log'da bir marta `ACTIVE` yoki `IGNORED` yoziladi. Sababi: bayroqsiz bu endpoint istalgan emailga cheksiz xat yuborib parolini almashtira oladi. Sinovdan keyin `.env` dan olib tashlang.

**Jonli serverda sinash: `PASSWORD_LOST_TEST_EMAILS` + `PASSWORD_LOST_TEST_IPS` + `PASSWORD_LOST_TEST_UNTIL`:** 비밀번호 찾기 va `verify` limitlari faqat uchala shart birga bajarilsa o'tkazib yuboriladi: so'rov ro'yxatdagi IP'dan (sizning ochiq IP'ingiz), ro'yxatdagi email bilan (ko'pi bilan 3 tadan) va `PASSWORD_LOST_TEST_UNTIL` (ISO vaqt, hozirdan ko'pi bilan 24 soat keyin; undan uzoq yoki bo'sh bo'lsa qoida ishlamaydi) muddati tugamagan bo'lsa. Emailni bilgan begona odam, boshqa IP'dan, limitni aylana olmaydi. Har bir o'tkazib yuborish audit log'ga `reset_limits_skipped` bo'lib yoziladi, ishga tushishda ogohlantirish chiqadi. Sinovdan keyin uchala qatorni olib tashlang.

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

## D4a — Social login (Kakao / Google) on Nest

Existing linked members sign in through Nest; an unlinked social profile (a new member) is still sent to the PHP flow
(`/auth/login.php?provider=…`). The Gnuboard session is minted by PHP through the session bridge (`social` = provider name).

1. Grants (both `localhost` and `127.0.0.1`):
   ```sql
   GRANT INSERT, UPDATE, DELETE ON hms_saas.g5_member_social_profiles TO 'sb_api_ro'@'localhost', 'sb_api_ro'@'127.0.0.1';
   GRANT UPDATE (mb_name, mb_hp, mb_birth, mb_2) ON hms_saas.g5_member TO 'sb_api_ro'@'localhost', 'sb_api_ro'@'127.0.0.1';
   ```
   (the column grants for g5_member may already exist from D2.)
2. Provider consoles — ADD (do not remove the old `/plugin/social/?hauth.done=…` URIs):
   - Kakao Developers → Kakao Login → Redirect URI: `https://hms.seoulbeauty.app/next/api/v1/auth/social/callback` (no "kakao" in it: the Kakao console rejects such a URI)
   - Google Cloud → OAuth client → Authorized redirect URI: `https://hms.seoulbeauty.app/next/api/v1/auth/social/google/callback`
3. `.env`: `SOCIAL_NEST_PROVIDERS=kakao,google` turns it on per provider (empty = everything stays on PHP, instant rollback).
   Optional: `SOCIAL_CALLBACK_BASE` (default `https://hms.seoulbeauty.app/next/api/v1/auth/social`), `SOCIAL_PHP_BASE` (default `https://hms.seoulbeauty.app`).
4. `systemctl restart seoulbeauty-api` (the service is a systemd unit, which also sets `NODE_ENV=production`; do not start it with pm2 / bare `node`), rebuild web. `GET /next/api/v1/auth/login-options` then lists `nest: ["kakao","google"]`.

## D4b — Social sign-up (new member from a Kakao / Google profile) on Nest

Needs D4a. With `SOCIAL_NEST_REGISTER=1` a social profile that is not linked to a member yet is registered by Nest; with the
variable empty the PHP flow (`/auth/login.php?provider=…` → `plugin/social/register_member*.php`) does it exactly as before (rollback switch).

1. Grants: nothing new beyond D2 (INSERT on the g5_member columns) and D4a (`g5_member_social_profiles` INSERT/UPDATE/DELETE).
2. Pull, `cd seoulbeauty-api && npm run build`, `cd ../seoulbeauty-web && npm ci && npm run build`, add `SOCIAL_NEST_REGISTER=1` to
   `seoulbeauty-api/.env`, `systemctl restart seoulbeauty-api seoulbeauty-web`. The PHP file `gangnamunni/api/session_login.php` changed
   too (register mode for `<provider>_<hash>` ids with a `social` field): it is part of the same pull.
3. Behaviour (same rules as `plugin/social/register_member.php` + `register_member_update.php`):
   - Kakao: no form, the member is created at the first successful sign-in. A Kakao-VERIFIED e-mail that already belongs to a member
     (not blocked, no Kakao link yet) links Kakao to that member instead and signs in.
   - Google: `/next/register/social` (terms + name + e-mail), the profile travels in the signed `sb_social_pending` cookie (15 min).
   - mb_id = `<provider>_<adler32(md5(identifier))>` (+1, +2 … if taken), nick from the display name, `mb_email_certify = now`,
     level `cf_register_level`, random password, a profile row in `g5_member_social_profiles`; the PHP bridge (register mode) adds the
     sign-up bonus, the HMS sync and the session. Admin notice mail only when `cf_email_mb_super_admin` is on.
   - Refusals: `social_error=exists` (the provider id was linked/registered before), `email_exists`, `failed`.
   - NOT ported: the member icon download from the provider photo, the shop sign-up coupon (`de_member_reg_coupon_*`).
4. Rollback: empty `SOCIAL_NEST_REGISTER`, restart `seoulbeauty-api`.

## D4c — Apple sign-in on Nest (2026-11-12)

Needs D4a (and D4b for new members). Keys come from `g5_config` (`cf_apple_bundle_id` = the Services ID, `cf_apple_team_id`,
`cf_apple_key_id`, `cf_apple_key_file` = the .p8 text): no new `.env` secret, no new grant, no PHP change
(`api/session_login.php` already accepts `social=Apple`).

1. Apple Developer → Identifiers → Services IDs → `com.celebhospital.patient.public.web` → Sign In with Apple → Configure →
   Return URLs: ADD `https://hms.seoulbeauty.app/next/api/v1/auth/social/apple/callback` (keep the old
   `/hairwang/plugin/social/…` URL: it is the rollback). Done 2026-11-12.
2. Pull, `cd seoulbeauty-api && npm run build`, add `apple` to `SOCIAL_NEST_PROVIDERS` (`kakao,google,apple`),
   `systemctl restart seoulbeauty-api`. `GET /next/api/v1/auth/login-options` then lists `apple` in `nest` and the Next login
   button goes to `/next/api/v1/auth/social/apple/start` (no web rebuild needed).
3. Flow: start (state + nonce in the signed `sb_social_state` cookie) → Apple → cross-site `POST …/apple/callback`
   (`response_mode=form_post`; it carries none of the site's Lax cookies, so it only stores code/state/name in the signed 2-minute
   `sb_apple_cb` cookie and answers 303) → `GET …/apple/callback` (state check, token exchange with an ES256 client secret, id_token
   verified against `appleid.apple.com/auth/keys`: RS256, iss, aud, exp, nonce) → linked member: session through the bridge;
   new profile: member created at once like Kakao (name = the name Apple posts on the first sign-in, else the member id;
   e-mail = Apple's, else the PHP placeholder `kakao_apple_<hash>@social.local`). No linking by e-mail (PHP does that for Kakao only).
4. Check after the restart: `journalctl -u seoulbeauty-api | grep -E "\[apple\]|provider=apple"` — a sign-in logs
   `[auth][social_login_success] provider=apple`; `id_token rejected: <reason>` / `client secret failed` name the cause of a failure.
5. Rollback: remove `apple` from `SOCIAL_NEST_PROVIDERS`, restart `seoulbeauty-api` (the button goes back to the PHP flow).
6. NOT done: revoking the Apple token when a member withdraws (PHP does not do it either); the Flutter `NextPilot` rewrite of a
   PHP `auth/login.php?provider=apple` link (the Next login page links to Nest directly, so the app does not need it).

## 결제 기한 자동 취소 (payment deadline, 2026-11-12)

The job of PHP `cron_booking_payment_expire.php` (which was never in the server's crontab): a booking the hospital confirmed is
cancelled by the system when it is still unpaid `payment_deadline_hours` (admin setting, default 24) after the confirmation. The member
gets "결제 기한이 지나 예약이 취소되었어요" (설정 > 예약 · 결제 알림) and the 내 예약 screen shows the expiry notice.

Narrower than the PHP cron on purpose: only a booking with a `pending` payment order (amount > 0) whose member can pay right now
(`PG_PAYMENTS_ENABLED=1`, Inicis configured, member in `INICIS_LIVE_MEMBERS` / `*`) is cancelled. No order, "결제 없이 계속하기", a paid
order, a booking without a member, or checkout closed for that member = left alone (listed as `not_payable`).

1. Needs `gangnamunni_bookings.confirmed_at` (`hairwang_full/gangnamunni/sql/booking_payment_deadline_20261022.sql`; it starts the clock
   of already-confirmed bookings at the moment it is run, so nothing is cancelled at once). No new grant (the booking UPDATE columns,
   `appointments` and the payment tables are already granted for the member's own cancel).
2. Pull, `npm run build`. ALWAYS look first: `sudo -u sbapi npm run payment-expire -- --dry-run` (changes nothing) prints the bookings
   it would cancel and those it leaves alone.
3. `.env`: `PAYMENT_EXPIRE_ENABLED=1` (`PAYMENT_EXPIRE_INTERVAL_MIN=10`), `systemctl restart seoulbeauty-api`; the log shows
   `결제 기한 자동 취소: every 10 min` and one `cancelled booking #N` line per cancellation.
4. Rollback: empty `PAYMENT_EXPIRE_ENABLED`, restart. A cancelled booking is not restored by that.

## Push token cleanup (replaces PHP `cron_push_token_cleanup.php`, 2026-11-12)

Same four idempotent steps as the PHP cron (iPhone token saved as android → ios; active token without a member older than 14 days → off;
several active tokens of one member on one device → only the newest stays on; off for 90 days → deleted).

1. Grants (the API user could only switch `pt_use`):
   ```sql
   GRANT UPDATE (pt_use, pt_platform), DELETE ON hms_saas.g5_push_tokens TO 'sb_api_ro'@'127.0.0.1';
   GRANT UPDATE (pt_use, pt_platform), DELETE ON hms_saas.g5_push_tokens TO 'sb_api_ro'@'localhost';
   ```
2. Pull, `npm run build`. Compare first: `sudo -u sbapi npm run push-token-cleanup -- --dry-run` (changes nothing) against what the
   PHP cron log reports; then one real run by hand: `sudo -u sbapi npm run push-token-cleanup`.
3. `.env`: `PUSH_TOKEN_CLEANUP_ENABLED=1` (`PUSH_TOKEN_CLEANUP_HOUR=0` = 00:xx KST), `systemctl restart seoulbeauty-api`; the log shows
   `push token cleanup: once a day in KST hour 0`. Then remove the PHP line from the crontab (`crontab -l | grep push_token_cleanup`).
   Running both for a few days is harmless.
4. Rollback: empty `PUSH_TOKEN_CLEANUP_ENABLED`, restart, put the crontab line back.

### Inicis card payment (INIPayPro, Nest, 2026-11-04)
Flow: Next `결제하기` -> `POST /v1/mypage/bookings/:id/payment/inicis/prepare` (signed SDK parameters, nothing written) -> `INIPayPro.requestPayment`
-> Inicis makes the customer's browser POST the authentication result to `POST /v1/payments/inicis/return` (public: cross-site, no session)
-> Nest checks it against the ledger, approves with Inicis (`payAppl.ini`), and moves the order to `paid` exactly like PHP `booking_payment_confirm()`
(same `payment:<sha256>` GET_LOCK, `gangnamunni_payment_events`, `gangnamunni_payment_status_history`, `gangnamunni_payment_attempts`) -> 303 to `/next/mypage/bookings/:id/payment/success`.
If the ledger write fails after Inicis approved, or the approval outcome is unknown, Nest calls `payNetCancel.ini` (망취소) and logs `[inicis][FINALIZE_FAILED]` / `[inicis][NET_CANCEL_FAILED]`.
Checkout stays OFF (the page shows "온라인 결제 준비 중" and `결제 없이 계속하기`) until ALL of these are set: `PG_PAYMENTS_ENABLED=1`, `INICIS_MID`, `INICIS_HASHKEY`, `DB_PAY_USER`, `DB_PAY_PASSWORD`.

1. A dedicated MariaDB account. `sb_api_ro` deliberately cannot mark an order paid; this one can, and only that:
```
CREATE USER 'sb_pay'@'127.0.0.1' IDENTIFIED BY '<long random>';   -- and the same for 'localhost'
GRANT SELECT ON hms_saas.gangnamunni_platform_payments TO 'sb_pay'@'127.0.0.1';
GRANT UPDATE (provider, provider_transaction_id, verified_amount, verified_at, verification_source, status, paid_at) ON hms_saas.gangnamunni_platform_payments TO 'sb_pay'@'127.0.0.1';
GRANT SELECT (id, status) ON hms_saas.gangnamunni_bookings TO 'sb_pay'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_payment_events TO 'sb_pay'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_payment_status_history TO 'sb_pay'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE (outcome, reason) ON hms_saas.gangnamunni_payment_attempts TO 'sb_pay'@'127.0.0.1';
```
   Check right after granting: as `sb_pay`, `SELECT id FROM gangnamunni_platform_payments WHERE id = 0 FOR UPDATE;` must work (a locking read needs UPDATE on some column).
2. `.env` (see `.env.example`): `INICIS_MID`, `INICIS_HASHKEY` (모바일 금액위변조 Hash Key), `INICIS_MODE=test|live`, `INICIS_PUBLIC_BASE`, `INICIS_APP_SCHEME`, `DB_PAY_USER`, `DB_PAY_PASSWORD`, `PG_PAYMENTS_ENABLED=1`.
   Start with the test MID `INIpayTest` (mode `test`: rows are `provider=inicis_test`; PHP settlement cron and admin revenue pages exclude them via `booking_payment_test_providers()` (`inicis_test` is listed there — deploy that PHP change BEFORE enabling test mode on production); Inicis cancels them at midnight; KB / KakaoBank cards cannot be tested).
   `INICIS_MODE=live` refuses a test MID, and `test` refuses a real MID.
   Test mode charges real cards, so checkout is limited to `INICIS_TEST_MEMBERS` (comma separated mb_id): everyone else keeps seeing "온라인 결제 준비 중" + `결제 없이 계속하기`. Live mode uses its own list `INICIS_LIVE_MEMBERS` (empty = nobody, `*` = everyone).
3. Network: outbound 443 to `stgpaypro.inicis.com` (183.109.71.64), `fcpaypro.inicis.com` (118.129.210.23), `kspaypro.inicis.com` (183.109.71.35), TLS 1.2+. Register the site domain/IP in the Inicis merchant admin.
4. `systemctl restart seoulbeauty-api`, rebuild + restart `seoulbeauty-web` (`npm ci && npm run build && chown -R root:sbapi .next && chmod -R g+rwX .next && systemctl restart seoulbeauty-web`).
5. Hardening (2026-11-05): `[inicis][ALERT][...]` log lines (+ optional `INICIS_ALERT_WEBHOOK`), `INICIS_TIMEOUT_MS`, `DB_PAY_POOL` default 8, a per-IP limit on `/return`
   (`INICIS_RETURN_RATE_PER_MIN`), at most 30 card authentications per order per hour (`TOO_MANY_ATTEMPTS`; one auth TID = one row, the window slides), no evidence row for a post without an auth TID. Find orders that need a person:
   ```sql
   -- card may be charged but the ledger says pending / approval outcome unknown (look each TID up in the Inicis admin)
   SELECT a.id, a.created_at, a.merchant_uid, a.pg_payment_key AS auth_tid, a.amount, a.reason, p.status AS order_status
     FROM gangnamunni_payment_attempts a JOIN gangnamunni_platform_payments p ON p.id = a.payment_id
    WHERE a.outcome = 'pending' AND p.status = 'pending' ORDER BY a.id DESC;
   ```
   `reason=FINALIZE_FAILED_UNCANCELLED` = card charged and NOT cancelled: refund in the Inicis admin by hand. `UNRESOLVED` = the approval answer never arrived and a net cancel was sent:
   confirm in the Inicis admin that nothing was charged (if it was, either book it by hand or refund it).
   `/return` is limited per IP (`req.ip`): `TRUST_PROXY` must match the proxy hops on the server (the example says 2), or every customer shares the proxy's IP and its limit. Korean mobile carriers share one IP among many users (CGNAT); raise `INICIS_RETURN_RATE_PER_MIN` if real customers hit it.
6. Not ported yet: cancellation / refund through Inicis (INIAPI), virtual account (P_NOTI_URL), points used at checkout (an order with `points_used > 0` is refused with `POINTS_NOT_SUPPORTED`), reconcile of orders that stay `pending` after an approval.
7. Rollback: set `PG_PAYMENTS_ENABLED=0` and restart `seoulbeauty-api`: checkout is hidden at once, the preview flow remains.


## Account security in Nest (47/48-kun, 2026-11-12)

Parol o'zgartirish, email o'zgartirish va 회원 탈퇴 Nest'ning o'zida bajariladi (2026-11-12 jonlida tasdiqlangan: parol, email, 탈퇴 parolli/Kakao/Apple). PHP `api/account_security.php`, `api/withdraw.php` o'chirilgan, `notification_settings.php` dagi `withdraw_social` 410 qaytaradi; `ACCOUNT_NEST_ACTIONS` bayrog'i olib tashlangan (`.env` da qolsa o'qilmaydi). Yangi serverda quyidagilar kerak:

- `password` — yangi GRANT kerak emas (password-lost GRANT'lari yetarli).
- `email` — tasdiqlash kodi Nest xotirasida (3 daqiqa, 5 urinish), xat `SMTP_*` orqali:
```sql
GRANT UPDATE (mb_email) ON hms_saas.g5_member TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
```
- `withdraw` — `.env` da `KAKAO_ADMIN_KEY` (Kakao a'zolari uchun shart; bo'lmasa 502 qaytadi, hech narsa o'chmaydi) va:
```sql
GRANT DELETE ON hms_saas.g5_member TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.member_sync TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_admins TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT INSERT, DELETE ON hms_saas.gangnamunni_withdrawals TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT UPDATE (status) ON hms_saas.users TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
-- social a'zoning xizmat ma'lumotlari (PHP withdraw_social bilan bir xil ro'yxat; yo'q jadval uchun GRANT xato beradi — o'sha qatorni tashlab keting)
GRANT DELETE ON hms_saas.gangnamunni_points TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_user_actions TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_coupon_claims TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_chat_rooms TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_content_reports TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_members TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_notification_settings TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_user_agreements TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_bookings TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.hospital_reviews TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_community_comments TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.gangnamunni_community_posts TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.g5_point TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.g5_scrap TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
GRANT DELETE ON hms_saas.g5_memo TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
```
(`gangnamunni_wishlist`, `gangnamunni_user_notifications`, `gangnamunni_community_likes`, `sso_tokens`, `g5_member_social_profiles` uchun DELETE avval berilgan.)

PHP'dan farqlar (ataylab): kod xati ketmasa foydalanuvchiga aytiladi (PHP jim o'tardi); sayt egasi (`cf_admin`) 탈퇴 qila olmaydi; Kakao "-101 allaqachon uzilgan" javobi muvaffaqiyat hisoblanadi (yarim qolgan urinishni takrorlash mumkin bo'lsin).

## Nest sessions (51-kun, 2026-11-12) — `NEST_SESSIONS`

B bosqichning 1-qismi: Nest o'z sessiyasini yuritadi (`sb_sessions` jadvali, `sb_sid` cookie). Yoqilganda kirgan foydalanuvchining so'rovlari PHP `session_me.php` ga bormaydi. Login hali PHP ko'prigi (`session_login.php`) orqali ham o'tadi — ilova native tokenlari PHP sessiyasiga muhtoj — va Nest sessiyasi shu ko'prikdan keyin ochiladi. `sb_sid` yo'q yoki yaroqsiz bo'lsa (eski sessiyalar, ilova cold-start) avvalgidek PHP so'raladi.

1. Jadval (root):
```
mysql -u root -p hms_saas < /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/sql/sb_sessions_20261112.sql
```
2. GRANT:
```sql
GRANT INSERT, DELETE, UPDATE (last_seen_at, expires_at) ON hms_saas.sb_sessions TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
```
3. `.env` → `NEST_SESSIONS=1`, `systemctl restart seoulbeauty-api`. Qaytarish: qiymatni bo'shatib restart (mavjud `sb_sid` lar e'tiborsiz qoladi, hamma PHP sessiyasi bilan davom etadi).

Sessiya tugaydi: muddat (remember: veb 31 kun / ilova 120 kun; aks holda 3 soat harakatsizlik), `mb_cred_epoch` o'zgarsa (parol, 탈퇴), blok/chiqish sanasi, `POST /v1/auth/logout`, PHP logout yoki PHP orqali boshqa a'zo bo'lib kirish (`hairwang_full/extend/sb_nest_session.extend.php` qatorni o'chiradi).
Kuzatish: `SELECT source, remember, COUNT(*) FROM sb_sessions WHERE expires_at > NOW() GROUP BY source, remember;`

### B2 — `NEST_SESSION_ADOPT` (51-kun davomi)
`NEST_SESSIONS=1` ustiga `NEST_SESSION_ADOPT=1`: PHP "kirgan" degan, lekin `sb_sid` yo'q brauzerga (ilova yopib-ochilgandan keyin, B1 dan oldingi sessiyalar) `GET /v1/me` da Nest sessiyasi beriladi → keyingi so'rovlarda PHP so'ralmaydi. Faqat brauzerning same-origin so'rovida (`Sec-Fetch-Site`), hech qachon "remember" emas (3 soat harakatsizlik), logout'dan keyin 60 s berilmaydi (`sb_noadopt`). Yangi GRANT/jadval kerak emas. Log: `[auth][session_adopted] account=<hash>`. Qaytarish: qiymatni bo'shatib restart.

## App native auth in Nest (D1, 54/55-kun, 2026-11-12) — `APP_AUTH_NEST`

Ilova tokenlari uchun Nest endpointlari: `POST /v1/app-auth/exchange|verify|refresh|logout|bootstrap`, `GET /v1/app-auth/land?url=`. Shartnoma va `sso_tokens` formati PHP (`api/app_auth_*.php`, `api/app_logout.php`, `app_session_land.php`) bilan bir xil — PHP chiqargan token Nest'da, Nest chiqargani PHP'da ishlaydi; shifrlangan exchange payload ikki tomonda ham o'qiladi (tekshirilgan). Farq: `bootstrap` va `land` PHP sessiyasi o'rniga Nest sessiyasi (`sb_sid`) bilan ishlaydi. Do'kondagi ilova (2.1.0) bu endpointlarni chaqirmaydi — yoqish unga ta'sir qilmaydi.

**Oqibat (yangi ilova, `land` Nest'da):** WebView'da faqat Nest sessiyasi (`sb_sid`) ochiladi, PHP sessiyasi emas. Shu sababli hali qolgan PHP bemor sahifalari (`booking_payment_request.php`, `chat.php`, `my_events.php` …) yangi ilovada a'zoni mehmon deb ko'radi. Next ularning hech biriga havola qilmaydi (51-kunda tekshirilgan), lekin yangi ilovani chiqarishdan oldin bunday sahifaga olib boradigan yo'l qolmaganini (push havolalari, eski chuqur havolalar) tekshirish kerak; qolganlari 50-kundagi 2-o'tishda o'chiriladi.
Xato logi: kutilmagan xatoda faqat xato sinfi va drayver kodi yoziladi (`exchange failed: Error ER_DUP_ENTRY`), xabar matni emas — MySQL xabari xom tokenni keltirishi mumkin.

1. GRANT:
```sql
GRANT INSERT, UPDATE (user_agent, payload, expires_at) ON hms_saas.sso_tokens TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
```
(DELETE avval berilgan.)
2. Kalit — PHP ishlatadigan qiymatning o'zi. Qayerdaligini topish: `grep -rn "APP_NATIVE_AUTH_KEY" /etc/httpd /etc/apache2 /etc/php* /etc/sysconfig 2>/dev/null | cut -c1-80` (qiymatni chatga yubormang). `.env` ga `APP_NATIVE_AUTH_KEY=<o'sha qiymat>`.
3. `.env` → `APP_AUTH_NEST=1`, restart. Qaytarish: bo'shatib restart.
Tekshiruv (tokensiz): `curl -s -X POST https://seoulbeauty.app/next/api/v1/app-auth/exchange -H 'Content-Type: application/json' -d '{"code":"x"}'` → `{"success":false,"error":"code and device_id are required"}`.

## Social login on the public host — `SOCIAL_CALLBACK_HOSTS` (2026-11-12)

Muammo (jonlida tasdiqlangan 16:54): `seoulbeauty.app` da (oddiy brauzer) Google/Kakao/Apple login "로그인 시간이 만료되었습니다" bilan qaytadi. Sabab: state cookie host-only, qaytish manzili esa doim `hms.seoulbeauty.app` (`SOCIAL_CALLBACK_BASE`). Ilovada (`hms`) ishlaydi.
Yechim: `SOCIAL_CALLBACK_HOSTS` dagi xost o'zining qaytish xosti bo'ladi (login qaysi xostda boshlansa, o'sha xostga qaytadi). Ro'yxatda yo'q xost — avvalgidek `SOCIAL_CALLBACK_BASE`.
**Tartib muhim — avval konsollar, keyin `.env`** (aks holda provayder `redirect_uri_mismatch` beradi):
1. Kakao Developers → 카카오 로그인 → Redirect URI: `https://seoulbeauty.app/next/api/v1/auth/social/callback` (qo'shish; eskisi qoladi).
2. Google Cloud → OAuth client → Authorized redirect URIs: `https://seoulbeauty.app/next/api/v1/auth/social/google/callback`.
3. Apple Developer → Services ID `com.celebhospital.patient.public.web` → Domains: `seoulbeauty.app`; Return URLs: `https://seoulbeauty.app/next/api/v1/auth/social/apple/callback`.
4. `.env`: `SOCIAL_CALLBACK_HOSTS=seoulbeauty.app` (ilova `seoulbeauty.app` ga ko'chgach `hms.seoulbeauty.app` ni qo'shish shart emas — u `SOCIAL_CALLBACK_BASE` orqali ishlayveradi), restart.
Qaytarish: qiymatni bo'shatib restart.
