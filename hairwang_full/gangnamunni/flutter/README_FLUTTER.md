# Flutter 연동 가이드

## 필요 패키지 (pubspec.yaml)

```yaml
dependencies:
  flutter_inappwebview: ^6.0.0
  image_picker: ^1.0.0
  http: ^1.1.0
  path: ^1.8.0
```

## 사용법

### 후기 작성
```dart
final result = await Navigator.push(context, MaterialPageRoute(
  builder: (_) => ReviewEditorWebView(
    baseUrl: 'https://your-domain.com/gangnamunni',
    hospitalId: 1,
    userId: 'user123',
  ),
));

if (result?['success'] == true) {
  print('후기 ID: ${result['reviewId']}');
}
```

### 후기 수정
```dart
ReviewEditorWebView(
  baseUrl: 'https://your-domain.com/gangnamunni',
  hospitalId: 1,
  userId: 'user123',
  reviewId: 42, // 수정할 후기 ID
)
```

### 후기 목록 (네이티브)
```dart
ReviewListScreen(
  baseUrl: 'https://your-domain.com/gangnamunni',
  hospitalId: 1,
  userId: 'user123',
)
```

## 파일 구조
- `review_editor_webview.dart` - WebView 기반 에디터
- `review_list_screen.dart` - 네이티브 후기 목록

## JS-Flutter 통신
| 방향 | 핸들러 | 용도 |
|------|--------|------|
| JS -> Flutter | `onReviewSubmitted` | 후기 저장 완료 |
| JS -> Flutter | `onEditorClose` | 에디터 닫기 |
| JS -> Flutter | `openImagePicker` | 네이티브 이미지 선택 |
| Flutter -> JS | `receiveNativeImage()` | 이미지 데이터 전달 |
