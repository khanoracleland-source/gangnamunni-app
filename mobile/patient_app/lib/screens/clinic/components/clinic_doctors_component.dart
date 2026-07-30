import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../components/loader_widget.dart';
import '../../../main.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../doctor/components/doctor_card.dart';
import '../../doctor/model/doctor_list_res.dart';
import '../clinic_detail_controller.dart';

class ClinicDoctorsComponent extends StatelessWidget {
  final ClinicDetailController clinicDetailCont;
  const ClinicDoctorsComponent({super.key, required this.clinicDetailCont});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Obx(
          () => SnapHelperWidget(
            future: clinicDetailCont.doctorsFuture.value,
            errorBuilder: (error) {
              return NoDataWidget(
                title: error,
                retryText: locale.value.reload,
                imageWidget: const ErrorStateWidget(),
                onRetry: () {
                  clinicDetailCont.doctorsPage(1);
                  clinicDetailCont.getDoctors();
                },
              ).paddingSymmetric(horizontal: 32);
            },
            loadingWidget: const Offstage(),
            onSuccess: (p0) {
              return AnimatedScrollView(
                children: [
                  AnimatedWrap(
                    spacing: 16,
                    runSpacing: 16,
                    children: List.generate(
                      clinicDetailCont.doctors.length,
                      (index) {
                        final Doctor doctor = clinicDetailCont.doctors[index];
                        return DoctorCard(doctorData: doctor);
                      },
                    ),
                  ),
                ],
                onNextPage: () async {
                  if (!clinicDetailCont.isDoctorsLastPage.value) {
                    clinicDetailCont.doctorsPage(clinicDetailCont.doctorsPage.value + 1);
                    clinicDetailCont.getDoctors();
                  }
                },
                onSwipeRefresh: () async {
                  clinicDetailCont.doctorsPage(1);
                  return clinicDetailCont.getDoctors();
                },
              ).paddingSymmetric(horizontal: 16);
            },
          ),
        ).paddingOnly(top: 16, bottom: 80),
        Obx(() => const LoaderWidget().center().paddingTop(Get.height * 0.12).visible(clinicDetailCont.isDoctorsLoading.value)),
      ],
    );
  }
}
