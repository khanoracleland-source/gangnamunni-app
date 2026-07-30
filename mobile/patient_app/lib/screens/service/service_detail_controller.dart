// ignore_for_file: depend_on_referenced_packages
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';
import '../clinic/model/clinic_detail_model.dart';
import '../clinic/model/clinics_res_model.dart';
import 'model/service_detail_model.dart';
import 'model/service_list_model.dart';

class ServiceDetailController extends GetxController {
  RxBool isLoading = false.obs;

  Rx<Future<ServiceDetailModel>> getServiceDetails = Future(() => ServiceDetailModel(data: ServiceElement())).obs;
  Rx<ServiceElement> serviceData = ServiceElement().obs;

  Rx<Clinic> selectedClinic = Clinic(clinicSession: ClinicSession()).obs;

  @override
  void onInit() {
    if (Get.arguments is ServiceElement) {
      serviceData(Get.arguments);
    } else if (Get.arguments is int) {
      serviceData(ServiceElement(id: Get.arguments as int));
    }
    init(showLoader: false);
    super.onInit();
  }

  ///Get Service Detail
  Future<void> init({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }
    await getServiceDetails(
      CoreServiceApis.getServiceDetail(serviceId: serviceData.value.id),
    ).then((value) {
      serviceData(value.data);
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
      log('ServiceDetail getServiceDetail err ==> $e');
    }).whenComplete(() => isLoading(false));
  }
}
