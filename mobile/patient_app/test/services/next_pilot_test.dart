import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/screens/slots/booking_form_screen.dart';
import 'package:kivicare_patient/services/next_pilot.dart';

/// Every PHP page with a Next.js counterpart, and the parameters that keep the PHP page.
void main() {
  const host = 'https://seoulbeauty.app';
  const old = 'https://hms.seoulbeauty.app';
  Uri php(String pathAndQuery) => Uri.parse('$host/$pathAndQuery');
  Uri legacy(String pathAndQuery) => Uri.parse('$host/hairwang/gangnamunni/$pathAndQuery');
  String? to(Uri u) {
    final r = NextPilot.rewrite(u);
    if (r == null) return null;
    return r.hasQuery ? '${r.path}?${r.query}' : r.path;
  }

  test('flag is on by default', () => expect(NextPilot.enabled, isTrue));

  test('only the trusted https origin is rewritten', () {
    expect(to(Uri.parse('https://evil.test/mypage.php')), isNull);
    expect(to(Uri.parse('http://seoulbeauty.app/mypage.php')), isNull);
    expect(to(Uri.parse('https://user@seoulbeauty.app/mypage.php')), isNull);
    expect(to(Uri.parse('https://seoulbeauty.app:8443/mypage.php')), isNull);
    expect(to(Uri.parse('https://SEOULBEAUTY.app/mypage.php')), '/next/mypage');
  });

  test('a page link on the previous app host opens on the app host', () {
    String? url(String u) => NextPilot.rewrite(Uri.parse(u))?.toString();
    expect(url('$old/mypage.php'), '$host/next/mypage');
    expect(url('$old/hairwang/gangnamunni/detail.php?id=5'), '$host/next/hospitals/5');
    // the page a card payment returns to (the return itself is posted to the old host)
    expect(url('$old/next/mypage/bookings/7/payment/success'), '$host/next/mypage/bookings/7/payment/success');
    expect(url('$old/next/mypage/bookings/7/payment?pay_error=X'), '$host/next/mypage/bookings/7/payment?pay_error=X');
    // a PHP page without a Next.js counterpart only changes host
    expect(url('$old/hairwang/gangnamunni/chat.php?id=3'), '$host/hairwang/gangnamunni/chat.php?id=3');
    // endpoints are never replayed on another host
    expect(url('$old/next/api/v1/payments/inicis/return'), isNull);
    expect(url('$old/next/api/v1/auth/social/callback?code=x&state=y'), isNull);
    // only a plain https link of that host
    expect(url('http://hms.seoulbeauty.app/mypage.php'), isNull);
    expect(url('https://user@hms.seoulbeauty.app/mypage.php'), isNull);
    expect(url('https://hms.seoulbeauty.app:8443/mypage.php'), isNull);
    expect(url('https://hms.seoulbeauty.app.evil.test/mypage.php'), isNull);
    // already on the app host: nothing to do (no reload loop)
    expect(url('$host/next/mypage'), isNull);
    expect(NextPilot.canonical(Uri.parse('$host/next/mypage')), Uri.parse('$host/next/mypage'));
  });

  test('session landing and the booking list', () {
    expect(NextPilot.landUri(Uri.parse('$host/next/')).toString(), '$host/next/api/v1/app-auth/land?url=%2Fnext%2F');
    expect(NextPilot.landUri(Uri.parse('$host/next/hospitals?q=a&sort=price')).queryParameters, {'url': '/next/hospitals?q=a&sort=price'});
    expect(NextPilot.bookingsUri.toString(), '$host/next/mypage/bookings');
    expect(NextPilot.isSiteUrl(Uri.parse('$host/next/')), isTrue);
    expect(NextPilot.isSiteUrl(Uri.parse('$old/next/')), isFalse);
    expect(NextPilot.isSiteUrl(Uri.parse('https://example.com/next/')), isFalse);
  });

  test('home', () {
    expect(to(Uri.parse('$host/')), '/next/');
    expect(to(php('index.php?app=patient')), '/next/');
    expect(to(legacy('?app=patient')), '/next/');
    expect(to(php('index.php?utm=x')), isNull);
    expect(NextPilot.isHomePage(Uri.parse('$host/next/')), isTrue);
    expect(NextPilot.isHomePage(Uri.parse('$host/next/mypage')), isFalse);
  });

  test('hospital list and detail', () {
    expect(to(php('hospitals.php')), '/next/hospitals');
    expect(to(legacy('hospitals.php?q=코&sort=price&app=1')), '/next/hospitals?q=%EC%BD%94&sort=price');
    expect(to(php('hospitals.php?page=2')), isNull);
    expect(to(php('detail.php?id=8216')), '/next/hospitals/8216');
    expect(to(php('detail.php?id=8216&tab=events&from=my_wishlist')), '/next/hospitals/8216?tab=events');
    expect(to(php('detail.php?id=8216&tab=home')), '/next/hospitals/8216');
    // treatment context: the Next.js detail opens that treatment's block (same rules as the website's phpLink.ts)
    expect(to(php('detail.php?id=8216&treatment_id=2')), '/next/hospitals/8216?treatment_id=2');
    expect(to(php('detail.php?id=8216&tab=events&treatment_id=2&treatment=%EB%B3%B4%ED%86%A1%EC%8A%A4&return_url=%2Fhospitals.php')),
        '/next/hospitals/8216?tab=events&treatment_id=2');
    expect(to(php('detail.php?id=8216&treatment_id=0')), isNull);
    expect(to(php('detail.php?id=8216&treatment_id=abc')), isNull);
    expect(to(php('detail.php?id=8216&treatment=%EB%B3%B4%ED%86%A1%EC%8A%A4')), isNull); // a name without an id stays PHP
    expect(to(php('detail.php?id=8216&page=2')), isNull);
    expect(to(php('detail.php?id=0')), isNull);
  });

  test('PHP pages that pushes still link to open their Next.js page', () {
    // admin_consultations.php: the push sent when a hospital accepts a 상담
    expect(to(php('my_appointments.php')), '/next/mypage/consultations');
    expect(to(legacy('my_appointments.php?app=patient')), '/next/mypage/consultations');
    expect(to(Uri.parse('$old/my_appointments.php')), '/next/mypage/consultations');
    expect(to(php('my_appointments.php?phone=01012345678')), isNull); // the phone lookup stays PHP
    // password_reset.php is a redirect to auth/password_lost.php
    expect(to(php('password_reset.php')), '/next/password-lost');
    expect(to(php('password_reset.php?token=x')), isNull);
  });

  test('events', () {
    expect(to(php('events.php')), '/next/events');
    expect(to(php('events.php?category=눈&filter=raffle')), '/next/events?category=%EB%88%88&filter=raffle');
    expect(to(php('events.php?filter=ended')), isNull);
    expect(to(php('event_detail.php?id=5')), '/next/events/5');
    expect(to(php('event_detail.php?id=5&preview=1')), isNull);
  });

  test('community', () {
    expect(to(php('community.php')), '/next/community');
    expect(to(php('community.php?tab=latest&page=1')), '/next/community');
    expect(to(php('community.php?tab=popular&page=2&q=코')), '/next/community?tab=popular&q=%EC%BD%94&page=2');
    expect(to(php('community.php?my=1')), isNull);
    expect(to(php('detail_community.php?id=31')), '/next/community/31');
    expect(to(php('detail_community.php?id=31&php=1')), isNull);
    expect(to(php('community_write.php')), '/next/community/write');
    expect(to(php('community_write.php?id=31')), '/next/community/write?id=31');
    expect(to(php('community_write.php?id=x')), isNull);
  });

  test('mypage family', () {
    expect(to(php('mypage.php')), '/next/mypage');
    expect(to(php('mypage.php?app=1')), '/next/mypage');
    expect(to(php('my_wishlist.php')), '/next/mypage/wishlist');
    expect(to(php('my_wishlist.php?type=community')), isNull);
    expect(to(php('my_reviews.php')), '/next/mypage/reviews');
    expect(to(php('my_reviews.php?tab=writable')), '/next/mypage/reviews');
    expect(to(php('my_reviews.php?tab=done')), '/next/mypage/reviews?tab=done');
    expect(to(php('my_reviews.php?tab=done&php=1')), isNull);
    expect(to(php('my_consultations.php')), '/next/mypage/consultations');
    expect(to(php('my_consultation_detail.php?id=7')), '/next/mypage/consultations/7');
    expect(to(php('profile_edit.php')), '/next/mypage/profile');
    expect(to(php('profile_edit.php?php=1')), isNull); // email / password change
    expect(to(php('notification_settings.php')), '/next/mypage/settings');
    expect(to(php('notification_settings.php?php=1')), isNull); // 회원 탈퇴
    expect(to(php('qna.php')), '/next/qna');
    expect(to(php('faq.php?cat=예약')), '/next/faq?cat=%EC%98%88%EC%95%BD');
  });

  test('bookings', () {
    expect(to(php('my_bookings.php')), '/next/mypage/bookings');
    expect(to(php('my_bookings.php?tab=active')), '/next/mypage/bookings');
    expect(to(php('my_bookings.php?tab=completed')), '/next/mypage/bookings?tab=completed');
    expect(to(php('booking_request_detail.php?booking_id=4')), '/next/mypage/bookings/4');
    expect(to(php('booking_cancel_detail.php?booking_id=4')), '/next/mypage/bookings/4');
    expect(to(php('booking_detail.php?booking_id=4&new=1')), isNull);
  });

  test('notifications', () {
    expect(to(php('my_notifications.php')), '/next/mypage/notifications');
    expect(to(php('my_notifications.php?type=all')), '/next/mypage/notifications');
    expect(to(php('my_notifications.php?type=booking')), '/next/mypage/notifications?type=booking');
    expect(to(php('my_notifications.php?type=x')), isNull);
    expect(to(php('my_notifications.php?from=push')), isNull);
  });

  test('reviews: detail, write, progress', () {
    expect(to(php('review_detail.php?id=31')), '/next/reviews/31');
    expect(to(php('review_write.php')), '/next/mypage/reviews/write');
    expect(to(php('review_write.php?booking_id=4&hospital_id=9&return_url=my_bookings.php%3Ftab%3Dcompleted')), '/next/mypage/reviews/write?booking_id=4&hospital_id=9');
    expect(to(php('review_write.php?id=8')), '/next/mypage/reviews/write?id=8');
    expect(to(php('review_write.php?id=abc')), isNull);
    expect(to(php('my_progress_reviews.php')), '/next/mypage/progress');
    expect(to(php('progress_review.php?id=11')), '/next/mypage/progress/11');
    expect(to(php('progress_review.php?id=11&msg=added')), isNull);
  });

  test('pages that stay on PHP are untouched', () {
    for (final p in ['bbs/logout.php?url=%2Findex.php', 'reviews.php', 'treatments.php', 'price_compare.php']) {
      expect(to(php(p)), isNull, reason: p);
    }
    expect(NextPilot.isNextPage(Uri.parse('$host/next/community/3')), isTrue);
    expect(NextPilot.isNextPage(Uri.parse('$host/community.php')), isFalse);
  });

  test('booking', () {
    expect(to(php('booking.php?id=7')), '/next/booking?id=7');
    expect(to(php('booking.php?id=7&treatment_id=11&option_id=101&treatment=%EB%B3%B4%ED%86%A1%EC%8A%A4')), '/next/booking?id=7&treatment_id=11&option_id=101&treatment=%EB%B3%B4%ED%86%A1%EC%8A%A4');
    expect(to(php('booking.php?id=7&rebook=1&via=again&source_booking_id=3')), '/next/booking?id=7&rebook=1&via=again&source_booking_id=3');
    expect(to(legacy('booking.php?id=7&from=my_wishlist&app=1')), '/next/booking?id=7');
    expect(to(php('booking.php')), isNull);
    expect(to(php('booking.php?id=0')), isNull);
    expect(to(php('booking.php?id=7&change=1')), isNull);
  });

  test('booking change / cancel', () {
    expect(to(php('my_bookings.php?action=change&booking_id=7')), '/next/booking?change=7');
    expect(to(php('my_bookings.php?action=cancel&booking_id=7')), '/next/mypage/bookings?action=cancel&booking_id=7');
    expect(to(php('my_bookings.php?action=pay&booking_id=7')), isNull);
    expect(to(php('booking_change.php?booking_id=7')), '/next/booking?change=7');
    expect(to(php('booking_change.php')), isNull);
    expect(to(php('booking_change.php?booking_id=7&x=1')), isNull);
    expect(to(php('booking_cancel_request.php?booking_id=7')), '/next/mypage/bookings');
    expect(to(legacy('booking_cancel_complete.php?booking_id=7&app=1')), '/next/mypage/bookings/7/cancel/done');
  });

  test('booking payment (preview flow; php=1 keeps the PHP page)', () {
    expect(to(php('booking_payment.php?booking_id=7')), '/next/mypage/bookings/7/payment');
    expect(to(php('booking_payment.php?booking_id=7&pay_error=UNRESOLVED')), '/next/mypage/bookings/7/payment?pay_error=UNRESOLVED');
    expect(to(php('booking_payment.php?booking_id=7&php=1')), isNull);
    expect(to(php('booking_payment.php')), isNull);
    expect(to(legacy('booking_payment_success.php?booking_id=7&app=1')), '/next/mypage/bookings/7/payment/success');
  });

  test('legal pages', () {
    expect(to(php('withdraw.php')), '/next/mypage/withdraw');
    expect(to(php('withdraw.php?x=1')), isNull);
    expect(to(php('terms_of_service.php')), '/next/terms');
    expect(to(legacy('privacy_policy.php?app=1')), '/next/privacy');
    expect(to(php('community_guidelines.php')), '/next/community-guidelines');
    expect(to(php('terms_of_service.php?x=1')), isNull);
    expect(to(php('bbs/register.php?view=terms')), '/next/register-terms');
    expect(to(php('bbs/register.php')), isNull); // sign-up stays PHP
    expect(to(php('bbs/register.php?view=terms&x=1')), isNull);
    expect(to(php('my_points_policy_detail.php?type=progress')), '/next/points-policy/progress');
    expect(to(php('my_points_policy_detail.php?type=bogus')), isNull);
    expect(to(php('my_points_policy_detail.php')), isNull);
    expect(to(php('auth/password_lost.php')), '/next/password-lost');
    expect(to(legacy('auth/password_lost.php?app=1')), '/next/password-lost');
    expect(to(php('auth/password_lost.php?x=1')), isNull);
    expect(to(php('auth/login.php')), '/next/login');
    expect(to(legacy('auth/login.php?app=1')), '/next/login');
    expect(to(php('auth/login.php?pw_changed=1')), '/next/login?pw_changed=1');
    expect(to(php('auth/login.php?provider=kakao')), '/next/api/v1/auth/social/kakao/start');
    expect(to(php('auth/login.php?provider=google&url=%2Fnext%2Fmypage&app=1')), '/next/api/v1/auth/social/google/start?url=%2Fnext%2Fmypage');
    expect(to(php('auth/login.php?provider=apple')), isNull);
    expect(to(php('auth/login.php?provider=kakao&php=1')), isNull);
    expect(to(php('auth/login.php?provider=kakao&x=1')), isNull);
    expect(to(php('auth/login.php?provider=kakao&url=https%3A%2F%2Fevil.test')), isNull);
    expect(to(php('auth/login.php?url=%2Fx')), '/next/login?url=%2Fx');
    expect(to(php('auth/login.php?url=%2Fnext%2Fmypage')), '/next/login?url=%2Fnext%2Fmypage'); // the PHP login gate now goes to the Next login too
    expect(to(php('auth/login.php?url=%2F%2Fevil.test')), isNull);
    expect(to(php('auth/login.php?url=https%3A%2F%2Fevil.test')), isNull);
    expect(to(php('auth/login.php?x=1')), isNull);
    expect(to(php('auth/register.php')), '/next/register');
    expect(to(legacy('auth/register.php?app=1')), '/next/register');
    expect(to(php('auth/register.php?mode=register')), isNull);
    expect(to(php('my_points.php')), '/next/mypage/points');
    expect(to(legacy('my_points.php?app=1')), '/next/mypage/points');
    expect(to(php('my_points.php?x=1')), isNull);
    expect(to(php('my_points_policy.php')), '/next/points-policy');
    expect(to(php('my_points_policy.php?tab=expire')), '/next/points-policy?tab=expire');
    expect(to(php('my_points_policy.php?tab=bogus')), isNull);
  });

  test('native booking entry hands over with a website hospital id, never an error page', () {
    expect(NextPilot.bookingUri(hospitalId: 8216).path, '/next/booking');
    expect(NextPilot.bookingUri(hospitalId: 8216).queryParameters, {'id': '8216'});
    expect(NextPilot.bookingUri(hospitalId: 8216, treatmentId: 2).queryParameters,
        {'id': '8216', 'treatment_id': '2'});
    expect(NextPilot.hospitalsUri.path, '/next/hospitals');
    // Native screens know only HMS clinic ids, so they open the hospital list.
    expect(const BookingFormScreen().targetUri, NextPilot.hospitalsUri);
    expect(const BookingFormScreen(hospitalId: 0).targetUri, NextPilot.hospitalsUri);
    expect(const BookingFormScreen(hospitalId: 8216, treatmentId: 2).targetUri.queryParameters,
        {'id': '8216', 'treatment_id': '2'});
  });
}
