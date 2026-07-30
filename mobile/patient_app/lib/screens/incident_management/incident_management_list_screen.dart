import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../components/app_scaffold.dart';
import '../../components/cached_image_widget.dart';
import '../../components/loader_widget.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/colors.dart';
import 'add_incident_management_screen.dart';
import 'components/incedent_management_card.dart';
import 'incident_management_controller.dart';
import 'model/incident_status_model.dart';

class IncidentManagementListScreen extends StatelessWidget {
  IncidentManagementListScreen({super.key});

  final IncidentManagement controller = Get.put(IncidentManagement());

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => AppScaffoldNew(
        appBartitleText: locale.value.incidentManagement,
        appBarVerticalSize: Get.height * 0.12,
        isLoading: controller.isLoading,
        actions: [
          if (controller.incidents.isNotEmpty) IconButton(
                  onPressed: () async {
                    controller.clearTextFields();
                    Get.to(() => AddIncidentManagement());
                  },
                  icon: const Icon(Icons.add_circle_outline_rounded, size: 28, color: Colors.white),
                ).paddingOnly(right: 8) else const SizedBox(),
        ],
        body: Obx(
          () => SnapHelperWidget(
            future: controller.incidenceFuture.value,
            initialData: controller.incidents.isNotEmpty ? controller.incidents : null,
            errorBuilder: (error) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CachedImageWidget(
                      url: Assets.iconsIcIncidentAlert,
                      height: Get.height * 0.12,
                      color: appColorPrimary,
                      fit: BoxFit.fitHeight,
                    ),
                    30.height,
                    Text(locale.value.noQueryYet, style: boldTextStyle(size: 20)),
                    10.height,
                    Text(
                      locale.value.toSubmitYourProblemsSimplyPressAddButtonAndExplainYourConcern,
                      style: primaryTextStyle(size: 13, color: darkGrayGeneral),
                      textAlign: TextAlign.center,
                    ).paddingOnly(left: 12, right: 12),
                    30.height,
                    ElevatedButton(
                      onPressed: () {
                        controller.clearTextFields();
                        Get.to(() => AddIncidentManagement());
                      },
                      style: ElevatedButton.styleFrom(
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(horizontal: 50, vertical: 8),
                        backgroundColor: appColorSecondary,
                      ),
                      child: Text(
                        locale.value.add,
                        style: const TextStyle(fontSize: 18, color: whiteTextColor),
                      ),
                    ),
                  ],
                ),
              );
            },
            loadingWidget: controller.isLoading.value ? const Offstage() : const LoaderWidget(),
            onSuccess: (_) {
              return AnimatedScrollView(
                listAnimationType: ListAnimationType.FadeIn,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  if (controller.incidents.isNotEmpty)
                    HorizontalList(
                      runSpacing: 8,
                      itemCount: controller.filterStatus.length,
                      itemBuilder: (context, index) {
                        final filterStatus = controller.filterStatus[index];
                        return Obx(
                          () {
                            final isSelected = controller.selectedTab.value.type == filterStatus.type;
                            return FilterChip(
                              showCheckmark: false,
                              labelPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(25),
                                side: const BorderSide(
                                  color: Colors.transparent,
                                ),
                              ),
                              label: Text(
                                filterStatus.name,
                                style: boldTextStyle(
                                  size: 14,
                                  color: isSelected ? switchActiveColor : textPrimaryColorGlobal,
                                ),
                              ).paddingSymmetric(horizontal: 10),
                              selected: isSelected,
                              backgroundColor: isSelected ? appColorPrimary : context.cardColor,
                              selectedColor: appColorPrimary,
                              onSelected: (_) {
                                controller.selectedTab(filterStatus);
                                controller.incidencePage(1);
                                controller.getIncidents();
                              },
                            );
                          },
                        );
                      },
                    ),
                  16.height,
                  Builder(
                    builder: (_) {
                      final selectedType = controller.selectedTab.value.type;
                      final filteredIncidents = controller.incidents.where((incident) {
                        if (selectedType == IncidentStatus.all) return true;
                        return incident.incidenceTypeName.toLowerCase() == selectedType.name.toLowerCase();
                      }).toList();
                      filteredIncidents.sort((a, b) => b.createdAt.compareTo(a.createdAt));
                      if (filteredIncidents.isEmpty) {
                        return Column(
                          children: [
                            CachedImageWidget(
                              url: Assets.iconsIcIncidentAlert,
                              height: Get.height * 0.12,
                              color: appColorPrimary,
                              fit: BoxFit.fitHeight,
                            ),
                            30.height,
                            Text(locale.value.noQueryYet, style: boldTextStyle(size: 20)),
                            10.height,
                            Text(
                              locale.value.toSubmitYourProblemsSimplyPressAddButtonAndExplainYourConcern,
                              style: primaryTextStyle(size: 13, color: darkGrayGeneral),
                              textAlign: TextAlign.center,
                            ).paddingOnly(left: 12, right: 12),
                            30.height,
                            ElevatedButton(
                              onPressed: () {
                                controller.clearTextFields();
                                Get.to(() => AddIncidentManagement());
                              },
                              style: ElevatedButton.styleFrom(
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                padding: const EdgeInsets.symmetric(horizontal: 50, vertical: 8),
                                backgroundColor: appColorSecondary,
                              ),
                              child: Text(
                                locale.value.add,
                                style: const TextStyle(fontSize: 18, color: whiteTextColor),
                              ),
                            ),
                          ],
                        ).paddingTop(Get.height * 0.15);
                      }

                      return Column(
                        children: filteredIncidents.map((incident) {
                          return IncidentManagementCard(
                            incidentController: controller,
                            incidentData: incident,
                            onUpdateBooking: () {
                              controller.incidencePage(1);
                              controller.getIncidents();
                            },
                          ).paddingBottom(16);
                        }).toList(),
                      );
                    },
                  ),
                ],
                onNextPage: () async {
                  if (!controller.isIncidenceLastPage.value) {
                    controller.incidencePage(controller.incidencePage.value + 1);
                    await controller.getIncidents();
                  }
                },
                onSwipeRefresh: () async {
                  controller.incidencePage(1);
                  return controller.getIncidents();
                },
              ).paddingOnly(bottom: 10);
            },
          ),
        ).paddingTop(16),
      ),
    );
  }
}
