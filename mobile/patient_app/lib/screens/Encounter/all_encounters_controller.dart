import 'dart:async';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';
import 'model/encounter_list_model.dart';

class AllEncountersController extends GetxController {
  Rx<Future<RxList<EncounterElement>>> encounterListFuture = Future(() => RxList<EncounterElement>()).obs;
  RxBool isLoading = false.obs;
  RxList<EncounterElement> encounterList = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;

  @override
  void onReady() {
    getAllEncounters();
    super.onReady();
  }

  Future<void> getAllEncounters({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await encounterListFuture(
      CoreServiceApis.getEncounterList(
        page: page.value,
        encounterList: encounterList,
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
