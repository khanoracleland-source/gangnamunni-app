// ignore_for_file: depend_on_referenced_packages

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';
import 'model/encounter_detail_model.dart';

class EncounterDetailController extends GetxController {
  RxBool isLoading = false.obs;

  Rx<Future<EncounterDetailModel>> getEncounterDetails = Future(() => EncounterDetailModel(data: EncounterData(soap: Soap()))).obs;

  Rx<EncounterData> encounterDetail = EncounterData(soap: Soap()).obs;

  RxInt encounterId = (-1).obs;

  @override
  void onInit() {
    if (Get.arguments is int) {
      encounterId(Get.arguments as int);
    }
    init(showLoader: false);
    super.onInit();
  }

  ///Get Encounter Detail
  Future<void> init({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await getEncounterDetails(
      CoreServiceApis.getEncounterDetail(encounterId: encounterId.value),
    ).then((value) {
      encounterDetail(value.data);
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
      log(e.toString());
    }).whenComplete(() => isLoading(false));
  }
}
