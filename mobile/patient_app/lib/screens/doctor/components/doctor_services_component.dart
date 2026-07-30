import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../../main.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../service/model/service_list_model.dart';
import '../doctor_detail_controller.dart';
import 'doctor_service_card.dart';

class DoctorServicesComponent extends StatelessWidget {
  DoctorServicesComponent({super.key});

  final DoctorDetailController doctorDetailCont = Get.find();

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.services,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: doctorDetailCont.isLoading,
      body: Obx(
        () => AnimatedListView(
          shrinkWrap: true,
          padding: const EdgeInsets.only(left: 16, right: 16, top: 16),
          itemCount: doctorDetailCont.doctorData.value.services.length,
          emptyWidget: NoDataWidget(
            title: locale.value.noServicesFoundAtAMoment,
            subTitle: locale.value.looksLikeThereIsNoServicesProvidedByThisDocto,
            titleTextStyle: primaryTextStyle(),
            imageWidget: const EmptyStateWidget(),
            retryText: locale.value.reload,
            onRetry: () {
              doctorDetailCont.servicesPage(1);
              doctorDetailCont.getServiceList();
            },
          ).paddingSymmetric(horizontal: 32).paddingBottom(Get.height * 0.1),
          itemBuilder: (context, index) {
            final ServiceElement serviceElement = doctorDetailCont.doctorData.value.services[index];
            return DoctorServiceCard(serviceElement: serviceElement, doctor: doctorDetailCont.doctorData.value).paddingBottom(16);
          },
          onNextPage: () async {
            if (!doctorDetailCont.isServicesLastPage.value) {
              doctorDetailCont.servicesPage(doctorDetailCont.servicesPage.value + 1);
              doctorDetailCont.getServiceList();
            }
          },
          onSwipeRefresh: () async {
            doctorDetailCont.servicesPage(1);
            return doctorDetailCont.getServiceList(showLoader: false);
          },
        ),
      ),
    );
  }
}
