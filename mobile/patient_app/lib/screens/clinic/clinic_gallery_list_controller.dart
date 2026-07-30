import 'dart:async';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/api/core_apis.dart';

import 'model/clinic_gallery_model.dart';

class ClinicGalleryListController extends GetxController {
  Rx<Future<RxList<GalleryData>>> galleryListFuture = Future(() => RxList<GalleryData>()).obs;
  RxBool isLoading = false.obs;
  RxList<GalleryData> galleryList = RxList();
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;
  RxInt clinicId = (-1).obs;

  @override
  void onInit() {
    if (Get.arguments is int) {
      clinicId(Get.arguments as int);
    }
    getGalleryList();
    super.onInit();
  }

  Future<void> getGalleryList({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await galleryListFuture(
      CoreServiceApis.getClinicGalleryList(
        page: page.value,
        galleryList: galleryList,
        clinicId: clinicId.value,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isLoading(false);
      log("getClinicGallery error $e");
    }).whenComplete(() => isLoading(false));
  }
}
