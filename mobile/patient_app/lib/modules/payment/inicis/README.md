# INICIS Payment Module

서울미녀 앱의 INICIS 모바일 결제 모듈입니다.

## 📁 파일 구조

```
lib/modules/payment/inicis/
├── inicis_payment_service.dart      # INICIS API 통신 서비스
├── inicis_payment_controller.dart   # GetX 결제 플로우 컨트롤러
├── inicis_webview_screen.dart       # WebView 결제 화면
└── README.md                         # 이 파일
```

## 🚀 사용 방법

### 1. 기본 설정

**pubspec.yaml**에 이미 추가되어 있는 dependencies:
```yaml
dependencies:
  webview_flutter: ^4.10.0
  get: ^4.7.2
  http: ^1.6.0
  nb_utils: ^7.1.8
```

### 2. 결제 시작하기

```dart
import 'package:get/get.dart';
import 'package:kivicare_patient/modules/payment/inicis/inicis_payment_controller.dart';

// Controller 초기화
final paymentController = Get.put(InicisPaymentController());

// 결제 시작
final success = await paymentController.startPayment(
  context: context,
  orderNumber: 'ORD1234567890',  // 또는 paymentController.generateOrderNumber()
  amount: 50000.0,
  productName: '피부관리 - 프리미엄 패키지',
  buyerName: '홍길동',
  buyerEmail: 'hong@example.com',
  buyerTel: '010-1234-5678',
);

if (success) {
  // 결제 성공 처리
  print('결제가 완료되었습니다');
} else {
  // 결제 실패 처리
  print('결제에 실패했습니다');
}
```

### 3. 결제 취소하기

```dart
final canceled = await paymentController.cancelPayment(
  tid: 'INICIS_TID_12345',
  orderNumber: 'ORD1234567890',
  cancelAmount: 50000.0,
  cancelReason: '고객 요청',
);

if (canceled) {
  print('결제가 취소되었습니다');
}
```

## 🔧 백엔드 API 엔드포인트

Laravel 백엔드에 다음 API 엔드포인트가 필요합니다:

### 1. 결제 요청 (POST `/api/payment/inicis/request`)

**Request:**
```json
{
  "order_number": "ORD1234567890",
  "amount": "50000",
  "product_name": "피부관리 - 프리미엄 패키지",
  "buyer_name": "홍길동",
  "buyer_email": "hong@example.com",
  "buyer_tel": "010-1234-5678"
}
```

**Response:**
```json
{
  "success": true,
  "payment_url": "https://mobile.inicis.com/smart/payment/...",
  "mid": "your_merchant_id",
  "oid": "ORD1234567890",
  "timestamp": "20260114153045"
}
```

### 2. 결제 검증 (POST `/api/payment/inicis/verify`)

**Request:**
```json
{
  "tid": "INICIS_TID_12345",
  "order_number": "ORD1234567890"
}
```

**Response:**
```json
{
  "success": true,
  "result_code": "00",
  "result_msg": "정상처리",
  "tid": "INICIS_TID_12345",
  "amount": "50000",
  "payment_method": "Card"
}
```

### 3. 결제 취소 (POST `/api/payment/inicis/cancel`)

**Request:**
```json
{
  "tid": "INICIS_TID_12345",
  "order_number": "ORD1234567890",
  "cancel_amount": "50000",
  "cancel_reason": "고객 요청"
}
```

**Response:**
```json
{
  "success": true,
  "result_code": "00",
  "result_msg": "취소 완료",
  "cancel_tid": "CANCEL_TID_67890"
}
```

## 📱 결제 플로우

```
1. 사용자가 결제 버튼 클릭
   ↓
2. startPayment() 호출
   ↓
3. Backend API로 결제 요청 (requestPayment)
   ↓
4. Backend가 INICIS에 결제 요청하고 payment_url 반환
   ↓
5. WebView로 payment_url 열기
   ↓
6. 사용자가 INICIS 결제 페이지에서 결제 진행
   ↓
7. 결제 완료 후 결과 URL로 리다이렉트
   ↓
8. WebView가 URL 감지하고 결과 처리
   ↓
9. Backend API로 결제 검증 요청 (verifyPayment)
   ↓
10. 검증 성공 시 결제 완료
```

## 🔐 보안 고려사항

1. **API 인증**: 모든 API 요청에 Bearer Token 필요
2. **금액 검증**: 백엔드에서 반드시 금액을 재검증
3. **중복 결제 방지**: order_number 중복 체크
4. **타임아웃**: 결제 세션 타임아웃 설정 (예: 30분)
5. **로그 기록**: 모든 결제 요청/응답 로그 저장

## 🎨 UI 커스터마이징

### WebView 화면 커스터마이징

`inicis_webview_screen.dart`에서 수정 가능:

```dart
// AppBar 색상 변경
AppBar(
  backgroundColor: context.primaryColor,  // 서울미녀 핑크색
  // ...
)

// 로딩 인디케이터 커스터마이징
CircularProgressIndicator(
  color: context.primaryColor,
)
```

## 🧪 테스트

### 테스트 결제 정보 (INICIS 테스트 환경)

- **테스트 카드번호**: 신한 9410-1234-5678-9012
- **유효기간**: 아무 미래 날짜
- **CVC**: 123
- **비밀번호 앞 2자리**: 00

### 디버깅

```dart
// 결제 처리 상태 확인
Obx(() => Text('Processing: ${paymentController.isProcessing.value}')),

// 결제 로그 확인
print('Order Number: ${paymentController.generateOrderNumber()}');
print('Formatted Amount: ${paymentController.formatAmountForDisplay(50000)}');
```

## 🌐 한국어 포맷팅

이 모듈은 `lib/utils/korean/korean_formatters.dart`를 사용합니다:

```dart
import 'package:kivicare_patient/utils/korean/korean_formatters.dart';

// 전화번호 포맷팅
String phone = KoreanFormatters.formatPhoneNumber('01012345678');
// 결과: "010-1234-5678"

// 통화 포맷팅
String price = KoreanFormatters.formatCurrency(50000);
// 결과: "50,000원"

// 날짜/시간 포맷팅
String dateTime = KoreanFormatters.formatDateTime(DateTime.now());
// 결과: "2026년 1월 14일 오후 3시 30분"
```

## ⚠️ 주의사항

1. **INICIS 가맹점 신청 필요**
   - 실제 결제를 위해서는 INICIS 가맹점 계약 필요
   - 테스트 계정: https://www.inicis.com

2. **Backend API 구현 필수**
   - 이 모듈은 Flutter 클라이언트만 구현되어 있음
   - Laravel 백엔드에서 INICIS API 연동 필요

3. **WebView 권한**
   - Android: `AndroidManifest.xml`에서 INTERNET 권한 필요 (이미 추가됨)
   - iOS: `Info.plist`에서 네트워크 권한 필요 (이미 추가됨)

4. **에러 처리**
   - 네트워크 에러, 타임아웃, 결제 실패 등 다양한 에러 케이스 처리
   - 사용자에게 명확한 에러 메시지 표시

## 📝 다음 단계

- [ ] Laravel 백엔드 INICIS API 구현
- [ ] 실제 결제 화면 UI 통합
- [ ] 결제 내역 조회 기능 추가
- [ ] 환불 UI 구현
- [ ] 결제 영수증 출력 기능

## 📧 문의

기술 지원이 필요하시면 개발팀에 문의하세요.
