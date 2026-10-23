# Server deploy (Rocky 9, Apache + systemd)

```bash
cd .../hms-saas/seoulbeauty-web
cp .env.example .env            # API_BASE, NEXT_PUBLIC_ASSET_BASE, NEXT_BASE_PATH=/next
npm ci && npm run build
chmod -R o+rX . ; chown -R root:sbapi .next && chmod -R g+rwX .next ; chown root:sbapi .env && chmod 640 .env
cp deploy/seoulbeauty-web.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now seoulbeauty-web
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3200/next/hospitals   # 200

# Apache: paste deploy/apache-next.conf.snippet into both VirtualHosts, then
apachectl configtest && systemctl reload httpd
curl -s -o /dev/null -w '%{http_code}\n' https://seoulbeauty.app/next/hospitals   # 200
```
After each `git pull`: `npm ci && npm run build && chown -R root:sbapi .next && chmod -R g+rwX .next && systemctl restart seoulbeauty-web`.
