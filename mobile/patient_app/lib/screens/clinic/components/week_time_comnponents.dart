import 'package:flutter/material.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_time_dropdown_widget.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../model/clinic_detail_model.dart';

class WeekTimeComponent extends StatelessWidget {
  final AllClinicSession weekData;

  const WeekTimeComponent({super.key, required this.weekData});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        16.height,
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppTimeDropDownWidget(
              value: weekData.startTime.isValidTime ? "1970-01-01 ${weekData.startTime}".timeInHHmmAmPmFormat : "12:00 AM",
              bgColor: isDarkMode.value ? lightCanvasColor : white,
            ).expand(),
            16.width,
            Text(
              "-",
              style: boldTextStyle(size: 20),
            ),
            16.width,
            AppTimeDropDownWidget(
              value: weekData.startTime.isValidTime ? "1970-01-01 ${weekData.endTime}".timeInHHmmAmPmFormat : "12:00 AM",
              bgColor: isDarkMode.value ? lightCanvasColor : white,
            ).expand(),
          ],
        ),
        if (weekData.breaks.isEmpty) 0.height else 24.height,
        AnimatedListView(
          shrinkWrap: true,
          itemCount: weekData.breaks.length,
          padding: EdgeInsets.zero,
          physics: const NeverScrollableScrollPhysics(),
          listAnimationType: ListAnimationType.None,
          itemBuilder: (ctx, index) {
            final BreakListModel breakReport = weekData.breaks[index];
            return Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  "${locale.value.lblBreak}: ${breakReport.startBreak.isValidTime ? "1970-01-01 ${breakReport.startBreak}".timeInHHmmAmPmFormat : "12:00 AM"} - ${breakReport.endBreak.isValidTime ? "1970-01-01 ${breakReport.endBreak}".timeInHHmmAmPmFormat : "12:00 AM"}",
                  style: primaryTextStyle(size: 12, color: textSecondaryColor),
                ).expand(),
              ],
            ).paddingBottom(6);
          },
        ),
      ],
    );
  }
}
