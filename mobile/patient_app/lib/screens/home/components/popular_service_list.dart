import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/booking/filter/filter_controller.dart';
import 'package:kivicare_patient/screens/service/components/popular_service_card.dart';
import 'package:kivicare_patient/screens/service/service_list_controller.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_scaffold.dart';
import '../../../components/cached_image_widget.dart';
import '../../../components/loader_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../booking/filter/filter_screen.dart';
import '../../service/model/service_list_model.dart';
import '../../service/popular_search_service.dart';

class PopularServiceListScreen extends StatelessWidget {
  final String? title;
  final bool isFromClinicDetail;
  final bool isFromDashboard;

  PopularServiceListScreen({super.key, this.title, this.isFromClinicDetail = false, this.isFromDashboard = false});

  final ServiceListController popularServiceCont = Get.put(ServiceListController());
  final FilterController filterController = Get.put(FilterController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: title,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: popularServiceCont.isLoading,
      body: Obx(
        () => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                PopularSearchServiceWidget(
                  popularServiceController: popularServiceCont,
                  onFieldSubmitted: (p0) {
                    hideKeyboard(context);
                    popularServiceCont.getServiceList();
                  },
                ).expand(),
                12.width,
                InkWell(
                  onTap: () {
                    popularServiceCont.searchCont.clear();
                    popularServiceCont.page(1);
                    Get.to(
                      () => FilterScreen(
                        displayName: "service",
                      ),
                      arguments: [popularServiceCont.clinicId.value, popularServiceCont.serviceType.value, popularServiceCont.priceMin, popularServiceCont.priceMax, "service"],
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
                      if (filterController.totalServiceCount.value > 0)
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
                              '${filterController.totalServiceCount.value}',
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
              future: popularServiceCont.serviceListFuture.value,
              errorBuilder: (error) {
                return NoDataWidget(
                  title: error,
                  retryText: locale.value.reload,
                  imageWidget: const ErrorStateWidget(),
                  onRetry: () {
                    popularServiceCont.page(1);
                    popularServiceCont.getServiceList();
                  },
                ).paddingSymmetric(horizontal: 32);
              },
              loadingWidget: popularServiceCont.isLoading.value ? const Offstage() : const LoaderWidget(),
              onSuccess: (p0) {
                if (popularServiceCont.serviceList.isEmpty && !popularServiceCont.isLoading.value) {
                  return NoDataWidget(
                    title: locale.value.noServicesFoundAtAMoment,
                    subTitle: '${locale.value.looksLikeThereIsNoServicesForThis}, ${locale.value.wellKeepYouPostedWhenTheresAnUpdate}',
                    titleTextStyle: primaryTextStyle(),
                    imageWidget: const EmptyStateWidget(),
                    retryText: locale.value.reload,
                    onRetry: () {
                      popularServiceCont.page(1);
                      popularServiceCont.getServiceList();
                    },
                  ).paddingSymmetric(horizontal: 32);
                }

                return AnimatedScrollView(
                  padding: const EdgeInsets.all(16),
                  physics: const AlwaysScrollableScrollPhysics(),
                  listAnimationType: ListAnimationType.FadeIn,
                  onSwipeRefresh: () async {
                    popularServiceCont.isPopular(1);
                    popularServiceCont.page(1);
                    return popularServiceCont.getServiceList(showLoader: false);
                  },
                  onNextPage: () async {
                    if (!popularServiceCont.isLastPage.value) {
                      popularServiceCont.page(popularServiceCont.page.value + 1);

                      popularServiceCont.getServiceList(showLoader: false);
                    }
                  },
                  children: [
                    AnimatedWrap(
                      runSpacing: 16,
                      spacing: 16,
                      itemCount: popularServiceCont.serviceList.length,
                      listAnimationType: ListAnimationType.FadeIn,
                      itemBuilder: (ctx, index) {
                        final ServiceElement serviceElement = popularServiceCont.serviceList[index];
                        return PopularServiceCard(serviceElement: serviceElement, isFromClinicDetail: isFromClinicDetail);
                      },
                    ),
                  ],
                );
              },
            ).expand(),
          ],
        ),
      ),
    );
  }
}
