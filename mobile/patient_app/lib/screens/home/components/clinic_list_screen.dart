import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/booking/filter/filter_controller.dart';
import 'package:kivicare_patient/screens/clinic/components/popular_clinic_card.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_scaffold.dart';
import '../../../components/cached_image_widget.dart';
import '../../../components/loader_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../booking/filter/filter_screen.dart';
import '../../clinic/clinic_list_controller.dart';
import '../../clinic/components/search_clinic_widget.dart';
import '../../clinic/model/clinics_res_model.dart';

class ClinicListComponent extends StatelessWidget {
  final String? title;
  final bool isFromClinicDetail;
  final bool isFromDashboard;

  ClinicListComponent({super.key, this.title, this.isFromClinicDetail = false, this.isFromDashboard = false});

  final ClinicListController clinicListCont = Get.put(ClinicListController());
  final FilterController filterController = Get.put(FilterController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: title,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: clinicListCont.isLoading,
      body: Obx(
        () => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                SearchClinicWidget(
                  clinicController: clinicListCont,
                  onFieldSubmitted: (p0) {
                    hideKeyboard(context);
                  },
                ).expand(),
                12.width,
                InkWell(
                  onTap: () {
                    clinicListCont.searchClinicCont.clear();
                    clinicListCont.page(1);
                    Get.to(
                      () => FilterScreen(
                        filterType: "clinic",
                        displayName: "clinic",
                      ),
                      arguments: [clinicListCont.clinicId.value, clinicListCont.serviceType.value, clinicListCont.priceMin, clinicListCont.priceMax, "clinic"],
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
                      if (filterController.seleFilterCount.value > 0)
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
                              '${filterController.seleFilterCount.value}',
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
                if (clinicListCont.clinics.isEmpty && !clinicListCont.isLoading.value) {
                  return NoDataWidget(
                    title: "No Clinics Found At A Moment",
                    subTitle: 'Looks like there is no clinic, ${locale.value.wellKeepYouPostedWhenTheresAnUpdate}',
                    titleTextStyle: primaryTextStyle(),
                    imageWidget: const EmptyStateWidget(),
                    retryText: locale.value.reload,
                    onRetry: () {
                      clinicListCont.page(1);
                      clinicListCont.getClinicList();
                    },
                  ).paddingSymmetric(horizontal: 32);
                }
                return AnimatedScrollView(
                  padding: const EdgeInsets.all(16),
                  physics: const AlwaysScrollableScrollPhysics(),
                  listAnimationType: ListAnimationType.FadeIn,
                  onSwipeRefresh: () async {
                    clinicListCont.page(1);
                    return clinicListCont.getClinicList(showLoader: false);
                  },
                  onNextPage: () async {
                    if (!clinicListCont.isLastPage.value) {
                      clinicListCont.page(clinicListCont.page.value + 1);
                      clinicListCont.getClinicList();
                    }
                  },
                  children: [
                    AnimatedWrap(
                      runSpacing: 16,
                      spacing: 16,
                      itemCount: clinicListCont.clinics.length,
                      direction: Axis.horizontal,
                      listAnimationType: ListAnimationType.FadeIn,
                      itemBuilder: (ctx, index) {
                        final Clinic clinicElement = clinicListCont.clinics[index];
                        return PopularClinicCard(clinicElement: clinicElement);
                      },
                    ),
                  ],
                ).visible(!clinicListCont.isLoading.value);
              },
            ).expand(),
          ],
        ),
      ),
    );
  }
}
