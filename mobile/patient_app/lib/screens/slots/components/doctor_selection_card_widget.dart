import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../utils/colors.dart';
import '../../doctor/model/doctor_list_res.dart';

class DoctorSelectionCardWidget extends StatelessWidget {
  final Doctor doctorData;
  final void Function()? onTap;
  final bool isSelected;

  const DoctorSelectionCardWidget({super.key, required this.doctorData, this.onTap, this.isSelected = false});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: boxDecorationDefault(color: context.cardColor),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                Container(
                  width: Get.height * 0.12,
                  height: Get.height * 0.12,
                  decoration: boxDecorationDefault(),
                  child: CachedImageWidget(url: doctorData.profileImage, fit: BoxFit.cover, radius: 6),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: CachedImageWidget(
                    url: Assets.iconsIcOnline,
                    color: doctorData.status == 1 ? Colors.green : redTextColor,
                    height: 14,
                    width: 14,
                  ),
                ),
              ],
            ),
            16.width,
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(doctorData.fullName, overflow: TextOverflow.ellipsis, style: boldTextStyle(size: 16)),
                if (doctorData.expert.isNotEmpty)
                  TextIcon(
                    text: doctorData.expert,
                    spacing: 12,
                    expandedText: true,
                    edgeInsets: EdgeInsets.zero,
                    textStyle: secondaryTextStyle(size: 14),
                    prefix: const CachedImageWidget(url: Assets.iconsIcQualification, color: secondaryTextColor, width: 14, height: 14),
                  ).paddingTop(12),
                if (doctorData.email.isNotEmpty)
                  TextIcon(
                    text: doctorData.email,
                    spacing: 12,
                    expandedText: true,
                    edgeInsets: EdgeInsets.zero,
                    textStyle: secondaryTextStyle(size: 14),
                    prefix: const CachedImageWidget(url: Assets.iconsIcMail, color: secondaryTextColor, width: 14, height: 14),
                  ).paddingTop(12),
                if (doctorData.experience.isNotEmpty)
                  TextIcon(
                    text: doctorData.experience,
                    spacing: 12,
                    expandedText: true,
                    edgeInsets: EdgeInsets.zero,
                    textStyle: secondaryTextStyle(size: 14),
                    prefix: const CachedImageWidget(url: Assets.iconsIcExperience, color: secondaryTextColor, width: 14, height: 14),
                  ).paddingTop(12),
              ],
            ).expand(),
          ],
        ),
      ),
    );
  }
}
