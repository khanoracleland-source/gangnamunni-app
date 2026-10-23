import 'package:kivicare_patient/configs.dart';

/// Next.js pilot (hospital list/detail at `/next/…`).
///
/// Enabled only with `--dart-define=NEXT_PILOT_HOSPITALS=true`; release builds
/// keep the PHP pages. When enabled, any in-app navigation to the PHP hospital
/// list (`hospitals.php`) on the trusted host is redirected to the Next.js list
/// with the same filters, so no PHP template has to change for the test.
class NextPilot {
  NextPilot._();

  static const bool enabled =
      bool.fromEnvironment('NEXT_PILOT_HOSPITALS', defaultValue: false);

  /// Apache serves the Next.js app under this prefix on the same host.
  static const String basePath = '/next';

  static final Uri _site = Uri.parse(STARTUP_WEBVIEW_URL);

  /// Filters the Next.js list understands. `dosage`/`hid` are PHP-only, so
  /// such URLs stay on PHP (returns null).
  static const Set<String> _listParams = {'q', 'category', 'treatment', 'region', 'sort'};

  static bool _sameOrigin(Uri a, Uri b) =>
      a.scheme == 'https' &&
      a.host.toLowerCase() == b.host.toLowerCase() &&
      a.port == b.port &&
      a.userInfo.isEmpty;

  /// Returns the Next.js URL to load instead of [uri], or null to leave it.
  static Uri? rewrite(Uri uri) {
    if (!enabled) return null;
    if (!_sameOrigin(uri, _site)) return null;
    final listPage = _site.resolve('hospitals.php');
    if (uri.path != listPage.path) return null;
    final q = uri.queryParameters;
    if (q.keys.any((k) => !_listParams.contains(k) && k != 'app')) return null;
    final kept = <String, String>{
      for (final e in q.entries)
        if (_listParams.contains(e.key) && e.value.isNotEmpty) e.key: e.value,
    };
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/hospitals',
      queryParameters: kept.isEmpty ? null : kept,
    );
  }

  /// True for pages rendered by the Next.js app (list or detail).
  static bool isNextPage(Uri uri) =>
      _sameOrigin(uri, _site) && uri.path.startsWith('$basePath/');
}
