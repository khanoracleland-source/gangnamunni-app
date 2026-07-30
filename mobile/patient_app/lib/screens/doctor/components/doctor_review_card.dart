import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../../booking/model/employee_review_data.dart';

class DoctorReviewCard extends StatelessWidget {
  final DoctorReviewData doctorReviewData;

  const DoctorReviewCard({super.key, required this.doctorReviewData});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: Get.width,
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: boxDecorationDefault(color: extraLightPrimaryColor, borderRadius: radius(22)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CachedImageWidget(
                        url: Assets.iconsIcStarFilled,
                        color: getRatingBarColor(doctorReviewData.rating),
                        height: 12,
                      ),
                      5.width,
                      Text(
                        doctorReviewData.rating.toString(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: boldTextStyle(size: 12, color: appColorPrimary),
                      ).paddingTop(2),
                    ],
                  ),
                ),
                Text(
                  doctorReviewData.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: boldTextStyle(size: 14),
                ).paddingLeft(8).expand(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: boxDecorationDefault(color: lightSecondaryColor, borderRadius: radius(22)),
                  alignment: Alignment.center,
                  child: Text(
                    doctorReviewData.serviceName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: boldTextStyle(size: 12, color: appColorSecondary),
                  ),
                ).paddingLeft(16).expand().visible(doctorReviewData.serviceName.isNotEmpty),
              ],
            ),
          ),
          Row(
            children: [
              CachedImageWidget(
                url: doctorReviewData.profileImage,
                firstName: doctorReviewData.username,
                height: 35,
                width: 35,
                fit: BoxFit.cover,
                circle: true,
              ),
              10.width,
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        "${locale.value.by} ${doctorReviewData.username}",
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: primaryTextStyle(),
                      ),
                      const CachedImageWidget(url: Assets.iconsIcVerified, width: 14, height: 14).paddingLeft(8),
                    ],
                  ),
                  4.height,
                  Text(
                    doctorReviewData.createdAt.dateInyyyyMMddHHmmFormat.timeAgoWithLocalization,
                    style: secondaryTextStyle(color: secondaryTextColor),
                  ),
                ],
              ),
            ],
          ).paddingTop(16).visible(doctorReviewData.username.isNotEmpty),
          Text(doctorReviewData.reviewMsg, style: secondaryTextStyle()).paddingTop(16).visible(doctorReviewData.reviewMsg.isNotEmpty),
        ],
      ),
    );
  }
}
