# 즉시 수정 방법 - 캐시 버스팅 해결

## 🚨 문제 원인
`webpack.mix.js`에 `mix.version()`이 없어서 브라우저가 구버전 JavaScript를 계속 로딩합니다.

## ⚡ 즉시 해결 방법 (서버에서)

### 1단계: SSH로 서버 접속
```bash
ssh your_user@your_server
cd /var/www/html/demo/hospital
```

### 2단계: 파일명에 타임스탬프 추가
```bash
# 현재 파일 백업
cp public/modules/frontend/script.js public/modules/frontend/script.js.backup

# 타임스탬프가 포함된 새 파일명으로 복사
cp public/modules/frontend/script.js public/modules/frontend/script.js?v=20260114

# 또는 mix-manifest.json 수정
php83 artisan optimize:clear
```

### 3단계: mix-manifest.json 직접 수정
```bash
# mix-manifest.json 파일 편집
nano public/mix-manifest.json
```

**다음과 같이 수정:**
```json
"/modules/frontend/script.js": "/modules/frontend/script.js?v=20260114"
```

**저장하고 나가기:** Ctrl + O → Enter → Ctrl + X

### 4단계: Laravel 캐시 삭제
```bash
php83 artisan cache:clear
php83 artisan config:clear
php83 artisan view:clear
php83 artisan optimize:clear
```

---

## 📋 또는 Blade 템플릿에서 직접 버전 추가

### Frontend 레이아웃 파일 찾기
```bash
# Frontend 레이아웃 파일 찾기
find . -name "*.blade.php" -path "*/Frontend/*" | grep -i layout
```

### script.js 로딩 부분 수정
**변경 전:**
```blade
<script src="{{ mix('modules/frontend/script.js') }}"></script>
```

**변경 후:**
```blade
<script src="{{ mix('modules/frontend/script.js') }}?v={{ time() }}"></script>
```

---

## 🔧 올바른 해결 방법 (로컬에서 재빌드)

### 로컬 컴퓨터에서:

#### 1. webpack.mix.js 수정
```bash
cd h:\carebaby_project\server_backup_dev\kivicare-laravel-web-v1.9.0
```

**파일 수정:**
```javascript
// webpack.mix.js 맨 끝에 추가
// !For Production Build Added To Version on File for cache
if (mix.inProduction()) {
    mix.version();
}
```

#### 2. 프로덕션 빌드
```bash
npm run production
```

#### 3. 생성된 파일 확인
```bash
# mix-manifest.json에 해시가 추가되었는지 확인
type public\mix-manifest.json
```

**예상 결과:**
```json
"/modules/frontend/script.js": "/modules/frontend/script.js?id=abc123def456"
```

#### 4. 서버에 업로드
```bash
# 다음 파일들을 서버에 업로드:
public/modules/frontend/script.js
public/mix-manifest.json
```

---

## ✅ 가장 빠른 해결책 (추천)

### 방법 1: 쿼리 스트링 수정 (가장 빠름)
서버의 `mix-manifest.json` 한 줄만 수정:

```bash
# SSH로 접속
cd /var/www/html/demo/hospital

# sed로 한 줄 수정
sed -i 's|"/modules/frontend/script.js": "/modules/frontend/script.js"|"/modules/frontend/script.js": "/modules/frontend/script.js?v=20260114"|g' public/mix-manifest.json

# 캐시 삭제
php83 artisan optimize:clear
```

### 방법 2: Blade 템플릿 직접 수정
Frontend 레이아웃 파일에서 script.js 로딩 부분에 `?v=` 추가

---

## 🎯 확인 방법

### 브라우저에서:
1. **F12 → Network 탭**
2. **Ctrl + Shift + R (강제 새로고침)**
3. **script.js 파일 확인:**
   ```
   Request URL: https://kivicare.seoulbeauty.app/modules/frontend/script.js?v=20260114
   ```
4. **Console 탭에서 에러 확인:**
   - "Modal is not defined" 에러가 사라져야 함

---

## 🚨 즉시 실행할 명령어 (복사해서 실행)

```bash
# SSH 접속
ssh your_user@your_server
cd /var/www/html/demo/hospital

# mix-manifest.json 수정
sed -i 's|"/modules/frontend/script.js": "/modules/frontend/script.js"|"/modules/frontend/script.js": "/modules/frontend/script.js?v=20260114162900"|g' public/mix-manifest.json

# 수정 확인
grep "frontend/script" public/mix-manifest.json

# 캐시 삭제
php83 artisan cache:clear
php83 artisan config:clear
php83 artisan view:clear
php83 artisan optimize:clear

# 완료 메시지
echo "✅ Cache busting applied! Please refresh browser with Ctrl+Shift+R"
```

---

## 📝 체크리스트

- [ ] SSH로 서버 접속
- [ ] mix-manifest.json에서 script.js에 버전 추가
- [ ] Laravel 캐시 삭제 (php83 artisan optimize:clear)
- [ ] 브라우저에서 Ctrl + Shift + R로 강제 새로고침
- [ ] Network 탭에서 script.js URL에 ?v= 파라미터 확인
- [ ] Console에서 "Modal is not defined" 에러 사라졌는지 확인

이 방법이 가장 빠르고 확실합니다!
