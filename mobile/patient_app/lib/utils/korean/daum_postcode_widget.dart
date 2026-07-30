import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:nb_utils/nb_utils.dart';

/// Daum Postcode API를 사용한 한국 주소 검색 위젯
/// 카카오(Daum) 우편번호 서비스 사용
class DaumPostcodeWidget extends StatefulWidget {
  final Function(DaumPostcodeResult) onSelected;

  const DaumPostcodeWidget({
    Key? key,
    required this.onSelected,
  }) : super(key: key);

  @override
  State<DaumPostcodeWidget> createState() => _DaumPostcodeWidgetState();
}

class _DaumPostcodeWidgetState extends State<DaumPostcodeWidget> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _initializeWebView();
  }

  void _initializeWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..addJavaScriptChannel(
        'Flutter',
        onMessageReceived: (JavaScriptMessage message) {
          // Daum Postcode에서 주소 선택 시 호출됨
          final data = message.message;
          _parseAndReturn(data);
        },
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
          },
        ),
      )
      ..loadHtmlString(_getDaumPostcodeHtml());
  }

  void _parseAndReturn(String data) {
    try {
      // JSON 파싱 (data는 JSON 문자열)
      final Map<String, dynamic> json = {};

      // 간단한 파싱 (실제로는 dart:convert 사용 권장)
      final parts = data.split(',');
      for (var part in parts) {
        final kv = part.split(':');
        if (kv.length == 2) {
          json[kv[0].trim().replaceAll('"', '')] = kv[1].trim().replaceAll('"', '');
        }
      }

      final result = DaumPostcodeResult(
        zonecode: json['zonecode'] ?? '',
        address: json['address'] ?? '',
        addressEnglish: json['addressEnglish'] ?? '',
        addressType: json['addressType'] ?? 'R',
        userSelectedType: json['userSelectedType'] ?? 'R',
        roadAddress: json['roadAddress'] ?? '',
        jibunAddress: json['jibunAddress'] ?? '',
        buildingName: json['buildingName'] ?? '',
        apartment: json['apartment'] == 'Y',
      );

      widget.onSelected(result);
      finish(context);
    } catch (e) {
      toast('주소 파싱 오류: $e');
    }
  }

  String _getDaumPostcodeHtml() {
    return '''
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>주소 검색</title>
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
</head>
<body>
    <script>
        new daum.Postcode({
            oncomplete: function(data) {
                // 선택된 주소 정보를 Flutter로 전송
                var message = JSON.stringify(data);
                if (window.Flutter) {
                    Flutter.postMessage(message);
                }
            },
            onclose: function() {
                // 창 닫기
                if (window.Flutter) {
                    Flutter.postMessage('CLOSE');
                }
            },
            width: '100%',
            height: '100%'
        }).embed(document.body);
    </script>
</body>
</html>
    ''';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('주소 검색'),
        leading: IconButton(
          icon: Icon(Icons.close),
          onPressed: () => finish(context),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_isLoading)
            Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(),
                  16.height,
                  Text('주소 검색 로딩 중...'),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Daum Postcode API 검색 결과
class DaumPostcodeResult {
  final String zonecode;           // 우편번호 (5자리)
  final String address;             // 전체 주소
  final String addressEnglish;      // 영문 주소
  final String addressType;         // 주소 타입 (R: 도로명, J: 지번)
  final String userSelectedType;    // 사용자가 선택한 주소 타입
  final String roadAddress;         // 도로명 주소
  final String jibunAddress;        // 지번 주소
  final String buildingName;        // 건물명
  final bool apartment;             // 아파트 여부

  DaumPostcodeResult({
    required this.zonecode,
    required this.address,
    this.addressEnglish = '',
    this.addressType = 'R',
    this.userSelectedType = 'R',
    this.roadAddress = '',
    this.jibunAddress = '',
    this.buildingName = '',
    this.apartment = false,
  });

  /// 우편번호 포함 전체 주소
  String get fullAddress => '($zonecode) $address';

  /// 도로명 또는 지번 주소
  String get preferredAddress =>
      addressType == 'R' ? roadAddress : jibunAddress;

  @override
  String toString() {
    return 'DaumPostcodeResult(zonecode: $zonecode, address: $address)';
  }
}

/// 주소 검색 화면 열기 헬퍼 함수
Future<DaumPostcodeResult?> showDaumPostcodeSearch(BuildContext context) async {
  return await Navigator.push<DaumPostcodeResult>(
    context,
    MaterialPageRoute(
      builder: (context) => DaumPostcodeWidget(
        onSelected: (result) {
          Navigator.pop(context, result);
        },
      ),
    ),
  );
}
