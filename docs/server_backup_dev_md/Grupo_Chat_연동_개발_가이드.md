# Grupo Pro Chat Room Script 연동 개발 가이드

**작성일**: 2026-01-14
**기준 계약**: 병원 정보 플랫폼 앱 개발계약서 (800만원)

---

## 📋 계약서 분석 결과

### ❌ **채팅 기능은 MVP 범위에 포함되지 않음**

**계약서 MVP 필수 기능 (4-5페이지):**
1. ✅ 회원 관리
2. ✅ 병원 검색 및 필터
3. ✅ 병원 상세 페이지
4. ✅ 후기 기능
5. ✅ 예약 기능
6. ✅ 병원 관계자 전용 대시보드
7. ✅ 관리자 페이지

**MVP 제외 기능 (6페이지):**
- 결제/정산 기능
- 병원 자동 심사/입점 절차
- 후기 인증
- 다중 소셜 로그인
- **원격 진료 기능** ❌
- 의료법 관련 검수 시스템

**→ 채팅 기능 언급 없음 = MVP 범위 밖**

---

## 💡 Grupo Pro Chat Room Script 정보

**CodeCanyon 제품:**
- 제품명: Grupo Pro Chat Room Script
- URL: https://codecanyon.net/item/grupo-pro-chat-room-script/25263116
- 가격: $29 (구매 완료)

**주요 기능:**
- 실시간 1:1 채팅
- 그룹 채팅룸
- 파일/이미지 공유
- 읽음/안읽음 표시
- 푸시 알림
- 사용자 관리

---

## 🎯 작업 내용 및 개발 리스트

### **Phase 1: Grupo Chat 소스 분석 및 설치 (1주)**

#### 1.1 Grupo Chat 소스 확인
- [ ] h:/carebaby_project 에서 Grupo 관련 파일 찾기
- [ ] Grupo Chat 압축 해제 및 구조 파악
- [ ] 데이터베이스 스키마 분석
- [ ] API 엔드포인트 문서화

#### 1.2 기술 스택 확인
- [ ] 프론트엔드 기술 (React/Vue/Vanilla JS)
- [ ] 백엔드 기술 (PHP/Node.js/Python)
- [ ] 데이터베이스 구조 (MySQL/MongoDB)
- [ ] 실시간 통신 방식 (WebSocket/Socket.io/Firebase)

#### 1.3 테스트 환경 구축
- [ ] Grupo Chat 독립 실행 환경 구축
- [ ] 로컬 테스트 (http://grupo-test.seoulbeauty.local)
- [ ] 기본 기능 동작 확인
- [ ] API 테스트

---

### **Phase 2: KiviCare와 연동 설계 (1주)**

#### 2.1 데이터베이스 통합 설계
```sql
-- KiviCare 사용자 → Grupo Chat 사용자 연결
CREATE TABLE chat_user_mapping (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    kivicare_user_id BIGINT NOT NULL,
    grupo_user_id BIGINT NOT NULL,
    user_type ENUM('patient', 'doctor', 'hospital_admin'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (kivicare_user_id)
);

-- 병원-환자 채팅방 매핑
CREATE TABLE hospital_chat_rooms (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hospital_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    grupo_room_id VARCHAR(255) NOT NULL,
    status ENUM('active', 'closed', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (hospital_id, patient_id),
    INDEX (grupo_room_id)
);

-- 채팅 메시지 백업 (분쟁 대비)
CREATE TABLE chat_message_backup (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    room_id BIGINT NOT NULL,
    sender_id BIGINT NOT NULL,
    message_type ENUM('text', 'image', 'file') NOT NULL,
    content TEXT NOT NULL,
    grupo_message_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (room_id, created_at)
);
```

#### 2.2 인증 통합
- [ ] KiviCare 세션 → Grupo Chat 인증 연동
- [ ] SSO (Single Sign-On) 구현
- [ ] JWT 토큰 공유 방식 설계
- [ ] 권한 관리 (환자/병원/관리자)

#### 2.3 API 래퍼 개발
```php
// KiviCare Modules/Chat/Http/Controllers/ChatController.php
class ChatController extends Controller
{
    // Grupo API 호출
    private function callGrupoApi($endpoint, $data) {
        // Grupo Chat API 호출 로직
    }

    // 채팅방 생성
    public function createRoom($hospitalId, $patientId) {
        // 1. Grupo에 채팅방 생성 요청
        // 2. hospital_chat_rooms 테이블에 매핑 저장
        // 3. 양쪽 사용자에게 알림 전송
    }

    // 메시지 전송
    public function sendMessage($roomId, $message) {
        // 1. Grupo API로 메시지 전송
        // 2. chat_message_backup에 백업
    }
}
```

---

### **Phase 3: 프론트엔드 통합 (2주)**

#### 3.1 KiviCare 채팅 UI 추가
- [ ] **병원 상세 페이지**: "상담하기" 버튼 추가
- [ ] **채팅 목록 페이지**: 사용자별 채팅방 목록
- [ ] **채팅 화면**: Grupo Chat 임베드 또는 커스텀 UI

**경로:**
```
kivicare-laravel-web-v1.9.0/
├── Modules/
│   └── Chat/
│       ├── Http/Controllers/ChatController.php
│       ├── Models/ChatRoom.php
│       ├── Resources/
│       │   └── views/
│       │       ├── chat-list.blade.php
│       │       └── chat-room.blade.php
│       └── Routes/
│           └── web.php
└── resources/
    └── js/
        └── components/
            └── Chat/
                ├── ChatButton.vue
                ├── ChatList.vue
                └── ChatRoom.vue
```

#### 3.2 Vue 3 컴포넌트 개발
```vue
<!-- ChatButton.vue -->
<template>
  <button @click="openChat" class="chat-button">
    <i class="fa fa-comments"></i> 상담하기
  </button>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  hospitalId: Number,
  hospitalName: String
})

const openChat = async () => {
  // 1. 채팅방 생성/조회 API 호출
  // 2. Grupo Chat 화면으로 이동 또는 모달 표시
}
</script>
```

#### 3.3 Grupo Chat 임베드
- [ ] iframe 방식 vs 직접 통합 선택
- [ ] 디자인 커스터마이징 (KiviCare 테마 적용)
- [ ] 모바일 반응형 대응

---

### **Phase 4: 병원 관리자 패널 (1주)**

#### 4.1 병원 관리자 채팅 대시보드
```
병원 관리자 패널 (kivicare.seoulbeauty.app/admin)
├── 채팅 관리 메뉴 추가
│   ├── 대기 중인 상담 (N건)
│   ├── 진행 중인 상담 (N건)
│   ├── 완료된 상담 (N건)
│   └── 채팅 통계 (일별/월별)
└── 채팅방 목록
    ├── 환자 정보 표시
    ├── 마지막 메시지 시간
    ├── 읽지 않은 메시지 수
    └── 채팅 시작/종료 버튼
```

#### 4.2 응답 템플릿 기능
```sql
-- 자주 쓰는 답변 템플릿
CREATE TABLE chat_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hospital_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100),
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (hospital_id, category)
);
```

- [ ] 템플릿 등록/수정/삭제
- [ ] 채팅 중 빠른 답변 선택
- [ ] 사용 빈도 통계

---

### **Phase 5: 알림 시스템 (1주)**

#### 5.1 실시간 알림
- [ ] **새 메시지 알림**: 브라우저 푸시 알림
- [ ] **상담 요청 알림**: 병원 관리자에게 즉시 알림
- [ ] **읽음 표시**: 메시지 읽음/안읽음 상태

#### 5.2 Firebase Cloud Messaging (FCM) 연동
```php
// Modules/Chat/Services/NotificationService.php
class NotificationService
{
    public function sendPushNotification($userId, $message) {
        // FCM API 호출
        $fcmToken = User::find($userId)->fcm_token;

        $notification = [
            'title' => '새로운 메시지',
            'body' => $message,
            'click_action' => route('chat.room', ['id' => $roomId])
        ];

        // Firebase HTTP v1 API 호출
    }
}
```

#### 5.3 이메일 알림 (선택)
- [ ] 새 상담 요청 시 이메일 발송
- [ ] 24시간 미응답 시 리마인더
- [ ] 상담 완료 안내 이메일

---

### **Phase 6: 의료법 대응 및 보안 (1주)**

#### 6.1 의료법 준수 기능
```php
// 채팅 시작 시 자동 안내 메시지
$welcomeMessage = "
⚠️ 안내사항
• 본 상담은 의료 정보 제공을 위한 참고용입니다.
• 정확한 진단은 병원 내 대면 상담을 통해 이루어집니다.
• 개인 의료 정보(진단서, 처방전)를 함부로 공유하지 마세요.
";
```

- [ ] 채팅방 생성 시 안내 메시지 자동 발송
- [ ] 과장 광고 필터링 (금지 키워드)
- [ ] 의료 상담 기록 보관 (5년)

#### 6.2 보안 강화
- [ ] 채팅 내용 암호화 (end-to-end)
- [ ] 개인정보 마스킹 (주민번호, 연락처)
- [ ] 관리자 로그 기록 (누가 언제 무엇을 조회)
- [ ] HTTPS 강제 적용

#### 6.3 신고/차단 기능
```sql
-- 신고 테이블
CREATE TABLE chat_reports (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    room_id BIGINT NOT NULL,
    reporter_id BIGINT NOT NULL,
    reported_user_id BIGINT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

- [ ] 사용자 신고 기능
- [ ] 관리자 검토 및 조치
- [ ] 악성 사용자 차단

---

### **Phase 7: 테스트 및 배포 (1주)**

#### 7.1 기능 테스트
- [ ] 회원가입 → 채팅방 생성 → 메시지 송수신
- [ ] 병원 관리자 → 상담 요청 수락 → 응답
- [ ] 이미지/파일 전송
- [ ] 푸시 알림 동작
- [ ] 읽음 표시 동작

#### 7.2 성능 테스트
- [ ] 동시 접속 100명 테스트
- [ ] 메시지 전송 속도 (< 1초)
- [ ] 서버 부하 테스트
- [ ] 데이터베이스 쿼리 최적화

#### 7.3 배포
- [ ] 스테이징 환경 배포 (https://staging.kivicare.seoulbeauty.app)
- [ ] QA 테스트
- [ ] 프로덕션 배포 (https://kivicare.seoulbeauty.app)
- [ ] 모니터링 설정 (에러 로그, 성능 지표)

---

## 💰 예상 비용 및 일정

### **옵션 1: MVP 채팅 기능 (최소)**
**기간**: 4주
**비용**: 200만원 (계약 외 추가)

**포함 기능:**
- Grupo Chat 연동
- 병원↔환자 1:1 채팅
- 텍스트 + 이미지 전송
- 기본 알림

---

### **옵션 2: 표준 채팅 기능 (추천 ⭐)**
**기간**: 6주
**비용**: 350만원 (계약 외 추가)

**포함 기능:**
- 옵션 1 전체 +
- 병원 관리자 대시보드
- 응답 템플릿
- 푸시 알림 (FCM)
- 채팅 기록 관리

---

### **옵션 3: 고급 채팅 기능**
**기간**: 8주
**비용**: 500만원 (계약 외 추가)

**포함 기능:**
- 옵션 2 전체 +
- 파일 첨부 (PDF, 진료 기록)
- 자동응답 (FAQ 연동)
- 예약 연결 (채팅→예약 전환)
- 의료법 준수 자동화
- 통계 대시보드

---

## 🔧 기술 스택 제안

### **현재 KiviCare 스택 활용**
- **Backend**: Laravel 11.0 (기존 사용)
- **Frontend**: Vue 3 (기존 사용)
- **Database**: MySQL (기존 사용)
- **Chat Engine**: Grupo Pro Chat Room Script (구매 완료)

### **추가 필요**
- **실시간 통신**:
  - Grupo 자체 WebSocket 사용 (권장)
  - 또는 Socket.io 추가 설치
- **푸시 알림**: Firebase Cloud Messaging (무료)
- **이미지 저장**: Laravel Media Library (기존 사용)

---

## 📂 Grupo Chat 소스 위치 확인 필요

**확인해야 할 위치:**
```
h:/carebaby_project/
├── hospital_source/
│   └── [Grupo Chat 폴더?]
├── server_backup_dev/
│   └── [Grupo Chat 폴더?]
└── *.zip (Grupo Chat 압축 파일?)
```

**다음 작업:**
1. Grupo Chat 소스 파일 찾기
2. 압축 해제 및 구조 분석
3. 설치 가이드 확인
4. 테스트 환경 구축

---

## ✅ 개발 체크리스트

### **Phase 1: 분석 및 설치 (1주)**
- [ ] Grupo Chat 소스 파일 확인
- [ ] 데이터베이스 스키마 분석
- [ ] API 문서 정리
- [ ] 독립 테스트 환경 구축

### **Phase 2: 연동 설계 (1주)**
- [ ] 데이터베이스 통합 설계
- [ ] 인증 시스템 통합 설계
- [ ] API 래퍼 개발

### **Phase 3: 프론트엔드 통합 (2주)**
- [ ] Chat 모듈 생성 (Laravel)
- [ ] Vue 3 컴포넌트 개발
- [ ] 병원 상세 페이지에 "상담하기" 버튼 추가
- [ ] 채팅 목록/채팅방 UI 구현

### **Phase 4: 병원 관리자 패널 (1주)**
- [ ] 관리자 채팅 메뉴 추가
- [ ] 대기 중 상담 목록
- [ ] 응답 템플릿 기능

### **Phase 5: 알림 시스템 (1주)**
- [ ] 브라우저 푸시 알림
- [ ] FCM 연동
- [ ] 이메일 알림 (선택)

### **Phase 6: 의료법 대응 및 보안 (1주)**
- [ ] 안내 메시지 자동 발송
- [ ] 채팅 암호화
- [ ] 신고/차단 기능

### **Phase 7: 테스트 및 배포 (1주)**
- [ ] 기능 테스트
- [ ] 성능 테스트
- [ ] 스테이징 배포
- [ ] 프로덕션 배포

---

## 🚨 주의사항

### **계약 범위 확인**
- 현재 계약(800만원)에는 **채팅 기능이 포함되지 않음**
- 추가 개발 시 **별도 협의 및 견적 필요**
- "갑"과 "을"이 서로 상의하여 진행

### **의료법 준수**
- 채팅 내용은 의료 상담 기록으로 간주
- 5년간 보관 의무
- 과장 광고 금지
- 개인정보 보호

### **보안 및 개인정보**
- 채팅 내용 암호화 필수
- HTTPS 강제 적용
- 개인정보 마스킹
- 관리자 접근 로그

---

## 📞 다음 단계

1. **Grupo Chat 소스 확인**
   - h:/carebaby_project 에서 파일 찾기
   - 압축 해제 및 설치 가이드 확인

2. **기능 범위 협의**
   - MVP (옵션 1) vs 표준 (옵션 2) vs 고급 (옵션 3)
   - 예산 및 일정 확인

3. **개발 착수**
   - Phase 1부터 순차 진행
   - 주 1회 진행 상황 보고

---

**작성**: Claude (2026-01-14)
**참고 계약서**: 병원_정보_플랫폼_앱_계약서.pdf.pdf
**다음 문서**: Grupo_Chat_기술분석.md (소스 확인 후 작성)
