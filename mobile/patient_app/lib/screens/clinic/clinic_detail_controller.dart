// ignore_for_file: depend_on_referenced_packages
import 'dart:async';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';
import '../doctor/model/doctor_list_res.dart';
import '../service/model/service_list_model.dart';
import 'model/clinic_detail_model.dart';
import 'model/clinics_res_model.dart';

class ClinicDetailController extends GetxController {
  RxBool isLoading = false.obs;

  Rx<Future<ClinicDetailModel>> getClinicDetail = Future(() => ClinicDetailModel(data: Clinic(clinicSession: ClinicSession()))).obs;
  Rx<Clinic> clinicData = Clinic(clinicSession: ClinicSession()).obs;

  //Services
  Rx<Future<RxList<ServiceElement>>> serviceListFuture = Future(() => RxList<ServiceElement>()).obs;
  RxBool isServicesLoading = false.obs;
  RxList<ServiceElement> serviceList = RxList();
  RxBool isServicesLastPage = false.obs;
  RxInt servicesPage = 1.obs;
  RxInt activeServicesCount = 0.obs;

  //Doctors
  Rx<Future<RxList<Doctor>>> doctorsFuture = Future(() => RxList<Doctor>()).obs;
  RxBool isDoctorsLoading = false.obs;
  RxList<Doctor> doctors = RxList();
  RxBool isDoctorsLastPage = false.obs;
  RxInt doctorsPage = 1.obs;
  RxInt activeDoctorsCount = 0.obs;

  @override
  void onInit() {
    if (Get.arguments is Clinic) {
      clinicData(Get.arguments);
    }
    init(showLoader: false);
    super.onInit();
  }

  ///Get Clinic Detail
  Future<void> init({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await getClinicDetail(
      CoreServiceApis.getClinicDetails(clinicId: clinicData.value.id),
    ).then((value) {
      clinicData(value.data);
      isLoading(false);
      // Load services after clinic details are loaded to calculate active count
      getServiceList(showLoader: false);
      // Load doctors after clinic details are loaded to calculate active count
      getDoctors(showLoader: false);
    }).catchError((e) {
      isLoading(false);
      log('ClinicDetail getClinicDetail err ==> $e');
    }).whenComplete(() => isLoading(false));
  }

  Future<void> getServiceList({bool showLoader = true}) async {
    if (showLoader) {
      isServicesLoading(true);
    }
    await serviceListFuture(
      CoreServiceApis.getServiceList(
        page: servicesPage.value,
        serviceList: serviceList,
        clinicId: clinicData.value.id,
        lastPageCallBack: (p0) {
          isServicesLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
      // Load all services to get accurate count
      getAllServicesForCount();
    }).catchError((e) {
      log('Clinic detail getServiceList err ==> $e');
    }).whenComplete(() => isServicesLoading(false));
  }

  /// Load all services for counting active services
  Future<void> getAllServicesForCount() async {
    try {
      final RxList<ServiceElement> allServicesList = RxList();
      await CoreServiceApis.getServiceList(
        serviceList: allServicesList,
        clinicId: clinicData.value.id,
        allServices: 'all',
        lastPageCallBack: (p0) {
        },
      );
      activeServicesCount(allServicesList.where((service) => service.status == 1).length);
    } catch (e) {
      log('Error loading all services for count: $e');
      activeServicesCount(serviceList.where((service) => service.status == 1).length);
    }
  }

  /// Load all doctors for counting active doctors
  Future<void> getAllDoctorsForCount() async {
    try {
      final RxList<Doctor> allDoctorsList = RxList();
      await CoreServiceApis.getDoctors(
        perPage: 1000,
        doctors: allDoctorsList,
        clinicId: clinicData.value.id,
        lastPageCallBack: (p0) {
        },
      );
      activeDoctorsCount(allDoctorsList.where((doctor) => doctor.status == 1).length);
    } catch (e) {
      activeDoctorsCount(doctors.where((doctor) => doctor.status == 1).length);
    }
  }

  Future<void> getDoctors({bool showLoader = true}) async {
    if (showLoader) {
      isDoctorsLoading(true);
    }
    await doctorsFuture(
      CoreServiceApis.getDoctors(
        page: doctorsPage.value,
        doctors: doctors,
        clinicId: clinicData.value.id,
        lastPageCallBack: (p0) {
          isDoctorsLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
      // Load all doctors to get accurate count
      getAllDoctorsForCount();
    }).catchError((e) {
      log("getDoctors error $e");
    }).whenComplete(() => isDoctorsLoading(false));
  }
}
