#!/usr/bin/env python3
"""Release smoke check using invalid random credentials; requires Python 3 + curl.
Usage: python3 scripts/auth/check_native_auth.py https://hms.seoulbeauty.app
"""
import json
import secrets
import subprocess
import sys
import urllib.parse

base = sys.argv[1] if len(sys.argv) > 1 else 'https://hms.seoulbeauty.app'
parsed = urllib.parse.urlparse(base)
if parsed.scheme != 'https' or not parsed.netloc or parsed.username or parsed.password:
    raise SystemExit('Use an HTTPS origin without credentials.')
base = f'{parsed.scheme}://{parsed.netloc}/hairwang/gangnamunni/api/'
device, invalid = secrets.token_hex(32), secrets.token_hex(32)
cases = [
    ('app_auth_exchange.php', {'code': invalid, 'device_id': device}, 'exchange_invalid'),
    ('app_auth_verify.php', {'access_token': invalid, 'device_id': device}, 'access_token_invalid'),
    ('app_auth_refresh.php', {'mb_id': 'auth_probe_invalid', 'refresh_token': invalid, 'device_id': device}, 'refresh_invalid'),
]
failed = False
for endpoint, body, expected in cases:
    try:
        # curl uses the host's normal TLS trust store; certificate verification
        # remains enabled. Input is passed on stdin, never shell-interpolated.
        response = subprocess.run([
            'curl', '--silent', '--show-error', '--max-time', '15',
            '--request', 'POST', '--header', 'Content-Type: application/json',
            '--data-binary', '@-', '--write-out', '\n%{http_code}', base + endpoint,
        ], input=json.dumps(body), capture_output=True, text=True, timeout=20, check=True)
        raw, status = response.stdout.rsplit('\n', 1)
        try:
            data = json.loads(raw)
        except ValueError:
            data = {}
        passed = status == '401' and isinstance(data, dict) and data.get('success') is False and data.get('error') == expected
        print(f'{"PASS" if passed else "FAIL"}: {endpoint}: HTTP {status}; expected 401 {expected}')
        failed |= not passed
    except Exception as error:
        print(f'FAIL: {endpoint}: {type(error).__name__}')
        failed = True
if failed:
    print('RELEASE BLOCKED: inspect PHP worker key, native migrations, DB connectivity and logs. No credentials were printed.')
else:
    print('API smoke checks passed. Real-device login, force-stop, upgrade and logout acceptance is still required.')
sys.exit(1 if failed else 0)
