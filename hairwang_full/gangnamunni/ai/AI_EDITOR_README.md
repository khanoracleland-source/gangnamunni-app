# AI 광고 에디터 가이드

## 개요

OpenAI GPT-4 및 DALL-E를 활용한 자동 광고 생성 및 A/B 테스트 시스템입니다.

## 기능

### 1. AI 광고 카피 생성 (GPT-4)
- 병원명, 시술명 입력 시 자동으로 광고 카피 생성
- 톤 & 스타일 선택 (전문적, 친근한, 고급스러운, 활기찬)
- 타겟 고객 선택 (20-30대 여성, 30-40대 여성 등)
- 3가지 버전의 카피 자동 생성 (제목, 부제목, 설명, CTA)

### 2. AI 광고 이미지 생성 (DALL-E 3)
- 텍스트 프롬프트 → 고품질 이미지 자동 생성
- 크기 선택: 정사각형(1024x1024), 가로형(1792x1024), 세로형(1024x1792)
- 품질 선택: 표준 또는 HD
- 생성된 이미지 자동 저장 (`assets/images/ai_generated/`)

### 3. A/B 테스트 버전 생성
- 원본 카피 기반으로 최적화된 B 버전 자동 생성
- 긴급성, 감성 어필, 구체적 숫자 강화
- 버전 A (원본) vs 버전 B (최적화) 비교

### 4. A/B 테스트 성과 추적
- 실시간 노출 수, 클릭 수, 전환 수 추적
- CTR (클릭률), 전환율 자동 계산
- 승리 버전 자동 판정
- Chart.js 기반 시각화 대시보드

## 파일 구조

```
ai/
├── openai_config.php        # OpenAI API 설정 및 헬퍼 함수
└── AI_EDITOR_README.md       # 이 문서

ai_ad_editor.php              # AI 광고 에디터 UI
ab_test_dashboard.php         # A/B 테스트 대시보드

sql_dumps/
└── create_ab_test_tables.sql # A/B 테스트 테이블 생성
```

## 설치

### 1. OpenAI API 키 설정

`ai/openai_config.php` 파일에서 API 키 설정:

```php
define('OPENAI_API_KEY', 'sk-proj-YOUR_API_KEY_HERE');
define('OPENAI_TEST_MODE', false);  // 실제 사용 시 false
```

**API 키 발급:**
1. https://platform.openai.com 회원가입
2. API Keys 메뉴에서 새 키 생성
3. 요금제 설정 (GPT-4: $10/1M tokens, DALL-E 3: $0.04/image)

### 2. DB 테이블 생성

```bash
mysql -u root -p hairwang < sql_dumps/create_ab_test_tables.sql
```

### 3. 이미지 저장 디렉토리 생성

```bash
mkdir -p assets/images/ai_generated
chmod 755 assets/images/ai_generated
```

## 사용 방법

### AI 광고 에디터

1. **광고 카피 생성**
   ```
   ai_ad_editor.php 접속
   └─> 병원명, 시술명 입력
       ├─> 톤 & 스타일 선택
       ├─> 타겟 고객 선택
       └─> "AI 카피 생성하기" 클릭
            └─> 3가지 버전의 광고 카피 생성
                 └─> 원하는 버전 선택
   ```

2. **광고 이미지 생성**
   ```
   이미지 프롬프트 입력 (영문 권장)
   예: "A modern Korean beauty clinic interior with soft lighting"
   └─> 크기, 품질 선택
       └─> "AI 이미지 생성하기" 클릭
            └─> DALL-E 이미지 생성 (30초~1분 소요)
                 └─> 자동 저장 및 미리보기
   ```

3. **A/B 테스트 버전 생성**
   ```
   원본 제목/부제목 입력
   └─> "A/B 테스트 버전 생성" 클릭
       └─> 버전 A (원본) + 버전 B (최적화) 생성
   ```

4. **광고 저장**
   ```
   최종 제목, 부제목, 이미지 URL 확인
   └─> 광고 유형 선택 (배너/프리미엄)
       └─> "광고 저장하기" 클릭
            └─> admin_ads.php로 리다이렉트
   ```

### A/B 테스트 대시보드

```
ab_test_dashboard.php 접속
├─> 전체 통계 확인 (활성 테스트, 총 노출/클릭/전환)
├─> 테스트 목록에서 특정 테스트 선택
└─> 상세 페이지
     ├─> 버전별 성과 비교
     ├─> CTR, 전환율 확인
     └─> 차트 시각화
```

## API 비용

### GPT-4o (텍스트 생성)
- Input: $2.50 / 1M tokens
- Output: $10.00 / 1M tokens
- 광고 카피 1회 생성: 약 500 tokens = **$0.005** (약 7원)

### DALL-E 3 (이미지 생성)
- 1024x1024 (표준): $0.040/image
- 1024x1024 (HD): $0.080/image
- 1792x1024 (HD): $0.120/image
- **평균 이미지 1장: $0.04~0.12** (약 50~150원)

### 월 예상 비용
- 광고 카피 100개 생성: $0.50 (약 700원)
- 이미지 50장 생성: $2~6 (약 3,000~8,000원)
- **월 총 비용: $3~7** (약 4,000~10,000원)

## 테스트 모드

개발 중에는 `OPENAI_TEST_MODE = true`로 설정하여 API 호출 없이 더미 데이터로 테스트 가능:

```php
define('OPENAI_TEST_MODE', true);  // ai/openai_config.php
```

**테스트 모드 동작:**
- `generateAdCopy()`: 더미 광고 카피 반환
- `generateAdImage()`: placeholder.com 이미지 반환
- `generateABTestVariants()`: 샘플 A/B 버전 반환

## 프롬프트 최적화 팁

### 광고 카피 생성
```
✅ 좋은 예:
- 병원명: "강남 성형외과"
- 시술명: "쌍꺼풀 수술"
- 톤: "친근하고 편안한"

❌ 나쁜 예:
- 병원명: "병원" (너무 일반적)
- 시술명: "성형" (구체적이지 않음)
```

### 이미지 생성 (영문 프롬프트 권장)
```
✅ 좋은 예:
"A modern Korean plastic surgery clinic interior, bright and clean white walls,
comfortable patient consultation room, professional medical equipment,
soft natural lighting, minimalist design, photorealistic, high quality"

❌ 나쁜 예:
"병원 사진" (한글, 너무 간단함)
"clinic" (세부 정보 부족)
```

**프롬프트 키워드:**
- 스타일: photorealistic, minimalist, modern, elegant, professional
- 조명: soft lighting, natural lighting, bright, warm
- 분위기: clean, comfortable, luxurious, inviting, safe
- 품질: high quality, detailed, sharp focus, 4k

## A/B 테스트 모범 사례

### 1. 테스트 기간
- 최소 7일 이상 실행
- 통계적 유의성을 위해 최소 100회 이상 노출 권장

### 2. 변수 제어
- 한 번에 하나의 요소만 변경 (제목 vs 제목, 이미지 vs 이미지)
- 여러 요소 동시 변경 시 어떤 요소가 효과적인지 판단 불가

### 3. 성과 지표
- **CTR (클릭률)**: 광고 효과성 측정
- **전환율**: 실제 비즈니스 성과 측정
- 전환율이 CTR보다 중요 (클릭만 많고 전환 없으면 의미 없음)

### 4. 승자 판정 기준
- CTR 차이 > 10%
- 전환율 차이 > 5%
- 최소 50회 이상 클릭 수

## DB 스키마

### gangnamunni_ab_tests (A/B 테스트 실험)
```sql
CREATE TABLE gangnamunni_ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_name VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    status VARCHAR(20) DEFAULT 'running',  -- running, paused, completed
    winner_variant VARCHAR(10),            -- A, B
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### gangnamunni_ab_variants (테스트 버전)
```sql
CREATE TABLE gangnamunni_ab_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    variant_name VARCHAR(10) NOT NULL,  -- A, B
    ad_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(200),
    image_url TEXT,
    impressions INT DEFAULT 0,           -- 노출 수
    clicks INT DEFAULT 0,                -- 클릭 수
    conversions INT DEFAULT 0,           -- 전환 수
    ctr DECIMAL(5,2) DEFAULT 0,          -- 클릭률 (%)
    conversion_rate DECIMAL(5,2) DEFAULT 0,  -- 전환율 (%)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### gangnamunni_ab_events (이벤트 로그)
```sql
CREATE TABLE gangnamunni_ab_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    variant_id INT NOT NULL,
    event_type VARCHAR(20) NOT NULL,  -- impression, click, conversion
    user_id INT,
    session_id VARCHAR(100),
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 자동화

### 통계 자동 업데이트
이벤트 로그 INSERT 시 트리거가 자동으로 통계 업데이트:
```sql
CREATE TRIGGER update_ab_stats_after_event
AFTER INSERT ON gangnamunni_ab_events
FOR EACH ROW
BEGIN
    -- impressions, clicks, conversions 자동 카운트
    -- CTR, conversion_rate 자동 계산
END;
```

### 테스트 자동 종료
7일 경과한 테스트를 매일 자동 종료:
```sql
CREATE EVENT auto_complete_ab_tests
ON SCHEDULE EVERY 1 DAY
DO
    UPDATE gangnamunni_ab_tests
    SET status = 'completed'
    WHERE start_date < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## 문제 해결

### OpenAI API 오류
```
Error: Incorrect API key provided
→ ai/openai_config.php에서 API 키 확인

Error: Rate limit exceeded
→ 요금제 확인 및 사용량 제한 조정 (platform.openai.com)

Error: Model not found
→ GPT-4 API 접근 권한 확인 (별도 신청 필요할 수 있음)
```

### 이미지 저장 실패
```
Error: Failed to save image locally
→ assets/images/ai_generated/ 디렉토리 권한 확인
→ chmod 755 assets/images/ai_generated/

Error: cURL error
→ 서버 외부 연결 차단 여부 확인 (방화벽)
```

### A/B 테스트 통계 미반영
```
통계가 0으로 표시되는 경우:
→ MySQL 트리거 생성 확인: SHOW TRIGGERS;
→ Event Scheduler 활성화: SET GLOBAL event_scheduler = ON;
```

## 프로덕션 체크리스트

- [ ] OpenAI API 키 설정 (실제 키)
- [ ] `OPENAI_TEST_MODE = false` 설정
- [ ] DB 테이블 생성 확인
- [ ] 이미지 저장 디렉토리 생성 및 권한 설정
- [ ] OpenAI 요금제 설정 및 예산 알림 설정
- [ ] A/B 테스트 자동 종료 이벤트 활성화
- [ ] 통계 트리거 정상 동작 확인

## 참고 자료

- OpenAI API 문서: https://platform.openai.com/docs
- GPT-4 가이드: https://platform.openai.com/docs/models/gpt-4
- DALL-E 3 가이드: https://platform.openai.com/docs/guides/images
- A/B 테스트 모범 사례: https://vwo.com/ab-testing/
