import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/screens/clinic/search_clinic_widget.dart';
import 'package:kivicare_patient/utils/colors.dart';

import '../../components/app_scaffold.dart';
import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import '../../utils/view_all_label_component.dart';
import '../doctor/doctor_list_screen.dart';
import 'clinic_list_controller.dart';
import 'components/clinic_card.dart';
import 'model/clinics_res_model.dart';

class ClinicListScreen extends StatelessWidget {
  ClinicListScreen({super.key});

  final ClinicListController clinicListCont = Get.put(ClinicListController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.clinics,
      scaffoldBackgroundColor: context.scaffoldBackgroundColor,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: clinicListCont.isLoading,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SearchClinicWidget(
            clinicListController: clinicListCont,
            onFieldSubmitted: (p0) {
              hideKeyboard(context);
            },
          ).paddingAll(16),
          ViewAllLabel(
            label: "${locale.value.availableClinicsFor} ${clinicListCont.service.value.name}",
            isShowAll: false,
            maxLines: 1,
            expandedText: true,
            textOverflow: TextOverflow.ellipsis,
          ).paddingSymmetric(horizontal: 22),
          8.height,
          Obx(
            () => SnapHelperWidget(
              future: clinicListCont.clinicsFuture.value,
              errorBuilder: (error) {
                return NoDataWidget(
                  title: error,
                  retryText: locale.value.reload,
                  imageWidget: const ErrorStateWidget(),
                  onRetry: () {
                    clinicListCont.page(1);
                    clinicListCont.getClinicList();
                  },
                ).paddingSymmetric(horizontal: 32);
              },
              loadingWidget: clinicListCont.isLoading.value ? const Offstage() : const LoaderWidget(),
              onSuccess: (p0) {
               /* // Auto-select clinic if only one available - run after build is complete
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (clinicListCont.clinics.length == 1 && clinicListCont.selectedClinic.value.id.isNegative) {
                    clinicListCont.selectedClinic(clinicListCont.clinics[0]);
                    // Set global variable for booking form
                    currentSelectedClinic(clinicListCont.clinics[0]);
                    log('Auto-selected clinic: ${clinicListCont.clinics[0].name}');
                    
                    // Navigate directly to booking form screen
                    Future.delayed(const Duration(milliseconds: 500), () {
                      Get.to(() => BookingFormScreen());
                    });
                  }
                });*/

                return AnimatedListView(
                  shrinkWrap: true,
                  padding: const EdgeInsets.only(left: 22, right: 22, top: 16, bottom: 80),
                  itemCount: clinicListCont.clinics.length,
                  physics: const AlwaysScrollableScrollPhysics(),
                  emptyWidget: SingleChildScrollView(
                    child: NoDataWidget(
                      title: locale.value.noClinicsFoundAtAMoment,
                      subTitle: locale.value.looksLikeThereIsNoClinicForThisServiceWellKee,
                      titleTextStyle: primaryTextStyle(),
                      imageWidget: const EmptyStateWidget(),
                      retryText: locale.value.reload,
                      onRetry: () {
                        clinicListCont.page(1);
                        clinicListCont.getClinicList();
                      },
                    ).paddingSymmetric(horizontal: 25),
                  ).center(),
                  itemBuilder: (context, index) {
                    final Clinic clinic = clinicListCont.clinics[index];
                    return ClinicCard(clinicData: clinic).paddingBottom(16);
                  },
                  onNextPage: () async {
                    if (!clinicListCont.isLastPage.value) {
                      clinicListCont.page(clinicListCont.page.value + 1);
                      clinicListCont.getClinicList();
                    }
                  },
                  onSwipeRefresh: () async {
                    clinicListCont.page(1);
                    return clinicListCont.getClinicList(showLoader: false);
                  },
                );
              },
            ),
          ).expand(),
        ],
      ),
      fabWidget: Obx(
        () => FloatingActionButton(
          backgroundColor: appColorSecondary,
          onPressed: () {
            if (!clinicListCont.selectedClinic.value.id.isNegative) {
              Get.to(() => DoctorsListScreen(), arguments: clinicListCont.selectedClinic.value.id);
            }
          },
          child: const Icon(Icons.arrow_forward_ios, color: Colors.white),
        ).visible(clinicListCont.clinics.isNotEmpty && (!clinicListCont.selectedClinic.value.id.isNegative)),
      ),
    );
  }
}
