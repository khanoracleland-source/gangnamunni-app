import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import '../booking/encounter_detail_screen.dart';
import 'all_encounters_controller.dart';
import 'components/all_encounters_card.dart';

class AllEncountersScreen extends StatelessWidget {
  AllEncountersScreen({super.key});

  final AllEncountersController allEncountersCont = Get.put(AllEncountersController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.encounters,
      isLoading: allEncountersCont.isLoading,
      appBarVerticalSize: Get.height * 0.12,
      body: SizedBox(
        height: Get.height,
        child: Column(
          children: [
            Obx(
              () => SnapHelperWidget(
                future: allEncountersCont.encounterListFuture.value,
                errorBuilder: (error) {
                  return NoDataWidget(
                    title: error,
                    retryText: locale.value.reload,
                    imageWidget: const ErrorStateWidget(),
                    onRetry: () {
                      allEncountersCont.page(1);
                      allEncountersCont.getAllEncounters();
                    },
                  ).paddingSymmetric(horizontal: 32);
                },
                loadingWidget: allEncountersCont.isLoading.value ? const Offstage() : const LoaderWidget(),
                onSuccess: (data) {
                  return AnimatedListView(
                    shrinkWrap: true,
                    padding: const EdgeInsets.only(left: 16, right: 16, top: 16),
                    itemCount: allEncountersCont.encounterList.length,
                    physics: const AlwaysScrollableScrollPhysics(),
                    emptyWidget: NoDataWidget(
                      title: locale.value.noEncountersFound,
                      subTitle: locale.value.looksLikeThereIsNoEncountersWellKeepYouPosted,
                      titleTextStyle: primaryTextStyle(),
                      imageWidget: const EmptyStateWidget(),
                      retryText: locale.value.reload,
                      onRetry: () {
                        allEncountersCont.page(1);
                        allEncountersCont.getAllEncounters();
                      },
                    ).paddingSymmetric(horizontal: 32).visible(!allEncountersCont.isLoading.value),
                    onNextPage: () async {
                      if (!allEncountersCont.isLastPage.value) {
                        allEncountersCont.page(allEncountersCont.page.value + 1);
                        allEncountersCont.getAllEncounters();
                      }
                    },
                    onSwipeRefresh: () async {
                      allEncountersCont.page(1);
                      return allEncountersCont.getAllEncounters(showLoader: false);
                    },
                    itemBuilder: (context, index) {
                      return InkWell(
                        onTap: () {
                          Get.to(() => EncounterDetailScreen(), arguments: allEncountersCont.encounterList[index].id);
                        },
                        child: AllEncountersCard(encounterElement: allEncountersCont.encounterList[index]).paddingBottom(16),
                      );
                    },
                  );
                },
              ).expand(),
            ),
          ],
        ),
      ),
    );
  }
}
