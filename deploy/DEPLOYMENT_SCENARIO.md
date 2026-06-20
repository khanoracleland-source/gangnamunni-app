# 개발/운영 환경 분리 및 배포 시나리오

## 📋 목차
1. [환경 구조](#환경-구조)
2. [새 기능 추가 워크플로우](#새-기능-추가-워크플로우)
3. [점검 모드 시나리오](#점검-모드-시나리오)
4. [배포 자동화](#배포-자동화)

---

## 환경 구조

### 디렉토리 구조
```
/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/
├── hairwang_full/                          # 운영 환경 (PRODUCTION)
│   ├── gangnamunni/
│   │   ├── .maintenance                   # 점검 모드 플래그 파일
│   │   ├── maintenance_config.php         # 점검 설정
│   │   ├── index.php                      # 메인 (점검 체크 포함)
│   │   ├── admin_ads.php
│   │   └── ...
│   └── ...
│
└── hairwang_dev/                           # 개발 환경 (DEV)
    ├── gangnamunni/
    │   ├── index.php
    │   ├── admin_ads.php
    │   └── ...
    └── ...
```

### URL 매핑
- **운영:** https://hms.seoulbeauty.app/hairwang/gangnamunni/
- **개발:** https://hms.seoulbeauty.app/hairwang_dev/gangnamunni/
- **점검:** https://hms.seoulbeauty.app/hairwang/gangnamunni/maintenance.php

---

## 새 기능 추가 워크플로우

### 시나리오 1: AI 이미지 편집 기능 추가

#### Phase 1: 개발 환경 준비
```bash
# 1. 운영 환경을 개발 환경으로 클론
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/
./deploy/clone_to_dev.sh

# 결과:
# ✓ hairwang_full/ → hairwang_dev/ 복사
# ✓ DB: hairwang → hairwang_dev (테이블만 복사, 데이터 제외 옵션)
# ✓ 개발 환경 설정 자동 적용 (DB 호스트, 디버그 모드 등)
```

**자동 실행 내역:**
1. rsync로 파일 복사 (제외: logs/, cache/, sessions/)
2. `hairwang_dev/gangnamunni/dbconfig.php` DB명 변경
3. DEV 배너 추가 (모든 페이지 상단에 "🔧 개발 환경" 표시)
4. 에러 로깅 활성화

#### Phase 2: 개발 및 테스트
```bash
# 2. 개발 환경에서 새 기능 개발
# URL: https://hms.seoulbeauty.app/hairwang_dev/gangnamunni/

# 개발자 작업:
# - hairwang_dev/gangnamunni/ai_image_editor.php 생성
# - 기능 구현
# - 테스트 데이터로 검증
```

**개발 환경 특징:**
- 에러 메시지 상세 표시
- 디버그 모드 활성화
- 테스트 DB 사용 (실제 데이터 영향 없음)
- 상단에 "🔧 개발 환경" 배너

#### Phase 3: 운영 반영 (배포)
```bash
# 3. 배포 스크립트 실행
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/
./deploy/deploy_to_production.sh

# 대화형 프롬프트:
# ? 배포할 파일/폴더를 선택하세요:
#   [x] ai_image_editor.php (신규)
#   [x] ai/openai_config.php (수정)
#   [ ] admin_ads.php (변경 없음)
#
# ? 점검 모드를 활성화할까요? (y/n): y
# ? 점검 메시지: "AI 이미지 편집 기능 추가 중입니다. 약 2분 소요됩니다."
# ? 즉시 배포할까요? (y/n): y

# 자동 실행 프로세스:
# [1/6] 점검 모드 활성화... ✓
# [2/6] DB 백업 생성... ✓ (backup_20260208_143052.sql)
# [3/6] 선택한 파일 복사... ✓ (2개 파일)
# [4/6] 권한 설정... ✓
# [5/6] 캐시 클리어... ✓
# [6/6] 점검 모드 해제... ✓
#
# ✅ 배포 완료! (소요 시간: 1분 32초)
```

#### Phase 4: 배포 후 검증
```bash
# 4. 운영 환경 검증
# URL: https://hms.seoulbeauty.app/hairwang/gangnamunni/

# 체크리스트:
# ✓ 새 기능 정상 동작 확인
# ✓ 기존 기능 영향 없음 확인
# ✓ 에러 로그 확인 (deploy/logs/deployment_20260208_143052.log)
```

---

## 점검 모드 시나리오

### 시나리오 2: 야간 DB 마이그레이션 점검

#### Phase 1: 점검 예약
```bash
# 관리자 페이지에서 점검 스케줄 설정
# URL: https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_maintenance.php

# 설정:
# - 시작 시간: 2026-02-09 02:00
# - 종료 시간: 2026-02-09 04:00
# - 점검 메시지: "DB 성능 개선 작업 중입니다"
# - 관리자 우회 허용: ON
# - 자동 종료: ON
```

**점검 설정 저장 위치:**
```json
// gangnamunni/.maintenance
{
    "enabled": true,
    "start_time": "2026-02-09 02:00:00",
    "end_time": "2026-02-09 04:00:00",
    "message": "DB 성능 개선 작업 중입니다",
    "admin_bypass": true,
    "auto_end": true,
    "allowed_ips": ["5.104.82.35", "127.0.0.1"]
}
```

#### Phase 2: 점검 모드 활성화
```bash
# 방법 1: 수동 활성화
./deploy/maintenance.sh on "긴급 점검 중입니다"

# 방법 2: 관리자 페이지
# admin_maintenance.php에서 "점검 모드 시작" 버튼 클릭

# 방법 3: 스케줄 (cron)
# 02:00에 자동 활성화
```

**활성화 시 동작:**
1. `.maintenance` 파일 생성
2. 모든 PHP 페이지 상단에서 점검 체크
3. 일반 사용자 → maintenance.php 리다이렉트
4. 관리자 IP → 정상 접속 (상단에 "🔧 점검 모드" 배너)

#### Phase 3: 점검 중 화면

**일반 사용자 화면 (maintenance.php):**
```
┌─────────────────────────────────────────┐
│                                         │
│         🔧  시스템 점검 중              │
│                                         │
│   DB 성능 개선 작업 중입니다            │
│                                         │
│   점검 시간: 02:00 ~ 04:00 (약 2시간)   │
│   현재 시간: 02:45                      │
│                                         │
│   [============>........] 62%           │
│                                         │
│   잠시 후 다시 접속해주세요             │
│                                         │
│   문의: support@seoulbeauty.app         │
│                                         │
└─────────────────────────────────────────┘

자동 새로고침: 30초 후
```

**관리자 화면 (우회 접속):**
```
┌─────────────────────────────────────────┐
│ 🔧 점검 모드 활성화됨 - 일반 사용자 차단│
│ [점검 모드 해제]                        │
└─────────────────────────────────────────┘

← 정상적으로 사이트 이용 가능 →
```

#### Phase 4: 점검 종료
```bash
# 방법 1: 수동 종료
./deploy/maintenance.sh off

# 방법 2: 관리자 페이지
# admin_maintenance.php에서 "점검 모드 해제" 버튼

# 방법 3: 자동 종료 (스케줄)
# 04:00에 자동 해제
```

---

## 배포 자동화

### 배포 스크립트 구조

#### 1. clone_to_dev.sh
```bash
#!/bin/bash
# 운영 → 개발 환경 클론

기능:
- rsync로 파일 복사 (제외: logs/, cache/, .git/)
- DB 클론 (옵션: 구조만 / 구조+데이터)
- 개발 설정 자동 적용
- DEV 배너 삽입
```

#### 2. deploy_to_production.sh
```bash
#!/bin/bash
# 개발 → 운영 배포

1. 사전 체크
   - 개발 환경 존재 확인
   - 변경된 파일 목록 출력
   - 배포 확인 프롬프트

2. 백업
   - 현재 운영 파일 백업 (타임스탬프)
   - DB 백업 (선택 사항)

3. 점검 모드
   - 점검 모드 활성화 (선택 사항)
   - 사용자 차단

4. 배포
   - 선택한 파일만 복사
   - 권한 설정 (644 파일, 755 디렉토리)
   - 캐시 클리어

5. 검증
   - 구문 오류 체크 (php -l)
   - 주요 페이지 HTTP 200 확인

6. 완료
   - 점검 모드 해제
   - 배포 로그 저장
   - Slack/이메일 알림 (선택)
```

#### 3. maintenance.sh
```bash
#!/bin/bash
# 점검 모드 제어

사용법:
  ./maintenance.sh on "점검 메시지"     # 활성화
  ./maintenance.sh off                  # 비활성화
  ./maintenance.sh status               # 상태 확인
  ./maintenance.sh schedule "..." 02:00 04:00  # 예약
```

#### 4. rollback.sh
```bash
#!/bin/bash
# 배포 롤백

기능:
- 백업 목록 표시
- 선택한 백업으로 복원
- DB 롤백 (선택 사항)
- 캐시 클리어
```

---

## 점검 모드 구현 세부사항

### 1. maintenance_config.php
```php
<?php
/**
 * 점검 모드 설정 및 체크
 */

define('MAINTENANCE_FILE', __DIR__ . '/.maintenance');
define('MAINTENANCE_PAGE', __DIR__ . '/maintenance.php');

function isMaintenanceMode() {
    if (!file_exists(MAINTENANCE_FILE)) {
        return false;
    }

    $config = json_decode(file_get_contents(MAINTENANCE_FILE), true);

    // 시간 범위 체크
    if (isset($config['start_time']) && isset($config['end_time'])) {
        $now = time();
        $start = strtotime($config['start_time']);
        $end = strtotime($config['end_time']);

        if ($now < $start || $now > $end) {
            return false;
        }
    }

    // 관리자 IP 체크
    if ($config['admin_bypass'] && in_array($_SERVER['REMOTE_ADDR'], $config['allowed_ips'])) {
        return false;
    }

    return $config['enabled'] ?? false;
}

function checkMaintenance() {
    if (isMaintenanceMode()) {
        header('Location: ' . MAINTENANCE_PAGE);
        exit;
    }
}

// 점검 모드 활성화
function enableMaintenance($message, $startTime = null, $endTime = null) {
    $config = [
        'enabled' => true,
        'message' => $message,
        'start_time' => $startTime ?? date('Y-m-d H:i:s'),
        'end_time' => $endTime,
        'admin_bypass' => true,
        'allowed_ips' => ['127.0.0.1', $_SERVER['SERVER_ADDR']],
        'created_at' => date('Y-m-d H:i:s'),
    ];

    file_put_contents(MAINTENANCE_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

// 점검 모드 비활성화
function disableMaintenance() {
    if (file_exists(MAINTENANCE_FILE)) {
        unlink(MAINTENANCE_FILE);
    }
}
?>
```

### 2. 모든 페이지 상단 삽입
```php
<?php
// 모든 공개 페이지 최상단
require_once __DIR__ . '/maintenance_config.php';
checkMaintenance();
?>
```

### 3. maintenance.php (점검 페이지)
- 점검 메시지 표시
- 진행률 바 (시간 기반)
- 예상 종료 시간
- 자동 새로고침 (30초)
- 반응형 디자인

---

## 롤백 시나리오

### 시나리오 3: 배포 후 오류 발생

```bash
# 1. 문제 발견
# 배포 후 5분 뒤, 결제 페이지 500 에러 발생

# 2. 즉시 롤백
./deploy/rollback.sh

# 프롬프트:
# 사용 가능한 백업:
# [1] 2026-02-08 14:30 (배포 직전) - 권장
# [2] 2026-02-08 10:15
# [3] 2026-02-07 22:00
#
# 선택 (1-3): 1
#
# DB도 롤백할까요? (y/n): n
#
# [1/4] 점검 모드 활성화... ✓
# [2/4] 백업 복원 중... ✓
# [3/4] 권한 설정... ✓
# [4/4] 점검 모드 해제... ✓
#
# ✅ 롤백 완료! (소요 시간: 45초)
```

---

## 크론 스케줄 예시

```cron
# 개발 환경 자동 동기화 (매일 새벽 3시)
0 3 * * * /var/www/.../deploy/clone_to_dev.sh --auto

# 점검 모드 자동 시작 (예약된 시간)
0 2 * * * /var/www/.../deploy/maintenance.sh check_schedule

# 백업 자동 삭제 (7일 이상 된 백업)
0 4 * * * find /var/www/.../deploy/backups/ -name "*.tar.gz" -mtime +7 -delete
```

---

## 모니터링 및 알림

### 배포 알림 (Slack/이메일)
```
✅ 배포 완료

환경: Production
배포자: admin
시간: 2026-02-08 14:30:52
파일: 2개 (ai_image_editor.php, openai_config.php)
점검 시간: 1분 32초

로그: /deploy/logs/deployment_20260208_143052.log
```

### 점검 모드 알림
```
🔧 점검 모드 활성화

메시지: DB 성능 개선 작업 중입니다
시작: 2026-02-09 02:00
종료: 2026-02-09 04:00 (예정)
관리자 우회: ON
```

---

## 체크리스트

### 배포 전
- [ ] 개발 환경에서 충분히 테스트
- [ ] 변경된 파일 목록 확인
- [ ] DB 마이그레이션 스크립트 준비 (필요시)
- [ ] 롤백 계획 수립
- [ ] 점검 시간 사용자 공지 (대규모 변경 시)

### 배포 중
- [ ] 점검 모드 활성화
- [ ] 백업 생성 확인
- [ ] 파일 복사 오류 없음 확인
- [ ] 권한 설정 확인

### 배포 후
- [ ] 주요 기능 동작 확인
- [ ] 에러 로그 확인
- [ ] 성능 모니터링
- [ ] 점검 모드 해제
- [ ] 배포 완료 알림

---

## 디렉토리 구조 (전체)

```
/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/
├── hairwang_full/                    # 운영 환경
│   ├── gangnamunni/
│   │   ├── .maintenance             # 점검 설정 (JSON)
│   │   ├── maintenance_config.php   # 점검 모드 라이브러리
│   │   ├── maintenance.php          # 점검 페이지
│   │   ├── admin_maintenance.php    # 점검 관리 페이지
│   │   └── ...
│   └── ...
│
├── hairwang_dev/                     # 개발 환경
│   └── gangnamunni/
│       └── ...
│
└── deploy/                           # 배포 스크립트
    ├── clone_to_dev.sh              # 개발 환경 클론
    ├── deploy_to_production.sh      # 운영 배포
    ├── maintenance.sh               # 점검 모드 제어
    ├── rollback.sh                  # 롤백
    ├── backups/                     # 백업 저장소
    │   ├── backup_20260208_143052.tar.gz
    │   └── ...
    └── logs/                        # 배포 로그
        ├── deployment_20260208_143052.log
        └── ...
```

---

## 요약

| 시나리오 | 소요 시간 | 다운타임 | 리스크 |
|---------|----------|---------|-------|
| 개발 → 배포 | 2-5분 | 1-2분 | 낮음 (롤백 가능) |
| 점검 모드 활성화 | 즉시 | 점검 시간 | 없음 |
| 롤백 | 1분 이내 | 30초-1분 | 매우 낮음 |
| 긴급 패치 | 1분 이내 | 없음 (Hot Fix) | 중간 |

**권장 배포 시간:** 새벽 2-4시 (트래픽 최소)
**필수 백업 주기:** 배포 전 매번
**백업 보관 기간:** 7일
