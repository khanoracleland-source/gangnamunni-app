# Hair Wang 앱 관리자 프로그램

## 개요

Hair Wang SNS 쇼핑몰을 위한 관리자 프로그램입니다. 기존 소스 코드를 수정하지 않고 별도의 `addon_app_admin` 폴더로 구성되어 있어 쉽게 설치 및 제거가 가능합니다.

**버전**: 1.0.0
**개발일**: 2026-01-19
**호환성**: 그누보드5 기반 시스템

---

## 주요 기능

### 1. 게시판 관리
- ✅ 게시판 이름 변경
- ✅ 게시판 아이콘 변경 (Font Awesome)
- ✅ 게시판 추가 및 삭제
- ✅ 게시판 표시 여부 설정
- ✅ 게시판 정렬 순서 변경
- ✅ PC/모바일 별도 표시 설정

### 2. 사이트 헤더 관리
- ✅ PC용 로고 변경
- ✅ 모바일용 로고 변경
- ✅ 파비콘 변경
- ✅ 헤더 배경색/텍스트색 설정

### 3. 사이트 푸터 관리
- ✅ 회사 정보 편집
- ✅ 저작권 정보 편집
- ✅ 링크 관리 (개인정보처리방침, 이용약관 등)
- ✅ 푸터 배경색/텍스트색 설정

### 4. 메인 화면 관리
- ✅ 메인 배너 관리
- ✅ 공지사항 콘텐츠 편집
- ✅ 주요 기능 섹션 편집
- ✅ 표시 순서 및 표시 여부 설정

### 5. 앱 패키징 대시보드
- ✅ Android/iOS 앱 소스 업로드
- ✅ 앱 아이콘 변경
- ✅ 스플래시 이미지 변경
- ✅ 스크린샷 이미지 변경
- ✅ rebuilder.co.kr 스타일의 직관적인 UI
- ✅ 드래그 앤 드롭 업로드

### 6. 미디어 관리
- ✅ 업로드된 이미지 파일 관리
- ✅ 파일 정보 조회 (크기, 타입, 해상도)
- ✅ 미디어 파일 삭제

### 7. 활동 로그
- ✅ 관리자 활동 기록
- ✅ IP 주소 및 User Agent 저장
- ✅ 최근 90일 로그 자동 보관

---

## 설치 방법

### 1. 파일 업로드

`addon_app_admin` 폴더를 그누보드5 루트 디렉토리에 업로드합니다.

```
그누보드5/
├── addon_app_admin/          ← 이 폴더를 업로드
│   ├── admin/
│   ├── assets/
│   ├── includes/
│   └── install/
├── adm/
├── bbs/
├── data/
└── ...
```

### 2. 데이터베이스 테이블 생성

phpMyAdmin 또는 MySQL 클라이언트에서 다음 SQL 파일을 실행합니다:

```sql
addon_app_admin/install/schema.sql
```

또는 그누보드 관리자 > SQL 실행 메뉴에서 해당 파일 내용을 복사하여 실행합니다.

### 3. 권한 설정

설치 후 `addon_app_admin/includes/config.php` 파일에서 권한 설정을 확인합니다:

```php
// 최고 관리자만 접근 가능 (기본값: true)
define('ADDON_APP_ADMIN_SUPER_ONLY', true);
```

일반 관리자도 접근하려면 `false`로 변경하세요.

### 4. 디렉토리 권한 설정

업로드 디렉토리에 쓰기 권한을 부여합니다:

```bash
chmod 707 data/addon_app_admin
chmod 707 data/addon_app_admin/uploads
chmod 707 data/addon_app_admin/apps
```

### 5. 접속

웹 브라우저에서 다음 URL로 접속합니다:

```
https://yourdomain.com/addon_app_admin/admin/
```

최고 관리자 계정으로 로그인하면 대시보드가 표시됩니다.

---

## 사용 방법

### 게시판 관리

1. 대시보드에서 "게시판 관리" 카드 클릭
2. 새 게시판 추가 양식 작성:
   - 게시판 키: 고유한 영문/숫자 조합 (예: `notice`, `qna`)
   - 게시판 이름: 표시될 이름 (예: `공지사항`)
   - 아이콘: Font Awesome 클래스명 (예: `fa-bullhorn`)
   - 정렬 순서: 숫자가 작을수록 먼저 표시
   - 표시 설정: 표시/숨김, PC/모바일 표시 여부
3. "게시판 추가" 버튼 클릭
4. 기존 게시판 수정/삭제는 목록에서 버튼 클릭

### 사이트 헤더 설정

1. 대시보드에서 "사이트 헤더" 카드 클릭
2. PC용 로고 업로드 (권장: PNG 형식, 투명 배경)
3. 모바일용 로고 업로드 (권장: PNG 형식, 작은 크기)
4. 파비콘 업로드 (권장: ICO 또는 PNG, 32x32px)
5. 색상 설정: 배경색, 텍스트 색상 선택
6. "저장" 버튼 클릭

### 앱 패키징

1. 대시보드에서 "앱 패키징" 카드 클릭
2. 앱 타입 선택 (Android 또는 iOS)
3. 버전 정보 입력 (선택사항)
4. 앱 소스 ZIP 파일 드래그 앤 드롭 또는 클릭하여 선택
5. "업로드 및 대시보드 열기" 버튼 클릭
6. 새 탭에서 열리는 이미지 편집 대시보드에서:
   - 앱 아이콘 변경 (512x512px 권장)
   - 스플래시 이미지 변경 (해상도별 제공)
   - 스크린샷 이미지 변경
7. 변경 완료 후 다운로드

---

## 폴더 구조

```
addon_app_admin/
├── admin/                     # 관리자 페이지
│   ├── index.php             # 대시보드 메인
│   ├── boards.php            # 게시판 관리
│   ├── header.php            # 사이트 헤더 관리
│   ├── footer.php            # 사이트 푸터 관리
│   ├── main.php              # 메인 화면 관리
│   ├── app-package.php       # 앱 패키징 대시보드
│   ├── app-image-editor.php  # 앱 이미지 편집기
│   ├── media.php             # 미디어 관리
│   ├── config.php            # 사이트 설정
│   ├── logs.php              # 활동 로그
│   └── _common.php           # 공통 파일
├── assets/                    # 정적 자원
│   ├── css/
│   │   ├── admin.css         # 관리자 스타일
│   │   └── app-package.css   # 앱 패키징 스타일
│   ├── js/
│   │   ├── admin.js          # 관리자 스크립트
│   │   └── app-package.js    # 앱 패키징 스크립트
│   └── images/               # 이미지 파일
├── includes/                  # 공통 라이브러리
│   ├── config.php            # 설정 파일
│   └── functions.php         # 공통 함수
├── install/                   # 설치 파일
│   └── schema.sql            # 데이터베이스 스키마
└── README.md                 # 이 파일
```

---

## 데이터베이스 테이블

모든 테이블은 `addon_app_admin_` 접두사를 사용합니다:

| 테이블명 | 설명 |
|---------|------|
| `addon_app_admin_boards` | 게시판 설정 |
| `addon_app_admin_site_config` | 사이트 설정 |
| `addon_app_admin_media` | 미디어 파일 |
| `addon_app_admin_main_content` | 메인 화면 콘텐츠 |
| `addon_app_admin_app_sources` | 앱 소스 파일 |
| `addon_app_admin_app_images` | 앱 이미지 설정 |
| `addon_app_admin_footer` | 하단 정보 |
| `addon_app_admin_logs` | 활동 로그 |
| `addon_app_admin_system` | 시스템 설정 |

---

## 제거 방법

### 1. 데이터베이스 테이블 삭제

phpMyAdmin 또는 MySQL 클라이언트에서 다음 SQL 실행:

```sql
DROP TABLE IF EXISTS
  addon_app_admin_boards,
  addon_app_admin_site_config,
  addon_app_admin_media,
  addon_app_admin_main_content,
  addon_app_admin_app_sources,
  addon_app_admin_app_images,
  addon_app_admin_footer,
  addon_app_admin_logs,
  addon_app_admin_system;
```

### 2. 파일 삭제

FTP 또는 파일 관리자에서 다음 폴더 삭제:

- `addon_app_admin/` 폴더 전체
- `data/addon_app_admin/` 폴더 전체

---

## 기술 스택

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, JavaScript (ES6)
- **UI Framework**: Custom CSS (rebuilder.co.kr 스타일 참조)
- **Icons**: Font Awesome 4.7
- **Compatible**: 그누보드5 기반 시스템

---

## 보안 고려사항

1. **권한 관리**: 최고 관리자만 접근 가능 (설정 변경 가능)
2. **CSRF 보호**: 모든 폼에 토큰 검증
3. **파일 업로드 검증**:
   - 허용 확장자 제한
   - 파일 크기 제한
   - MIME 타입 검증
4. **SQL Injection 방지**: Prepared Statement 사용
5. **XSS 방지**: 출력 시 이스케이핑
6. **로깅**: 모든 활동 기록

---

## 문제 해결

### 업로드가 안 됨

1. 디렉토리 권한 확인:
   ```bash
   chmod 707 data/addon_app_admin/uploads
   ```

2. PHP 설정 확인:
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   max_execution_time = 300
   ```

### 접근 권한 오류

1. 최고 관리자로 로그인했는지 확인
2. `config.php`에서 `ADDON_APP_ADMIN_SUPER_ONLY` 설정 확인

### 데이터베이스 오류

1. 테이블이 정상적으로 생성되었는지 확인
2. DB 사용자 권한 확인 (CREATE, SELECT, INSERT, UPDATE, DELETE)

---

## 라이선스

Copyright © 2026 Hair Wang. All rights reserved.

이 프로그램은 Hair Wang 프로젝트 전용으로 개발되었습니다.

---

## 변경 이력

### v1.0.0 (2026-01-19)
- 초기 릴리스
- 게시판 관리 기능
- 사이트 헤더/푸터 관리
- 메인 화면 관리
- 앱 패키징 대시보드
- rebuilder.co.kr 스타일 UI

---

## 지원

문의사항이나 버그 리포트는 개발팀에 문의하세요.
