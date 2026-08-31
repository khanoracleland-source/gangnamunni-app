import 'package:flutter/services.dart';

/// Persists Android WebView cookies to disk.
///
/// Android may keep recently received persistent cookies in memory until
/// CookieManager.flush() runs. The system's "Close all" action can kill the
/// process before that happens, so the next launch would lose the login even
/// though the server issued 120-day cookies.
class WebViewCookiePersistence {
  WebViewCookiePersistence._();

  static const MethodChannel _channel =
      MethodChannel('seoulbeauty.app/webview_cookie');

  static Future<void> flush() async {
    try {
      await _channel.invokeMethod<void>('flush');
    } on MissingPluginException {
      // Android needs the explicit flush. Other platforms persist their
      // WebView cookie store through their platform implementation.
    } on PlatformException {
      // Cookie persistence must never make page navigation fail. A later page
      // completion or lifecycle transition retries the flush.
    }
  }
}
