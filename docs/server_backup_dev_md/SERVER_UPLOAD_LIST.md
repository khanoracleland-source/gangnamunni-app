# 서버 업로드 파일 목록

## ✅ 필수 업로드 파일 (반드시 업로드)

### 1. 빌드된 JavaScript/CSS 파일
```
public/js/app.js
public/css/app.css
public/mix-manifest.json
```

### 2. 생성된 이미지 파일
```
public/img/frontend/ko.png
public/img/frontend/why-choose-us.png
public/img/frontend/video-image.png
```

### 3. 설정 파일
```
.env
app/Providers/AppServiceProvider.php
```

### 4. 소스 파일 (선택사항 - 나중에 재빌드 필요시)
```
Modules/Frontend/Resources/assets/js/app.js
```

---

## 📋 수정 내용 요약

### Bootstrap Modal 에러 수정
- **파일**: `Modules/Frontend/Resources/assets/js/app.js`
- **수정**: `new Modal()` → `new bootstrap.Modal()`
- **수정**: `new Tooltip()` → `new bootstrap.Tooltip()`

### HTTPS 강제 적용
- **파일**: `.env`
- **추가**: `FORCE_HTTPS=true`
- **변경**: `APP_URL=https://frezka.seoulbeauty.app`

- **파일**: `app/Providers/AppServiceProvider.php`
- **추가**: HTTPS 강제 적용 로직

### 누락된 이미지 생성
- `ko.png`, `why-choose-us.png`, `video-image.png` 생성

---

## 🚀 업로드 후 서버 명령어

SSH로 서버 접속 후:

```bash
cd /path/to/project

# Laravel 캐시 클리어
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan optimize:clear
```

---

## 🔍 확인 사항

### 업로드 전:
- [ ] `npm run dev` 완료 확인
- [ ] `public/js/app.js` 파일이 최신인지 확인
- [ ] 이미지 파일 3개 생성 확인

### 업로드 후:
- [ ] 서버에서 캐시 클리어 실행
- [ ] https://kivicare.seoulbeauty.app 접속 테스트
- [ ] 브라우저 콘솔에서 Modal 에러 확인
- [ ] 이미지 로딩 확인 (404 에러 없는지)
- [ ] Mixed Content 에러 없는지 확인

---

## 📞 문제 발생 시

### JavaScript 에러가 계속 나는 경우:
1. `public/js/app.js` 파일이 제대로 업로드되었는지 확인
2. 브라우저 캐시 클리어 (Ctrl + Shift + Delete)
3. 서버에서 `php artisan optimize:clear` 재실행

### 이미지가 안 보이는 경우:
1. `public/img/frontend/` 폴더에 파일이 있는지 확인
2. 파일 권한 확인: `chmod 644 public/img/frontend/*.png`

### Mixed Content 에러가 나는 경우:
1. `.env` 파일의 `FORCE_HTTPS=true` 확인
2. `AppServiceProvider.php` 업로드 확인
3. 서버에서 `php artisan config:clear` 재실행
