import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:nb_utils/nb_utils.dart';

class InicisWebViewScreen extends StatefulWidget {
  final String paymentUrl;
  final String orderNumber;
  final Function(bool success, String? tid) onPaymentResult;

  const InicisWebViewScreen({
    Key? key,
    required this.paymentUrl,
    required this.orderNumber,
    required this.onPaymentResult,
  }) : super(key: key);

  @override
  State<InicisWebViewScreen> createState() => _InicisWebViewScreenState();
}

class _InicisWebViewScreenState extends State<InicisWebViewScreen> {
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
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
            });
            _checkPaymentResult(url);
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
          },
          onWebResourceError: (WebResourceError error) {
            toast('결제 페이지 로딩 오류: ${error.description}');
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.paymentUrl));
  }

  void _checkPaymentResult(String url) {
    // Check if URL contains payment result
    if (url.contains('/payment/success')) {
      final uri = Uri.parse(url);
      final tid = uri.queryParameters['tid'];
      widget.onPaymentResult(true, tid);
      finish(context);
    } else if (url.contains('/payment/fail') || url.contains('/payment/cancel')) {
      widget.onPaymentResult(false, null);
      finish(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('서울미녀 결제'),
        leading: IconButton(
          icon: Icon(Icons.close),
          onPressed: () {
            showConfirmDialogCustom(
              context,
              title: '결제 취소',
              dialogType: DialogType.CONFIRMATION,
              primaryColor: context.primaryColor,
              onAccept: (context) {
                widget.onPaymentResult(false, null);
                finish(context);
                finish(context);
              },
            );
          },
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
                  Text('결제 페이지를 불러오는 중...'),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
