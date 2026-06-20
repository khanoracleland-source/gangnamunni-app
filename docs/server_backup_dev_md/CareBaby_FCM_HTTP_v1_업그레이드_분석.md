# CareBaby FCM HTTP v1 업그레이드 분석

**작성일**: 2025-12-08
**현재 방식**: FCM Legacy HTTP API (Server Key)
**목표 방식**: FCM HTTP v1 API (OAuth 2.0)

---

## 1. 왜 FCM HTTP v1으로 업그레이드해야 하는가?

### 1.1 Google의 공식 발표

```
⚠️ FCM Legacy API 지원 중단 예정

Google 공식 입장:
- Legacy HTTP API는 "deprecated" 상태
- 신규 기능 추가 없음
- 향후 완전 중단 예정 (정확한 날짜 미정)
- HTTP v1 API로 마이그레이션 강력 권장
```

### 1.2 업그레이드 필수 이유

#### 🔴 서비스 중단 위험
```
Legacy API 중단 시:
- 모든 푸시 알림 발송 불가
- 앱 사용자에게 알림 도달 불가
- 주문/예약 알림 누락
- 채팅 알림 누락
- 고객 이탈 위험
```

#### 🟡 보안 강화
```
Legacy API 문제:
- Server Key가 노출되면 누구나 푸시 발송 가능
- 키 관리가 어려움
- 권한 제어 불가

HTTP v1 API 장점:
- OAuth 2.0 기반 인증
- Service Account로 권한 관리
- 토큰 자동 갱신
- 더 안전한 인증 구조
```

#### 🟢 기능 향상
```
HTTP v1에서만 지원되는 기능:
- 플랫폼별 맞춤 메시지 (Android/iOS/Web)
- 더 세밀한 메시지 옵션
- 향상된 에러 처리
- 더 나은 전송 결과 분석
- 조건부 메시지 발송
- 이미지/버튼 등 리치 메시지
```

#### 🔵 성능 개선
```
HTTP v1 장점:
- 더 빠른 메시지 전송
- 더 높은 전송 성공률
- 향상된 메시지 추적
- 더 나은 에러 리포팅
```

### 1.3 Legacy vs HTTP v1 비교

| 항목 | Legacy HTTP API | HTTP v1 API |
|------|-----------------|-------------|
| **인증** | Server Key (고정) | OAuth 2.0 (동적) |
| **보안** | 낮음 | 높음 |
| **엔드포인트** | `/fcm/send` | `/v1/projects/{id}/messages:send` |
| **다중 토큰** | `registration_ids` 배열 | 개별 요청 |
| **플랫폼 맞춤** | 제한적 | 완전 지원 |
| **리치 메시지** | 제한적 | 완전 지원 |
| **에러 처리** | 기본 | 상세 |
| **지원 상태** | ❌ Deprecated | ✅ Active |

---

## 2. CareBaby 현재 FCM 구현 분석

### 2.1 현재 구현 위치

```
메인 함수: lib/function.lib.php
라인: 330-371
함수명: send_notification($tokens, $data, $type)
```

### 2.2 현재 코드 전문

```php
function send_notification($tokens, $data, $type){
    global $api;

    // 토큰 설정 (배열 또는 단일)
    if(is_array($tokens)){
        $fields[registration_ids] = $tokens;  // ❌ Legacy 방식
    }else{
        $fields[to] = $tokens;
    }

    // iOS 전용 notification 설정
    if($type=="ios"){
        $data[message] = $data[memo];
        $notification[body] = $data[memo];
        $notification[badge] = "1";
        $notification[sound] = "default";
        $fields[notification] = $notification;
    }

    $fields[priority] = 'high';
    $fields[content_available] = true;
    $fields[data] = $data;

    // ❌ Legacy 인증 방식 (Server Key)
    $headers = array(
        'Authorization:key =' . $api[api_firebase_key_id],
        'Content-Type: application/json'
    );

    $ch = curl_init();
    // ❌ Legacy 엔드포인트
    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/fcm/send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
```

### 2.3 FCM 사용 위치

| 파일 | 용도 | 호출 방식 |
|------|------|----------|
| `admin/push_list.php` | 관리자 푸시 발송 | Topic 발송 |
| `used_chat.php` | 중고마켓 채팅 알림 | 개별 토큰 |
| 기타 주문/예약 파일 | 주문/예약 알림 | 개별 토큰 |

### 2.4 현재 설정 저장 위치

```
테이블: nfor_api
필드: api_firebase_key_id (Server Key)
관리: admin/api_form.php
```

---

## 3. HTTP v1으로 업그레이드 작업

### 3.1 사전 준비 (Firebase Console)

```
Step 1: Firebase Console 접속
- https://console.firebase.google.com

Step 2: 프로젝트 설정 → 서비스 계정
- "새 비공개 키 생성" 클릭
- JSON 파일 다운로드

Step 3: Project ID 확인
- 프로젝트 설정 → 일반 탭
- 프로젝트 ID 복사
```

### 3.2 수정해야 할 파일

#### 파일 1: lib/function.lib.php (핵심)

**새로운 함수 추가:**

```php
/**
 * Firebase Access Token 획득 (OAuth 2.0)
 */
function getFirebaseAccessToken(){
    global $api;

    // Service Account JSON 파싱
    $serviceAccount = json_decode($api['api_firebase_service_account'], true);

    if(!$serviceAccount){
        return false;
    }

    // JWT 토큰 생성
    $now = time();
    $header = base64_encode(json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]));

    $payload = base64_encode(json_encode([
        'iss' => $serviceAccount['client_email'],
        'sub' => $serviceAccount['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ]));

    // 서명
    $signature = '';
    openssl_sign(
        "$header.$payload",
        $signature,
        $serviceAccount['private_key'],
        'SHA256'
    );
    $signature = base64_encode($signature);

    $jwt = "$header.$payload.$signature";

    // Access Token 요청
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

/**
 * FCM HTTP v1 푸시 발송
 */
function send_notification_v1($token, $data, $type){
    global $api;

    // Access Token 획득
    $accessToken = getFirebaseAccessToken();
    if(!$accessToken){
        return json_encode(['error' => 'Failed to get access token']);
    }

    $projectId = $api['api_firebase_project_id'];

    // HTTP v1 메시지 구조
    $message = [
        'message' => [
            'token' => $token,
            'data' => [
                'title' => $data['title'] ?? $data['subject'] ?? '',
                'body' => $data['memo'] ?? '',
                'url' => $data['url'] ?? '',
                'image' => $data['image'] ?? ''
            ]
        ]
    ];

    // Android 설정
    if($type == "android" || $type == "all"){
        $message['message']['android'] = [
            'priority' => 'high',
            'notification' => [
                'title' => $data['title'] ?? $data['subject'] ?? '',
                'body' => $data['memo'] ?? '',
                'sound' => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ]
        ];
    }

    // iOS 설정
    if($type == "ios" || $type == "all"){
        $message['message']['apns'] = [
            'headers' => [
                'apns-priority' => '10'
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $data['title'] ?? $data['subject'] ?? '',
                        'body' => $data['memo'] ?? ''
                    ],
                    'badge' => 1,
                    'sound' => 'default'
                ]
            ]
        ];
    }

    // HTTP v1 엔드포인트로 발송
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,
        "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send"
    );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $result;
}

/**
 * FCM HTTP v1 Topic 발송
 */
function send_notification_topic_v1($topic, $data, $type){
    global $api;

    $accessToken = getFirebaseAccessToken();
    if(!$accessToken){
        return json_encode(['error' => 'Failed to get access token']);
    }

    $projectId = $api['api_firebase_project_id'];

    $message = [
        'message' => [
            'topic' => $topic,
            'data' => [
                'title' => $data['title'] ?? $data['subject'] ?? '',
                'body' => $data['memo'] ?? '',
                'url' => $data['url'] ?? ''
            ]
        ]
    ];

    // Android/iOS 설정 (위와 동일)
    // ...

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,
        "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send"
    );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

/**
 * 기존 함수 래퍼 (하위 호환성)
 */
function send_notification($tokens, $data, $type){
    // HTTP v1으로 전환
    if(is_array($tokens)){
        // 다중 토큰은 개별 발송
        $results = [];
        foreach($tokens as $token){
            $results[] = send_notification_v1($token, $data, $type);
        }
        return json_encode($results);
    } else if(strpos($tokens, '/topics/') === 0){
        // Topic 발송
        $topic = str_replace('/topics/', '', $tokens);
        return send_notification_topic_v1($topic, $data, $type);
    } else {
        // 단일 토큰
        return send_notification_v1($tokens, $data, $type);
    }
}
```

#### 파일 2: admin/api_form.php (관리자 설정)

**추가할 필드:**

```php
<!-- 기존 필드 -->
<tr>
    <th>Firebase Server Key (Legacy)</th>
    <td>
        <input type="text" name="api_firebase_key_id"
               value="<?=$api['api_firebase_key_id']?>" class="form-control">
        <small class="text-muted">⚠️ Deprecated - HTTP v1 사용 권장</small>
    </td>
</tr>

<!-- 새로 추가할 필드 -->
<tr>
    <th>Firebase Project ID</th>
    <td>
        <input type="text" name="api_firebase_project_id"
               value="<?=$api['api_firebase_project_id']?>"
               class="form-control" required>
        <small class="text-muted">Firebase Console → 프로젝트 설정에서 확인</small>
    </td>
</tr>

<tr>
    <th>Firebase Service Account (JSON)</th>
    <td>
        <textarea name="api_firebase_service_account"
                  class="form-control" rows="10" required><?=$api['api_firebase_service_account']?></textarea>
        <small class="text-muted">
            Firebase Console → 프로젝트 설정 → 서비스 계정 → 새 비공개 키 생성<br>
            다운로드한 JSON 파일 내용 전체를 붙여넣기
        </small>
    </td>
</tr>
```

#### 파일 3: 데이터베이스 스키마 변경

```sql
-- nfor_api 테이블에 새 컬럼 추가
ALTER TABLE nfor_api
ADD COLUMN api_firebase_project_id VARCHAR(100) AFTER api_firebase_key_id,
ADD COLUMN api_firebase_service_account TEXT AFTER api_firebase_project_id;
```

### 3.3 호출 코드 수정

#### admin/push_list.php (변경 전)
```php
// 기존 코드
if($write[pu_target]=="Google"){
    $message_status = send_notification("/topics/android", $data, "android");
} elseif($write[pu_target]=="Apple"){
    $message_status = send_notification("/topics/ios", $data, "ios");
}
```

#### admin/push_list.php (변경 후)
```php
// 새 코드 - 함수 내부에서 HTTP v1으로 처리되므로 호출 코드는 동일
// (하위 호환성 유지)
if($write[pu_target]=="Google"){
    $message_status = send_notification("/topics/android", $data, "android");
} elseif($write[pu_target]=="Apple"){
    $message_status = send_notification("/topics/ios", $data, "ios");
}
// 변경 없음 - 래퍼 함수가 자동으로 HTTP v1 호출
```

---

## 4. 비용 및 일정 비교

### 4.0 프로젝트 규모

```
⚠️ FCM 업그레이드 영향 범위:
- 푸시 알림 사용 위치: 전체 앱 (20개 모듈)
- 테스트 필요 디바이스: Android + iOS
- 알림 유형: 개별 발송, Topic 발송, 채팅 알림, 주문/예약 알림
- 외부 연동: Firebase Console 설정, Service Account 관리
- 실기기 테스트: 필수 (에뮬레이터로 불충분)
```

### 4.1 AI(Claude) 활용 시

| 작업 단계 | 예상 시간 | 예상 비용 | 설명 |
|----------|----------|----------|------|
| Firebase Console 설정 | 0.5시간 | 1만원 | Service Account 생성 |
| 핵심 함수 재작성 | 2-3시간 | 5만원 | OAuth + HTTP v1 구현 |
| admin/api_form.php 수정 | 0.5시간 | 1만원 | 관리자 설정 UI |
| DB 스키마 변경 | 0.5시간 | 1만원 | 새 필드 추가 |
| Android 테스트 | 2-3시간 | 5만원 | 실기기 테스트 |
| iOS 테스트 | 2-3시간 | 5만원 | 실기기 테스트 |
| 기능별 알림 테스트 | 2-3시간 | 5만원 | 채팅/주문/예약 |
| 디버깅 및 수정 | 2-3시간 | 5만원 | 발견된 오류 수정 |
| 문서화 | 0.5시간 | 1만원 | 변경사항 기록 |
| **총계** | **12-18시간** | **28-30만원** |  |

### 4.2 수동 개발 시

| 작업 단계 | 예상 시간 | 예상 비용 | 설명 |
|----------|----------|----------|------|
| HTTP v1 API 문서 학습 | 4-6시간 | 20-30만원 | OAuth 2.0 이해 필수 |
| OAuth 2.0 구현 | 6-8시간 | 30-40만원 | JWT 서명 구현 |
| 함수 재작성 | 4-6시간 | 20-30만원 | 전체 로직 변경 |
| 관리자 페이지 수정 | 2-3시간 | 10-15만원 | UI + 검증 |
| Android/iOS 테스트 | 6-8시간 | 30-40만원 | 실기기 테스트 |
| 기능별 알림 테스트 | 4-6시간 | 20-30만원 | 채팅/주문/예약 |
| 디버깅 및 수정 | 4-6시간 | 20-30만원 | 오류 추적 |
| 문서화 | 2시간 | 10만원 | 상세 문서화 |
| **총계** | **32-45시간** | **160-225만원** |  |

### 4.3 비용 비교

```
┌─────────────────────────────────────────────────────────┐
│                    비용 비교 차트                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  AI 활용    ████████ 28-30만원                          │
│             (12-18시간)                                 │
│                                                         │
│  수동 개발  ████████████████████████████████████████    │
│             160-225만원                                 │
│             (32-45시간)                                 │
│                                                         │
│  AI 활용 시 약 82-87% 비용 절감                          │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 4.4 테스트 상세 내역

```
📋 FCM 테스트 체크리스트:

Android 테스트 (4-6시간):
□ 포그라운드 알림 수신 - 0.5시간
□ 백그라운드 알림 수신 - 0.5시간
□ 알림 클릭 시 앱 이동 - 0.5시간
□ 데이터 메시지 처리 - 1시간
□ Topic 구독/해제 - 0.5시간
□ 다양한 Android 버전 테스트 - 1시간

iOS 테스트 (4-6시간):
□ 포그라운드 알림 수신 - 0.5시간
□ 백그라운드 알림 수신 - 0.5시간
□ 알림 클릭 시 앱 이동 - 0.5시간
□ APNS 연동 확인 - 1시간
□ Topic 구독/해제 - 0.5시간
□ 다양한 iOS 버전 테스트 - 1시간

기능별 알림 테스트 (5시간):
□ 채팅 메시지 알림 - 1시간
□ 주문 접수 알림 - 1시간
□ 예약 확정 알림 - 1시간
□ 예약 취소 알림 - 0.5시간
□ 관리자 전체 발송 - 0.5시간
□ 결제 완료 알림 - 0.5시간
□ 리뷰 요청 알림 - 0.5시간

총 예상 테스트 시간: 13-17시간
```

---

## 5. 체크리스트

### 5.1 Firebase Console 작업
```
□ Firebase Console 접속
□ 프로젝트 설정 → 서비스 계정 이동
□ "새 비공개 키 생성" 클릭
□ JSON 파일 다운로드
□ Project ID 확인 및 기록
```

### 5.2 서버 작업
```
□ 데이터베이스 ALTER TABLE 실행
□ lib/function.lib.php 수정
  □ getFirebaseAccessToken() 함수 추가
  □ send_notification_v1() 함수 추가
  □ send_notification_topic_v1() 함수 추가
  □ send_notification() 래퍼 함수 수정
□ admin/api_form.php 수정
  □ Project ID 입력 필드 추가
  □ Service Account JSON 입력 필드 추가
```

### 5.3 관리자 설정
```
□ 관리자 페이지 접속
□ API 설정 페이지 이동
□ Firebase Project ID 입력
□ Service Account JSON 붙여넣기
□ 저장
```

### 5.4 테스트
```
□ Android 개별 푸시 테스트
□ iOS 개별 푸시 테스트
□ Android Topic 푸시 테스트
□ iOS Topic 푸시 테스트
□ 중고마켓 채팅 알림 테스트
□ 주문/예약 알림 테스트
□ 관리자 전체 발송 테스트
```

### 5.5 정리
```
□ Legacy API 코드 주석 처리 (백업용)
□ 변경 사항 문서화
□ 모니터링 설정
```

---

## 6. 주의사항

### 6.1 하위 호환성

```
✅ 기존 호출 코드 변경 불필요
- send_notification() 함수 시그니처 유지
- 내부적으로 HTTP v1 호출
- 기존 코드 그대로 동작

⚠️ 단, 다음은 확인 필요:
- 에러 응답 형식이 다를 수 있음
- 결과 로깅 로직 확인 필요
```

### 6.2 Access Token 캐싱

```
권장: Access Token 캐싱 구현
- 토큰 유효기간: 1시간
- 매 요청마다 새 토큰 발급 비효율
- 캐시 구현으로 성능 향상

예시:
function getFirebaseAccessToken(){
    // 캐시 확인
    $cached = getCache('firebase_access_token');
    if($cached && $cached['expires'] > time()){
        return $cached['token'];
    }

    // 새 토큰 발급
    $token = requestNewAccessToken();

    // 캐시 저장 (50분)
    setCache('firebase_access_token', [
        'token' => $token,
        'expires' => time() + 3000
    ]);

    return $token;
}
```

### 6.3 에러 처리

```
HTTP v1 에러 응답 예시:
{
  "error": {
    "code": 404,
    "message": "Requested entity was not found.",
    "status": "NOT_FOUND",
    "details": [...]
  }
}

처리 필요:
- 토큰 만료 시 재발급
- 잘못된 토큰 처리
- 전송 실패 로깅
```

---

## 7. 결론

### 7.1 업그레이드 필수 여부

```
✅ 반드시 업그레이드 필요

이유:
1. Legacy API 지원 중단 예정
2. 서비스 중단 위험 방지
3. 보안 강화 (OAuth 2.0)
4. 신규 기능 사용 가능
5. 더 나은 에러 처리
```

### 7.2 권장 일정

```
Day 1: 준비
- Firebase Console 설정
- Service Account JSON 생성
- 개발 환경 준비

Day 2: 개발
- 코드 수정
- 관리자 페이지 수정
- DB 스키마 변경

Day 3: 테스트
- Android 테스트
- iOS 테스트
- 전체 기능 테스트

Day 4: 배포
- 프로덕션 배포
- 모니터링
```

### 7.3 예상 총 비용

| 방식 | 시간 | 비용 | 권장 |
|------|------|------|------|
| **AI 활용** | 12-18시간 | **28-30만원** | ⭐⭐⭐⭐⭐ |
| 수동 개발 | 32-45시간 | 160-225만원 | ⭐⭐ |

### 7.4 비용 구성 분석 (AI 활용 시)

```
AI 활용 시 비용 구성 (28-30만원):
┌────────────────────────────────────────┐
│ 코드 수정      ████████ 8만원 (27%)    │
│ 테스트        ██████████ 15만원 (50%)  │
│ 디버깅/기타    ██████ 6만원 (23%)       │
└────────────────────────────────────────┘

FCM은 PHP보다 범위가 작음:
- 수정 파일: 2개 (function.lib.php, api_form.php)
- 호출 코드: 변경 불필요 (래퍼 함수로 하위호환)
- 테스트: Android/iOS 실기기 필수
```

---

## 8. PHP + FCM 동시 업그레이드 시

### 8.1 통합 비용

| 작업 | AI 활용 시 | 수동 개발 시 |
|------|-----------|-------------|
| PHP 8.x 업그레이드 | 90-100만원 | 560-840만원 |
| FCM HTTP v1 업그레이드 | 28-30만원 | 160-225만원 |
| 통합 테스트 시너지 | -8만원 | -30만원 |
| **총계** | **110-122만원** | **690-1,035만원** |

### 8.2 비용 비교 요약

```
┌─────────────────────────────────────────────────────────┐
│         PHP + FCM 동시 업그레이드 비용 비교              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  AI 활용    ████████████████████ 110-122만원            │
│             (55-81시간)                                 │
│                                                         │
│  수동 개발  ████████████████████████████████████████████│
│             ████████████████████████████                │
│             690-1,035만원 (144-213시간)                 │
│                                                         │
│  AI 활용 시 약 84-88% 비용 절감                          │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 8.3 권장사항

```
✅ 동시 업그레이드 권장

이유:
1. 서버 작업 1회로 통합
2. 테스트 기간 단축 (중복 테스트 제거)
3. 다운타임 최소화
4. 약 8만원 시너지 절감

일정:
- 준비: 1일
- 코드 수정: 2-3일
- 테스트: 5-7일 ⚠️ (가장 중요)
- 배포 및 안정화: 2-3일
- 총: 10-14일 (2주)
```

---

**문서 작성**: 2025-12-08
**분석 도구**: Claude AI (Opus 4.5)
