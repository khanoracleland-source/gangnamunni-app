import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../main.dart';
import '../../../utils/common_base.dart';
import '../../../utils/view_all_label_component.dart';
import '../encounter_detail_controller.dart';
import '../model/encounter_detail_model.dart';


class PrescriptionComponent extends StatelessWidget {
  const PrescriptionComponent({
    super.key,
    required this.encounterDetailCont,
  });

  final EncounterDetailController encounterDetailCont;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        16.height,
        ViewAllLabel(label: locale.value.prescription, isShowAll: false).paddingOnly(left: 16, right: 8),
        AnimatedWrap(
          runSpacing: 16,
          itemCount: encounterDetailCont.encounterDetail.value.prescriptions.length,
          itemBuilder: (ctx, index) {
            final Prescriptions prescriptionsData = encounterDetailCont.encounterDetail.value.prescriptions[index];

            return Container(
              width: Get.width,
              padding: const EdgeInsets.all(16),
              decoration: boxDecorationDefault(color: context.cardColor),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    prescriptionsData.name,
                    style: boldTextStyle(size: 12),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  8.height,
                  Text(prescriptionsData.instruction, style: secondaryTextStyle()),
                  commonDivider.paddingSymmetric(vertical: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text("${locale.value.frequency}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                          6.height,
                          Text(' ${prescriptionsData.frequency}', style: boldTextStyle(size: 12)),
                        ],
                      ).expand(flex: 3),
                      16.width,
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text("${locale.value.days}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                          6.height,
                          Text(' ${prescriptionsData.duration}', style: boldTextStyle(size: 12)),
                        ],
                      ).expand(flex: 2),
                    ],
                  ),
                ],
              ),
            ).paddingSymmetric(horizontal: 16);
          },
        ),
      ],
    );
  }
}
