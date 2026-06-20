# 🔧 Frezka DB Migration & Validation Tool 사용 설명서

**버전**: 1.0
**작성일**: 2025-12-29
**목적**: Frezka 데이터베이스 테이블 생성 및 검증 자동화

---

## 📋 개요

이 도구는 Frezka Laravel 프로젝트의 데이터베이스 설치 문제를 해결하기 위해 만들어졌습니다.

### 주요 기능

✅ **데이터베이스 연결 테스트**
- MySQL/MariaDB 연결 확인
- 여러 데이터베이스 동시 테스트

✅ **누락된 테이블 자동 생성**
- `frezka_local` 및 `frezka` DB에 동시 생성 가능
- 43개 필수 테이블 스키마 포함
- 기존 테이블 보호 (중복 생성 방지)

✅ **데이터베이스 검증**
- 필수 테이블 존재 여부 확인
- 누락된 테이블 목록 표시

✅ **데이터 무결성 체크**
- 각 테이블의 레코드 수 확인
- 중요 테이블 데이터 상태 체크
- 빈 테이블 식별

---

## 📁 파일 구성

```
H:\carebaby_project\server_backup_dev\
├── db_migration_tool.html           # 웹 인터페이스 (프론트엔드)
├── db_migration_backend.php          # PHP 백엔드 (API)
├── table_schemas.php                 # 테이블 스키마 정의
├── extract_sql_from_migrations.php   # SQL 추출 도구
├── frezka_table_schemas.sql          # 추출된 SQL 파일
└── DB_MIGRATION_TOOL_사용설명서.md    # 이 파일
```

---

## 🚀 설치 및 실행

### 1단계: 파일 배치

모든 파일이 다음 경로에 있는지 확인:
```
H:\carebaby_project\server_backup_dev\
```

### 2단계: 웹 서버 실행

XAMPP Apache가 실행 중인지 확인:
```
1. XAMPP Control Panel 열기
2. Apache "Start" 버튼 클릭
3. MySQL "Start" 버튼 클릭
```

### 3단계: 브라우저에서 접속

**로컬 파일로 열기** (권장):
```
파일 탐색기에서 db_migration_tool.html을 더블클릭
```

또는 **웹 서버를 통해 접속**:
```
http://localhost/server_backup_dev/db_migration_tool.html
```

---

## 📖 사용 방법

### Step 1: 데이터베이스 설정

웹 페이지 상단의 "📊 데이터베이스 설정" 섹션에서 입력:

| 항목 | 기본값 | 설명 |
|------|--------|------|
| DB Host | `127.0.0.1` | MySQL 서버 주소 |
| DB Port | `3306` | MySQL 포트 |
| DB Username | `root` | 데이터베이스 사용자명 |
| DB Password | `<REMOVED_DB_PASSWORD>` | 데이터베이스 비밀번호 |

**대상 데이터베이스 선택**:
- ☑ `frezka_local` - 로컬 테스트용
- ☑ `frezka` - 메인 데이터베이스

**둘 다 체크하면 양쪽 DB에 동시 작업**

---

### Step 2: 연결 테스트

1. **🔌 데이터베이스 연결 테스트** 버튼 클릭
2. 결과 확인:
   ```
   ✅ 데이터베이스 연결 성공!
     ✓ frezka_local: Connected successfully
     ✓ frezka: Connected successfully
   ```

**연결 실패 시**:
- DB 정보가 정확한지 확인
- MySQL이 실행 중인지 확인
- 비밀번호에 특수문자가 있으면 주의

---

### Step 3: 데이터베이스 검증

1. **✅ 데이터베이스 검증** 버튼 클릭
2. 누락된 테이블 확인:
   ```
   📊 데이터베이스: frezka_local
     총 테이블 수: 15개
     누락된 테이블: 28개
     누락된 테이블 목록:
       ⚠ frontend_settings
       ⚠ wallet_histories
       ⚠ sliders
       ...
   ```

---

### Step 4: 누락된 테이블 생성

1. **🚀 누락된 테이블 생성 (Migration)** 버튼 클릭
2. 확인 팝업에서 **확인** 클릭
3. 진행 상황 확인:
   ```
   🚀 마이그레이션 시작...
   [진행률 바 표시]

   ✅ 마이그레이션 완료!

   📊 데이터베이스: frezka_local
     생성된 테이블: 28개
     이미 존재: 15개
     생성된 테이블 목록:
       ✓ frontend_settings
       ✓ wallet_histories
       ✓ sliders
       ...
   ```

4. 통계 확인:
   ```
   총 생성된 테이블: 28
   이미 존재하는 테이블: 15
   에러 발생: 0
   ```

---

### Step 5: 데이터 무결성 체크

1. **🔍 데이터 무결성 체크** 버튼 클릭
2. 결과 확인:
   ```
   📊 데이터베이스: frezka_local
     데이터가 있는 테이블: 12개
     빈 테이블: 31개
     총 레코드 수: 1,234개

     중요 테이블 상태:
       users: 5개 레코드
       settings: 142개 레코드
       categories: 0개 레코드 ⚠️
       services: 0개 레코드 ⚠️
       branches: 1개 레코드
       frontend_settings: 0개 레코드 ⚠️
   ```

**⚠️ 빈 테이블 경고**:
- 빈 테이블이 있어도 정상입니다
- `/install` 페이지에서 설치를 완료하면 데이터가 채워집니다

---

## 🔧 고급 기능

### 전체 테이블 목록 보기

**📋 전체 테이블 목록 보기** 버튼 클릭:

```
📊 데이터베이스: frezka_local (43개 테이블)
  1. personal_access_tokens
  2. users
  3. user_providers
  4. password_resets
  5. roles
  ...
  43. video_sections
```

---

## 📊 필수 테이블 목록

### 인증 및 권한 (9개)
- `users` - 사용자
- `user_providers` - 소셜 로그인
- `user_profiles` - 사용자 프로필
- `password_resets` - 비밀번호 재설정
- `roles` - 역할
- `permissions` - 권한
- `model_has_permissions`
- `model_has_roles`
- `role_has_permissions`

### 시스템 (7개)
- `settings` - 시스템 설정
- `notifications` - 알림
- `media` - 미디어 파일
- `activity_log` - 활동 로그
- `failed_jobs` - 실패한 작업
- `jobs` - 작업 큐
- `modules` - 모듈

### 비즈니스 (15개)
- `branches` - 지점
- `addresses` - 주소
- `branch_galleries` - 지점 갤러리
- `categories` - 카테고리
- `services` - 서비스
- `products` - 제품
- `product_variations` - 제품 옵션
- `taxes` - 세금
- `coupons` - 쿠폰
- `commission` - 수수료
- `packages` - 패키지
- `package_service` - 패키지-서비스 연결
- `bookings` - 예약
- `booking_services` - 예약-서비스
- `booking_products` - 예약-제품

### 결제 및 지갑 (2개)
- `booking_transactions` - 결제 내역
- `wallet_histories` - 지갑 내역

### 프론트엔드 (6개)
- `frontend_settings` - 프론트 설정
- `sliders` - 슬라이더
- `faqs` - FAQ
- `blogs` - 블로그
- `employee_rating` - 직원 평가
- `why_choose` - 선택 이유
- `video_sections` - 비디오 섹션

### 기타 (4개)
- `delivery_zones` - 배송 지역
- `webhook_calls` - 웹훅 호출
- `personal_access_tokens` - API 토큰

**총 43개 테이블**

---

## ⚠️ 주의사항

### 1. 데이터 손실 방지

✅ **안전한 작업**:
- 기존 테이블은 절대 삭제하지 않습니다
- 누락된 테이블만 생성합니다
- 기존 데이터는 보존됩니다

❌ **주의할 점**:
- 테이블을 삭제하고 싶으면 phpMyAdmin 사용
- 이 도구는 생성만 가능, 삭제 불가

### 2. 동시 작업

✅ **가능**:
- `frezka_local`과 `frezka` 동시 생성
- 여러 테이블 동시 생성

❌ **불가능**:
- 설치 중 브라우저 닫기
- 진행 중 새로고침

### 3. 에러 처리

**일부 테이블 생성 실패 시**:
```
❌ 에러 발생:
  ✗ some_table: SQLSTATE[42S01]: Base table or view already exists
```

→ 정상입니다. 이미 존재하는 테이블입니다.

**연결 실패 시**:
```
❌ 연결 실패: Access denied for user 'root'@'localhost'
```

→ DB 비밀번호를 확인하세요.

---

## 🐛 문제 해결

### Q1: "Cannot connect to database" 에러

**원인**: MySQL이 실행되지 않았거나 접속 정보가 틀림

**해결**:
1. XAMPP MySQL 재시작
2. DB 정보 재확인
3. 비밀번호에 특수문자가 있으면 주의

### Q2: 일부 테이블만 생성됨

**원인**: 테이블 간 의존성 문제 또는 SQL 에러

**해결**:
1. 에러 메시지 확인
2. 다시 "🚀 누락된 테이블 생성" 실행
3. 여전히 실패하면 phpMyAdmin에서 수동 생성

### Q3: "table_schemas.php not found" 에러

**원인**: 스키마 파일이 없음

**해결**:
```cmd
cd H:\carebaby_project\server_backup_dev
php extract_sql_from_migrations.php
```

### Q4: 설치 후에도 500 에러

**원인**: 데이터가 없는 빈 테이블

**해결**:
1. Frezka `/install` 페이지 재방문
2. 설치 프로세스 완료
3. 샘플 데이터 입력

---

## 📝 완전 재설치 가이드

설치를 완전히 처음부터 하려면:

### 1단계: 데이터베이스 삭제

phpMyAdmin (`http://localhost/phpmyadmin`):
```sql
DROP DATABASE IF EXISTS frezka_local;
DROP DATABASE IF EXISTS frezka;
```

### 2단계: 데이터베이스 재생성

```sql
CREATE DATABASE frezka_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE frezka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3단계: 마이그레이션 도구 실행

1. `db_migration_tool.html` 열기
2. DB 정보 입력
3. "🔌 데이터베이스 연결 테스트"
4. "🚀 누락된 테이블 생성"
5. "✅ 데이터베이스 검증"

### 4단계: Frezka 설치 완료

```
http://frezka.seoulbeauty.local/install
```

설치 마법사에서:
- Environment 설정
- Database 정보 입력
- Install 버튼 클릭
- 완료까지 대기 (중단 금지!)

---

## 🎯 사용 시나리오

### 시나리오 1: 설치 중 에러로 중단됨

**상황**:
```
http://frezka.seoulbeauty.local/install/final
에러: Table 'wallet_histories' already exists
```

**해결**:
1. `db_migration_tool.html` 열기
2. "✅ 데이터베이스 검증" 클릭
3. 누락된 테이블 확인
4. "🚀 누락된 테이블 생성" 클릭
5. Frezka `/install` 재시도

---

### 시나리오 2: frontend_settings 테이블 없음

**상황**:
```
500 Error: Table 'frezka_local.frontend_settings' doesn't exist
```

**해결**:
1. `db_migration_tool.html` 열기
2. `frezka_local` 체크
3. "🚀 누락된 테이블 생성" 클릭
4. `frontend_settings` 생성 확인
5. 브라우저 새로고침 (F5)

---

### 시나리오 3: 2개 DB에 동시 설치

**상황**:
로컬(`frezka_local`)과 메인(`frezka`) DB 모두 설정 필요

**해결**:
1. `db_migration_tool.html` 열기
2. 두 DB 모두 체크:
   - ☑ `frezka_local`
   - ☑ `frezka`
3. "🚀 누락된 테이블 생성" 클릭
4. 양쪽 DB 결과 확인

---

## 📚 참고 자료

### 관련 파일

- [419에러원인.md](H:\carebaby_project\server_backup_dev\419에러원인.md) - 419 에러 해결 가이드
- [frezka_인스톨_작업.md](H:\carebaby_project\server_backup_dev\frezka_인스톨_작업.md) - 설치 작업 로그

### 생성된 SQL 파일

```
H:\carebaby_project\server_backup_dev\frezka_table_schemas.sql
```

이 파일을 phpMyAdmin에서 직접 실행해도 됩니다:
1. phpMyAdmin → `frezka_local` 선택
2. "Import" 탭
3. `frezka_table_schemas.sql` 선택
4. "Go" 버튼 클릭

---

## ✅ 체크리스트

### 설치 전 체크

- [ ] XAMPP Apache 실행 중
- [ ] XAMPP MySQL 실행 중
- [ ] DB 접속 정보 확인
- [ ] `db_migration_tool.html` 파일 존재
- [ ] `db_migration_backend.php` 파일 존재
- [ ] `table_schemas.php` 파일 존재

### 설치 후 체크

- [ ] "🔌 데이터베이스 연결 테스트" 성공
- [ ] "✅ 데이터베이스 검증" 완료
- [ ] "🚀 누락된 테이블 생성" 성공
- [ ] "🔍 데이터 무결성 체크" 완료
- [ ] 43개 테이블 모두 생성됨
- [ ] Frezka `/install` 페이지 정상 동작

---

## 🆘 지원

### 에러 발생 시

1. **에러 메시지 복사**
2. **브라우저 콘솔 확인** (F12 → Console 탭)
3. **PHP 에러 로그 확인**:
   ```
   C:\xampp\php\logs\php_error_log
   ```

### 긴급 복구

데이터가 손상되었다면:
```sql
-- phpMyAdmin에서 실행
DROP DATABASE frezka_local;
CREATE DATABASE frezka_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

그 다음 마이그레이션 도구로 테이블 재생성

---

**작업 완료일**: 2025-12-29
**작성자**: Claude Code
**버전**: 1.0
