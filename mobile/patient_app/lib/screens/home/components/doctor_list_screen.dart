import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/booking/filter/filter_controller.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_scaffold.dart';
import '../../../components/cached_image_widget.dart';
import '../../../components/loader_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../booking/filter/filter_screen.dart';
import '../../doctor/components/search_doctor_service.dart';
import '../../doctor/doctor_list_controller.dart';
import '../../doctor/model/doctor_list_res.dart';
import '../../doctor/components/popular_doctor_card.dart';

class DoctorViewListScreen extends StatelessWidget {
  final String? title;
  final bool isFromClinicDetail;
  final bool isFromDashboard;

  DoctorViewListScreen({super.key, this.title, this.isFromClinicDetail = false, this.isFromDashboard = false});

  final DoctorListController doctorListCont = Get.put(DoctorListController());
  final FilterController filterController = Get.put(FilterController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: title,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: doctorListCont.isLoading,
      body: Obx(
        () => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                SearchDoctorWidget(
                  doctorController: doctorListCont,
                  onFieldSubmitted: (p0) {
                    hideKeyboard(context);
                  },
                ).expand(),
                12.width,
                InkWell(
                  onTap: () {
                    doctorListCont.searchDoctorCont.clear();
                    doctorListCont.page(1);
                    Get.to(
                      () => FilterScreen(
                        filterType: "doctor",
                        displayName: "doctor",
                      ),
                      arguments: [doctorListCont.clinicId.value, doctorListCont.serviceType.value, doctorListCont.ratingMin, doctorListCont.ratingMax, "doctor"],
                      binding: BindingsBuilder(() {
                        setStatusBarColor(
                          transparentColor,
                          statusBarIconBrightness: Brightness.light,
                          statusBarBrightness: Brightness.light,
                          systemNavigationBarColor: whiteTextColor,
                        );
                      }),
                    );
                  },
                  child: Stack(
                    alignment: Alignment.topRight,
                    children: [
                      Container(
                        height: 46,
                        width: 46,
                        alignment: Alignment.center,
                        decoration: boxDecorationDefault(
                          color: appColorPrimary,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const CachedImageWidget(
                          url: Assets.iconsIcFilter,
                          height: 28,
                          color: white,
                        ),
                      ),
                      if (filterController.totalDoctorCount.value > 0)
                        Positioned(
                          top: -4,
                          right: 0,
                          child: Container(
                            padding: const EdgeInsets.all(4),
                            decoration: const BoxDecoration(
                              color: Colors.red,
                              shape: BoxShape.circle,
                            ),
                            child: Text(
                              '${filterController.totalDoctorCount.value}',
                              style: const TextStyle(color: Colors.white, fontSize: 10),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ).paddingAll(16),
            SnapHelperWidget(
              future: doctorListCont.doctorsFuture.value,
              errorBuilder: (error) {
                return NoDataWidget(
                  title: error,
                  retryText: locale.value.reload,
                  imageWidget: const ErrorStateWidget(),
                  onRetry: () {
                    doctorListCont.page(1);
                    doctorListCont.getDoctors();
                  },
                ).paddingSymmetric(horizontal: 32);
              },
              loadingWidget: doctorListCont.isLoading.value ? const Offstage() : const LoaderWidget(),
              onSuccess: (p0) {
                if (doctorListCont.doctors.isEmpty && !doctorListCont.isLoading.value) {
                  return NoDataWidget(
                    title: "No Doctors Found At A Moment",
                    subTitle: 'Looks like there is no doctors, ${locale.value.wellKeepYouPostedWhenTheresAnUpdate}',
                    titleTextStyle: primaryTextStyle(),
                    imageWidget: const EmptyStateWidget(),
                    retryText: locale.value.reload,
                    onRetry: () {
                      doctorListCont.page(1);
                      doctorListCont.getDoctors();
                    },
                  ).paddingSymmetric(horizontal: 32);
                }

                return AnimatedScrollView(
                  padding: const EdgeInsets.all(16),
                  physics: const AlwaysScrollableScrollPhysics(),
                  listAnimationType: ListAnimationType.FadeIn,
                  onSwipeRefresh: () async {
                    doctorListCont.page(1);
                    return doctorListCont.getDoctors(showLoader: false);
                  },
                  onNextPage: () async {
                    if (!doctorListCont.isLastPage.value) {
                      doctorListCont.page(doctorListCont.page.value + 1);
                      doctorListCont.getDoctors();
                    }
                  },
                  children: [
                    AnimatedWrap(
                      runSpacing: 16,
                      spacing: 16,
                      itemCount: doctorListCont.doctors.length,
                      direction: Axis.horizontal,
                      listAnimationType: ListAnimationType.FadeIn,
                      itemBuilder: (ctx, index) {
                        final Doctor doctorElement = doctorListCont.doctors[index];
                        return PopularDoctorCard(doctorElement: doctorElement, isFromClinicDetail: isFromClinicDetail);
                      },
                    ),
                  ],
                ).visible(!doctorListCont.isLoading.value);
              },
            ).expand(),
          ],
        ),
      ),
    );
  }
}
