# Hair Wang 앱 관리자 설치 가이드

## 빠른 설치 (5분 완료)

### 1단계: 파일 업로드

FTP 또는 파일 관리자를 사용하여 `addon_app_admin` 폴더를 그누보드 루트에 업로드합니다.

```
업로드 전:
그누보드5/
├── adm/
├── bbs/
├── data/
└── ...

업로드 후:
그누보드5/
├── addon_app_admin/  ← 새로 추가됨
├── adm/
├── bbs/
├── data/
└── ...
```

### 2단계: 데이터베이스 설정

**방법 A: phpMyAdmin 사용**

1. phpMyAdmin 접속
2. 해당 데이터베이스 선택
3. SQL 탭 클릭
4. `addon_app_admin/install/schema.sql` 파일 내용 복사
5. 붙여넣기 후 실행

**방법 B: 그누보드 관리자 사용**

1. 그누보드 관리자 로그인
2. 관리 > SQL 실행 메뉴
3. `addon_app_admin/install/schema.sql` 파일 내용 복사
4. 붙여넣기 후 실행

**방법 C: MySQL 명령줄 사용**

```bash
mysql -u [사용자명] -p [데이터베이스명] < addon_app_admin/install/schema.sql
```

### 3단계: 권한 설정

**Linux/Unix 서버:**

```bash
cd /path/to/gnuboard5
chmod 707 data/addon_app_admin
chmod 707 data/addon_app_admin/uploads
chmod 707 data/addon_app_admin/apps
```

**Windows 서버:**

파일 탐색기에서 해당 폴더 우클릭 > 속성 > 보안 > 쓰기 권한 부여

### 4단계: 접속 확인

1. 웹 브라우저 열기
2. 주소 입력: `https://yourdomain.com/addon_app_admin/admin/`
3. 최고 관리자 계정으로 로그인
4. 대시보드 확인

---

## 상세 설치 가이드

### 시스템 요구사항

**필수 요구사항:**
- PHP 7.4 이상
- MySQL 5.7 이상 또는 MariaDB 10.3 이상
- 그누보드5 기반 시스템
- 최고 관리자 권한

**권장 사양:**
- PHP 8.0 이상
- MySQL 8.0 이상 또는 MariaDB 10.6 이상
- 100MB 이상 디스크 공간
- SSL 인증서 (HTTPS)

**PHP 확장 모듈:**
- GD Library (이미지 처리)
- JSON
- mbstring
- mysqli

### PHP 설정 확인

`php.ini` 파일에서 다음 설정을 확인하세요:

```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
```

설정 변경 후 웹 서버 재시작:

```bash
# Apache
sudo service apache2 restart

# Nginx + PHP-FPM
sudo service php-fpm restart
sudo service nginx restart
```

### 디렉토리 구조 확인

설치 후 다음과 같은 구조가 되어야 합니다:

```
addon_app_admin/
├── admin/              # 관리자 페이지 (13개 파일)
│   ├── _common.php
│   ├── index.php
│   ├── boards.php
│   ├── header.php
│   ├── footer.php
│   ├── main.php
│   ├── app-package.php
│   ├── media.php
│   ├── config.php
│   └── logs.php
├── assets/             # 정적 자원
│   ├── css/
│   │   ├── admin.css
│   │   └── app-package.css
│   ├── js/
│   │   ├── admin.js
│   │   └── app-package.js
│   ├── images/
│   └── uploads/        # 업로드 디렉토리
├── includes/           # 공통 라이브러리
│   ├── config.php
│   └── functions.php
├── install/            # 설치 파일
│   └── schema.sql
├── INSTALL.md         # 이 파일
└── README.md          # 사용 가이드
```

### 데이터베이스 테이블 확인

설치 후 다음 9개 테이블이 생성되어야 합니다:

```sql
-- 테이블 확인 쿼리
SHOW TABLES LIKE 'addon_app_admin_%';
```

**결과:**
1. addon_app_admin_boards
2. addon_app_admin_site_config
3. addon_app_admin_media
4. addon_app_admin_main_content
5. addon_app_admin_app_sources
6. addon_app_admin_app_images
7. addon_app_admin_footer
8. addon_app_admin_logs
9. addon_app_admin_system

### 초기 데이터 확인

다음 쿼리로 초기 데이터가 정상적으로 입력되었는지 확인:

```sql
-- 게시판 데이터 확인
SELECT * FROM addon_app_admin_boards;

-- 사이트 설정 확인
SELECT * FROM addon_app_admin_site_config;

-- 시스템 설정 확인
SELECT * FROM addon_app_admin_system;
```

---

## 설정 커스터마이징

### 권한 설정 변경

`addon_app_admin/includes/config.php` 파일 편집:

```php
// 최고 관리자만 접근 (기본값)
define('ADDON_APP_ADMIN_SUPER_ONLY', true);

// 일반 관리자도 접근 가능하게 변경
define('ADDON_APP_ADMIN_SUPER_ONLY', false);
```

### 업로드 크기 제한 변경

```php
// 최대 업로드 크기 (기본: 10MB)
define('ADDON_APP_ADMIN_MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// 100MB로 변경
define('ADDON_APP_ADMIN_MAX_UPLOAD_SIZE', 100 * 1024 * 1024);
```

### 로그 보관 기간 변경

```php
// 로그 보관 기간 (기본: 90일)
define('ADDON_APP_ADMIN_LOG_RETENTION_DAYS', 90);

// 180일로 변경
define('ADDON_APP_ADMIN_LOG_RETENTION_DAYS', 180);
```

---

## 문제 해결

### 문제: "접근 권한이 없습니다" 오류

**원인:** 최고 관리자가 아니거나 권한 설정 문제

**해결방법:**
1. 최고 관리자 계정으로 로그인했는지 확인
2. `config.php`에서 `ADDON_APP_ADMIN_SUPER_ONLY` 확인
3. 그누보드 회원 테이블에서 mb_level 확인

### 문제: 업로드가 작동하지 않음

**원인:** 디렉토리 권한 또는 PHP 설정 문제

**해결방법:**
1. 디렉토리 권한 확인:
   ```bash
   ls -la data/addon_app_admin
   ```

2. PHP 설정 확인:
   ```php
   <?php
   echo ini_get('upload_max_filesize');
   echo ini_get('post_max_size');
   ?>
   ```

3. 권한 재설정:
   ```bash
   chmod 707 data/addon_app_admin/uploads
   chown www-data:www-data data/addon_app_admin/uploads
   ```

### 문제: 데이터베이스 오류

**원인:** 테이블 생성 실패 또는 권한 부족

**해결방법:**
1. 테이블 존재 확인:
   ```sql
   SHOW TABLES LIKE 'addon_app_admin_%';
   ```

2. SQL 파일 재실행:
   ```sql
   SOURCE /path/to/addon_app_admin/install/schema.sql;
   ```

3. DB 사용자 권한 확인:
   ```sql
   SHOW GRANTS FOR 'your_user'@'localhost';
   ```

### 문제: CSS/JS 파일이 로드되지 않음

**원인:** 경로 설정 또는 .htaccess 문제

**해결방법:**
1. 브라우저 개발자 도구에서 Network 탭 확인
2. 파일 경로 확인:
   ```
   https://yourdomain.com/addon_app_admin/assets/css/admin.css
   ```

3. .htaccess 확인 (필요시 RewriteRule 추가)

### 문제: 한글이 깨져 보임

**원인:** 데이터베이스 또는 PHP 문자셋 문제

**해결방법:**
1. 데이터베이스 문자셋 확인:
   ```sql
   SHOW VARIABLES LIKE 'character_set%';
   ```

2. 테이블 문자셋 변경:
   ```sql
   ALTER TABLE addon_app_admin_boards CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. PHP 설정:
   ```php
   header('Content-Type: text/html; charset=utf-8');
   ```

---

## 업그레이드

향후 버전 업그레이드 시:

1. 기존 파일 백업
2. 새 파일 업로드 (덮어쓰기)
3. 데이터베이스 변경사항 확인 (migration.sql 파일 실행)
4. 캐시 삭제

---

## 제거

완전히 제거하려면:

1. **데이터베이스 삭제:**
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

2. **파일 삭제:**
   ```bash
   rm -rf addon_app_admin/
   rm -rf data/addon_app_admin/
   ```

---

## 지원

설치 중 문제가 발생하면 개발팀에 문의하세요.

**준비사항:**
- 서버 환경 정보 (PHP 버전, MySQL 버전)
- 오류 메시지 전문
- 브라우저 콘솔 로그
