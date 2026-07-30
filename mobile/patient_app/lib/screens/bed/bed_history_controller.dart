import 'dart:async';

import 'package:get/get.dart';
import 'package:kivicare_patient/screens/bed/model/bed_history_model.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';

class BedHistoryController extends GetxController {
  Rx<Future<RxList<BedHistoryData>>> bedHistoryListFuture = Future(() => RxList<BedHistoryData>()).obs;
  RxBool isLoading = false.obs;
  RxList<BedHistoryData> bedHistoryList = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;

  @override
  void onReady() {
    getBedHistoryList(showLoader: false);
    super.onReady();
  }

  Future<void> getBedHistoryList({bool showLoader = true}) async {
    isLoading(showLoader);
    await bedHistoryListFuture(
      CoreServiceApis.getBedHistory(
        page: page.value,
        bedHistoryList: bedHistoryList,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isLoading(false);
      log("getEncounterList Err : $e");
    }).whenComplete(() => isLoading(false));
  }
}
