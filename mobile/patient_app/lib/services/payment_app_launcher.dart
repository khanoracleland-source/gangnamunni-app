import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

/// 결제 중 카드사·간편결제·은행·본인인증 앱 호출을 외부 앱으로 넘긴다.
///
/// 토스페이먼츠 웹뷰 연동 가이드(https://docs.tosspayments.com/guides/v2/webview)를 따른다.
/// - Android: `intent:` URL 을 앱 스킴 URL 로 변환해 실행하고(가이드의 ConvertUrl 과 같은 변환),
///   앱이 없으면 intent 의 package 로 Play 스토어를 연다.
/// - iOS: 앱 스킴 URL 을 UIApplication.open 으로 연다(url_launcher externalApplication).
///   canOpenURL 을 쓰지 않으므로 LSApplicationQueriesSchemes 등록이 필요 없다(iOS 27 의 25개 제한 대응).
///
/// 웹 콘텐츠가 임의의 앱을 열지 못하도록 결제에 쓰이는 스킴·패키지만 허용한다.
class PaymentAppLauncher {
  PaymentAppLauncher._();

  /// 결제 과정에서 호출되는 앱 스킴.
  static const Set<String> schemes = {
    // 간편결제
    'supertoss', 'kakaotalk', 'kakaopay', 'naversearchthirdlogin', 'payco',
    'samsungpay', 'monimopay', 'monimopayauth', 'lpayapp', 'lmslpay',
    'shinsegaeeasypayment', 'smilepayapp', 'chaipayment', 'kpay', 'lguthepay-xpay',
    // 카드사 앱카드·안심클릭
    'ispmobile', 'kb-acp', 'kb-auth', 'kbbank', 'liivbank', 'newliiv',
    'hdcardappcardansimclick', 'smhyundaiansimclick',
    'shinhan-sr-ansimclick', 'smshinhanansimclick', 'shinhan-sr-ansimclick-naverpay', 'shinhan-sr-ansimclick-payco',
    'mpocket.online.ansimclick', 'scardcertiapp',
    'lotteappcard', 'lottesmartpay',
    'cloudpay', 'hanawalletmembers', 'hanaskcardmobileportal',
    'citimobileapp', 'citispay', 'citicardappkr',
    'wooripay', 'com.wooricard.wcard', 'newsmartpib',
    'nhallonepayansimclick', 'nhappcardansimclick', 'nonghyupcardansimclick',
    // 계좌이체(뱅크페이)·은행
    'kftc-bankpay', 'kb-bankpay', 'nhb-bankpay', 'mg-bankpay', 'kn-bankpay', 'kakaobank',
    // 백신·본인인증
    'v3mobileplusweb', 'tauthlink', 'ktauthexternalcall', 'upluscorporation',
  };

  /// Android intent 에 실려 오는 결제 앱 패키지. AndroidManifest 의 <queries> 와 같은 목록.
  static const Set<String> packages = {
    'viva.republica.toss', 'com.kakao.talk', 'com.kakaopay.app', 'com.nhn.android.search',
    'com.nhnent.payapp', 'com.samsung.android.spay', 'net.ib.android.smcard', 'com.lotte.lpay',
    'com.lottemembers.android', 'com.ssg.serviceapp.android.egiftcertificate', 'com.mysmilepay.app',
    'finance.chai.app', 'com.inicis.kpay', 'com.lguplus.paynow',
    'kvp.jjy.MispAndroid320', 'com.kbcard.cxh.appcard', 'com.kbcard.kbkookmincard', 'com.kbstar.kbbank',
    'com.kbstar.liivbank', 'com.kbstar.reboot', 'com.hyundaicard.appcard',
    'com.shcard.smartpay', 'com.shinhancard.smartshinhan', 'kr.co.samsungcard.mpocket',
    'com.lcacApp', 'com.hanaskcard.paycla', 'com.hanaskcard.rocomo.potal', 'kr.co.hanamembers.hmscustomer',
    'kr.co.citibank.citimobile', 'com.wooricard.smartapp', 'com.wooribank.smart.npib',
    'nh.smart.nhallonepay', 'nh.smart.mobilecard', 'com.nh.cashcardapp',
    'com.kftc.bankpay.android', 'kr.co.kfcc.mobilebank', 'com.knb.psb', 'com.kakaobank.channel',
    'com.ahnlab.v3mobileplus', 'com.sktelecom.tauth', 'com.kt.ktauth', 'com.lguplus.smartotp',
  };

  /// [url] 이 결제 앱 호출이면 실행할 링크를, 아니면 null 을 돌려준다.
  static PaymentAppLink? parse(String url) {
    final String raw = url.trim();
    final String lower = raw.toLowerCase();

    if (lower.startsWith('intent:')) return _parseIntent(raw);

    if (lower.startsWith('market://') ||
        lower.startsWith('itms-apps://') ||
        lower.startsWith('https://play.google.com/store/apps/details') ||
        lower.startsWith('https://apps.apple.com/')) {
      return PaymentAppLink(appUrl: raw, isStore: true);
    }

    final int sep = raw.indexOf('://');
    if (sep <= 0) return null;
    final String scheme = lower.substring(0, sep);
    if (!schemes.contains(scheme)) return null;
    return PaymentAppLink(appUrl: raw);
  }

  /// `intent://host#Intent;scheme=app;package=pkg;end` 또는
  /// `intent:app://host#Intent;package=pkg;end` (현대카드 형태) 를 `app://host` 로 바꾼다.
  static PaymentAppLink? _parseIntent(String raw) {
    final int hash = raw.indexOf('#Intent;');
    if (hash < 0) return null;
    final String target = raw.substring('intent:'.length, hash);
    String? scheme;
    String? package;
    for (final String part in raw.substring(hash + '#Intent;'.length).split(';')) {
      final int eq = part.indexOf('=');
      if (eq <= 0) continue;
      final String key = part.substring(0, eq);
      final String value = part.substring(eq + 1);
      if (key == 'scheme') scheme = value;
      if (key == 'package') package = value;
    }

    String appUrl;
    if (target.startsWith('//')) {
      if (scheme == null || scheme.isEmpty) return null;
      // 농협카드 등 일부 URL 은 호스트 부분에 ':' 가 들어 있어 그대로 열 수 없다.
      appUrl = '$scheme://${target.substring(2).replaceAll(':', '%3A')}';
    } else {
      final int sep = target.indexOf('://');
      if (sep <= 0) return null;
      scheme = target.substring(0, sep);
      appUrl = target;
    }

    final bool allowed = schemes.contains(scheme.toLowerCase()) ||
        (package != null && packages.contains(package));
    if (!allowed) return null;
    return PaymentAppLink(appUrl: appUrl, package: package);
  }

  /// 앱을 연다. 앱이 없으면(Android) 해당 패키지의 Play 스토어 화면을 연다.
  static Future<bool> launch(PaymentAppLink link) async {
    bool opened = false;
    final Uri? uri = Uri.tryParse(link.appUrl);
    if (uri != null) {
      try {
        opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      } catch (e) {
        debugPrint('[PaymentAppLauncher] launch failed: $e');
      }
    }
    if (!opened &&
        defaultTargetPlatform == TargetPlatform.android &&
        link.package != null &&
        link.package!.isNotEmpty) {
      try {
        opened = await launchUrl(
          Uri.parse('market://details?id=${link.package}'),
          mode: LaunchMode.externalApplication,
        );
      } catch (e) {
        debugPrint('[PaymentAppLauncher] store fallback failed: $e');
      }
    }
    return opened;
  }
}

class PaymentAppLink {
  const PaymentAppLink({required this.appUrl, this.package, this.isStore = false});

  /// 실제로 실행할 URL (앱 스킴 또는 스토어 URL).
  final String appUrl;

  /// Android 패키지(앱 미설치 시 스토어 이동용).
  final String? package;

  /// 스토어 링크 여부.
  final bool isStore;
}
