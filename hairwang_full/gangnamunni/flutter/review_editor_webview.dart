import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:path/path.dart' as path;

/// 후기 에디터 WebView 페이지
/// 사용법:
///   Navigator.push(context, MaterialPageRoute(
///     builder: (_) => ReviewEditorWebView(
///       baseUrl: 'https://your-domain.com/gangnamunni',
///       hospitalId: 1,
///       userId: 'user123',
///     ),
///   ));
class ReviewEditorWebView extends StatefulWidget {
  final String baseUrl;
  final int hospitalId;
  final String userId;
  final int? reviewId; // 수정 모드일 때

  const ReviewEditorWebView({
    Key? key,
    required this.baseUrl,
    required this.hospitalId,
    required this.userId,
    this.reviewId,
  }) : super(key: key);

  @override
  State<ReviewEditorWebView> createState() => _ReviewEditorWebViewState();
}

class _ReviewEditorWebViewState extends State<ReviewEditorWebView> {
  InAppWebViewController? _webViewController;
  bool _isLoading = true;
  double _progress = 0;

  String get _editorUrl {
    final mode = widget.reviewId != null ? 'edit' : 'create';
    var url = '${widget.baseUrl}/editor/review_editor.php'
        '?mode=$mode'
        '&hospital_id=${widget.hospitalId}'
        '&user_id=${widget.userId}';
    if (widget.reviewId != null) {
      url += '&review_id=${widget.reviewId}';
    }
    return url;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            InAppWebView(
              initialUrlRequest: URLRequest(url: WebUri(_editorUrl)),
              initialSettings: InAppWebViewSettings(
                javaScriptEnabled: true,
                mediaPlaybackRequiresUserGesture: false,
                allowFileAccessFromFileURLs: true,
                allowUniversalAccessFromFileURLs: true,
                useHybridComposition: true,
                // iOS 키보드 줌 방지
                enableViewportScale: false,
              ),
              onWebViewCreated: (controller) {
                _webViewController = controller;
                _registerHandlers(controller);
              },
              onLoadStart: (controller, url) {
                setState(() => _isLoading = true);
              },
              onLoadStop: (controller, url) {
                setState(() => _isLoading = false);
              },
              onProgressChanged: (controller, progress) {
                setState(() => _progress = progress / 100);
              },
              onReceivedError: (controller, request, error) {
                _showError('페이지를 불러올 수 없습니다.\n네트워크 연결을 확인해주세요.');
              },
            ),
            if (_isLoading)
              Container(
                color: Colors.white,
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CircularProgressIndicator(
                        value: _progress > 0 ? _progress : null,
                        color: const Color(0xFF7C3AED),
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        '에디터 로딩 중...',
                        style: TextStyle(color: Colors.grey, fontSize: 14),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// JS 핸들러 등록
  void _registerHandlers(InAppWebViewController controller) {
    // 후기 제출 완료 시
    controller.addJavaScriptHandler(
      handlerName: 'onReviewSubmitted',
      callback: (args) {
        final data = args.isNotEmpty ? args[0] : {};
        Navigator.pop(context, {
          'success': true,
          'reviewId': data['reviewId'],
          'mode': data['mode'],
        });
      },
    );

    // 에디터 닫기 요청 시
    controller.addJavaScriptHandler(
      handlerName: 'onEditorClose',
      callback: (args) {
        Navigator.pop(context, {'success': false});
      },
    );

    // 네이티브 이미지 피커 요청 시
    controller.addJavaScriptHandler(
      handlerName: 'openImagePicker',
      callback: (args) async {
        await _pickAndUploadImage();
      },
    );
  }

  /// 네이티브 이미지 선택 및 업로드
  Future<void> _pickAndUploadImage() async {
    final picker = ImagePicker();

    // 이미지 소스 선택 다이얼로그
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('카메라'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('갤러리'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.cancel),
              title: const Text('취소'),
              onTap: () => Navigator.pop(ctx),
            ),
          ],
        ),
      ),
    );

    if (source == null) return;

    try {
      final XFile? pickedFile = await picker.pickImage(
        source: source,
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );

      if (pickedFile == null) return;

      // 서버에 업로드
      final uri = Uri.parse('${widget.baseUrl}/api/upload_image.php');
      final request = http.MultipartRequest('POST', uri);
      request.files.add(await http.MultipartFile.fromPath(
        'image',
        pickedFile.path,
        filename: path.basename(pickedFile.path),
      ));

      final response = await request.send();
      final responseBody = await response.stream.bytesToString();
      final data = json.decode(responseBody);

      if (data['success'] == true) {
        // WebView에 이미지 데이터 전달
        final imageData = json.encode({
          'url': data['url'],
          'thumbnail_url': data['thumbnail_url'],
          'file_size': data['file_size'],
          'original_name': path.basename(pickedFile.path),
        });
        _webViewController?.evaluateJavascript(
          source: 'window.receiveNativeImage($imageData);',
        );
      } else {
        _showError(data['message'] ?? '이미지 업로드에 실패했습니다.');
      }
    } catch (e) {
      _showError('이미지 처리 중 오류가 발생했습니다.');
    }
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }
}
