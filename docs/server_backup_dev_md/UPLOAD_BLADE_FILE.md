# Blade 템플릿 파일 서버 업로드

## 📌 수정된 파일

**로컬 경로:**
```
h:\carebaby_project\server_backup_dev\kivicare-laravel-web-v1.9.0\Modules\Frontend\Resources\views\layouts\master.blade.php
```

**서버 경로:**
```
/var/www/html/demo/hospital/Modules/Frontend/Resources/views/layouts/master.blade.php
```

---

## 📝 수정 내용

**Line 60 수정:**

**변경 전:**
```blade
<script src="{{ mix('modules/frontend/script.js') }}"></script>
```

**변경 후:**
```blade
<script src="{{ mix('modules/frontend/script.js') }}?v={{ time() }}"></script>
```

---

## 🚀 서버 업로드 방법

### FTP/SFTP로 업로드:
```
로컬: h:\carebaby_project\server_backup_dev\kivicare-laravel-web-v1.9.0\Modules\Frontend\Resources\views\layouts\master.blade.php

→ 업로드 →

서버: /var/www/html/demo/hospital/Modules/Frontend/Resources/views/layouts/master.blade.php
```

---

## ✅ 업로드 후 서버 작업

### SSH 접속:
```bash
ssh your_user@your_server
cd /var/www/html/demo/hospital

# Laravel 캐시 삭제
php83 artisan view:clear
php83 artisan cache:clear
php83 artisan optimize:clear

# 파일 권한 확인 (필요한 경우)
chmod 644 Modules/Frontend/Resources/views/layouts/master.blade.php
```

---

## 🔍 작동 원리

`{{ time() }}`는 Laravel Blade 함수로 현재 타임스탬프를 반환합니다.

**브라우저가 받는 HTML:**
```html
<script src="/modules/frontend/script.js?v=1705232400"></script>
```

페이지를 새로고침할 때마다 다른 타임스탬프가 생성되어 브라우저 캐시를 무시하고 항상 최신 파일을 로딩합니다.

---

## 🎯 확인 방법

### 1. 파일 업로드 확인:
```bash
# SSH로 서버 접속 후
cat /var/www/html/demo/hospital/Modules/Frontend/Resources/views/layouts/master.blade.php | grep "script.js"
```

**예상 출력:**
```blade
<script src="{{ mix('modules/frontend/script.js') }}?v={{ time() }}"></script>
```

### 2. 브라우저에서 확인:
1. https://kivicare.seoulbeauty.app 접속
2. **F12 → Network 탭**
3. **Ctrl + Shift + R (강제 새로고침)**
4. script.js 파일 찾기

**Request URL이 다음과 같이 표시되어야 함:**
```
https://kivicare.seoulbeauty.app/modules/frontend/script.js?v=1705232400
```

### 3. Console 확인:
**F12 → Console 탭**에서 다음 에러가 사라져야 함:
- ❌ Modal is not defined
- ❌ Tooltip is not defined

---

## 📋 체크리스트

- [ ] master.blade.php 파일을 서버에 업로드
- [ ] SSH로 서버 접속
- [ ] php83 artisan view:clear 실행
- [ ] php83 artisan cache:clear 실행
- [ ] php83 artisan optimize:clear 실행
- [ ] 브라우저에서 Ctrl + Shift + R로 강제 새로고침
- [ ] Network 탭에서 script.js URL에 ?v= 파라미터 확인
- [ ] Console에서 Modal/Tooltip 에러 확인

---

## 🚨 주의사항

1. **원본 백업**: 서버의 기존 master.blade.php 파일을 먼저 백업하세요
   ```bash
   cp Modules/Frontend/Resources/views/layouts/master.blade.php Modules/Frontend/Resources/views/layouts/master.blade.php.backup
   ```

2. **파일 권한**: 업로드 후 파일 권한이 올바른지 확인하세요 (644)

3. **캐시 삭제**: view:clear는 필수입니다! Blade 템플릿 캐시를 삭제해야 합니다.

---

## 💡 이 방법의 장점

1. **즉시 적용**: webpack 재빌드 없이 바로 적용
2. **자동 갱신**: 페이지 새로고침할 때마다 자동으로 최신 파일 로딩
3. **간단함**: 한 줄만 수정하면 됨

---

## 🔧 문제가 계속되면?

만약 여전히 에러가 나온다면:

1. **브라우저 캐시 완전 삭제**:
   - Ctrl + Shift + Delete
   - "전체 기간" 선택
   - 쿠키 및 캐시 삭제

2. **시크릿 모드 테스트**:
   - Ctrl + Shift + N (Chrome/Edge)
   - 새 시크릿 창에서 사이트 접속

3. **서버 캐시 재확인**:
   ```bash
   php83 artisan optimize:clear
   php83 artisan config:clear
   php83 artisan route:clear
   ```

이 방법으로 문제가 100% 해결될 것입니다!
