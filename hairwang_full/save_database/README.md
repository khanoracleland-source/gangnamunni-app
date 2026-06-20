# 데이터베이스 백업 도구

## 개요

이 도구는 XAMPP 환경에서 4개의 데이터베이스를 쉽게 백업할 수 있는 웹 인터페이스입니다.

## 지원하는 데이터베이스

1. **carecraft_2025** - carebaby 프로젝트 (NFOR 솔루션)
2. **frezka** - frezka & kivicare 프로젝트 (Laravel)
3. **hairwang** - hairwang 프로젝트 (그누보드5, dbconfig.php에서 설정)
4. **g5_default** - 그누보드5 기본 데이터베이스 (선택사항)

## 사용 방법

### 1. 웹 브라우저에서 접속

```
http://localhost/hairwang_dev_sns_shop_2026_01_18/save_database/
```

### 2. 데이터베이스 선택 및 백업

1. 백업할 데이터베이스를 체크박스로 선택합니다.
2. "전체 선택"을 클릭하면 모든 데이터베이스가 선택됩니다.
3. "선택한 데이터베이스 백업" 버튼을 클릭합니다.
4. 백업이 완료되면 `dbbackup/` 폴더에 저장됩니다.

### 3. 단일 데이터베이스 백업

1. 데이터베이스 목록에서 "백업" 버튼을 클릭합니다.
2. 해당 데이터베이스만 백업됩니다.

### 4. 백업 파일 다운로드 및 삭제

1. "기존 백업 파일" 섹션에서 백업 파일 목록을 확인합니다.
2. "다운로드" 버튼으로 다운로드할 수 있습니다.
3. "삭제" 버튼으로 백업 파일을 삭제할 수 있습니다.

## 파일 구조

```
save_database/
├── index.php          # 메인 페이지
├── delete.php          # 파일 삭제 처리
├── dbbackup/           # 백업 파일 저장 폴더
│   ├── carecraft_2025_backup_2026-01-19_12-00-00.sql
│   ├── frezka_backup_2026-01-19_12-00-00.sql
│   ├── hairwang_backup_2026-01-19_12-00-00.sql
│   └── ...
└── README.md          # 이 파일
```

## 백업 파일 명명 규칙

```
[데이터베이스명]_backup_[YYYY-MM-DD]_[HH-MM-SS].sql
```

예시:
- `carecraft_2025_backup_2026-01-19_12-00-00.sql`
- `frezka_backup_2026-01-19_12-01-00.sql`

## 데이터베이스 설정

### XAMPP MySQL 설정

```
호스트: localhost
포트: 3306
사용자: root
비밀번호: <REMOVED_DB_PASSWORD>
```

### hairwang 데이터베이스 설정

hairwang 데이터베이스 이름은 `../dbconfig.php` 파일에서 읽어옵니다.

dbconfig.php 파일이 없으면 다음 형식으로 생성하세요:

```php
<?php
// 데이터베이스 설정 파일
define('G5_MYSQL_HOST', 'localhost');
define('G5_MYSQL_PORT', '3306');
define('G5_MYSQL_USER', 'root');
define('G5_MYSQL_PASSWORD', '<REMOVED_DB_PASSWORD>');
define('G5_MYSQL_DB', 'hairwang');
?>
```

## 복구 방법

### 1. MySQL Command Line

```bash
mysql -u root -p<REMOVED_DB_PASSWORD> 데이터베이스명 < 백업파일.sql
```

예시:
```bash
mysql -u root -p<REMOVED_DB_PASSWORD> carecraft_2025 < carecraft_2025_backup_2026-01-19_12-00-00.sql
```

### 2. phpMyAdmin

1. phpMyAdmin에 접속합니다 (http://localhost/phpmyadmin/)
2. 데이터베이스를 선택하거나 새로 생성합니다.
3. "가져오기(Import)" 탭을 클릭합니다.
4. 백업 파일을 선택합니다.
5. "가져오기" 버튼을 클릭합니다.

### 3. XAMPP MySQL Command Line

```bash
cd C:\xampp\mysql\bin
mysql -u root -p<REMOVED_DB_PASSWORD> 데이터베이스명 < "백업파일 경로"
```

## 보안 권장사항

1. **비밀번호 변경**: `<REMOVED_DB_PASSWORD>` 비밀번호를 더 강력한 비밀번호로 변경하세요.
2. **폴더 보호**: `save_database/` 폴더를 웹에서 접근할 수 없도록 `.htaccess` 파일을 추가하세요.
3. **백업 보관**: 백업 파일을 정기적으로 외부 저장소에 백업하세요.
4. **백업 삭제**: 오래된 백업 파일은 정기적으로 삭제하세요.

## .htaccess 파일 예시

```apache
# 디렉토리 목록 차단
Options -Indexes

# 특정 IP만 접근 허용 (선택사항)
# Require ip 192.168.1.100
# Require ip 127.0.0.1

# 기본 인증 (선택사항)
# AuthType Basic
# AuthName "Restricted Access"
# AuthUserFile /path/to/.htpasswd
# Require valid-user
```

## 정기 백업 자동화

### Windows Task Scheduler

1. "작업 스케줄러" 열기
2. "기본 작업 만들기" 클릭
3. 작업 이름 입력 (예: "데이터베이스 백업")
4. 트리거 설정 (예: 매일 새벽 2시)
5. 작업 설정:
   ```
   프로그램 또는 스크립트: C:\xampp\php\php.exe
   인수: C:\xampp\htdocs\hairwang_dev_sns_shop_2026_01_18\save_database\backup_all.php
   시작 위치: C:\xampp\htdocs\hairwang_dev_sns_shop_2026_01_18\save_database
   ```
6. 작업 저장

### backup_all.php 예시

```php
<?php
// 정기 백업 스크립트
include 'index.php';
?>
```

## 문제 해결

### 백업 파일이 생성되지 않음

1. XAMPP MySQL이 실행 중인지 확인하세요.
2. `dbbackup/` 폴더에 쓰기 권한이 있는지 확인하세요.
3. MySQL 비밀번호가 올바른지 확인하세요.

### MySQL 연결 실패

1. XAMPP Control Panel에서 MySQL이 실행 중인지 확인하세요.
2. MySQL 포트(3306)가 올바른지 확인하세요.
3. MySQL 서비스가 충돌하지 않는지 확인하세요.

### 백업 파일이 너무 큼

- `mysqldump` 명령어에 `--quick` 옵션을 추가하여 큰 버퍼를 사용하지 않도록 할 수 있습니다.
- `--single-transaction` 옵션을 사용하여 InnoDB 테이블을 일관되게 백업할 수 있습니다.

## 추가 기능

### 백업 압축

백업 파일을 압축하여 저장 공간을 절약할 수 있습니다.

```php
$command = '"C:\xampp\mysql\bin\mysqldump.exe" -h localhost -u root -p<REMOVED_DB_PASSWORD> ' . $db['name'] . ' | gzip > ' . $backup_file . '.gz';
```

### 백업 알림

백업 완료 후 이메일 또는 SMS로 알림을 받을 수 있습니다.

## 기술 스택

- **PHP**: 7.4+
- **MySQL**: 5.7+ / MariaDB 10.3+
- **Web Server**: Apache (XAMPP)
- **OS**: Windows (XAMPP)

## 라이선스

이 도구는 내부 사용을 위해 개발되었습니다.

## 지원

문제가 있으면 관리자에게 문의하세요.

---

**버전**: 1.0
**작성일**: 2026-01-19
**작성자**: AI Assistant
