import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../model/doctor_detail_model.dart';

class QualificationCard extends StatelessWidget {
  final Qualifications qualificationData;

  const QualificationCard({super.key, required this.qualificationData});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          16.width,
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("${locale.value.year}:", style: secondaryTextStyle(size: 12)),
              8.height,
              Text(qualificationData.year, style: boldTextStyle(size: 12)),
            ],
          ).expand().visible(qualificationData.year.isNotEmpty),
          16.width,
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("${locale.value.degree}:", style: secondaryTextStyle(size: 12)),
              8.height,
              Text(qualificationData.degree, style: boldTextStyle(size: 12)),
            ],
          ).expand(flex: 2),
          16.width,
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("${locale.value.university}:", style: secondaryTextStyle(size: 12)),
              8.height,
              Text(qualificationData.university, style: boldTextStyle(size: 12)),
            ],
          ).expand(flex: 2),
          16.width,
        ],
      ),
    );
  }
}
