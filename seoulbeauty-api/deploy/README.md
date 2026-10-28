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
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'sb_api_ro'@'127.0.0.1';
```
Every new writing endpoint must add its table here (and nothing broader).

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
