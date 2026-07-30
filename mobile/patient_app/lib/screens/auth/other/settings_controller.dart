import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/screens/booking/model/appointment_status_model.dart';
import 'package:kivicare_patient/screens/dashboard/components/menu.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../configs.dart';
import '../../dashboard/dashboard_screen.dart';
import '../../home/home_controller.dart';
import '../model/theme_mode_data_model.dart';
import '../../../api/auth_apis.dart';
import '../../../utils/app_common.dart';
import '../../../utils/common_base.dart';
import '../../../utils/constants.dart';
import '../../../utils/local_storage.dart';

class SettingsController extends GetxController {
  RxBool isLoading = false.obs;
  RxBool isPayment = false.obs;
  RxBool isTouchId = false.obs;

  Rx<LanguageDataModel> selectedLang = LanguageDataModel().obs;
  List<ThemeModeData> themeModes = [ThemeModeData(id: THEME_MODE_SYSTEM, mode: "System"), ThemeModeData(id: THEME_MODE_LIGHT, mode: "Light"), ThemeModeData(id: THEME_MODE_DARK, mode: "Dark")];
  Rx<ThemeModeData> dropdownValue = ThemeModeData().obs;

  void handleDeleteAccountClick() {
    ifNotTester(() {
      isLoading(true);

      AuthServiceApis.deleteAccountCompletely().then((value) async {
        AuthServiceApis.clearData(isFromDeleteAcc: true);
        isLoading(false);
        toast(value.message);
        Get.offAll(
          () => DashboardScreen(),
          binding: BindingsBuilder(() {
            Get.put(HomeController());
          }),
        );
      }).catchError((e) {
        isLoading(false);
        toast(e.toString());
      });
    });
  }

  @override
  Future<void> onInit() async {
    final cached = getValueFromLocal(SELECTED_LANGUAGE_CODE) ?? DEFAULT_LANGUAGE;
    selectedLanguageCode(cached);
    if (appLanguageList.isEmpty) appLanguageList.assignAll(languageList());
    // Ensure the selectedLang points to an item instance from appLanguageList
    selectedLang(
      appLanguageList.firstWhere(
        (element) => element.languageCode == selectedLanguageCode.value,
        orElse: () => appLanguageList.first,
      ),
    );
    log('ISDARK: ${isDarkMode.value}');

    super.onInit();
  }

  @override
  void onReady() {
    try {
      final getThemeFromLocal = getValueFromLocal(SettingsLocalConst.THEME_MODE);
      if (getThemeFromLocal is int) {
        dropdownValue(
          themeModes.firstWhere(
            (element) => element.id == getThemeFromLocal,
            orElse: () => ThemeModeData(),
          ),
        );
        toggleThemeMode(themeId: getThemeFromLocal);
      }
    } catch (e) {
      log('getThemeFromLocal from cache E: $e');
    }
    super.onReady();
  }

  void onLanguageChange() {
    log('SettingsController - onClose called-----------------------');
    bottomNavItems[0].title.value = locale.value.home;
    bottomNavItems[1].title.value = locale.value.appointment;
    bottomNavItems[2].title.value = locale.value.profile;
    for (final status in filterStatus) {
      switch (status.type) {
        case AppointmentStatus.all:
          status.name!.value = locale.value.all;
          break;
        case AppointmentStatus.upcoming:
          status.name!.value = locale.value.upcoming;
          break;
        case AppointmentStatus.completed:
          status.name!.value = locale.value.completed;
          break;
        case AppointmentStatus.pending:
          status.name!.value = locale.value.pending;
          break;
        case AppointmentStatus.cancelled:
          status.name!.value = locale.value.cancel;
          break;
      }
    }
  }
}
