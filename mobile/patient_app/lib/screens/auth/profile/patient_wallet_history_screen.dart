import 'package:flutter/material.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../components/app_scaffold.dart';
import '../../../components/loader_widget.dart';
import '../../../main.dart';
import '../../../utils/empty_error_state_widget.dart';
import 'wallet_history_card.dart';
import 'patient_wallet_history_controller.dart';

class PatientWalletHistory extends StatelessWidget {
  PatientWalletHistory({super.key});

  final PatientWalletHistoryController walletHistoryCont = Get.put(PatientWalletHistoryController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.walletHistory,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: walletHistoryCont.isLoading,
      body: SnapHelperWidget(
        future: walletHistoryCont.walletHistoryFuture.value,
        loadingWidget: walletHistoryCont.isLoading.value ? const Offstage() : const LoaderWidget(),
        errorBuilder: (error) {
          return NoDataWidget(
            title: error,
            retryText: locale.value.reload,
            imageWidget: const ErrorStateWidget(),
            onRetry: () {
              walletHistoryCont.historyPage(1);
              walletHistoryCont.getWalletHistory();
            },
          );
        },
        onSuccess: (res) {
          return Obx(
            () => AnimatedListView(
              shrinkWrap: true,
              itemCount: walletHistoryCont.historyData.length,
              padding: EdgeInsets.zero,
              physics: const AlwaysScrollableScrollPhysics(),
              emptyWidget: NoDataWidget(
                title: locale.value.noWalletDataFound,
                subTitle: locale.value.oppsNoWalletDataFoundAtAMoment,
                imageWidget: const EmptyStateWidget(),
              ).paddingSymmetric(horizontal: 32).visible(!walletHistoryCont.isLoading.value),
              onSwipeRefresh: () async {
                walletHistoryCont.historyPage(1);
                return walletHistoryCont.getWalletHistory(showloader: false);
              },
              onNextPage: () async {
                if (!walletHistoryCont.isPatientLastPage.value) {
                  walletHistoryCont.historyPage++;
                  walletHistoryCont.getWalletHistory();
                }
              },
              itemBuilder: (ctx, index) {
                return WalletHistoryCardWid(walletHistoryElement: walletHistoryCont.historyData[index]).paddingBottom(16);
              },
            ),
          );
        },
      ).paddingSymmetric(vertical: 16),
    );
  }
}
