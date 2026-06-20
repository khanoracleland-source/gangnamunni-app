# GitHub 저장소 설정 가이드

## 📋 현재 상태

✅ **로컬 Git 저장소 초기화 완료**
✅ **초기 커밋 완료** (2개 커밋)
✅ **데이터베이스 백업 완료** (6.1 MB)
✅ **README 작성 완료**

---

## 🚀 GitHub에 올리기

### 1. GitHub에서 새 저장소 생성

1. https://github.com 접속 및 로그인
2. 우측 상단 "+" → "New repository" 클릭
3. 저장소 정보 입력:
   - Repository name: `carebaby-hospital-booking`
   - Description: `병원 예약 플랫폼 - KiviCare & Frezka 기반`
   - Visibility: **Private** (권장) 또는 Public
   - ❌ **Initialize this repository with:** 모두 체크 해제 (이미 로컬에 있음)
4. "Create repository" 클릭

### 2. 로컬 저장소와 GitHub 연결

GitHub에서 저장소 생성 후 나오는 명령어 중 **"…or push an existing repository from the command line"** 섹션 사용:

```bash
cd H:\carebaby_project

# 원격 저장소 추가
git remote add origin https://github.com/YOUR_USERNAME/carebaby-hospital-booking.git

# 브랜치 이름 확인 (master 또는 main)
git branch

# GitHub에 푸시
git push -u origin master
```

**주의**: `YOUR_USERNAME`을 실제 GitHub 사용자명으로 변경하세요.

### 3. 푸시 확인

```bash
# 원격 저장소 확인
git remote -v

# 푸시 상태 확인
git log --oneline
```

---

## 📁 커밋된 파일 목록

### 백업 및 분석 문서
- `server_backup_dev/kivicare_frezka_20260128_050635_사이트방영전.sql` (6.1 MB)
- `server_backup_dev/KiviCare_Frezka_종합분석.md`
- `server_backup_dev/데이터베이스_임포트_완료_보고서.md`

### 크롤링 스크립트
- `site_get/crawler.js`
- `site_get/fix_links.js`
- `site_get/extract_hospital_data.js`
- `site_get/import_to_mysql.js`
- `site_get/verify_import.js`
- `site_get/auto_fix_monitor.js`
- `site_get/progress_reporter.js`
- `site_get/test_and_monitor.js`
- `site_get/test_mysql_connection.js`
- `site_get/generate_sql.js`
- `site_get/analyze_pages.js`

### 데이터 파일
- `site_get/hospital_data.json` (99개 병원 데이터)
- `site_get/page_analysis.json` (페이지 템플릿 분류)
- `site_get/package.json`
- `site_get/package-lock.json`

### 프로젝트 문서
- `README.md`
- `.gitignore`

---

## ⚠️ 제외된 파일들 (.gitignore)

다음 파일들은 보안 및 크기 문제로 GitHub에 올라가지 않습니다:

- `server_backup_dev/kivicare-laravel-web-v1.9.0/` (소스 코드)
- `server_backup_dev/frezka_admin_panel_v4.0.3/` (소스 코드)
- `site_get/output/` (다운로드된 HTML 파일 1,200+개)
- `.env` 파일 (환경변수)
- `node_modules/` (Node.js 패키지)

---

## 🔐 보안 주의사항

### 민감한 정보 확인
```bash
# .env 파일이 커밋되지 않았는지 확인
git ls-files | grep .env

# 결과가 없어야 정상
```

### 이미 커밋된 민감한 정보가 있다면
```bash
# 특정 파일을 Git 히스토리에서 완전히 제거
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch PATH_TO_FILE" \
  --prune-empty --tag-name-filter cat -- --all

# 강제 푸시 (주의!)
git push origin --force --all
```

---

## 📊 커밋 히스토리

```bash
# 현재 커밋 로그 확인
git log --oneline --graph --all
```

**예상 결과:**
```
* 23431dd Add README with project overview and setup instructions
* e0eb712 Initial commit: Database backup and analysis before site launch
```

---

## 🚀 다음 단계

GitHub 푸시 완료 후:

1. **개발 브랜치 생성**
   ```bash
   git checkout -b develop
   git push -u origin develop
   ```

2. **Laravel 프로젝트 시작**
   ```bash
   cd server_backup_dev/kivicare-laravel-web-v1.9.0
   composer install
   php artisan migrate
   ```

3. **Frontend 프로젝트 시작**
   ```bash
   # Next.js 프로젝트 생성
   npx create-next-app@latest hospital-frontend
   ```

---

## 📞 문제 해결

### GitHub 인증 오류
```bash
# Personal Access Token 사용
git remote set-url origin https://YOUR_TOKEN@github.com/YOUR_USERNAME/carebaby-hospital-booking.git
```

### 푸시가 거부될 때
```bash
# 원격 저장소 강제 덮어쓰기 (주의!)
git push -f origin master
```

### 파일 크기 초과 오류
```bash
# 큰 파일 확인
git ls-files | xargs ls -lh | sort -k5 -h -r | head -10

# Git LFS 사용 (대용량 파일)
git lfs install
git lfs track "*.sql"
git add .gitattributes
git commit -m "Add Git LFS for large files"
```

---

**작성일**: 2026-01-28
**버전**: 1.0
