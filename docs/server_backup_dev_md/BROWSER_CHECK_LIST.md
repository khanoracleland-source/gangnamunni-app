# 브라우저 캐시 문제 확인 체크리스트

## 🔍 1단계: 브라우저 개발자 도구 확인

### Network 탭 확인
1. **Chrome/Edge 개발자 도구 열기**
   - F12 키 누르기
   - 또는 우클릭 → 검사

2. **Network 탭으로 이동**
   - 상단 메뉴에서 "Network" 클릭

3. **캐시 비활성화 체크**
   - Network 탭 상단에서 "Disable cache" 체크박스 활성화
   - 개발자 도구가 열린 상태에서만 작동

4. **페이지 새로고침 (Ctrl + F5)**
   - 강제 새로고침으로 캐시 무시

5. **script.js 파일 찾기**
   - Network 탭에서 "script.js" 검색
   - 또는 modules/frontend/script.js 찾기

6. **파일 정보 확인**
   ```
   ✅ 확인 항목:
   - Status: 200 (정상) 또는 304 (캐시됨)?
   - Size: 470 KB 또는 460 KB인가?
   - Time: Last-Modified 날짜가 2026-01-14인가?
   ```

---

## 🧹 2단계: 브라우저 캐시 완전 삭제

### Chrome/Edge 브라우저
1. **개발자 도구 열기** (F12)
2. **Application 탭으로 이동**
3. **왼쪽 메뉴에서 "Storage" 확장**
4. **"Clear site data" 클릭**
   - Cookies and other site data 체크
   - Cached images and files 체크
   - "Clear site data" 버튼 클릭

### 또는 브라우저 설정에서
1. **Ctrl + Shift + Delete** 누르기
2. **시간 범위: "전체 기간" 선택**
3. **다음 항목 체크:**
   - ✅ 쿠키 및 기타 사이트 데이터
   - ✅ 캐시된 이미지 및 파일
4. **"데이터 삭제" 클릭**

---

## 🔄 3단계: 강제 새로고침 방법

### 방법 1: 키보드 단축키
```
Windows/Linux:
- Ctrl + F5 (강제 새로고침)
- Ctrl + Shift + R (강제 새로고침)

Mac:
- Cmd + Shift + R
```

### 방법 2: 개발자 도구에서
1. **개발자 도구 열기** (F12)
2. **새로고침 버튼 우클릭**
3. **"Empty Cache and Hard Reload" 선택**
   (비어있는 캐시 및 강력 새로고침)

---

## 🕵️ 4단계: 파일 내용 직접 확인

### Network 탭에서 파일 내용 보기
1. **Network 탭에서 script.js 클릭**
2. **Response 탭으로 이동**
3. **다음 코드가 있는지 검색 (Ctrl + F):**
   ```
   new bootstrap.Modal
   new bootstrap.Tooltip
   ```

4. **만약 다음과 같은 코드가 보인다면 → 구 버전 로딩 중:**
   ```
   new Modal(
   new Tooltip(
   ```

---

## 🚀 5단계: 시크릿 모드 테스트

### 시크릿/비공개 브라우저로 테스트
1. **Chrome/Edge: Ctrl + Shift + N**
2. **Firefox: Ctrl + Shift + P**
3. **https://kivicare.seoulbeauty.app 접속**
4. **F12 → Console 탭 확인**
   - Modal is not defined 에러가 여전히 나오는가?

---

## 📊 6단계: 서버 응답 헤더 확인

### Network 탭에서 Headers 확인
1. **script.js 파일 클릭**
2. **Headers 탭으로 이동**
3. **Response Headers 확인:**
   ```
   ✅ 확인 항목:
   - Last-Modified: 날짜가 2026-01-14인가?
   - Content-Length: 470747 또는 비슷한 값인가?
   - Cache-Control: 캐시 관련 설정 확인
   ```

---

## ⚙️ 7단계: Laravel 캐시 재확인 (서버측)

### SSH로 서버 접속 후:
```bash
cd /var/www/html/demo/hospital

# Laravel 캐시 완전 삭제
php83 artisan cache:clear
php83 artisan config:clear
php83 artisan view:clear
php83 artisan route:clear
php83 artisan optimize:clear

# 파일 권한 확인
ls -lh public/modules/frontend/script.js

# 파일 수정 날짜 확인
stat public/modules/frontend/script.js
```

---

## 🎯 예상 결과

### ✅ 정상적으로 수정된 경우:
```javascript
// Browser Console에서 확인:
console.log(bootstrap)
// 출력: {Modal: ƒ, Tooltip: ƒ, ...}

// 에러 없이 정상 작동
```

### ❌ 여전히 에러가 나는 경우:
```
Uncaught ReferenceError: Modal is not defined
Uncaught ReferenceError: Tooltip is not defined
```

→ 이 경우 다음 확인 필요:
1. **서버 파일이 정말 최신인가?**
2. **CDN이나 프록시가 캐시하고 있는가?**
3. **Service Worker가 캐시하고 있는가?**

---

## 🔧 Service Worker 확인 (추가 확인)

### Application 탭에서
1. **개발자 도구 → Application 탭**
2. **왼쪽 메뉴 "Service Workers" 클릭**
3. **등록된 Service Worker가 있다면:**
   - "Unregister" 클릭
   - 페이지 새로고침

---

## 📝 체크리스트 요약

- [ ] 1. Network 탭에서 Disable cache 활성화
- [ ] 2. Ctrl + F5로 강제 새로고침
- [ ] 3. script.js 파일 Size가 470KB인지 확인
- [ ] 4. Last-Modified 날짜가 2026-01-14인지 확인
- [ ] 5. Response 내용에 "bootstrap.Modal" 코드가 있는지 확인
- [ ] 6. 브라우저 캐시 완전 삭제 (Ctrl + Shift + Delete)
- [ ] 7. 시크릿 모드로 테스트
- [ ] 8. Service Worker 제거 (있는 경우)
- [ ] 9. 서버에서 Laravel 캐시 재삭제
- [ ] 10. Console에서 "Modal is not defined" 에러 사라졌는지 확인

---

## 🚨 여전히 문제가 있다면?

다음 정보를 확인해주세요:

1. **Network 탭 스크린샷:**
   - script.js 파일의 Headers 전체
   - Response 내용 일부

2. **Console 탭 스크린샷:**
   - 전체 에러 메시지

3. **서버 파일 확인:**
   ```bash
   # 서버에서 파일 내용 일부 확인
   head -n 100 public/modules/frontend/script.js | grep -i "modal\|tooltip"
   ```

이 정보들을 제공하시면 정확한 원인을 찾을 수 있습니다.
