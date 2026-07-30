import 'package:flutter/material.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../utils/colors.dart';
import '../model/encounter_list_model.dart';

class AllEncountersCard extends StatelessWidget {
  final EncounterElement encounterElement;

  const AllEncountersCard({super.key, required this.encounterElement});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: boxDecorationDefault(
        color: context.cardColor,
        borderRadius: radius(defaultRadius / 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          16.height,
          Row(
            children: [
              Text(encounterElement.doctorName, style: boldTextStyle(size: 16)),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: boxDecorationDefault(
                  borderRadius: BorderRadius.circular(20),
                  color: encounterElement.status ? lightGreenColor : lightSecondaryColor,
                ),
                child: Text(
                  encounterElement.status ? locale.value.active : locale.value.closed,
                  style: boldTextStyle(
                    size: 10,
                    color: encounterElement.status ? completedStatusColor : pendingStatusColor,
                    weight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ).paddingSymmetric(horizontal: 16),
          8.height,
          Row(
            children: [
              Text(encounterElement.appointmentId == -1 ? locale.value.encounterId : locale.value.appointmentId, style: primaryTextStyle(size: 12, color: secondaryTextColor)),
              6.width,
              Text(
                encounterElement.appointmentId == -1 ? encounterElement.id.toString() : encounterElement.appointmentId.toString(),
                overflow: TextOverflow.ellipsis,
                style: boldTextStyle(size: 12),
              ).expand(),
            ],
          ).paddingSymmetric(horizontal: 16),
          8.height,
          Row(
            children: [
              Text('${locale.value.date}:', style: primaryTextStyle(size: 12, color: secondaryTextColor)),
              6.width,
              Text(
                encounterElement.encounterDate.dateInDMMMMyyyyFormat,
                overflow: TextOverflow.ellipsis,
                style: boldTextStyle(size: 12),
              ).expand(),
            ],
          ).paddingSymmetric(horizontal: 16),
          8.height,
          Row(
            children: [
              Text('${locale.value.clinicName}:', style: primaryTextStyle(size: 12, color: secondaryTextColor)),
              6.width,
              Text(
                encounterElement.clinicName,
                overflow: TextOverflow.ellipsis,
                style: boldTextStyle(size: 12),
              ).expand(),
            ],
          ).paddingSymmetric(horizontal: 16).visible(encounterElement.clinicName.isNotEmpty),
          16.height,
        ],
      ),
    );
  }
}
