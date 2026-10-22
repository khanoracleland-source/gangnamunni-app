# Server deploy (Rocky Linux 9, systemd)

Run once as root, from the `seoulbeauty-api/` directory:

```bash
# 1. dedicated system user (no shell, no home)
useradd --system --no-create-home --shell /sbin/nologin sbapi || true

# 2. build artefacts readable by sbapi (dir is owned by root; sbapi only needs read)
chmod -R o+rX . 2>/dev/null || true   # or: chgrp -R sbapi . && chmod -R g+rX .

# 3. .env readable by the service only (must come AFTER the recursive chmod above)
chown root:sbapi .env && chmod 640 .env

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
```
