import 'package:flutter/foundation.dart' show debugPrint;
import 'package:kivicare_patient/configs.dart';

/// Next.js pages (`/next/…`): home, hospitals, events, community, mypage.
///
/// On by default since 2.0.8 (day 13). `--dart-define=NEXT_PILOT_HOSPITALS=false`
/// builds an app that keeps every PHP page (emergency fallback). When on, any
/// in-app navigation to a PHP page that has a Next.js counterpart on the trusted
/// host is redirected there with the same parameters; a parameter the Next.js
/// page does not understand (and the `php=1` escape hatch) keeps the PHP page.
class NextPilot {
  NextPilot._();

  static const bool enabled =
      bool.fromEnvironment('NEXT_PILOT_HOSPITALS', defaultValue: true);

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
    if (_notificationsPaths.contains(uri.path)) return _rewriteNotifications(uri);
    if (_profilePaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/profile', const {}, const {});
    if (_settingsPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/settings', const {}, const {});
    if (_withdrawPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/withdraw', const {}, const {});
    if (_termsPaths.contains(uri.path)) return _rewriteMine(uri, 'terms', const {}, const {});
    if (_privacyPaths.contains(uri.path)) return _rewriteMine(uri, 'privacy', const {}, const {});
    if (_guidelinesPaths.contains(uri.path)) return _rewriteMine(uri, 'community-guidelines', const {}, const {});
    if (_registerTermsPaths.contains(uri.path)) return _rewriteRegisterTerms(uri);
    if (_pointsPolicyPaths.contains(uri.path)) return _rewritePointsPolicy(uri);
    if (_passwordLostPaths.contains(uri.path)) return _rewriteMine(uri, 'password-lost', const {}, const {});
    if (_loginPaths.contains(uri.path)) return _rewriteMine(uri, 'login', const {'pw_changed'}, const {'pw_changed': {'1'}});
    if (_registerPaths.contains(uri.path)) return _rewriteMine(uri, 'register', const {}, const {});
    if (_myPointsPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/points', const {}, const {});
    if (_pointsPolicyListPaths.contains(uri.path)) return _rewriteMine(uri, 'points-policy', const {'tab'}, const {'tab': {'earn', 'revoke', 'expire', 'use', 'refund'}});
    if (_bookingPaths.contains(uri.path)) return _rewriteBooking(uri);
    if (_bookingChangePaths.contains(uri.path)) return _rewriteBookingChange(uri);
    if (_cancelRequestPaths.contains(uri.path)) return _rewriteCancelRequest(uri);
    if (_cancelCompletePaths.contains(uri.path)) return _rewriteCancelComplete(uri);
    if (_paymentPaths.contains(uri.path)) return _rewritePayment(uri);
    if (_paymentSuccessPaths.contains(uri.path)) return _rewritePaymentSuccess(uri);
    if (_reviewWritePaths.contains(uri.path)) return _rewriteReviewWrite(uri);
    if (_progressListPaths.contains(uri.path)) return _rewriteMine(uri, 'mypage/progress', const {}, const {});
    if (_progressDetailPaths.contains(uri.path)) return _rewriteIdPage(uri, 'mypage/progress');
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
  static final Set<String> _profilePaths = {'/profile_edit.php', _site.resolve('profile_edit.php').path};
  static final Set<String> _settingsPaths = {'/notification_settings.php', _site.resolve('notification_settings.php').path};
  static final Set<String> _reviewWritePaths = {'/review_write.php', _site.resolve('review_write.php').path};
  static final Set<String> _progressListPaths = {'/my_progress_reviews.php', _site.resolve('my_progress_reviews.php').path};
  static final Set<String> _progressDetailPaths = {'/progress_review.php', _site.resolve('progress_review.php').path};
  static final Set<String> _notificationsPaths = {'/my_notifications.php', _site.resolve('my_notifications.php').path};
  static final Set<String> _faqPaths = {'/faq.php', _site.resolve('faq.php').path};
  static final Set<String> _withdrawPaths = {'/withdraw.php', _site.resolve('withdraw.php').path};
  static final Set<String> _termsPaths = {'/terms_of_service.php', _site.resolve('terms_of_service.php').path};
  static final Set<String> _privacyPaths = {'/privacy_policy.php', _site.resolve('privacy_policy.php').path};
  static final Set<String> _guidelinesPaths = {'/community_guidelines.php', _site.resolve('community_guidelines.php').path};
  static final Set<String> _registerTermsPaths = {'/bbs/register.php'};
  static final Set<String> _passwordLostPaths = {'/auth/password_lost.php', _site.resolve('auth/password_lost.php').path};
  static final Set<String> _loginPaths = {'/auth/login.php', _site.resolve('auth/login.php').path};
  static final Set<String> _registerPaths = {'/auth/register.php', _site.resolve('auth/register.php').path};
  static final Set<String> _myPointsPaths = {'/my_points.php', _site.resolve('my_points.php').path};
  static final Set<String> _pointsPolicyListPaths = {'/my_points_policy.php', _site.resolve('my_points_policy.php').path};
  static final Set<String> _pointsPolicyPaths = {'/my_points_policy_detail.php', _site.resolve('my_points_policy_detail.php').path};

  /// Earn types the Next.js points policy page knows (includes/points_policy_content.php).
  static const Set<String> _pointsTypes = {'progress', 'photo', 'receipt', 'first', 'payment'};

  /// bbs/register.php?view=terms[&app=] -> /next/register-terms. Without view=terms it is the sign-up page (stays PHP).
  static Uri? _rewriteRegisterTerms(Uri uri) {
    final q = uri.queryParameters;
    if (q['view'] != 'terms' || q.keys.any((k) => k != 'view' && k != 'app')) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/register-terms');
  }

  /// my_points_policy_detail.php?type=X[&app=] -> /next/points-policy/X (unknown type keeps the PHP page).
  static Uri? _rewritePointsPolicy(Uri uri) {
    final q = uri.queryParameters;
    if (!_pointsTypes.contains(q['type']) || q.keys.any((k) => k != 'type' && k != 'app')) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/points-policy/${q['type']}');
  }
  static final Set<String> _bookingPaths = {'/booking.php', _site.resolve('booking.php').path};
  static const Set<String> _bookingParams = {'id', 'treatment_id', 'option_id', 'treatment', 'rebook', 'via', 'source_booking_id', 'from', 'return_url'};

  /// booking.php?id=N[&treatment_id=][&option_id=][&treatment=][&rebook=1][&via=again][&source_booking_id=][&from=][&return_url=][&app=] -> /next/booking?…
  /// (`from`/`return_url` only shape the back link and are dropped; a change (`change_booking`) or any other parameter keeps the PHP page).
  static Uri? _rewriteBooking(Uri uri) {
    final q = uri.queryParameters;
    final id = int.tryParse(q['id'] ?? '');
    if (id == null || id <= 0) return null;
    if (q.keys.any((k) => !_bookingParams.contains(k) && k != 'app')) return null;
    final kept = <String, String>{
      for (final e in q.entries)
        if (_bookingParams.contains(e.key) && e.key != 'from' && e.key != 'return_url' && e.value.isNotEmpty) e.key: e.value,
    };
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/booking', queryParameters: kept);
  }
  static final Set<String> _bookingChangePaths = {'/booking_change.php', _site.resolve('booking_change.php').path};
  static final Set<String> _cancelRequestPaths = {'/booking_cancel_request.php', _site.resolve('booking_cancel_request.php').path};
  static final Set<String> _cancelCompletePaths = {'/booking_cancel_complete.php', _site.resolve('booking_cancel_complete.php').path};

  static int? _bookingIdOf(Uri uri) {
    final q = uri.queryParameters;
    final id = int.tryParse(q['booking_id'] ?? '');
    if (id == null || id <= 0 || q.keys.any((k) => k != 'booking_id' && k != 'app')) return null;
    return id;
  }

  /// booking_change.php?booking_id=N[&app=] -> /next/booking?change=N
  static Uri? _rewriteBookingChange(Uri uri) {
    final id = _bookingIdOf(uri);
    if (id == null) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/booking', queryParameters: {'change': '$id'});
  }

  /// booking_cancel_request.php?booking_id=N -> /next/mypage/bookings (the reason sheet starts the Next.js flow; PHP kept it in the session).
  static Uri? _rewriteCancelRequest(Uri uri) {
    final id = _bookingIdOf(uri);
    if (id == null) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/mypage/bookings');
  }

  /// booking_cancel_complete.php?booking_id=N -> /next/mypage/bookings/N/cancel/done
  static Uri? _rewriteCancelComplete(Uri uri) {
    final id = _bookingIdOf(uri);
    if (id == null) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/mypage/bookings/$id/cancel/done');
  }
  static final Set<String> _paymentPaths = {'/booking_payment.php', _site.resolve('booking_payment.php').path};
  static final Set<String> _paymentSuccessPaths = {'/booking_payment_success.php', _site.resolve('booking_payment_success.php').path};

  /// booking_payment.php?booking_id=N[&pay_error=CODE][&app=] -> /next/mypage/bookings/N/payment[?pay_error=]
  /// (`php=1` and anything else keep the PHP page: the live Toss checkout still runs there).
  static Uri? _rewritePayment(Uri uri) {
    final q = uri.queryParameters;
    final id = int.tryParse(q['booking_id'] ?? '');
    if (id == null || id <= 0 || q.keys.any((k) => !const {'booking_id', 'pay_error', 'app'}.contains(k))) return null;
    final err = q['pay_error'] ?? '';
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/mypage/bookings/$id/payment', queryParameters: err.isEmpty ? null : {'pay_error': err});
  }

  /// booking_payment_success.php?booking_id=N[&app=] -> /next/mypage/bookings/N/payment/success
  static Uri? _rewritePaymentSuccess(Uri uri) {
    final id = _bookingIdOf(uri);
    if (id == null) return null;
    return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/mypage/bookings/$id/payment/success');
  }
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

  /// my_bookings.php[?tab=active|completed|cancelled][&app=] -> /next/mypage/bookings[?tab=]; `action=change|cancel&booking_id=` handled below; `from=` etc. keep the PHP page.
  static Uri? _rewriteBookings(Uri uri) {
    final q = uri.queryParameters;
    // my_bookings.php?action=change|cancel&booking_id=N: the change confirmation / cancel reason sheet for that booking.
    final action = q['action'] ?? '';
    if (action.isNotEmpty) {
      final id = int.tryParse(q['booking_id'] ?? '');
      if (id == null || id <= 0 || q.keys.any((k) => !const {'action', 'booking_id', 'tab', 'app'}.contains(k))) return null;
      if (action == 'change') return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/booking', queryParameters: {'change': '$id'});
      if (action != 'cancel') return null;
      return Uri(scheme: _site.scheme, host: _site.host, port: _site.hasPort ? _site.port : null, path: '$basePath/mypage/bookings', queryParameters: {'action': 'cancel', 'booking_id': '$id'});
    }
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

  /// review_write.php[?booking_id=N][&hospital_id=N][&id=N][&return_url=][&app=] -> /next/mypage/reviews/write (ids kept; the page goes back with history, so return_url is dropped).
  static Uri? _rewriteReviewWrite(Uri uri) {
    final q = uri.queryParameters;
    if (q.keys.any((k) => !const {'booking_id', 'hospital_id', 'id', 'return_url', 'app'}.contains(k))) return null;
    final kept = <String, String>{};
    for (final k in const ['booking_id', 'hospital_id', 'id']) {
      final raw = q[k] ?? '';
      if (raw.isEmpty) continue;
      final n = int.tryParse(raw);
      if (n == null || n <= 0) return null;
      kept[k] = '$n';
    }
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/mypage/reviews/write',
      queryParameters: kept.isEmpty ? null : kept,
    );
  }

  /// my_notifications.php[?type=all|booking|review|benefit][&app=] -> /next/mypage/notifications[?type=] (`from=`, `return_to=` etc. keep the PHP page).
  static Uri? _rewriteNotifications(Uri uri) {
    final q = uri.queryParameters;
    if (q.keys.any((k) => k != 'type' && k != 'app')) return null;
    final type = q['type'] ?? '';
    if (type.isNotEmpty && !const {'all', 'booking', 'review', 'benefit'}.contains(type)) return null;
    return Uri(
      scheme: _site.scheme,
      host: _site.host,
      port: _site.hasPort ? _site.port : null,
      path: '$basePath/mypage/notifications',
      queryParameters: (type.isEmpty || type == 'all') ? null : {'type': type},
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
