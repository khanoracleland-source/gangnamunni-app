# CareBaby 프로젝트 문서 가이드

**프로젝트**: 강남언니 유사 의료/뷰티 플랫폼  
**상태**: Phase 1 완료 (PHP 8.2 마이그레이션, 기본 설치)  
**최종 업데이트**: 2025-01-09

---

## 📁 문서 구조

### 🔥 핵심 문서 (docs 폴더)
통합 정리된 최신 문서입니다. **신규 개발자는 여기서 시작하세요.**

| 문서 | 설명 | 용도 |
|------|------|------|
| **[00_프로젝트_개요.md](docs/00_프로젝트_개요.md)** | 프로젝트 전체 현황, 진행률, 접속 주소 | 전체 이해 |
| **[01_아키텍처_분석.md](docs/01_아키텍처_분석.md)** | 3개 프로젝트 비교, 기술 선정 근거 | 기술 이해 |
| **[04_도메인_설정_가이드.md](docs/04_도메인_설정_가이드.md)** | 로컬/서버 도메인 설정 방법 | 환경 구축 |
| **[07_설치_가이드.md](docs/07_설치_가이드.md)** | Frezka, KiviCare 설치 절차 | 프로젝트 설치 |

### 📂 참고 문서 (루트 폴더)
특정 주제에 대한 상세 가이드입니다.

#### PHP 마이그레이션
- **[PHP8_마이그레이션_완료_보고서_20251231.md](PHP8_마이그레이션_완료_보고서_20251231.md)** ⭐ - 최종 보고서
- [PHP버전_문제_해결_가이드.md](PHP버전_문제_해결_가이드.md) - 버전 충돌 해결

#### 환경 설정
- [도메인_설정_완료.md](도메인_설정_완료.md) - 로컬 도메인 설정 (상세)
- [새로운도메인작업.md](새로운도메인작업.md) - seoulbeauty.app 서버 설정
- [Apache_에러_해결_가이드.md](Apache_에러_해결_가이드.md) - Apache 문제 해결

#### 프로젝트별 가이드
- **[개발시폴더위치및어느것이메인이되어야하나.md](개발시폴더위치및어느것이메인이되어야하나.md)** ⭐ - 메인 프로젝트 선정
- [광고_관리_시스템_사용_가이드.md](광고_관리_시스템_사용_가이드.md) - 광고 시스템 (완성)
- [한국어설정.md](한국어설정.md) - 한국어 번역 작업
- [프로젝트시작주소.md](프로젝트시작주소.md) - 모든 접속 URL

#### 도구 및 유틸리티
- [DB_MIGRATION_TOOL_사용설명서.md](DB_MIGRATION_TOOL_사용설명서.md) - DB 마이그레이션
- [github에_소스_올리는_방법.md](github에_소스_올리는_방법.md) - Git 사용법
- [PHP8_사용_및_DB_테이블_생성_가이드.md](PHP8_사용_및_DB_테이블_생성_가이드.md) - DB 테이블 생성

#### 고급 주제
- [ARCHITECTURE_ANALYSIS_AND_DEVELOPMENT_STRATEGY.md](ARCHITECTURE_ANALYSIS_AND_DEVELOPMENT_STRATEGY.md) - 아키텍처 상세 분석
- [CareBaby_FCM_HTTP_v1_업그레이드_분석.md](CareBaby_FCM_HTTP_v1_업그레이드_분석.md) - FCM 업그레이드

### 📦 백업 문서 (백업_사용안함_md 폴더)
이미 통합된 구 버전 문서들입니다. 참고용으로만 보관.

---

## 🚀 빠른 시작 가이드

### 1단계: 프로젝트 이해 (30분)
```bash
# 필수 읽기
docs/00_프로젝트_개요.md          # 전체 현황 파악
docs/01_아키텍처_분석.md          # 기술 선정 이유
```

### 2단계: 로컬 환경 구축 (1시간)
```bash
# 도메인 설정
docs/04_도메인_설정_가이드.md     # hosts 파일, VirtualHost 설정

# 확인
http://dashboard.seoulbeauty.local/  # 통합 대시보드
```

### 3단계: 프로젝트 설치 (2시간)
```bash
# Frezka, KiviCare 설치
docs/07_설치_가이드.md

# 로그인 테스트
https://frezka-paikpc.seoulbeauty.app/     # Frezka
https://kivicare-paikpc.seoulbeauty.app/   # KiviCare
```

### 4단계: 개발 시작
```bash
# 메인 프로젝트 확인
개발시폴더위치및어느것이메인이되어야하나.md  # KiviCare 선정 근거

# 광고 시스템 (이미 완성됨)
광고_관리_시스템_사용_가이드.md
```

---

## 📊 현재 상태 (2025-01-09)

| 항목 | 상태 | 진행률 |
|------|------|--------|
| PHP 마이그레이션 (8.2) | ✅ 완료 | 100% |
| 도메인 설정 (로컬+서버) | ✅ 완료 | 100% |
| 광고 시스템 개발 | ✅ 완료 | 100% |
| Frezka 설치 | ✅ 완료 | 100% |
| KiviCare 설치 | ✅ 완료 | 100% |
| 통합 문서 정리 | ✅ 완료 | 100% |
| 한국어 번역 | 📝 파일 생성 | 30% |
| 한국 PG 연동 | ⏳ 대기 | 0% |

---

## 🎯 다음 단계

### Phase 2: 한국화 작업 (1-2주)
- [ ] 한국어 번역 완료 (Frezka 67개 + KiviCare 76개 파일)
- [ ] 한국 PG 연동 (포트원 권장)
- [ ] 카카오/네이버 지도 API

### Phase 3: 통합 개발 (4-8주)
- [ ] gangnam_clinic_app 신규 프로젝트 생성 (KiviCare 기반)
- [ ] Frezka 모듈 선택 이식 (리뷰, Wallet 등)
- [ ] 광고 시스템 통합
- [ ] 랭킹 알고리즘 개발

---

## 💡 문제 해결

### 로그인 실패
```bash
# 계정 정보 확인
docs/00_프로젝트_개요.md  # 섹션: 로그인 계정 정보
```

### 도메인 접속 안됨
```bash
# 문제 해결 가이드
docs/04_도메인_설정_가이드.md  # 섹션: 문제 해결
Apache_에러_해결_가이드.md
```

### PHP 버전 충돌
```bash
PHP버전_문제_해결_가이드.md
```

### DB 연결 실패
```bash
docs/00_프로젝트_개요.md  # 섹션: 데이터베이스 정보
docs/07_설치_가이드.md     # 섹션: 문제 해결
```

---

## 📞 추가 도움

### 로그 파일 위치
**로컬 (Windows XAMPP)**:
- Apache: `C:\xampp\apache\logs\error.log`
- PHP: `C:\xampp\php\logs\php_error_log`

**서버 (Rocky Linux)**:
- Apache: `/var/log/httpd/error_log`
- PHP: `/var/log/php-fpm/www-error.log`

### 주요 설정 파일
- Apache VirtualHost: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
- hosts: `C:\Windows\System32\drivers\etc\hosts`
- PHP: `C:\xampp\php\php.ini`

---

**최종 업데이트**: 2025-01-09  
**문서 작성**: Claude Code (Sonnet 4.5)  
**프로젝트 경로**: `h:\carebaby_project\server_backup_dev`
