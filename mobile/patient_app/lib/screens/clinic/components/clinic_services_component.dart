import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../components/loader_widget.dart';
import '../../../main.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../service/components/service_card.dart';
import '../../service/model/service_list_model.dart';
import '../clinic_detail_controller.dart';

class ClinicServicesComponent extends StatelessWidget {
  final ClinicDetailController clinicDetailCont;
  const ClinicServicesComponent({super.key, required this.clinicDetailCont});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Obx(
          () => SnapHelperWidget(
            future: clinicDetailCont.serviceListFuture.value,
            errorBuilder: (error) {
              return NoDataWidget(
                title: error,
                retryText: locale.value.reload,
                imageWidget: const ErrorStateWidget(),
                onRetry: () {
                  clinicDetailCont.servicesPage(1);
                  clinicDetailCont.getServiceList();
                },
              ).paddingSymmetric(horizontal: 32);
            },
            loadingWidget: const Offstage(),
            onSuccess: (p0) {
              return AnimatedListView(
                shrinkWrap: true,
                padding: EdgeInsets.zero,
                itemCount: clinicDetailCont.serviceList.length,
                physics: const NeverScrollableScrollPhysics(),
                emptyWidget: NoDataWidget(
                  title: locale.value.noServicesFoundAtAMoment,
                  subTitle: locale.value.looksLikeThereIsNoServicesListedOnThisClinicW,
                  titleTextStyle: primaryTextStyle(),
                  imageWidget: const EmptyStateWidget(),
                  retryText: locale.value.reload,
                  onRetry: () {
                    clinicDetailCont.servicesPage(1);
                    clinicDetailCont.getServiceList();
                  },
                ).paddingSymmetric(horizontal: 32).paddingBottom(Get.height * 0.1),
                itemBuilder: (context, index) {
                  final ServiceElement serviceElement = clinicDetailCont.serviceList[index];
                  return ServiceCard(serviceElement: serviceElement).paddingTop(index == 0 ? 0 : 16);
                },
                onNextPage: () async {
                  if (!clinicDetailCont.isServicesLastPage.value) {
                    clinicDetailCont.servicesPage(clinicDetailCont.servicesPage.value + 1);
                    clinicDetailCont.getServiceList();
                  }
                },
                onSwipeRefresh: () async {
                  clinicDetailCont.servicesPage(1);
                  return clinicDetailCont.getServiceList(showLoader: false);
                },
              ).paddingSymmetric(horizontal: 16);
            },
          ),
        ).paddingOnly(top: 16, bottom: 80),
        Obx(() => const LoaderWidget().center().paddingTop(Get.height * 0.12).visible(clinicDetailCont.isServicesLoading.value)),
      ],
    );
  }
}
