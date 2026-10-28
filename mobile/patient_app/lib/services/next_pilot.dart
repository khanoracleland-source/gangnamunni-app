import 'package:flutter/foundation.dart' show debugPrint;
import 'package:kivicare_patient/configs.dart';

/// Next.js pilot (home, hospitals, events at `/next/…`).
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

  /// Filters the Next.js list understands (day 9: dosage filter and the
  /// hospital search view `hid`/`tcat` too). Anything else stays on PHP.
  static const Set<String> _listParams = {'q', 'category', 'treatment', 'region', 'sort', 'dosage', 'hid', 'tcat'};

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
    if (_eventsPaths.contains(uri.path)) return _rewriteEvents(uri);
    if (_eventDetailPaths.contains(uri.path)) return _rewriteEventDetail(uri);
    if (_communityPaths.contains(uri.path)) return _rewriteCommunity(uri);
    if (_communityDetailPaths.contains(uri.path)) return _rewriteCommunityDetail(uri);
    if (_communityWritePaths.contains(uri.path)) return _rewriteCommunityWrite(uri);
    if (_mypagePaths.contains(uri.path)) return _rewriteMine(uri, 'mypage', const {}, const {});
    if (_myWishlistPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/wishlist', const {}, const {});
    if (_myReviewsPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/reviews', const {'tab'}, const {'tab': {'writable', 'done'}});
    if (_myConsultationsPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/consultations', const {}, const {});
    if (_myBookingsPaths.contains(uri.path)) return _rewriteBookings(uri);
    if (_bookingDetailPaths.contains(uri.path)) return _rewriteIdPage(uri, 'mypage/bookings', idParam: 'booking_id');
    if (_myConsultationDetailPaths.contains(uri.path)) return _rewriteIdPage(uri, 'mypage/consultations');
    if (_reviewDetailPaths.contains(uri.path)) return _rewriteIdPage(uri, 'reviews');
    if (_qnaPaths.contains(uri.path)) return _rewriteMine(uri, 'qna', const {}, const {});
    if (_faqPaths.contains(uri.path)) return _rewriteFaq(uri);
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

  static final Set<String> _eventsPaths = {'/events.php', _site.resolve('events.php').path};
  static final Set<String> _eventDetailPaths = {'/event_detail.php', _site.resolve('event_detail.php').path};

  /// events.php[?category=][&filter=raffle][&app=] -> /next/events (any other parameter keeps the PHP page).
  static Uri? _rewriteEvents(Uri uri) {
    final q = uri.queryParameters;
    const allowed = {'category', 'filter', 'app'};
    if (q.keys.any((k) => !allowed.contains(k))) return null;
    final filter = q['filter'];
    if (filter != null && filter.isNotEmpty && filter != 'raffle') return null;
    final kept = <String, String>{
      if ((q['category'] ?? '').isNotEmpty) 'category': q['category']!,
      if (filter == 'raffle') 'filter': 'raffle',
    };
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/events',
      queryParameters: kept.isEmpty ? null : kept,
    );
  }

  /// event_detail.php?id=N[&app=] -> /next/events/N (any other parameter keeps the PHP page).
  static Uri? _rewriteEventDetail(Uri uri) {
    final q = uri.queryParameters;
    final id = int.tryParse(q['id'] ?? '');
    if (id == null || id <= 0) return null;
    if (q.keys.any((k) => k != 'id' && k != 'app')) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/events/$id',
    );
  }

  static final Set<String> _communityPaths = {'/community.php', _site.resolve('community.php').path};
  static final Set<String> _mypagePaths = {'/mypage.php', _site.resolve('mypage.php').path};
  static final Set<String> _myWishlistPaths = {'/my_wishlist.php', _site.resolve('my_wishlist.php').path};
  static final Set<String> _myReviewsPaths = {'/my_reviews.php', _site.resolve('my_reviews.php').path};
  static final Set<String> _myConsultationsPaths = {'/my_consultations.php', _site.resolve('my_consultations.php').path};
  static final Set<String> _myBookingsPaths = {'/my_bookings.php', _site.resolve('my_bookings.php').path};
  // booking_request_detail.php (active/completed) and booking_cancel_detail.php (cancelled) share one Next page.
  static final Set<String> _bookingDetailPaths = {
    '/booking_request_detail.php', _site.resolve('booking_request_detail.php').path,
    '/booking_cancel_detail.php', _site.resolve('booking_cancel_detail.php').path,
  };
  static final Set<String> _myConsultationDetailPaths = {'/my_consultation_detail.php', _site.resolve('my_consultation_detail.php').path};
  static final Set<String> _reviewDetailPaths = {'/review_detail.php', _site.resolve('review_detail.php').path};
  static final Set<String> _qnaPaths = {'/qna.php', _site.resolve('qna.php').path};
  static final Set<String> _faqPaths = {'/faq.php', _site.resolve('faq.php').path};
  static final Set<String> _communityWritePaths = {'/community_write.php', _site.resolve('community_write.php').path};
  static final Set<String> _communityDetailPaths = {'/detail_community.php', _site.resolve('detail_community.php').path};

  /// community.php[?tab=][&category=][&region=][&q=][&page=][&app=] -> /next/community (any other parameter keeps the PHP page).
  static Uri? _rewriteCommunity(Uri uri) {
    final q = uri.queryParameters;
    const allowed = {'tab', 'category', 'region', 'q', 'page', 'app'};
    if (q.keys.any((k) => !allowed.contains(k))) return null;
    final tab = q['tab'];
    if (tab != null && tab.isNotEmpty && !const {'latest', 'popular', 'review'}.contains(tab)) return null;
    final page = q['page'];
    if (page != null && page.isNotEmpty && (int.tryParse(page) ?? 0) < 1) return null;
    final kept = <String, String>{
      for (final k in const ['tab', 'category', 'region', 'q', 'page'])
        if ((q[k] ?? '').isNotEmpty && !(k == 'tab' && q[k] == 'latest') && !(k == 'page' && q[k] == '1')) k: q[k]!,
    };
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/community',
      queryParameters: kept.isEmpty ? null : kept,
    );
  }

  /// mypage.php, my_wishlist.php, my_reviews.php[?tab=], my_consultations.php [&app=] -> /next/mypage[/...].
  /// Any other parameter keeps the PHP page (e.g. my_wishlist.php?type=community, `php=1` escape hatch).
  static Uri? _rewriteMine(Uri uri, String path, Set<String> params, Map<String, Set<String>> values) {
    final q = uri.queryParameters;
    if (q.keys.any((k) => k != 'app' && !params.contains(k))) return null;
    final kept = <String, String>{};
    for (final k in params) {
      final v = q[k] ?? '';
      if (v.isEmpty) continue;
      if (!(values[k]?.contains(v) ?? false)) return null;
      // the default tab (writable) is the bare URL
      if (!(k == 'tab' && v == 'writable')) kept[k] = v;
    }
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/$path',
      queryParameters: kept.isEmpty ? null : kept,
    );
  }

  /// my_consultation_detail.php?id=N, review_detail.php?id=N [&app=] -> `/next/<prefix>/N` (any other parameter keeps the PHP page).
  static Uri? _rewriteIdPage(Uri uri, String prefix, {String idParam = 'id'}) {
    final q = uri.queryParameters;
    final id = int.tryParse(q[idParam] ?? '');
    if (id == null || id <= 0) return null;
    if (q.keys.any((k) => k != idParam && k != 'app')) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/$prefix/$id',
    );
  }

  /// my_bookings.php[?tab=active|completed|cancelled][&app=] -> /next/mypage/bookings[?tab=] (`action=`, `from=` etc. keep the PHP page).
  static Uri? _rewriteBookings(Uri uri) {
    final q = uri.queryParameters;
    if (q.keys.any((k) => k != 'tab' && k != 'app')) return null;
    final tab = q['tab'] ?? '';
    if (tab.isNotEmpty && !const {'active', 'completed', 'cancelled'}.contains(tab)) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/mypage/bookings',
      queryParameters: (tab.isEmpty || tab == 'active') ? null : {'tab': tab},
    );
  }

  /// faq.php[?cat=][&app=] -> /next/faq[?cat=] (any other parameter keeps the PHP page).
  static Uri? _rewriteFaq(Uri uri) {
    final q = uri.queryParameters;
    if (q.keys.any((k) => k != 'cat' && k != 'app')) return null;
    final cat = q['cat'] ?? '';
    if (cat.length > 50) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/faq',
      queryParameters: cat.isEmpty ? null : {'cat': cat},
    );
  }

  /// community_write.php[?id=N][&app=] -> /next/community/write[?id=N] (any other parameter keeps the PHP page).
  static Uri? _rewriteCommunityWrite(Uri uri) {
    final q = uri.queryParameters;
    if (q.keys.any((k) => k != 'id' && k != 'app')) return null;
    final idRaw = q['id'] ?? '';
    final id = int.tryParse(idRaw);
    if (idRaw.isNotEmpty && (id == null || id <= 0)) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/community/write',
      queryParameters: idRaw.isEmpty ? null : {'id': idRaw},
    );
  }

  /// detail_community.php?id=N[&app=] -> /next/community/N. Any other parameter keeps the PHP page (`&php=1` escape hatch).
  static Uri? _rewriteCommunityDetail(Uri uri) {
    final q = uri.queryParameters;
    final id = int.tryParse(q['id'] ?? '');
    if (id == null || id <= 0) return null;
    if (q.keys.any((k) => k != 'id' && k != 'app')) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/community/$id',
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

  /// True for pages rendered by the Next.js app (hospitals, events, home).
  static bool isNextPage(Uri uri) =>
      _sameOrigin(uri, _site) && uri.path.startsWith('$basePath/');
}
