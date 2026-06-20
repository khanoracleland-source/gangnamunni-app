# 통합 워크스페이스 보고서

**일시**: 2026-02-08
**서버**: contabo1 (5.104.82.35)
**경로**: /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/

---

## 폴더 구조

hms-saas/ (Laravel 10 HMS 루트, hms.seoulbeauty.app)
  app/                         - HMS Laravel core (3,548 파일)
  routes/                      - web, api, tenant, landing
  config/                      - Laravel 설정
  database/                    - migrations, seeders
  lang/ko/                     - 한국어 포함
  public/                      - 웹 루트
  vendor/                      - Composer 패키지
  .env                         - HMS 환경설정
  artisan                      - Laravel CLI

  hairwang_full/               - 그누보드 전체 (20,951+ 파일)
    common.php                 - 그누보드 코어
    config.php                 - 그누보드 설정
    dbconfig.php               - DB 접속 (localhost로 변경됨)
    gangnamunni/               - 강남언니 서비스 (149 파일, PC 최신)
    rb/                        - 그누보드 리빌더 (843 파일)
    api/sns/                   - SNS OAuth 로그인
    new_http_v1_mars/          - FCM 푸시 알림
    addon_app_admin/           - 앱 관리 시스템
    hairwang_app_source/       - Flutter 앱 소스 (4,798 파일)

  site_get/                    - 크롤링 도구 (85 파일, Node.js)

  docs/                        - 전체 문서 (141 파일)
    root_md/                   - 프로젝트 루트 문서 (27개)
    server_backup_dev_md/      - 개발 보고서 (85개)
    site_get_md/               - 크롤링 문서 (28개)

  sql_dumps/                   - SQL 덤프 (10개, 24MB)
    hairwang_full_backup_2026_02_08.sql  - hairwang DB 전체 (6.7MB, 175 테이블)
    gangnamunni_essential_2026_02_07.sql - 강남언니 핵심 테이블
    hms_saas 관련 덤프
    기타 덤프

  WORKSPACE_REPORT.md          - 이 문서

## 데이터베이스

### contabo1 로컬 MariaDB (localhost)

| DB명 | 용도 | 테이블 수 |
|------|------|-----------|
| hms_saas | HMS 운영 | - |
| hms_saas_dev | HMS 개발 | - |
| hairwang | 그누보드/강남언니 | 175 |

### DB 접속 정보
- HMS: .env (APP_URL=https://hms.seoulbeauty.app, DB_DATABASE=hms_saas)
- hairwang: hairwang_full/dbconfig.php (localhost, root, <REMOVED_DB_PASSWORD>, hairwang)
- 원본 원격 DB: 124.111.85.242:3306 (백업 완료, 로컬로 이전됨)

## 개발 방법

### Cursor AI로 열기
File - Open Folder - /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas

### Claude CLI로 작업
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
claude

### 서버 이전 시
이 폴더 하나만 복사 + MariaDB 덤프

## 변경 이력
- 2026-02-08: hairwang DB 원격에서 로컬 이전 완료 (175 테이블)
- 2026-02-08: gangnamunni/ standalone 제거, hairwang_full/gangnamunni/로 통합
- 2026-02-08: 초기 워크스페이스 구성 (PC 파일 전송)

---
작성: Claude Opus 4.6
