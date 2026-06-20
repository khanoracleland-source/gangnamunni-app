# CareBaby 소스 GitHub 업로드 가이드

**작성일**: 2025-12-09
**저장소명**: hispital

---

## 1. 사전 준비

### 1.1 필요 사항
```
□ GitHub 계정
□ Git 설치 (https://git-scm.com)
□ GitHub CLI 또는 웹에서 저장소 생성
```

### 1.2 GitHub 저장소 생성
```
1. https://github.com 접속
2. 우측 상단 '+' → 'New repository'
3. Repository name: hispital
4. Private 선택 (소스코드 보호)
5. Create repository
```

---

## 2. Git 초기화 및 업로드

### 2.1 Git 초기화
```bash
cd H:\carebaby_project\server_backup_dev\carebaby

# Git 초기화
git init

# 사용자 설정 (처음 한 번만)
git config user.name "사용자이름"
git config user.email "이메일@example.com"
```

### 2.2 .gitignore 생성
```bash
# 민감 정보 및 불필요 파일 제외
```

### 2.3 파일 추가 및 커밋
```bash
# 전체 파일 추가
git add .

# 첫 커밋
git commit -m "Initial commit: CareBaby 소스 업로드"
```

### 2.4 GitHub 연결 및 푸시
```bash
# GitHub 저장소 연결
git remote add origin https://github.com/사용자명/hispital.git

# 메인 브랜치로 푸시
git branch -M main
git push -u origin main
```

---

## 3. 민감 정보 처리

### 3.1 제외해야 할 파일
```
config.php          - DB 비밀번호, API 키
.env                - 환경 변수
pg/inipay/key/      - 결제 키 파일
google/*.json       - Firebase 서비스 계정
```

### 3.2 .gitignore 내용
```
# 설정 파일
config.php
.env

# 결제 키
pg/inipay/key/

# Firebase
google/*.json
*-firebase-adminsdk-*.json

# 로그
*.log
logs/

# 캐시
cache/
tmp/

# IDE
.idea/
.vscode/
*.swp
```

---

## 4. 작업 완료 후 확인

```
□ GitHub에서 저장소 확인
□ 민감 정보 노출 여부 확인
□ README.md 작성 (선택)
```

---

**문서 작성**: 2025-12-09
**분석 도구**: Claude AI (Opus 4.5)
