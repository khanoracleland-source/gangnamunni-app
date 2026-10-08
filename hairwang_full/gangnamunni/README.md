# 강남언니 스타일 미용병원 플랫폼

> HMS (Hospital Management System) + 강남언니 UI + Flutter 앱 통합 솔루션

## 📋 프로젝트 개요

**서울미녀**는 강남언니 스타일의 미용/성형병원 예약 및 리뷰 플랫폼입니다. InfyHMS를 백엔드로 사용하고, 강남언니의 UI/UX를 적용하여 한국 시장에 최적화된 서비스를 제공합니다.

### 핵심 기능
- 🏥 병원 검색 및 정보 제공
- ⭐ 실시간 리뷰 시스템
- 📅 온라인 상담 예약
- 💳 결제 시스템 (PG 연동 준비 중 — Toss Payments 예정)
- 🤖 AI 광고 자동 생성 (GPT-4 + DALL-E)
- 🧪 A/B 테스트 시스템
- 📱 Flutter 모바일 앱
- 🔄 HMS ↔ 서울미녀 DB 자동 동기화
- 🔧 무중단 배포 및 점검 모드

## 🚀 주요 관리 페이지

| 기능 | URL | 설명 |
|------|-----|------|
| **통합 대시보드** | https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php | 모든 기능 통합 접근 |
| **광고 관리** | https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_ads.php | 광고 CRUD, 결제, AI, A/B |
| **AI 에디터** | https://hms.seoulbeauty.app/hairwang/gangnamunni/ai_ad_editor.php | GPT-4 카피, DALL-E 이미지 |
| **A/B 테스트** | https://hms.seoulbeauty.app/hairwang/gangnamunni/ab_test_dashboard.php | 성과 분석, 시각화 |
| **점검 관리** | https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_maintenance.php | 점검 ON/OFF, 예약 |

**로그인**: admin / admin123

## 📊 구현된 기능 목록

### ✅ 완료 (100%)
1. **Flutter 모바일 에디터 WebView 통합**
   - WebView ↔ Native 양방향 통신
   - Quill 에디터 통합

2. **HMS ↔ 서울미녀 DB 동기화**
   - HMS → 서울미녀: 병원/의사 (5분마다)
   - 서울미녀 → HMS: 상담 신청 (5분마다)
   - 문서: [sync/README.md](sync/README.md)

3. **결제 시스템 (PG 연동 준비 중)**
   - 4가지 광고 상품 (배너/프리미엄 × 1/3개월)
   - 서버사이드 금액 검증
   - 환불 처리
   - 문서: [payment/PAYMENT_GUIDE.md](payment/PAYMENT_GUIDE.md)

4. **AI 광고 에디터**
   - GPT-4 광고 카피 생성 (3버전)
   - DALL-E 이미지 생성
   - A/B 테스트 버전 생성
   - 문서: [ai/AI_EDITOR_README.md](ai/AI_EDITOR_README.md)

5. **A/B 테스트 시스템**
   - 노출/클릭/전환 자동 추적
   - CTR, 전환율 실시간 계산
   - Chart.js 시각화

6. **배포 자동화 시스템**
   - 개발 환경 클론 (clone_to_dev.sh)
   - 운영 배포 (deploy_to_production.sh)
   - 롤백 (rollback.sh)
   - 문서: [../deploy/DEPLOYMENT_SCENARIO.md](../deploy/DEPLOYMENT_SCENARIO.md)

7. **점검 모드 시스템**
   - 웹 UI + CLI 제어
   - 진행률 표시, 관리자 우회
   - 예약 점검 (cron)

## 🛠️ 기술 스택

- **Backend**: PHP 8.2, Laravel 10, Gnuboard 5
- **Frontend**: Tailwind CSS, Vanilla JS, Quill.js
- **Mobile**: Flutter 3.x
- **Database**: MariaDB 10.6
- **APIs**: OpenAI (GPT-4, DALL-E)
- **Infra**: Rocky Linux 9.7, Apache 2.4, Cloudflare

## 📂 주요 파일 구조

```
gangnamunni/
├── dashboard.php              # 통합 대시보드 ⭐
├── admin_ads.php              # 광고 관리 (결제/AI/A/B 통합)
├── ai_ad_editor.php           # AI 에디터
├── ab_test_dashboard.php      # A/B 테스트
├── admin_maintenance.php      # 점검 관리
├── maintenance.php            # 점검 페이지
│
├── ai/                        # AI 시스템
│   └── openai_config.php
├── payment/                   # 결제 시스템
│   ├── ad_payment.php       # 폐기된 패키지 결제 (HTTP 410)
│   └── payment_callback.php # PG 미연동 시 fail-closed
├── sync/                      # DB 동기화
│   ├── sync_hms_to_gangnamunni.php
│   └── sync_gangnamunni_to_hms.php
└── sql_dumps/                 # SQL 스키마
    ├── create_payment_tables.sql
    └── create_ab_test_tables.sql
```

## 🚀 빠른 시작

### SSH 접속
```bash
ssh root@5.104.82.35
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/
```

### 배포 스크립트
```bash
cd ../../../deploy/

# 개발 환경 클론
./clone_to_dev.sh

# 운영 배포
./deploy_to_production.sh

# 점검 모드
./maintenance.sh on "점검 중"
./maintenance.sh off

# 롤백
./rollback.sh
```

## 📝 문서

- [배포 시나리오](../deploy/DEPLOYMENT_SCENARIO.md) - 개발/운영 분리, 배포 워크플로우
- [결제 가이드](payment/PAYMENT_GUIDE.md) - 예약 결제·정산 구조
- [AI 에디터 가이드](ai/AI_EDITOR_README.md) - GPT-4, DALL-E 사용법
- [DB 동기화 가이드](sync/README.md) - HMS ↔ 서울미녀 동기화

## 🔐 보안 주의사항

1. **API 키 관리** - PG 키는 서버 설정(`data/dbconfig.php`)에만 두고, `openai_config.php`에서 실제 키로 교체
2. **테스트 모드** - 프로덕션 배포 시 `TEST_MODE = false` 설정
3. **로그인 정보** - 기본 계정(admin/admin123) 변경 권장

## 📞 지원

- **서버**: contabo1 (5.104.82.35)
- **도메인**: https://hms.seoulbeauty.app
- **DB**: hairwang (MariaDB)

---

**버전**: 2.0
**최종 업데이트**: 2026-02-08
**개발 완료율**: 100%
