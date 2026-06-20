# 푸시 알림 시스템 사용 가이드

이 문서는 푸시 알림 시스템의 사용법과 테스트 방법을 설명합니다.

## 목차

1. [시스템 개요](#시스템-개요)
2. [파일 구조](#파일-구조)
3. [웹 인터페이스 사용법](#웹-인터페이스-사용법)
4. [HTTP API 호출 방법](#http-api-호출-방법)
5. [테스트 방법](#테스트-방법)
6. [파라미터 상세 설명](#파라미터-상세-설명)
7. [문제 해결](#문제-해결)

## 시스템 개요

이 푸시 알림 시스템은 Firebase Cloud Messaging(FCM)을 기반으로 안드로이드와 iOS 기기에 푸시 알림을 보낼 수 있는 기능을 제공합니다. 개별 푸시, 전체 푸시, 토픽 푸시를 지원하며, 모든 인텐트 파라미터를 전달할 수 있습니다.

## 파일 구조

- `push_notification.php`: 웹 인터페이스를 제공하는 HTML 파일
- `push_api.php`: HTTP API를 통해 푸시 알림을 보낼 수 있는 PHP 파일
- `mars-38372-firebase-adminsdk-60l5a-23f211e854.json`: Firebase 서비스 계정 키 파일

## 웹 인터페이스 사용법

웹 브라우저에서 다음 URL로 접속하여 푸시 알림 시스템을 사용할 수 있습니다:

```
https://waterreg.andpia.com/new_http_v1/app/push_notification.php
```

웹 인터페이스는 세 가지 탭으로 구성되어 있습니다:

### 1. 개별 푸시

특정 기기에 푸시 알림을 보내는 기능을 제공합니다:

1. **기기 유형** 선택: 안드로이드 또는 iOS
2. **사용자 토큰** 입력: FCM 토큰 입력 (필수)
3. **제목** 입력: 알림 제목 (필수)
4. **내용** 입력: 알림 내용 (필수)
5. **URL** 입력: 알림 클릭 시 이동할 URL (선택사항)
6. **이미지 URL** 입력: 알림에 표시할 이미지 URL (선택사항)
7. **고급 옵션** 설정:
   - **주문 ID**: 주문 관련 ID 값 (선택사항)
   - **추가 파라미터 1 (arg1)**: 기본값 `$arg1` (선택사항)
   - **추가 파라미터 2 (arg2)**: 팝업 표시 여부, `1`(표시) 또는 `0`(미표시) (선택사항)
   - **추가 파라미터 3 (arg3)**: 기본값 `MARS` (선택사항)
8. "푸시 알림 보내기" 버튼 클릭

### 2. 전체 푸시

여러 기기에 동시에 푸시 알림을 보내는 기능을 제공합니다:

1. **대상 기기** 선택: 안드로이드, iOS 또는 둘 다
2. **제목** 입력: 알림 제목 (필수)
3. **내용** 입력: 알림 내용 (필수)
4. **URL** 입력: 알림 클릭 시 이동할 URL (선택사항)
5. **이미지 URL** 입력: 알림에 표시할 이미지 URL (선택사항)
6. **고급 옵션** 설정 (개별 푸시와 동일)
7. "전체 푸시 알림 보내기" 버튼 클릭
8. 확인 대화상자에서 "확인" 클릭

> **중요**: 전체 푸시는 내부적으로 `news` 토픽을 사용합니다. 모든 클라이언트 앱에서 이 토픽을 구독해야 전체 푸시를 받을 수 있습니다.

### 안드로이드 앱에서 토픽 구독 방법:
```java
// 앱 시작 시(예: MainActivity.onCreate 또는 Application 클래스)에 추가
FirebaseMessaging.getInstance().subscribeToTopic("news")
        .addOnCompleteListener(task -> {
            if (task.isSuccessful()) {
                Log.d("PushNotification", "Successfully subscribed to topic: news");
                // 구독 성공 시 토큰을 저장하거나 UI 업데이트 등 추가 작업 수행
                SharedPreferences prefs = getSharedPreferences("push_prefs", Context.MODE_PRIVATE);
                prefs.edit().putBoolean("subscribed_to_news", true).apply();
            } else {
                Log.e("PushNotification", "Failed to subscribe to topic: news", task.getException());
                // 실패 시 오류 처리
            }
        });

// 구독 상태 확인 방법
private boolean isSubscribedToNewsTopic() {
    SharedPreferences prefs = getSharedPreferences("push_prefs", Context.MODE_PRIVATE);
    return prefs.getBoolean("subscribed_to_news", false);
}

// 필요한 경우 구독 취소 방법
FirebaseMessaging.getInstance().unsubscribeFromTopic("news")
        .addOnCompleteListener(task -> {
            if (task.isSuccessful()) {
                Log.d("PushNotification", "Successfully unsubscribed from topic: news");
                SharedPreferences prefs = getSharedPreferences("push_prefs", Context.MODE_PRIVATE);
                prefs.edit().putBoolean("subscribed_to_news", false).apply();
            } else {
                Log.e("PushNotification", "Failed to unsubscribe from topic: news", task.getException());
            }
        });
```

### iOS 앱에서 토픽 구독 방법:
```swift
// AppDelegate의 didFinishLaunchingWithOptions에 추가
Messaging.messaging().subscribe(toTopic: "news") { error in
    if let error = error {
        print("Failed to subscribe to topic: \(error.localizedDescription)")
        // 실패 시 오류 처리
    } else {
        print("Successfully subscribed to topic: news")
        // 구독 성공 시 UserDefaults에 저장
        UserDefaults.standard.set(true, forKey: "subscribed_to_news")
    }
}

// 구독 상태 확인 방법
func isSubscribedToNewsTopic() -> Bool {
    return UserDefaults.standard.bool(forKey: "subscribed_to_news")
}

// 필요한 경우 구독 취소 방법
Messaging.messaging().unsubscribe(fromTopic: "news") { error in
    if let error = error {
        print("Failed to unsubscribe from topic: \(error.localizedDescription)")
    } else {
        print("Successfully unsubscribed from topic: news")
        UserDefaults.standard.set(false, forKey: "subscribed_to_news")
    }
}
```

> **주의사항**: 토픽 구독은 일반적으로 백그라운드에서 비동기적으로 이루어집니다. 구독 성공 여부는 위 코드의 완료 핸들러에서 확인할 수 있습니다. 구독 후 바로 테스트하면 실패할 수 있으므로, 구독이 완료되었는지 확인한 후 테스트하는 것이 좋습니다.

### 전체 푸시 알림 API

```
https://waterreg.andpia.com/new_http_v1/app/push_api.php?push_type=group&title=알림제목&message=알림내용&url=https://example.com&file_url=https://example.com/image.jpg&order_id=12345&arg1=값1&arg2=1&arg3=발신자
```

### 전체 푸시 알림 테스트

1. 먼저 테스트 기기에서 `news` 토픽을 구독합니다:
   - 안드로이드: `FirebaseMessaging.getInstance().subscribeToTopic("news")`
   - iOS: `Messaging.messaging().subscribe(toTopic: "news")`

2. 웹 인터페이스 또는 HTTP API를 통해 다음과 같이 테스트합니다:

   ```
   https://waterreg.andpia.com/new_http_v1/app/push_api.php?push_type=group&title=전체테스트&message=전체푸시테스트입니다&url=http://example.com
   ```

3. `news` 토픽을 구독한 모든 기기에서 알림 수신 확인

### 3. 토픽 푸시

특정 토픽을 구독한 모든 기기에 푸시 알림을 보내는 기능을 제공합니다:

1. **토픽 이름** 입력: 알림을 보낼 토픽 이름 (필수)
2. **제목** 입력: 알림 제목 (필수)
3. **내용** 입력: 알림 내용 (필수)
4. **URL** 입력: 알림 클릭 시 이동할 URL (선택사항)
5. **이미지 URL** 입력: 알림에 표시할 이미지 URL (선택사항)
6. **고급 옵션** 설정 (개별 푸시와 동일)
7. "토픽 푸시 알림 보내기" 버튼 클릭

## HTTP API 호출 방법

### 개별 푸시 알림 API

#### 안드로이드 기기

```
https://waterreg.andpia.com/new_http_v1/app/push_api.php?app=android&user_token=DEVICE_TOKEN&title=알림제목&message=알림내용&url=https://example.com&file_url=https://example.com/image.jpg&order_id=12345&arg1=값1&arg2=1&arg3=발신자


fA4sPToYQMSBsOvZ2UpXuq:APA91bEbg7UvmGfo1WcZE2yqlJTlRky6-lwqlDh9BjsprqbV_9197GgYjfMDId6AxPH7PZkF6SK0CMKFAXgvGfkCxbnm7wdYRGE1qxfDXvkH2T4l7jCbzes
```

#### iOS 기기

```
https://waterreg.andpia.com/new_http_v1/app/push_api.php?app=ios&user_token=DEVICE_TOKEN&title=알림제목&message=알림내용&url=https://example.com&file_url=https://example.com/image.jpg&order_id=12345&arg1=값1&arg2=1&arg3=발신자
```

### 토픽 푸시 알림 API

```
https://waterreg.andpia.com/new_http_v1/app/push_api.php?topic=TOPIC_NAME&title=알림제목&message=알림내용&url=https://example.com&file_url=https://example.com/image.jpg&order_id=12345&arg1=값1&arg2=1&arg3=발신자
```

### 전체 푸시 알림 API

```
https://waterreg.andpia.com/new_http_v1/app/push_api.php?push_type=group&title=알림제목&message=알림내용&target_devices[]=android&target_devices[]=ios&url=https://example.com&file_url=https://example.com/image.jpg&order_id=12345&arg1=값1&arg2=1&arg3=발신자
```

## 테스트 방법

### 개별 푸시 알림 테스트

1. 테스트할 기기에서 FCM 토큰을 확보합니다.
2. 웹 인터페이스 또는 HTTP API를 통해 다음과 같이 테스트합니다:

   ```
   https://waterreg.andpia.com/new_http_v1/app/push_api.php?app=android&user_token=FCM_토큰&title=테스트알림&message=안드로이드테스트알림입니다&url=http://example.com
   ```

3. 테스트 기기에서 알림 수신 확인

### 토픽 푸시 알림 테스트

1. 테스트 기기가 특정 토픽을 구독하도록 설정합니다:
   - 안드로이드: `FirebaseMessaging.getInstance().subscribeToTopic("test_topic")`
   - iOS: `Messaging.messaging().subscribe(toTopic: "test_topic")`

2. 웹 인터페이스 또는 HTTP API를 통해 다음과 같이 테스트합니다:

   ```
   https://waterreg.andpia.com/new_http_v1/app/push_api.php?topic=test_topic&title=토픽테스트&message=토픽테스트알림입니다&url=http://example.com
   ```

3. 토픽을 구독한 모든 기기에서 알림 수신 확인

### 전체 푸시 알림 테스트

1. 웹 인터페이스 또는 HTTP API를 통해 다음과 같이 테스트합니다:

   ```
   https://waterreg.andpia.com/new_http_v1/app/push_api.php?push_type=group&title=전체테스트&message=전체푸시테스트입니다&url=http://example.com
   ```

2. 모든 기기에서 알림 수신 확인 (테스트 환경에서는 하드코딩된 토큰에만 전송됨)

## 파라미터 상세 설명

### 필수 파라미터

- **app**: 기기 유형 (`android` 또는 `ios`) - 개별 푸시에만 필요
- **user_token**: 사용자 FCM 토큰 - 개별 푸시에만 필요
- **topic**: 토픽 이름 - 토픽 푸시에만 필요
- **push_type**: 푸시 유형 (`group`으로 설정) - 전체 푸시에만 필요
- **title**: 알림 제목
- **message** 또는 **memo**: 알림 내용 (둘 중 하나만 있으면 됨)

### 선택적 파라미터

- **target_devices[]**: 전체 푸시 대상 기기 유형 (값: `android` 또는 `ios`) - 전체 푸시에서만 사용
- **url**: 알림 클릭 시 이동할 URL
- **file_url** 또는 **image_url**: 알림에 표시할 이미지 URL (둘 중 하나만 있으면 됨)
- **order_id**: 주문 ID
- **arg1**: 추가 파라미터 1 (기본값: `$arg1`)
- **arg2**: 추가 파라미터 2, 팝업 표시 여부 (기본값: `1`)
- **arg3**: 추가 파라미터 3 (기본값: `MARS`)

## 인텐트 파라미터

이 푸시 알림 시스템은 안드로이드 앱에서 다음과 같은 인텐트 파라미터를 처리합니다:

```java
intent.putExtra("title", title);
intent.putExtra("message", message);
intent.putExtra("order_id", order_id);
intent.putExtra("url", url);
intent.putExtra("arg1", arg1);
intent.putExtra("arg2", arg2);
intent.putExtra("arg3", arg3);
intent.putExtra("param_intent_url", url);  // url과 동일한 값
intent.putExtra("file_url", file_url);
```

알림을 보낼 때 이러한 파라미터를 모두 설정할 수 있으며, 앱에서는 이 파라미터들을 통해 알림의 동작을 제어할 수 있습니다.

## 문제 해결

### 푸시 알림이 전송되지 않는 경우

1. **파라미터 확인**
   - 필수 파라미터가 모두 포함되어 있는지 확인
   - 특수문자나 공백이 포함된 경우 URL 인코딩 확인

2. **토큰 유효성**
   - 사용자 토큰이 유효한지 확인
   - 토큰이 만료되었거나 변경되었을 수 있음

3. **서비스 계정 파일**
   - Firebase 서비스 계정 키 파일이 올바른 위치에 있는지 확인

4. **네트워크 연결**
   - 서버가 FCM 서버에 연결할 수 있는지 확인
   - 방화벽 설정 확인

5. **응답 확인**
   - API 응답의 오류 메시지 확인

### 전체 푸시가 작동하지 않는 경우

1. **푸시 타입 확인**
   - `push_type=group` 파라미터가 포함되어 있는지 확인
   
2. **필수 파라미터 확인**
   - `title`과 `message`(또는 `memo`) 파라미터가 포함되어 있는지 확인

3. **대상 기기 지정**
   - `target_devices[]` 파라미터로 대상 기기 유형을 지정할 수 있음 (예: `target_devices[]=android&target_devices[]=ios`) 

### 전체 푸시 알림이 작동하지 않을 때 문제 해결

전체 푸시 알림이 작동하지 않는 가장 일반적인 원인:

1. **앱에서 토픽 구독 누락**:
   - 모든 클라이언트 앱이 `news` 토픽을 구독했는지 확인하세요.
   - 앱이 토픽 구독 시 성공 로그를 확인하세요.

2. **Firebase 프로젝트 설정 문제**:
   - Firebase 콘솔에서 Cloud Messaging API가 활성화되어 있는지 확인하세요.
   - 서비스 계정이 FCM 메시지 전송 권한을 가지고 있는지 확인하세요.

3. **앱 재시작 필요**:
   - 토픽 구독 후 앱을 재시작해야 할 수 있습니다.
   - 토픽 구독 후 최대 24시간까지 지연될 수 있습니다(일반적으로는 즉시 적용).

4. **서버 디버깅**:
   - 서버 로그에서 FCM 응답 및 오류 메시지를 확인하세요.
   - `{"error": {"status":"PERMISSION_DENIED" ...}}` 오류가 나타나면 서비스 계정 권한 문제입니다. 