import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/screens/doctor/components/doctor_card.dart';
import 'package:kivicare_patient/screens/doctor/search_doctor_widget.dart';

import '../../../components/app_scaffold.dart';
import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/colors.dart';
import '../../utils/empty_error_state_widget.dart';
import '../slots/booking_form_screen.dart';
import 'doctor_list_controller.dart';
import 'model/doctor_list_res.dart';

class DoctorsListScreen extends StatelessWidget {
  DoctorsListScreen({super.key});

  final DoctorListController doctorsListCont = Get.put(DoctorListController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.chooseDoctor,
      scaffoldBackgroundColor: context.scaffoldBackgroundColor,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: doctorsListCont.isLoading,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SearchDoctorWidget(
            doctorListController: doctorsListCont,
            onFieldSubmitted: (p0) {
              hideKeyboard(context);
            },
          ).paddingAll(16),
          Obx(
            () => SnapHelperWidget(
              future: doctorsListCont.doctorsFuture.value,
              errorBuilder: (error) {
                return NoDataWidget(
                  title: error,
                  retryText: locale.value.reload,
                  imageWidget: const ErrorStateWidget(),
                  onRetry: () {
                    doctorsListCont.page(1);
                    doctorsListCont.getDoctors();
                  },
                ).paddingSymmetric(horizontal: 32);
              },
              loadingWidget: doctorsListCont.isLoading.value ? const Offstage() : const LoaderWidget(),
              onSuccess: (p0) {
                // Auto-select doctor if only one available - run after build is complete
                /*WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (doctorsListCont.doctors.length == 1 && doctorsListCont.selectedDoctor.value.doctorId.isNegative) {
                    doctorsListCont.selectedDoctor(doctorsListCont.doctors[0]);
                    // Set global variable for booking form
                    currentSelectedDoctor(doctorsListCont.doctors[0]);
                    log('Auto-selected doctor: ${doctorsListCont.doctors[0].fullName}');
                    
                    // Navigate directly to booking form screen
                    Future.delayed(const Duration(milliseconds: 500), () {
                      Get.to(() => BookingFormScreen());
                    });
                  }
                });*/
                
                if (doctorsListCont.doctors.isEmpty) {
                  return NoDataWidget(
                    title: locale.value.noDoctorsFoundAtAMoment,
                    subTitle: locale.value.looksLikeThereIsNoDoctorsForThisClinicWellKee,
                    titleTextStyle: primaryTextStyle(),
                    imageWidget: const EmptyStateWidget(),
                    retryText: locale.value.reload,
                    onRetry: () {
                      doctorsListCont.page(1);
                      doctorsListCont.getDoctors();
                    },
                  ).paddingSymmetric(horizontal: 32).paddingBottom(Get.height * 0.15);
                }
                return AnimatedScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  listAnimationType: ListAnimationType.FadeIn,
                  children: [
                    16.height,
                    AnimatedWrap(
                      spacing: 16,
                      runSpacing: 16,
                      listAnimationType: ListAnimationType.FadeIn,
                      children: List.generate(
                        doctorsListCont.doctors.length,
                        (index) {
                          final Doctor doctorData = doctorsListCont.doctors[index];
                          return DoctorCard(doctorData: doctorData);
                        },
                      ),
                    ),
                  ],
                  onNextPage: () async {
                    if (!doctorsListCont.isLastPage.value) {
                      doctorsListCont.page(doctorsListCont.page.value + 1);
                      doctorsListCont.getDoctors();
                    }
                  },
                  onSwipeRefresh: () async {
                    doctorsListCont.page(1);
                    return doctorsListCont.getDoctors(showLoader: false);
                  },
                ).paddingSymmetric(horizontal: 16);
              },
            ),
          ).expand(),
        ],
      ),
      fabWidget: Obx(
        () => FloatingActionButton(
          backgroundColor: appColorSecondary,
          onPressed: () {
            if (!doctorsListCont.selectedDoctor.value.doctorId.isNegative) {
              Get.to(() => BookingFormScreen());
            }
          },
          child: const Icon(Icons.arrow_forward_ios, color: Colors.white),
        ).visible(doctorsListCont.doctors.isNotEmpty && (!doctorsListCont.selectedDoctor.value.doctorId.isNegative)),
      ),
    );
  }
}
