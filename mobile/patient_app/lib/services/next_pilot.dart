import 'package:flutter/foundation.dart' show debugPrint;
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

  static final Set<String> _listPaths = {'/hospitals.php', _site.resolve('hospitals.php').path};

  static bool _sameOrigin(Uri a, Uri b) =>
      a.scheme == 'https' &&
      a.host.toLowerCase() == b.host.toLowerCase() &&
      a.port == b.port &&
      a.userInfo.isEmpty;

  /// Returns the Next.js URL to load instead of [uri], or null to leave it.
  static Uri? rewrite(Uri uri) {
    if (!enabled) return null;
    final result = _rewrite(uri);
    debugPrint('[NextPilot] nav ${uri.host}${uri.path} -> ${result?.path ?? 'keep'}');
    return result;
  }

  static final Set<String> _detailPaths = {'/detail.php', _site.resolve('detail.php').path};

  /// detail.php?tab= values the Next.js detail understands (PHP "events" = 시술목록).
  static const Set<String> _detailTabs = {'home', 'events', 'reviews', 'doctors'};

  /// PHP home: site root, /index.php, and the legacy start path (server redirects it to "/").
  static final Set<String> _homePaths = {
    '/', '/index.php', _site.path, _site.resolve('index.php').path,
  };

  static Uri? _rewrite(Uri uri) {
    if (!_sameOrigin(uri, _site)) return null;
    if (_homePaths.contains(uri.path)) return _rewriteHome(uri);
    if (_detailPaths.contains(uri.path)) return _rewriteDetail(uri);
    // The startup URL (/hairwang/gangnamunni/) redirects to the site root, so the
    // list is served as /hospitals.php; accept the legacy path too.
    if (!_listPaths.contains(uri.path)) return null;
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

  /// detail.php?id=N[&tab=][&from=my_wishlist][&app=] -> /next/hospitals/N[?tab=].
  /// Any other parameter (treatment context, page, ...) keeps the PHP page.
  static Uri? _rewriteDetail(Uri uri) {
    final q = uri.queryParameters;
    final id = int.tryParse(q['id'] ?? '');
    if (id == null || id <= 0) return null;
    const allowed = {'id', 'tab', 'from', 'app'};
    if (q.keys.any((k) => !allowed.contains(k))) return null;
    final tab = q['tab'];
    if (tab != null && tab.isNotEmpty && !_detailTabs.contains(tab)) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/hospitals/$id',
      queryParameters: (tab == null || tab.isEmpty || tab == 'home') ? null : {'tab': tab},
    );
  }

  /// index.php[?app=patient] -> /next/ (any other parameter keeps the PHP page).
  static Uri? _rewriteHome(Uri uri) {
    if (uri.queryParameters.keys.any((k) => k != 'app')) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/');
  }

  /// Next.js home — the app's root page for the hardware back button.
  static bool isHomePage(Uri uri) =>
      enabled && _sameOrigin(uri, _site) && (uri.path == basePath || uri.path == '$basePath/');

  /// True for pages rendered by the Next.js app (list or detail).
  static bool isNextPage(Uri uri) =>
      _sameOrigin(uri, _site) && uri.path.startsWith('$basePath/');
}
