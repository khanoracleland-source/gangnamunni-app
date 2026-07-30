// ignore_for_file: depend_on_referenced_packages
import 'dart:async';

import 'package:flutter/cupertino.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:stream_transform/stream_transform.dart';

import '../../api/core_apis.dart';
import '../service/model/service_list_model.dart';
import 'model/doctor_detail_model.dart';
import 'model/doctor_list_res.dart';

class DoctorDetailController extends GetxController {
  RxBool isLoading = false.obs;

  Rx<Future<DoctorDetailModel>> getDoctorDetail = Future(() => DoctorDetailModel(data: Doctor())).obs;
  Rx<Doctor> doctorData = Doctor().obs;

  //Services
  Rx<Future<RxList<ServiceElement>>> serviceListFuture = Future(() => RxList<ServiceElement>()).obs;
  RxBool isServicesLoading = false.obs;
  RxList<ServiceElement> serviceList = RxList();
  RxBool isServicesLastPage = false.obs;
  RxInt servicesPage = 1.obs;

  ///Search
  TextEditingController searchCont = TextEditingController();
  RxBool isSearchText = false.obs;
  StreamController<String> searchStream = StreamController<String>();
  final _scrollController = ScrollController();

  @override
  void onInit() {
    _scrollController.addListener(() => Get.context != null ? hideKeyboard(Get.context) : null);
    searchStream.stream.debounce(const Duration(seconds: 1)).listen((s) {
      getServiceList();
    });
    if (Get.arguments is Doctor) {
      doctorData(Get.arguments);
    }
    init(showLoader: false);
    super.onInit();
  }

  ///Get Doctor Detail
  Future<void> init({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await getDoctorDetail(
      CoreServiceApis.getDoctorDetails(doctorId: doctorData.value.doctorId),
    ).then((value) {
      doctorData(value.data);
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
      log('DoctorDetail getDoctorDetail err ==> $e');
    }).whenComplete(() => isLoading(false));
  }

  Future<void> getServiceList({bool showLoader = true}) async {
    if (showLoader) {
      isServicesLoading(true);
    }
    await serviceListFuture(
      CoreServiceApis.getDoctorServiceList(
        page: servicesPage.value,
        serviceList: serviceList,
        doctorId: doctorData.value.doctorId,
        search: searchCont.text.trim(),
        lastPageCallBack: (p0) {
          isServicesLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isServicesLoading(false);
      log('doctor detail getServiceList err ==> $e');
    }).whenComplete(() => isServicesLoading(false));
  }

  @override
  void onClose() {
    searchStream.close();
    if (Get.context != null) {
      _scrollController.removeListener(() => hideKeyboard(Get.context));
    }
    super.onClose();
  }
}
