import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/services/hospital_list_navigation.dart';

void main() {
  final site = Uri.parse('https://hms.seoulbeauty.app/hairwang/gangnamunni/');
  final list = site.resolve('hospitals.php?treatment=티타늄&sort=price&region=강남');
  final detail =
      site.resolve('detail.php?id=8216&treatment_id=2&treatment=티타늄');
  late HospitalListNavigation navigation;
  setUp(() => navigation = HospitalListNavigation(site));

  test('detail reload retains every filter', () {
    navigation.pageStarted(list.toString());
    navigation.pageStarted(detail.toString());
    navigation.pageStarted(detail.toString());
    expect(navigation.returnUrl, list);
  });
  test('switching detail tabs retains original list instead of previous tab',
      () {
    navigation.pageStarted(list.toString());
    navigation.pageStarted(detail.toString());
    navigation.pageStarted('$detail&tab=reviews');
    expect(navigation.returnUrl, list);
  });
  test('booking round trip retains list destination and sorting', () {
    navigation.pageStarted(list.toString());
    navigation.pageStarted(detail.toString());
    navigation.pageStarted(site.resolve('booking.php?id=8216').toString());
    expect(navigation.returnUrl, isNull);
    navigation.pageStarted(detail.toString());
    expect(navigation.returnUrl, list);
  });
  test('a new unfiltered list replaces a previous search', () {
    navigation.pageStarted(list.toString());
    navigation.pageStarted(detail.toString());
    final freshList = site.resolve('hospitals.php');
    navigation.pageStarted(freshList.toString());
    navigation.pageStarted(detail.toString());
    expect(navigation.returnUrl, freshList);
  });
  test('a directly opened detail never reuses another WebView search', () {
    navigation.pageStarted(detail.toString());
    expect(navigation.returnUrl, site.resolve('hospitals.php'));
  });
  for (final other in [
    site.resolve('my_wishlist.php'),
    site.resolve('index.php'),
    Uri.parse('https://other.example/hairwang/gangnamunni/hospitals.php'),
    Uri.parse('http://hms.seoulbeauty.app/hairwang/gangnamunni/hospitals.php'),
    Uri.parse(
        'https://hms.seoulbeauty.app:8443/hairwang/gangnamunni/hospitals.php'),
  ]) {
    test('leaving list/detail flow clears stale return URL: $other', () {
      navigation.pageStarted(list.toString());
      navigation.pageStarted(other.toString());
      navigation.pageStarted(detail.toString());
      expect(navigation.returnUrl, site.resolve('hospitals.php'));
    });
  }
}
