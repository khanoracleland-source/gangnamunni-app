import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';
import 'package:kivicare_patient/screens/service/system_service_list_controller.dart';

import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import 'components/system_service_card.dart';

class SystemServiceListScreen extends StatelessWidget {
  SystemServiceListScreen({super.key});

  final SystemServiceListController systemServiceListCont = Get.put(SystemServiceListController());

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => AppScaffoldNew(
        appBartitleText: appbarTitle,
        appBarVerticalSize: Get.height * 0.12,
        isLoading: systemServiceListCont.isLoading,
        body: SnapHelperWidget(
          future: systemServiceListCont.systemServiceListFuture.value,
          errorBuilder: (error) {
            return NoDataWidget(
              title: error,
              retryText: locale.value.reload,
              imageWidget: const ErrorStateWidget(),
              onRetry: () {
                systemServiceListCont.page(1);
                systemServiceListCont.getSystemServiceList();
              },
            ).paddingSymmetric(horizontal: 32);
          },
          loadingWidget: systemServiceListCont.isLoading.value ? const Offstage() : const LoaderWidget(),
          onSuccess: (data) {
            return AnimatedListView(
              shrinkWrap: true,
              itemCount: systemServiceListCont.systemServiceList.length,
              listAnimationType: ListAnimationType.FadeIn,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(left: 16, right: 16, top: 16),
              emptyWidget: NoDataWidget(
                title: locale.value.noSystemServicesFoundAtAMoment,
                subTitle: '${locale.value.looksLikeThereIsNoSystemServicesForThis} $appbarTitle, ${locale.value.wellKeepYouPostedWhenTheresAnUpdate}',
                titleTextStyle: primaryTextStyle(),
                imageWidget: const EmptyStateWidget(),
                retryText: locale.value.reload,
                onRetry: () {
                  systemServiceListCont.page(1);
                  systemServiceListCont.getSystemServiceList();
                },
              ).paddingSymmetric(horizontal: 32).paddingBottom(Get.height * 0.1),
              itemBuilder: (context, index) {
                return SystemServiceCard(systemServiceElement: systemServiceListCont.systemServiceList[index]);
              },
              onNextPage: () async {
                if (!systemServiceListCont.isLastPage.value) {
                  systemServiceListCont.page(systemServiceListCont.page.value + 1);
                  systemServiceListCont.getSystemServiceList();
                }
              },
              onSwipeRefresh: () async {
                systemServiceListCont.page(1);
                return systemServiceListCont.getSystemServiceList(showLoader: false);
              },
            );
          },
        ),
      ),
    );
  }

  String get appbarTitle => systemServiceListCont.category.value.name.isNotEmpty ? systemServiceListCont.category.value.name : "System Services";
}
