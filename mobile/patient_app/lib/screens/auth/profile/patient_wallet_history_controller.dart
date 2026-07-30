import 'package:get/get.dart';
import 'package:kivicare_patient/api/auth_apis.dart';
import 'package:nb_utils/nb_utils.dart';
import '../model/patient_wallet_history_res.dart';

class PatientWalletHistoryController extends GetxController {
  RxBool isLoading = false.obs;
  Rx<Future<RxList<WalletHistoryElement>>> walletHistoryFuture = Future(() => RxList<WalletHistoryElement>()).obs;
  RxList<WalletHistoryElement> historyData = RxList();
  RxBool isPatientLastPage = false.obs;
  RxInt historyPage = 1.obs;

  @override
  void onInit() {
    getWalletHistory();
    super.onInit();
  }

  Future<void> getWalletHistory({bool showloader = true}) async {
    if (showloader) {
      isLoading(true);
    }
    await walletHistoryFuture(
      AuthServiceApis.getWalletHistory(
        historyData: historyData,
        page: historyPage.value,
        lastPageCallBack: (p0) {
          isPatientLastPage(p0);
        },
      ),
    ).then((value) {}).catchError((e) {
      toast("Error: $e");
      log("getWalletHistory err: $e");
    }).whenComplete(() => isLoading(false));
  }
}
