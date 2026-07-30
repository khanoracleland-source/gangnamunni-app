// ignore_for_file: invalid_use_of_protected_member

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/auth_apis.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/common_base.dart';
import '../../utils/constants.dart';
import '../../utils/local_storage.dart';
import '../auth/other/settings_screen.dart';
import '../auth/profile/profile_controller.dart';
import '../auth/profile/profile_screen.dart';
import '../booking/appointments_screen.dart';
import '../home/home_screen.dart';
import 'components/menu.dart';

class DashboardController extends GetxController {
  RxInt currentIndex = 0.obs;
  RxBool isLoading = false.obs;
  TextEditingController phoneController = TextEditingController();
  final GlobalKey<FormState> phoneFormKey = GlobalKey();

  Rx<BottomBarItem> selectedBottomNav = BottomBarItem(title: locale.value.home.obs, icon: Assets.navigationIcHomeOutlined, activeIcon: Assets.navigationIcHomeFilled, type: BottomItem.home.name).obs;

  RxList<StatelessWidget> screen = [
    HomeScreen(),
    AppointmentsScreen(),
  ].obs;

  @override
  void onInit() {
    if (!isLoggedIn.value) {
      ProfileController().getAboutPageData();
    }
    getAppConfigurations().then((value) {
      Future.delayed(const Duration(seconds: 2), () {
        if (Get.context != null) {
          showForceUpdateDialog(Get.context!);
        }
      });
    });
    super.onInit();
  }

  @override
  void onReady() {
    reloadBottomTabs();
    if (Get.context != null) {
      View.of(Get.context!).platformDispatcher.onPlatformBrightnessChanged = () {
        WidgetsBinding.instance.handlePlatformBrightnessChanged();
        try {
          final getThemeFromLocal = getValueFromLocal(SettingsLocalConst.THEME_MODE);
          if (getThemeFromLocal is int) {
            toggleThemeMode(themeId: getThemeFromLocal);
          }
        } catch (e) {
          log('getThemeFromLocal from cache E: $e');
        }
      };
    }
    super.onReady();
  }

  void reloadBottomTabs() {
    debugPrint('reloadBottomTabs ISLOGGEDIN.VALUE: ${isLoggedIn.value}');
    if (isLoggedIn.value) {
      screen.removeWhere((element) => element is SettingScreen);
      if (bottomNavItems.indexWhere((element) => element is ProfileScreen).isNegative) {
        screen.add(ProfileScreen());
      }
      screen.toSet();

      bottomNavItems.removeWhere((element) => element.type == BottomItem.settings.name);
      if (bottomNavItems.indexWhere((element) => element.type == BottomItem.profile.name).isNegative) {
        bottomNavItems.add(BottomBarItem(title: locale.value.profile.obs, icon: Assets.navigationIcUserOutlined, activeIcon: Assets.navigationIcUserFilled, type: BottomItem.profile.name));
      }
      bottomNavItems.toSet();
    } else {
      screen.removeWhere((element) => element is ProfileScreen);
      screen.add(SettingScreen());
      screen.toSet();

      bottomNavItems.removeWhere((element) => element.type == BottomItem.profile.name);
      if (bottomNavItems.indexWhere((element) => element.type == BottomItem.settings.name).isNegative) {
        bottomNavItems.add(BottomBarItem(title: locale.value.settings.obs, icon: Assets.iconsIcSettingOutlined, activeIcon: Assets.iconsIcSetting, type: BottomItem.settings.name));
      }
      bottomNavItems.toSet();
    }
    selectedBottomNav(bottomNavItems[currentIndex.value]);
  }

  /*Future<void> showNumberSheet() async {
    Get.bottomSheet(
      PhoneNumberBottomSheet(
        onConfirm: (phone) async {
          Map<String,String> req = {
            'phone_number+': phone,
          };
          await AuthServiceApis.addUserPhoneNumber(request: req).then((value) async {
            if (value.status == true) {
              toast(value.message);
            }
          }).catchError((e) {
            isLoading(false);
            toast(e.toString(), print: true);
          });
        },
      ),
      isScrollControlled: true,
    );
  }*/
}

///Get App Configuration Api
Future<void> getAppConfigurations() async {
  await AuthServiceApis.getAppConfigurations().then((value) async {
    appConfigs(value);

    /// Place ChatGPT Key Here
    chatGPTAPIkey = value.chatgptKey;

    final adminLang = value.applicationLanguage.trim();
    if (adminLang.isNotEmpty) {
      await applyLanguage(adminLang);
    }
  }).onError((error, stackTrace) {
    toast(error.toString());
  });
}
