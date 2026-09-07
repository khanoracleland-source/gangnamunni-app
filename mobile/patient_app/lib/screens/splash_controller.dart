// ignore_for_file: depend_on_referenced_packages

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/screens/home/home_controller.dart';
import 'package:kivicare_patient/screens/walkthrough/walkthrough_screen.dart';
import 'package:kivicare_patient/screens/webview/app_auth_gate_screen.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:kivicare_patient/utils/local_storage.dart';
import '../main.dart';
import '../api/auth_apis.dart';
import '../configs.dart';
import '../utils/common_base.dart';
import '../utils/constants.dart';
import '../utils/push_notification_service.dart';
import 'auth/model/login_response.dart';
import 'dashboard/dashboard_screen.dart';

class SplashScreenController extends GetxController {
  @override
  void onInit() {
    super.onInit();
    //Get Package Info
    getPackageInfo().then((value) => currentPackageinfo(value));
    getAppConfigurations();
  }

  @override
  void onReady() {
    try {
      final getThemeFromLocal =
          getValueFromLocal(SettingsLocalConst.THEME_MODE);
      if (getThemeFromLocal is int) {
        toggleThemeMode(themeId: getThemeFromLocal);
      } else {
        toggleThemeMode(themeId: THEME_MODE_LIGHT);
      }
    } catch (e) {
      log('getThemeFromLocal from cache E: $e');
    }
    super.onReady();
  }

  ///Get ChooseService List
  Future<void> getAppConfigurations() async {
    await AuthServiceApis.getAppConfigurations().then((value) {
      appCurrency(value.currency);
      appConfigs(value);

      // Force-apply admin default language when provided
      final adminLang = value.applicationLanguage.trim();
      if (adminLang.isNotEmpty) {
        applyLanguage(adminLang);
      }

      ///Navigation logic
      navigationLogic();
    }).onError((error, stackTrace) {
      toast(error.toString());

      ///Navigation logic
      navigationLogic();
    });
  }

  void navigationLogic() {
    // Check if WebView should be shown on startup
    if (SHOW_WEBVIEW_ON_STARTUP) {
      Get.offAll(() => AppAuthGateScreen(
            url: STARTUP_WEBVIEW_URL,
            type: STARTUP_WEBVIEW_TYPE,
          ));
      return;
    }

    // Original navigation logic
    if ((getValueFromLocal(SharedPreferenceConst.FIRST_TIME) ?? false) ==
        false) {
      Get.offAll(() => WalkthroughScreen());
    } else if (getValueFromLocal(SharedPreferenceConst.IS_LOGGED_IN) == true) {
      try {
        final userData = getValueFromLocal(SharedPreferenceConst.USER_DATA);
        isLoggedIn(true);
        loginUserData(UserData.fromJson(userData));
        PushNotificationService().registerFCMAndTopics();
        Get.offAll(
          () => DashboardScreen(),
          binding: BindingsBuilder(() {
            Get.put(HomeController());
          }),
        );
      } catch (e) {
        log('SplashScreenController Err: $e');
        Get.offAll(
          () => DashboardScreen(),
          binding: BindingsBuilder(() {
            Get.put(HomeController());
          }),
        );
      }
    } else {
      Get.offAll(
        () => DashboardScreen(),
        binding: BindingsBuilder(() {
          Get.put(HomeController());
        }),
      );
    }
  }
}
