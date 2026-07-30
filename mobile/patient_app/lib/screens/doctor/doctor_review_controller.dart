import 'dart:async';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/api/core_apis.dart';

import '../booking/model/employee_review_data.dart';

class DoctorReviewController extends GetxController {
  Rx<Future<RxList<DoctorReviewData>>> doctorReviewListFuture = Future(() => RxList<DoctorReviewData>()).obs;
  RxBool isLoading = false.obs;
  RxList<DoctorReviewData> doctorReviewList = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;
  RxInt doctorId = (-1).obs;

  @override
  void onInit() {
    if (Get.arguments is int) {
      doctorId(Get.arguments as int);
    }
    getDoctorReviewList();
    super.onInit();
  }

  Future<void> getDoctorReviewList({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await doctorReviewListFuture(
      CoreServiceApis.getDoctorReviews(
        page: page.value,
        reviewList: doctorReviewList,
        doctorId: doctorId.value,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isLoading(false);
      log("getDoctorReviewList error $e");
    }).whenComplete(() => isLoading(false));
  }
}
