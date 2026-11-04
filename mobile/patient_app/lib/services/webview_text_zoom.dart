import 'package:flutter/foundation.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

/// Android WebView applies the system "font size" setting (Galaxy: Settings >
/// Display > Font size and style) as `textZoom`. CSS `text-size-adjust` does not
/// stop that, so the page layout would change with the user's phone setting.
/// Pin it to 100% so every WebView screen looks the same on every device.
/// No-op on iOS.
class WebViewTextZoom {
  WebViewTextZoom._();

  static Future<void> lock(WebViewController controller) async {
    final platform = controller.platform;
    if (platform is! AndroidWebViewController) return;
    try {
      await platform.setTextZoom(100);
    } catch (e) {
      debugPrint('[WebViewTextZoom] setTextZoom failed: $e');
    }
  }
}
