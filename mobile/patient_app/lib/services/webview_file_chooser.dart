import 'package:flutter/foundation.dart' show debugPrint;
import 'package:image_picker/image_picker.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

/// Android WebView does nothing for `<input type="file">` unless the app answers
/// `onShowFileChooser`; iOS (WKWebView) handles it natively. The website's photo
/// inputs (review / community / profile photos, both Next.js and PHP pages) are
/// image-only, so the system photo picker is used; anything else gets no file.
class WebViewFileChooser {
  WebViewFileChooser._();

  static void install(WebViewController controller) {
    final platform = controller.platform;
    if (platform is! AndroidWebViewController) return;
    platform.setOnShowFileSelector(_pick);
  }

  static Future<List<String>> _pick(FileSelectorParams params) async {
    try {
      final accepts = params.acceptTypes.map((t) => t.toLowerCase()).toList();
      final wantsImages = accepts.isEmpty || accepts.any((t) => t.startsWith('image/') || t == '*/*');
      if (!wantsImages) {
        debugPrint('[FileChooser] unsupported accept types: ${params.acceptTypes}');
        return const [];
      }
      final picker = ImagePicker();
      final List<XFile> files;
      if (params.mode == FileSelectorMode.openMultiple) {
        files = await picker.pickMultiImage();
      } else {
        final one = await picker.pickImage(source: ImageSource.gallery);
        files = one == null ? const [] : [one];
      }
      debugPrint('[FileChooser] picked ${files.length} file(s)');
      return files.map((f) => Uri.file(f.path).toString()).toList();
    } catch (e) {
      debugPrint('[FileChooser] failed: $e');
      return const [];
    }
  }
}
