# 500 에러 디버깅 가이드

## 🚨 500 Internal Server Error 발생

### 즉시 확인할 사항

#### 1. Laravel 로그 확인 (가장 중요!)
```bash
ssh your_user@your_server
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# 최근 에러 로그 확인
tail -50 storage/logs/laravel.log
```

#### 2. 웹 서버 에러 로그 확인
```bash
# Apache 에러 로그
tail -50 /var/log/apache2/error.log

# 또는 Nginx 에러 로그
tail -50 /var/log/nginx/error.log
```

---

## 🔍 가능한 원인

### 원인 1: Blade 문법 오류
master.blade.php에서 `{{ time() }}` 부분이 문제일 수 있습니다.

**해결책: 원래대로 되돌리기**
```bash
# SSH로 서버 접속
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# 백업 파일이 있다면 복원
cp Modules/Frontend/Resources/views/layouts/master.blade.php.backup Modules/Frontend/Resources/views/layouts/master.blade.php

# 캐시 삭제
php83 artisan view:clear
php83 artisan cache:clear
```

### 원인 2: 파일 권한 문제
```bash
# 파일 권한 확인
ls -l Modules/Frontend/Resources/views/layouts/master.blade.php

# 권한 수정 (필요한 경우)
chmod 644 Modules/Frontend/Resources/views/layouts/master.blade.php
chown apache:apache Modules/Frontend/Resources/views/layouts/master.blade.php
```

### 원인 3: storage 폴더 권한
```bash
# storage 폴더 권한 확인
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R apache:apache storage
chown -R apache:apache bootstrap/cache
```

---

## ⚡ 즉시 복구 방법

### 1단계: 원본 파일로 복원
```bash
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# master.blade.php를 다음과 같이 수정
nano Modules/Frontend/Resources/views/layouts/master.blade.php
```

**Line 60을 다음과 같이 변경:**
```blade
<script src="{{ mix('modules/frontend/script.js') }}"></script>
```

저장: Ctrl + O → Enter → Ctrl + X

### 2단계: 캐시 삭제
```bash
php83 artisan view:clear
php83 artisan cache:clear
php83 artisan config:clear
php83 artisan optimize:clear
```

### 3단계: 사이트 확인
브라우저에서 https://kivicare.seoulbeauty.app 새로고침

---

## 🔧 대체 해결 방법

### 방법 A: mix-manifest.json 수정 (더 안전함)
```bash
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# mix-manifest.json 수정
nano public/mix-manifest.json
```

**Line 39 수정:**
```json
변경 전: "/modules/frontend/script.js": "/modules/frontend/script.js"
변경 후: "/modules/frontend/script.js": "/modules/frontend/script.js?v=20260114"
```

저장: Ctrl + O → Enter → Ctrl + X

```bash
# 캐시 삭제
php83 artisan cache:clear
php83 artisan optimize:clear
```

### 방법 B: asset() 함수 사용
master.blade.php에서:
```blade
<script src="{{ asset('modules/frontend/script.js?v=' . time()) }}"></script>
```

---

## 📋 체크리스트

**즉시 실행:**
- [ ] SSH로 서버 접속
- [ ] `tail -50 storage/logs/laravel.log` 실행하여 에러 확인
- [ ] 에러 메시지를 확인하고 복사
- [ ] master.blade.php를 원래대로 복원
- [ ] `php83 artisan view:clear` 실행
- [ ] 사이트가 복구되는지 확인

**복구 후:**
- [ ] mix-manifest.json 수정 방법 시도
- [ ] 캐시 삭제
- [ ] 브라우저에서 강제 새로고침

---

## 🚨 긴급 복구 명령어 (복사해서 실행)

```bash
# SSH 접속
ssh your_user@your_server
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# 에러 로그 확인
echo "=== Laravel Log ==="
tail -50 storage/logs/laravel.log

# 파일 권한 수정
chmod -R 775 storage bootstrap/cache
chown -R apache:apache storage bootstrap/cache

# 캐시 완전 삭제
php83 artisan view:clear
php83 artisan cache:clear
php83 artisan config:clear
php83 artisan route:clear
php83 artisan optimize:clear

# 상태 확인
echo "✅ Cache cleared. Please check the site."
```

---

## 📞 에러 로그를 보내주세요

다음 명령어를 실행하고 출력 결과를 보내주시면 정확한 원인을 찾을 수 있습니다:

```bash
tail -100 storage/logs/laravel.log
```

**또는:**

```bash
cat storage/logs/laravel-$(date +%Y-%m-%d).log | tail -100
```
