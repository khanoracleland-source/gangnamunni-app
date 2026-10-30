import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/services/next_pilot.dart';

/// Every PHP page with a Next.js counterpart, and the parameters that keep the PHP page.
void main() {
  const host = 'https://hms.seoulbeauty.app';
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
    expect(to(Uri.parse('http://hms.seoulbeauty.app/mypage.php')), isNull);
    expect(to(Uri.parse('https://user@hms.seoulbeauty.app/mypage.php')), isNull);
    expect(to(Uri.parse('https://HMS.seoulbeauty.app/mypage.php')), '/next/mypage');
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
    expect(to(php('detail.php?id=8216&treatment_id=2')), isNull); // treatment context stays PHP
    expect(to(php('detail.php?id=0')), isNull);
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
    for (final p in ['auth/login.php?url=%2Fnext%2Fmypage', 'bbs/logout.php?url=%2Findex.php', 'reviews.php', 'treatments.php', 'price_compare.php']) {
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

  test('booking payment (preview flow; Toss checkout stays PHP via php=1)', () {
    expect(to(php('booking_payment.php?booking_id=7')), '/next/mypage/bookings/7/payment');
    expect(to(php('booking_payment.php?booking_id=7&pay_error=USER_CANCEL')), '/next/mypage/bookings/7/payment?pay_error=USER_CANCEL');
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
    expect(to(php('my_points.php')), '/next/mypage/points');
    expect(to(legacy('my_points.php?app=1')), '/next/mypage/points');
    expect(to(php('my_points.php?x=1')), isNull);
    expect(to(php('my_points_policy.php')), '/next/points-policy');
    expect(to(php('my_points_policy.php?tab=expire')), '/next/points-policy?tab=expire');
    expect(to(php('my_points_policy.php?tab=bogus')), isNull);
  });
}
