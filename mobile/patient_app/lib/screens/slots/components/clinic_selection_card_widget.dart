import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../../clinic/model/clinics_res_model.dart';

class ClinicSelectionCardWidget extends StatelessWidget {
  final Clinic clinicData;
  final void Function()? onTap;
  final bool isSelected;

  const ClinicSelectionCardWidget({super.key, required this.clinicData, this.onTap, this.isSelected = false});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: boxDecorationDefault(color: context.cardColor),
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: Get.height * 0.12,
              height: Get.height * 0.12,
              decoration: boxDecorationDefault(),
              child: CachedImageWidget(url: clinicData.clinicImage, fit: BoxFit.cover, radius: 6),
            ),
            16.width,
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(clinicData.name, overflow: TextOverflow.ellipsis, style: boldTextStyle(size: 16)),
                if (clinicData.address.isNotEmpty)
                  TextIcon(
                    text: clinicData.address,
                    spacing: 12,
                    edgeInsets: EdgeInsets.zero,
                    expandedText: true,
                    textStyle: secondaryTextStyle(size: 14),
                    prefix: const CachedImageWidget(url: Assets.iconsIcLocation, color: secondaryTextColor, width: 14, height: 14),
                    onTap: () {
                      launchMap(clinicData.address);
                    },
                  ).paddingTop(12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    TextIcon(
                      text: clinicData.contactNumber,
                      spacing: 12,
                      edgeInsets: EdgeInsets.zero,
                      expandedText: true,
                      useMarquee: true,
                      textStyle: primaryTextStyle(color: appColorPrimary),
                      prefix: const CachedImageWidget(url: Assets.iconsIcCall, color: secondaryTextColor, width: 14, height: 14),
                      onTap: () {
                        launchMap(clinicData.contactNumber);
                      },
                    ).expand(),
                    6.width,
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                      decoration: boxDecorationDefault(
                        color: getClinicStatusLightColor(clinicStatus: clinicData.clinicStatus.toLowerCase()),
                        borderRadius: radius(22),
                      ),
                      child: Text(
                        getClinicStatus(status: clinicData.clinicStatus.toLowerCase()),
                        style: boldTextStyle(size: 10, color: getClinicStatusColor(clinicStatus: clinicData.clinicStatus.toLowerCase())),
                      ),
                    ),
                  ],
                ).paddingTop(12),
              ],
            ).expand(),
          ],
        ),
      ),
    );
  }
}
