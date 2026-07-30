import 'package:flutter/material.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_scaffold.dart';
import '../../../main.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../model/doctor_detail_model.dart';
import 'doctor_qualification_card.dart';

class QualificationComponent extends StatelessWidget {
  final List<Qualifications> qualificationList;

  const QualificationComponent({super.key, required this.qualificationList});

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.qualification,
      appBarVerticalSize: Get.height * 0.12,
      body: AnimatedListView(
        shrinkWrap: true,
        itemCount: qualificationList.length,
        padding: const EdgeInsets.symmetric(horizontal: 24),
        physics: const AlwaysScrollableScrollPhysics(),
        emptyWidget: NoDataWidget(
          title: locale.value.noQualificationsFound,
          subTitle: locale.value.looksLikeThereAreNoQualificationsAddedByThisD,
          titleTextStyle: primaryTextStyle(),
          imageWidget: const EmptyStateWidget(),
        ).paddingSymmetric(horizontal: 32).paddingBottom(Get.height * 0.1),
        itemBuilder: (BuildContext context, int index) {
          final Qualifications qualificationData = qualificationList[index];
          return QualificationCard(qualificationData: qualificationData).paddingBottom(16);
        },
      ).paddingTop(24).paddingBottom(8),
    );
  }
}
