import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';
import 'package:kivicare_patient/screens/clinic/components/week_time_comnponents.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../../../utils/colors.dart';
import '../clinic_detail_controller.dart';
import '../model/clinic_detail_model.dart';

class ClinicSessionComponent extends StatelessWidget {
  final ClinicDetailController clinicDetailCont;

  const ClinicSessionComponent({super.key, required this.clinicDetailCont});

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.session,
      scaffoldBackgroundColor: context.scaffoldBackgroundColor,
      appBarVerticalSize: Get.height * 0.12,
      body: RefreshIndicator(
        onRefresh: () {
          return clinicDetailCont.init(showLoader: false);
        },
        child: Obx(
          () => AnimatedListView(
            listAnimationType: ListAnimationType.FadeIn,
            padding: const EdgeInsets.only(left: 16, right: 16, top: 16),
            shrinkWrap: true,
            itemCount: clinicDetailCont.clinicData.value.allClinicSession.length,
            itemBuilder: (ctx, index) {
              final AllClinicSession allClinicSessionData = clinicDetailCont.clinicData.value.allClinicSession[index];

              return Container(
                padding: const EdgeInsets.all(16),
                margin: const EdgeInsets.only(bottom: 16),
                decoration: boxDecorationDefault(color: context.cardColor, borderRadius: BorderRadius.circular(6)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        Text(
                          allClinicSessionData.day.capitalize.toString(),
                          style: primaryTextStyle(
                            size: 12,
                            weight: FontWeight.w500,
                            color: allClinicSessionData.isHoliday ? dividerColor : null,
                          ),
                        ).expand(),
                        if (allClinicSessionData.isHoliday)
                          Text(
                            locale.value.clinicClosed,
                            style: primaryTextStyle(
                              size: 12,
                              weight: FontWeight.w500,
                              color: dividerColor,
                            ),
                          ),
                      ],
                    ),
                    if (!allClinicSessionData.isHoliday) WeekTimeComponent(weekData: allClinicSessionData),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
