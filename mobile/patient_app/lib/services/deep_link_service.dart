import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:uni_links/uni_links.dart';

import '../screens/booking/appointment_detail_screen.dart';
import '../screens/booking/model/appointments_res_model.dart';
import '../screens/clinic/clinic_detail_screen.dart';
import '../screens/clinic/model/clinic_detail_model.dart';
import '../screens/clinic/model/clinics_res_model.dart';
import '../screens/dashboard/dashboard_controller.dart';
import '../screens/dashboard/dashboard_screen.dart';
import '../screens/dashboard/components/menu.dart';
import '../screens/doctor/doctor_detail_screen.dart';
import '../screens/doctor/model/doctor_list_res.dart';
import '../screens/service/service_detail_screen.dart';
import '../screens/slots/booking_form_screen.dart';
import '../screens/webview/startup_webview_screen.dart';

/// 딥링크 처리 서비스
///
/// Custom-scheme links and verified Seoul Beauty HTTPS links are routed into
/// the appropriate native screen or the app WebView.
///
/// 지원 경로:
///   /dashboard         -> DashboardScreen
///   /appointment/{id}  -> AppointmentDetail (예약 상세)
///   /clinic/{id}       -> ClinicDetailScreen (클리닉 상세)
///   /doctor/{id}       -> DoctorDetailScreen (의사 상세)
///   /booking           -> BookingFormScreen (예약 폼)
///   /service/{id}      -> ServiceDetailScreen (서비스 상세)
///   /profile           -> DashboardScreen 프로필 탭
///   /settings          -> DashboardScreen 설정 탭 (비로그인 시)
///   /webview?url={url} -> StartupWebViewScreen (웹뷰)
///   /oauth/callback    -> OAuth 콜백 위임
class DeepLinkService {
  // -------------------------------------------------------
  // 싱글턴 패턴
  // -------------------------------------------------------
  DeepLinkService._internal();

  static final DeepLinkService _instance = DeepLinkService._internal();

  factory DeepLinkService() => _instance;

  /// 외부에서 접근 가능한 싱글턴 인스턴스
  static DeepLinkService get instance => _instance;

  // -------------------------------------------------------
  // 글로벌 네비게이터 키 (GetMaterialApp.navigatorKey에 할당)
  // -------------------------------------------------------
  static final GlobalKey<NavigatorState> navigatorKey =
      GlobalKey<NavigatorState>();

  // -------------------------------------------------------
  // 내부 상태
  // -------------------------------------------------------
  StreamSubscription<Uri?>? _linkSubscription;
  bool _isInitialized = false;

  /// 지원하는 URL 스킴 목록
  static const List<String> _supportedSchemes = ['wellness', 'kivicare'];

  /// Only verified first-party web links may be opened inside the app.
  static const List<String> _supportedWebHosts = ['hms.seoulbeauty.app'];
  static const String _supportedWebPath = '/hairwang/gangnamunni';

  // -------------------------------------------------------
  // 초기화
  // -------------------------------------------------------

  /// 딥링크 리스너를 초기화합니다.
  ///
  /// 앱 시작 시 한 번만 호출해야 합니다.
  /// 중복 호출 시 안전하게 무시됩니다.
  Future<void> init() async {
    if (_isInitialized) {
      debugPrint('[DeepLinkService] 이미 초기화됨 - 중복 호출 무시');
      return;
    }
    _isInitialized = true;

    debugPrint('[DeepLinkService] 초기화 시작');

    // 앱이 닫혀 있을 때 딥링크로 실행된 경우 처리
    await _handleInitialLink();

    // 앱 실행 중 수신되는 딥링크 스트림 리스닝
    _linkSubscription = uriLinkStream.listen(
      (Uri? uri) {
        if (uri != null) {
          debugPrint('[DeepLinkService] 수신된 링크: $uri');
          handleDeepLink(uri);
        }
      },
      onError: (Object error) {
        debugPrint('[DeepLinkService] 스트림 에러: $error');
      },
    );

    debugPrint('[DeepLinkService] 초기화 완료');
  }

  /// 앱이 완전히 종료된 상태에서 딥링크로 열린 경우의 초기 링크 처리
  Future<void> _handleInitialLink() async {
    try {
      final Uri? initialUri = await getInitialUri();
      if (initialUri != null) {
        debugPrint('[DeepLinkService] 초기 딥링크 감지: $initialUri');
        // 스플래시 화면 및 초기 라우팅이 완료된 후 처리하기 위해 약간의 딜레이
        Future.delayed(const Duration(milliseconds: 1500), () {
          handleDeepLink(initialUri);
        });
      }
    } catch (e) {
      debugPrint('[DeepLinkService] 초기 딥링크 처리 실패: $e');
    }
  }

  // -------------------------------------------------------
  // 리소스 정리
  // -------------------------------------------------------

  /// 리스너를 해제하고 리소스를 정리합니다.
  void dispose() {
    _linkSubscription?.cancel();
    _linkSubscription = null;
    _isInitialized = false;
    debugPrint('[DeepLinkService] 리소스 정리 완료');
  }

  // -------------------------------------------------------
  // 공개 딥링크 핸들러
  // -------------------------------------------------------

  /// 주어진 URI를 파싱하여 해당하는 화면으로 라우팅합니다.
  ///
  /// 푸시 알림 등 외부에서도 직접 호출할 수 있습니다.
  ///
  /// [uri] wellness:// 또는 kivicare:// 스킴의 URI
  void handleDeepLink(Uri uri) {
    debugPrint('[DeepLinkService] 딥링크 처리 시작: $uri');

    // Android App Links / iOS Universal Links keep the original HTTPS URL.
    // Opening the same URL in the WebView also preserves every query parameter
    // (for example detail.php?id=123 or my_reviews.php?tab=done).
    if (_isSupportedWebLink(uri)) {
      _navigateToVerifiedWebView(uri);
      return;
    }

    // 스킴 유효성 검증
    if (!_isSupportedScheme(uri.scheme)) {
      debugPrint('[DeepLinkService] 지원하지 않는 스킴: ${uri.scheme}');
      return;
    }

    // URI에서 경로 세그먼트 추출
    // wellness://appointment/123 -> host="appointment", pathSegments=["123"]
    // wellness://oauth/callback  -> host="oauth", pathSegments=["callback"]
    final String host = uri.host;
    final List<String> pathSegments = uri.pathSegments;

    debugPrint('[DeepLinkService] host=$host, pathSegments=$pathSegments, '
        'queryParams=${uri.queryParameters}');

    try {
      _routeToScreen(host, pathSegments, uri.queryParameters);
    } catch (e, stackTrace) {
      debugPrint('[DeepLinkService] 라우팅 에러: $e');
      debugPrint('[DeepLinkService] 스택 트레이스: $stackTrace');
      toast('딥링크 처리 중 오류가 발생했습니다.');
    }
  }

  /// 문자열 URL을 파싱하여 딥링크를 처리합니다.
  ///
  /// 잘못된 URL 형식에 대해 안전하게 처리됩니다.
  void handleDeepLinkFromString(String url) {
    if (url.isEmpty) {
      debugPrint('[DeepLinkService] 빈 URL 무시');
      return;
    }

    try {
      final Uri uri = Uri.parse(url);
      handleDeepLink(uri);
    } catch (e) {
      debugPrint('[DeepLinkService] URL 파싱 실패: $e (url=$url)');
    }
  }

  // -------------------------------------------------------
  // 내부 라우팅 로직
  // -------------------------------------------------------

  /// 호스트와 경로 세그먼트에 기반하여 적절한 화면으로 라우팅
  void _routeToScreen(
    String host,
    List<String> pathSegments,
    Map<String, String> queryParameters,
  ) {
    switch (host) {
      case 'dashboard':
        _navigateToDashboard();
        break;

      case 'appointment':
        _navigateToAppointmentDetail(pathSegments);
        break;

      case 'clinic':
        _navigateToClinicDetail(pathSegments);
        break;

      case 'doctor':
        _navigateToDoctorDetail(pathSegments);
        break;

      case 'booking':
        _navigateToBookingForm();
        break;

      case 'service':
        _navigateToServiceDetail(pathSegments);
        break;

      case 'profile':
        _navigateToProfileTab();
        break;

      case 'settings':
        _navigateToSettingsTab();
        break;

      case 'webview':
        _navigateToWebView(queryParameters);
        break;

      case 'oauth':
        _handleOAuthCallback(pathSegments, queryParameters);
        break;

      case 'payment':
        // 토스페이먼츠 appScheme(wellness://payment): 페이북/ISP 앱에서 결제를 마치고
        // 앱으로 돌아온 것뿐이다. 결제는 웹뷰 안에서 계속 진행되므로 화면을 바꾸지 않는다.
        debugPrint('[DeepLinkService] 결제 앱에서 복귀');
        break;

      default:
        debugPrint('[DeepLinkService] 알 수 없는 경로: $host');
        toast('지원하지 않는 딥링크입니다.');
        break;
    }
  }

  // -------------------------------------------------------
  // 개별 화면 네비게이션 메서드
  // -------------------------------------------------------

  /// 대시보드 화면으로 이동
  void _navigateToDashboard() {
    debugPrint('[DeepLinkService] -> DashboardScreen');
    Get.offAll(() => DashboardScreen());
  }

  /// 예약 상세 화면으로 이동
  ///
  /// pathSegments[0]에 예약 ID가 포함되어야 합니다.
  void _navigateToAppointmentDetail(List<String> pathSegments) {
    final int? appointmentId = _extractId(pathSegments);
    if (appointmentId == null) {
      debugPrint('[DeepLinkService] 예약 ID 누락 또는 잘못된 형식');
      toast('잘못된 예약 링크입니다.');
      return;
    }

    debugPrint('[DeepLinkService] -> AppointmentDetail (id=$appointmentId)');

    // AppointmentDetailController.onInit()에서 Get.arguments를 AppointmentData로 수신
    final appointmentData = AppointmentData(id: appointmentId);
    Get.to(
      () => AppointmentDetail(),
      arguments: appointmentData,
    );
  }

  /// 클리닉 상세 화면으로 이동
  ///
  /// pathSegments[0]에 클리닉 ID가 포함되어야 합니다.
  void _navigateToClinicDetail(List<String> pathSegments) {
    final int? clinicId = _extractId(pathSegments);
    if (clinicId == null) {
      debugPrint('[DeepLinkService] 클리닉 ID 누락 또는 잘못된 형식');
      toast('잘못된 클리닉 링크입니다.');
      return;
    }

    debugPrint('[DeepLinkService] -> ClinicDetailScreen (id=$clinicId)');

    // ClinicDetailController.onInit()에서 Get.arguments를 Clinic으로 수신
    final clinicData = Clinic(id: clinicId, clinicSession: ClinicSession());
    Get.to(
      () => ClinicDetailScreen(),
      arguments: clinicData,
    );
  }

  /// 의사 상세 화면으로 이동
  ///
  /// pathSegments[0]에 의사 ID가 포함되어야 합니다.
  void _navigateToDoctorDetail(List<String> pathSegments) {
    final int? doctorId = _extractId(pathSegments);
    if (doctorId == null) {
      debugPrint('[DeepLinkService] 의사 ID 누락 또는 잘못된 형식');
      toast('잘못된 의사 링크입니다.');
      return;
    }

    debugPrint('[DeepLinkService] -> DoctorDetailScreen (id=$doctorId)');

    // DoctorDetailController.onInit()에서 Get.arguments를 Doctor로 수신
    final doctorData = Doctor(doctorId: doctorId);
    Get.to(
      () => DoctorDetailScreen(),
      arguments: doctorData,
    );
  }

  /// 예약 폼 화면으로 이동
  void _navigateToBookingForm() {
    debugPrint('[DeepLinkService] -> BookingFormScreen');
    Get.to(() => BookingFormScreen());
  }

  /// 서비스 상세 화면으로 이동
  ///
  /// pathSegments[0]에 서비스 ID가 포함되어야 합니다.
  void _navigateToServiceDetail(List<String> pathSegments) {
    final int? serviceId = _extractId(pathSegments);
    if (serviceId == null) {
      debugPrint('[DeepLinkService] 서비스 ID 누락 또는 잘못된 형식');
      toast('잘못된 서비스 링크입니다.');
      return;
    }

    debugPrint('[DeepLinkService] -> ServiceDetailScreen (id=$serviceId)');

    // ServiceDetailController.onInit()에서 Get.arguments가 int이면 ServiceElement(id: ...)로 변환
    Get.to(
      () => ServiceDetailScreen(),
      arguments: serviceId,
    );
  }

  /// 대시보드의 프로필 탭으로 이동
  ///
  /// DashboardController의 currentIndex를 프로필 탭 인덱스로 변경합니다.
  /// 로그인 상태에서는 ProfileScreen, 비로그인 상태에서는 SettingsScreen이 표시됩니다.
  void _navigateToProfileTab() {
    debugPrint('[DeepLinkService] -> 프로필 탭');
    _navigateToDashboardTab(BottomItem.profile);
  }

  /// 대시보드의 설정 탭으로 이동
  /// bottomNavItems에 settings 탭이 없을 수 있으므로 profile 탭으로 이동
  void _navigateToSettingsTab() {
    debugPrint('[DeepLinkService] -> 설정/프로필 탭');
    _navigateToDashboardTab(BottomItem.profile);
  }

  /// 대시보드의 특정 탭으로 이동하는 공통 메서드
  void _navigateToDashboardTab(BottomItem targetTab) {
    // 먼저 DashboardScreen으로 이동
    Get.offAll(() => DashboardScreen());

    // DashboardController가 등록된 후 탭 인덱스 변경
    WidgetsBinding.instance.addPostFrameCallback((_) {
      try {
        final DashboardController dashboardController =
            Get.find<DashboardController>();

        final int tabIndex = bottomNavItems.indexWhere(
          (item) => item.type == targetTab.name,
        );

        if (tabIndex >= 0) {
          dashboardController.currentIndex(tabIndex);
          dashboardController.selectedBottomNav(bottomNavItems[tabIndex]);
          debugPrint(
              '[DeepLinkService] 탭 전환 완료: ${targetTab.name} (index=$tabIndex)');
        } else {
          debugPrint('[DeepLinkService] 탭을 찾을 수 없음: ${targetTab.name}');
        }
      } catch (e) {
        debugPrint('[DeepLinkService] 탭 전환 실패: $e');
      }
    });
  }

  /// 웹뷰 화면으로 이동
  ///
  /// queryParameters에 'url' 키로 인코딩된 URL이 포함되어야 합니다.
  /// 선택적으로 'type' 파라미터를 지정할 수 있습니다 ('general' 또는 'sns').
  void _navigateToWebView(Map<String, String> queryParameters) {
    final String? encodedUrl = queryParameters['url'];
    if (encodedUrl == null || encodedUrl.isEmpty) {
      debugPrint('[DeepLinkService] 웹뷰 URL 누락');
      toast('웹뷰 URL이 지정되지 않았습니다.');
      return;
    }

    // URL 디코딩
    final String decodedUrl = Uri.decodeComponent(encodedUrl);
    final String type = queryParameters['type'] ?? 'general';

    debugPrint(
        '[DeepLinkService] -> StartupWebViewScreen (url=$decodedUrl, type=$type)');

    Get.to(
      () => StartupWebViewScreen(url: decodedUrl, type: type),
    );
  }

  /// Opens a verified shared website URL as the app's active WebView screen.
  void _navigateToVerifiedWebView(Uri uri) {
    final String url = uri.toString();
    debugPrint('[DeepLinkService] -> verified web URL: $url');
    Get.offAll(
      () => StartupWebViewScreen(url: url, type: 'general'),
    );
  }

  /// OAuth 콜백 처리를 기존 OAuthService에 위임
  void _handleOAuthCallback(
    List<String> pathSegments,
    Map<String, String> queryParameters,
  ) {
    // pathSegments가 ["callback"]인지 확인
    if (pathSegments.isEmpty || pathSegments.first != 'callback') {
      debugPrint('[DeepLinkService] 알 수 없는 OAuth 경로: $pathSegments');
      return;
    }

    debugPrint('[DeepLinkService] -> OAuth 콜백 위임');

    // 쿼리 파라미터에서 code/error를 추출하여 기존 로직으로 위임
    final String? code = queryParameters['code'];
    final String? error = queryParameters['error'];

    if (error != null && error.isNotEmpty) {
      debugPrint('[DeepLinkService] OAuth 에러: $error');
      toast('OAuth Error: $error');
      return;
    }

    if (code != null && code.isNotEmpty) {
      debugPrint('[DeepLinkService] OAuth 코드 수신: $code');
      // OAuthService.parseOAuthCallback과 동일한 결과를 외부에서 처리할 수 있도록
      // 콜백 핸들러가 등록되어 있다면 호출
      if (_oauthCallbackHandler != null) {
        _oauthCallbackHandler!(code, queryParameters['state'] ?? '');
      }
      return;
    }

    debugPrint('[DeepLinkService] OAuth 콜백에 code/error 없음');
  }

  // -------------------------------------------------------
  // OAuth 콜백 핸들러 등록
  // -------------------------------------------------------

  /// OAuth 인증 코드 수신 시 호출될 콜백
  void Function(String code, String state)? _oauthCallbackHandler;

  /// OAuth 콜백 핸들러를 등록합니다.
  ///
  /// OAuth 인증 플로우 시작 전에 호출하여 콜백을 등록하고,
  /// 인증 완료 후 자동으로 처리되도록 합니다.
  ///
  /// [handler] 인증 코드(code)와 상태(state)를 받는 콜백 함수
  void registerOAuthCallback(void Function(String code, String state) handler) {
    _oauthCallbackHandler = handler;
    debugPrint('[DeepLinkService] OAuth 콜백 핸들러 등록됨');
  }

  /// 등록된 OAuth 콜백 핸들러를 해제합니다.
  void unregisterOAuthCallback() {
    _oauthCallbackHandler = null;
    debugPrint('[DeepLinkService] OAuth 콜백 핸들러 해제됨');
  }

  // -------------------------------------------------------
  // 유틸리티 메서드
  // -------------------------------------------------------

  /// URI 스킴이 지원되는지 확인
  bool _isSupportedScheme(String scheme) {
    return _supportedSchemes.contains(scheme.toLowerCase());
  }

  bool _isSupportedWebLink(Uri uri) {
    if (uri.scheme.toLowerCase() != 'https') return false;
    if (!_supportedWebHosts.contains(uri.host.toLowerCase())) return false;

    final path = uri.path.toLowerCase();
    return path == _supportedWebPath ||
        path == '$_supportedWebPath/' ||
        path.startsWith('$_supportedWebPath/');
  }

  /// 경로 세그먼트의 첫 번째 요소를 정수 ID로 추출
  ///
  /// pathSegments가 비어있거나 파싱 실패 시 null 반환
  int? _extractId(List<String> pathSegments) {
    if (pathSegments.isEmpty) return null;

    final String rawId = pathSegments.first;
    final int? id = int.tryParse(rawId);

    if (id == null) {
      debugPrint('[DeepLinkService] ID 파싱 실패: "$rawId"');
    }

    return id;
  }
}
