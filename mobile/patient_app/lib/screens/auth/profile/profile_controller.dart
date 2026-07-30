// ignore_for_file: depend_on_referenced_packages

import 'package:get/get.dart';
import 'package:kivicare_patient/utils/constants.dart';
import 'package:kivicare_patient/utils/local_storage.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../utils/app_common.dart';
import '../../dashboard/dashboard_screen.dart';
import '../../home/home_controller.dart';
import '../../../api/auth_apis.dart';

class ProfileController extends GetxController {
  RxBool isLoading = false.obs;
  RxBool isWalletLoading = false.obs;
  RxBool isAutoUpdateOn = false.obs;

  @override
  void onInit() {
    init();
    super.onInit();
  }

  void init() {
    getAboutPageData();
    isWalletLoading(true);
    AuthServiceApis.getUserWallet().whenComplete(() => isWalletLoading(false));
    isAutoUpdateOn(getValueFromLocal(AutoUpdateConst.isAutoUpdateOn) ?? false);
  }

  Future<void> handleLogout() async {
    if (isLoading.value) return;
    isLoading(true);
    log('HANDLELOGOUT: called');
    await AuthServiceApis.logoutApi().then((value) {
      isLoading(false);
    }).catchError((e) {
      toast(e.toString());
    }).whenComplete(() {
      AuthServiceApis.clearData();
      isLoggedIn.value = false;
      setValueToLocal(SharedPreferenceConst.IS_LOGGED_IN, false);
      Get.offAll(
        () => DashboardScreen(),
        binding: BindingsBuilder(() {
          Get.put(HomeController());
        }),
      );
    });
  }

  ///Get About Pages
  void getAboutPageData({bool isFromSwipeRefresh = false}) {
    if (!isFromSwipeRefresh) {
      isLoading(true);
    }
    isLoading(true);
    AuthServiceApis.getAboutPageData().then((value) {
      isLoading(false);
      aboutPages(value.data);
    }).onError((error, stackTrace) {
      isLoading(false);
      toast(error.toString());
    });
  }
}
