import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/bed/bed_history_controller.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import '../booking/encounter_detail_screen.dart';
import 'component/bed_history_component.dart';

class BedHistoryScreen extends StatelessWidget {
  BedHistoryScreen({super.key});

  final BedHistoryController bedHistoryCont = Get.put(BedHistoryController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.bedHistory,
      isLoading: bedHistoryCont.isLoading,
      appBarVerticalSize: Get.height * 0.12,
      body: SnapHelperWidget(
        future: bedHistoryCont.bedHistoryListFuture.value,
        errorBuilder: (error) {
          return NoDataWidget(
            title: error,
            retryText: locale.value.reload,
            imageWidget: const ErrorStateWidget(),
            onRetry: () {
              bedHistoryCont.page(1);
              bedHistoryCont.getBedHistoryList();
            },
          ).paddingSymmetric(horizontal: 32);
        },
        loadingWidget: const LoaderWidget(),
        onSuccess: (data) {
          return AnimatedListView(
            shrinkWrap: true,
            padding: const EdgeInsets.only(left: 16, right: 16, top: 16),
            itemCount: bedHistoryCont.bedHistoryList.length,
            physics: const AlwaysScrollableScrollPhysics(),
            emptyWidget: NoDataWidget(
              title: locale.value.noBedHistoryFound,
              subTitle: locale.value.looksNoBedHistoryFound,
              titleTextStyle: primaryTextStyle(),
              imageWidget: const EmptyStateWidget(),
              retryText: locale.value.reload,
              onRetry: () {
                bedHistoryCont.page(1);
                bedHistoryCont.getBedHistoryList();
              },
            ).paddingSymmetric(horizontal: 32).visible(!bedHistoryCont.isLoading.value),
            onNextPage: () async {
              if (!bedHistoryCont.isLastPage.value) {
                bedHistoryCont.page(bedHistoryCont.page.value + 1);
                bedHistoryCont.getBedHistoryList();
              }
            },
            onSwipeRefresh: () async {
              bedHistoryCont.page(1);
              return await bedHistoryCont.getBedHistoryList(showLoader: false);
            },
            itemBuilder: (context, index) {
              return InkWell(
                onTap: () {
                  Get.to(() => EncounterDetailScreen(), arguments: bedHistoryCont.bedHistoryList[index].encounterId);
                },
                child: BedHistoryComponent(
                  bedHistoryData: bedHistoryCont.bedHistoryList[index],
                ).paddingBottom(16),
              );
            },
          );
        },
      ),
    );
  }
}
