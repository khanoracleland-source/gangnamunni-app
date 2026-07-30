import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_scaffold.dart';
import '../../components/cached_image_widget.dart';
import '../../components/loader_widget.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/colors.dart';
import '../../utils/empty_error_state_widget.dart';
import '../booking/filter/filter_screen.dart';
import 'components/service_card.dart';
import 'model/service_list_model.dart';
import 'search_service_widget.dart';
import 'service_list_controller.dart';

class ServiceListScreen extends StatelessWidget {
  final String? title;
  final bool isFromClinicDetail;
  final bool isFromDashboard;

  ServiceListScreen({super.key, this.title, this.isFromClinicDetail = false, this.isFromDashboard = false});

  final ServiceListController serviceListCont = Get.put(ServiceListController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: title ?? appbarTitle,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: serviceListCont.isLoading,
      body: Obx(
        () => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                SearchServiceWidget(
                  servicesController: serviceListCont,
                  onFieldSubmitted: (p0) {
                    hideKeyboard(context);
                  },
                ).expand(),
                12.width,
                InkWell(
                  onTap: () async {
                    serviceListCont.searchCont.clear();
                    serviceListCont.page(1);
                    log('-----------------------1--------------------');
                    log(
                      [
                        serviceListCont.clinicId.value,
                        serviceListCont.serviceType.value,
                        serviceListCont.priceMin,
                        serviceListCont.priceMax,
                        "category",
                        serviceListCont.category.value.id,
                      ],
                    );
                    await Get.to(
                      () => FilterScreen(displayName: 'category'),
                      arguments: [
                        serviceListCont.clinicId.value,
                        serviceListCont.serviceType.value,
                        serviceListCont.priceMin.value,
                        serviceListCont.priceMax.value,
                        "category",
                        serviceListCont.category.value.id,
                      ],
                      binding: BindingsBuilder(
                        () {
                          setStatusBarColor(
                            transparentColor,
                            statusBarIconBrightness: Brightness.light,
                            statusBarBrightness: Brightness.light,
                            systemNavigationBarColor: whiteTextColor,
                          );
                        },
                      ),
                    )?.then((value) {
                      if (value is int) {
                        serviceListCont.selectedFilterCount.value = value;
                      }
                    });
                  },
                  child: Stack(
                    children: [
                      Container(
                        height: 46,
                        width: 46,
                        alignment: Alignment.center,
                        decoration: boxDecorationDefault(color: appColorPrimary, borderRadius: BorderRadius.circular(12)),
                        child: const CachedImageWidget(
                          url: Assets.iconsIcFilter,
                          height: 28,
                          color: white,
                        ),
                      ),
                      if (serviceListCont.selectedFilterCount.value > 0)
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
                              '${serviceListCont.selectedFilterCount.value}',
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
              future: serviceListCont.serviceListFuture.value,
              errorBuilder: (error) {
                return NoDataWidget(
                  title: error,
                  retryText: locale.value.reload,
                  imageWidget: const ErrorStateWidget(),
                  onRetry: () {
                    serviceListCont.page(1);
                    serviceListCont.getServiceList();
                  },
                ).paddingSymmetric(horizontal: 32);
              },
              loadingWidget: serviceListCont.isLoading.value ? const Offstage() : const LoaderWidget(),
              onSuccess: (p0) {
                if (serviceListCont.serviceList.isEmpty) {
                  return NoDataWidget(
                    title: locale.value.noServicesFoundAtAMoment,
                    subTitle: '${locale.value.looksLikeThereIsNoServicesForThis}$appbarTitle, ${locale.value.wellKeepYouPostedWhenTheresAnUpdate}',
                    titleTextStyle: primaryTextStyle(),
                    imageWidget: const EmptyStateWidget(),
                    retryText: locale.value.reload,
                    onRetry: () {
                      serviceListCont.page(1);
                      serviceListCont.getServiceList();
                    },
                  ).paddingSymmetric(horizontal: 32);
                }

                return AnimatedScrollView(
                  padding: const EdgeInsets.all(16),
                  physics: const AlwaysScrollableScrollPhysics(),
                  listAnimationType: ListAnimationType.FadeIn,
                  onSwipeRefresh: () async {
                    serviceListCont.page(1);
                    return serviceListCont.getServiceList(showLoader: false);
                  },
                  onNextPage: () async {
                    if (!serviceListCont.isLastPage.value) {
                      serviceListCont.page(serviceListCont.page.value + 1);

                      serviceListCont.getServiceList();
                    }
                  },
                  children: [
                    AnimatedWrap(
                      runSpacing: 16,
                      spacing: 16,
                      itemCount: serviceListCont.serviceList.length,
                      listAnimationType: ListAnimationType.FadeIn,
                      itemBuilder: (ctx, index) {
                        final ServiceElement serviceElement = serviceListCont.serviceList[index];
                        return ServiceCard(serviceElement: serviceElement, isFromClinicDetail: isFromClinicDetail);
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

  String get appbarTitle => isFromDashboard
      ? ''
      : serviceListCont.category.value.name.isNotEmpty
          ? " ${serviceListCont.category.value.name}"
          : " ${selectedSysService.value.name} ${locale.value.services}";
}
