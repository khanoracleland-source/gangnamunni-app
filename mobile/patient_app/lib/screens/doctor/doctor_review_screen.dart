import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import '../booking/model/employee_review_data.dart';
import 'components/doctor_review_card.dart';
import 'doctor_review_controller.dart';

class DoctorReviewScreen extends StatelessWidget {
  DoctorReviewScreen({super.key});

  final DoctorReviewController doctorReviewCont = Get.put(DoctorReviewController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.reviews,
      scaffoldBackgroundColor: context.scaffoldBackgroundColor,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: doctorReviewCont.isLoading,
      body: Obx(
        () => SnapHelperWidget(
          future: doctorReviewCont.doctorReviewListFuture.value,
          errorBuilder: (error) {
            return NoDataWidget(
              title: error,
              retryText: locale.value.reload,
              imageWidget: const ErrorStateWidget(),
              onRetry: () {
                doctorReviewCont.page(1);
                doctorReviewCont.getDoctorReviewList();
              },
            ).paddingSymmetric(horizontal: 32);
          },
          loadingWidget: doctorReviewCont.isLoading.value ? const Offstage() : const LoaderWidget(),
          onSuccess: (p0) {
            return AnimatedListView(
              shrinkWrap: true,
              padding: const EdgeInsets.only(left: 16, right: 16, top: 16),
              itemCount: doctorReviewCont.doctorReviewList.length,
              physics: const AlwaysScrollableScrollPhysics(),
              emptyWidget: NoDataWidget(
                title: locale.value.noReviewsFoundAtAMoment,
                subTitle: locale.value.looksLikeThereIsNoReviewsWellKeepYouPostedWhe,
                titleTextStyle: primaryTextStyle(),
                imageWidget: const EmptyStateWidget(),
                retryText: locale.value.reload,
                onRetry: () {
                  doctorReviewCont.page(1);
                  doctorReviewCont.getDoctorReviewList();
                },
              ).paddingSymmetric(horizontal: 32),
              itemBuilder: (context, index) {
                final DoctorReviewData doctorReviewData = doctorReviewCont.doctorReviewList[index];
                return DoctorReviewCard(doctorReviewData: doctorReviewData).paddingBottom(16);
              },
              onNextPage: () async {
                if (!doctorReviewCont.isLastPage.value) {
                  doctorReviewCont.page(doctorReviewCont.page.value + 1);
                  doctorReviewCont.getDoctorReviewList();
                }
              },
              onSwipeRefresh: () async {
                doctorReviewCont.page(1);
                return doctorReviewCont.getDoctorReviewList(showLoader: false);
              },
            );
          },
        ),
      ),
    );
  }
}
