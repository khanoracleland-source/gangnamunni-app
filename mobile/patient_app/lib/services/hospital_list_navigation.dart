/// Keeps the list URL for this WebView's current list -> detail visit.
/// Referrer and JavaScript session storage are not navigation authorities.
class HospitalListNavigation {
  HospitalListNavigation(Uri website)
      : _listPage = website.resolve('hospitals.php'),
        _detailPage = website.resolve('detail.php');

  final Uri _listPage;
  final Uri _detailPage;
  Uri? _current;
  Uri? _listUrl;
  bool _directlyFromList = false;

  bool _samePage(Uri uri, Uri page) =>
      uri.scheme == page.scheme &&
      uri.host == page.host &&
      uri.port == page.port &&
      uri.path == page.path &&
      uri.userInfo.isEmpty;

  bool isHospitalDetail(Uri uri) =>
      _samePage(uri, _detailPage) &&
      uri.queryParameters['from'] != 'my_wishlist';

  Uri? get returnUrl =>
      _current != null && isHospitalDetail(_current!) ? _listUrl : null;

  bool get canReturnThroughHistory => returnUrl != null && _directlyFromList;

  void pageStarted(String url) {
    final next = Uri.tryParse(url);
    if (next == null) {
      _current = null;
      _listUrl = null;
      _directlyFromList = false;
      return;
    }
    if (_samePage(next, _listPage)) {
      // Preserve every query parameter, including future filters.
      _listUrl = next;
      _directlyFromList = false;
    } else if (isHospitalDetail(next)) {
      if (next != _current) {
        _directlyFromList = _current != null && _samePage(_current!, _listPage);
      }
    } else {
      // Never reuse a previous search after visiting wishlist/home/etc.
      _listUrl = null;
      _directlyFromList = false;
    }
    _current = next;
  }

  /// Capture only the detail header arrow, before its web-only click handler.
  /// The URL stays in Dart; messages cannot choose an arbitrary destination.
  static const headerBackScript = '''
(function () {
  var button = document.getElementById('detailBackBtn');
  if (!button || button.dataset.nativeHospitalListBack) return;
  button.dataset.nativeHospitalListBack = '1';
  button.addEventListener('click', function (event) {
    if (!window.FlutterBridge) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    FlutterBridge.postMessage(JSON.stringify({action: 'hospitalListBack'}));
  }, true);
})();
''';
}
