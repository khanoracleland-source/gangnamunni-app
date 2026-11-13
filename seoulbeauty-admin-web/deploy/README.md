# Server deploy (Rocky 9, Apache + systemd)

Needs `seoulbeauty-admin-api` running on 127.0.0.1:3300 (see its `deploy/README.md`).

```bash
cd .../hms-saas/seoulbeauty-admin-web
cp .env.example .env            # API_BASE=http://127.0.0.1:3300, NEXT_BASE_PATH=/admin-next, NEXT_PUBLIC_ADMIN_PHP_LOGIN_URL=/hairwang/gangnamunni/dashboard.php
npm ci && npm run build
chmod -R o+rX . ; chown -R root:sbapi .next && chmod -R g+rwX .next ; chown root:sbapi .env && chmod 640 .env
cp deploy/seoulbeauty-admin-web.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now seoulbeauty-admin-web
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3400/admin-next   # 200

# Apache: paste deploy/apache-admin-next.conf.snippet into the VirtualHosts, then
apachectl configtest && systemctl reload httpd
curl -s -o /dev/null -w '%{http_code}\n' https://seoulbeauty.app/admin-next   # 200
```
After each `git pull`: `npm ci && npm run build && chown -R root:sbapi .next && chmod -R g+rwX .next && systemctl restart seoulbeauty-admin-web`.

The page itself is public HTML with no data in it; every number comes from `/admin-next/api/v1/*`,
which answers only to a logged-in super admin (and `404` while the admin API has `ADMIN_PANEL_ENABLED=0`).
