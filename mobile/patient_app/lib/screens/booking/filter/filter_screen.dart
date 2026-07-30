import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../../utils/app_common.dart';
import '../../../utils/colors.dart';
import 'components/type_list_component.dart';
import 'filter_controller.dart';

class FilterScreen extends StatelessWidget {
  final String filterType;
  final String? displayName;

  FilterScreen({super.key, this.filterType = 'service', this.displayName});

  final FilterController filterCont = Get.put(FilterController(), permanent: true);

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.filterBy,
      appBarVerticalSize: Get.height * 0.12,
      actions: [
        Obx(
          () => TextButton(
            onPressed: () {
              filterCont.resetFilter(filterType, displayName ?? 'service');
            },
            child: Text(
              locale.value.reset,
              style: boldTextStyle(size: 14, color: whiteTextColor),
            ),
          ),
        ),
      ],
      body: Container(
        height: Get.height,
        width: double.infinity,
        decoration: BoxDecoration(
          color: isDarkMode.value ? appScreenBackgroundDark : appScreenBackground,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            16.height,
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FilterTypeListComponent(
                  filterList: displayName == "service"
                      ? filterCont.serviceFilterList
                      : displayName == "clinic"
                          ? filterCont.clinicFilterList
                          : displayName == "category"
                              ? filterCont.categoryFilterList
                              : displayName == "appointment"
                                  ? filterCont.appointmentFiterList
                                  : filterCont.filterList,
                ).expand(),
                Obx(() => filterCont.viewFilterWidget(displayName ?? "", filterCont)),
              ],
            ).expand(),
            Container(
              decoration: boxDecorationDefault(borderRadius: radius(0), color: context.cardColor),
              width: Get.width,
              padding: const EdgeInsets.all(16),
              child: AppButton(
                width: Get.width,
                text: locale.value.apply,
                color: appColorPrimary,
                textStyle: appButtonTextStyleWhite,
                onTap: () {
                  log(filterType);
                  filterCont.applyFilter(filterType);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
