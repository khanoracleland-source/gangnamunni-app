import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../model/doctor_list_res.dart';

class AboutDoctorComponent extends StatelessWidget {
  final Doctor doctorData;

  const AboutDoctorComponent({super.key, required this.doctorData});

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.aboutMyself,
      appBarVerticalSize: Get.height * 0.12,
      body: AnimatedScrollView(
        padding: const EdgeInsets.all(24),
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(locale.value.about, style: boldTextStyle(size: 16)),
              16.height,
              Container(
                padding: const EdgeInsets.all(16),
                decoration: boxDecorationDefault(color: context.cardColor),
                child: ReadMoreText(
                  parseHtmlString(doctorData.aboutSelf),
                  trimLines: 4,
                  style: secondaryTextStyle(size: 14, color: secondaryTextColor),
                  colorClickableText: appColorPrimary,
                  trimMode: TrimMode.Line,
                  textAlign: TextAlign.justify,
                  trimCollapsedText: " ...${locale.value.readMore}",
                  trimExpandedText: locale.value.readLess,
                  locale: Localizations.localeOf(context),
                ),
              ),
            ],
          ).paddingBottom(30).visible(doctorData.aboutSelf.isNotEmpty),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(locale.value.contactInfo, style: boldTextStyle(size: 16)),
              16.height,
              Container(
                padding: const EdgeInsets.all(16),
                decoration: boxDecorationDefault(color: context.cardColor),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (doctorData.email.isNotEmpty)
                      GestureDetector(
                        onTap: () {
                          launchMail(doctorData.email);
                        },
                        behavior: HitTestBehavior.translucent,
                        child: Row(
                          children: [
                            CachedImageWidget(url: Assets.iconsIcMail, color: context.primaryColor, width: 14, height: 14),
                            12.width,
                            Text(doctorData.email, style: primaryTextStyle(color: secondaryTextColor)),
                          ],
                        ),
                      ).paddingBottom(16),
                    if (doctorData.mobile.isNotEmpty)
                      GestureDetector(
                        onTap: () {
                          launchCall(doctorData.mobile);
                        },
                        behavior: HitTestBehavior.translucent,
                        child: Row(
                          children: [
                            CachedImageWidget(url: Assets.iconsIcCall, color: context.primaryColor, width: 14, height: 14),
                            12.width,
                            Text(doctorData.mobile, style: primaryTextStyle(color: secondaryTextColor)),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ).paddingBottom(30).visible(doctorData.email.isNotEmpty || doctorData.mobile.isNotEmpty),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(locale.value.specialization, style: boldTextStyle(size: 16)),
              16.height,
              Container(
                padding: const EdgeInsets.all(16),
                decoration: boxDecorationDefault(color: context.cardColor),
                child: Row(
                  children: [
                    CachedImageWidget(url: Assets.iconsIcSpecialization, color: context.primaryColor, width: 14, height: 14),
                    12.width,
                    Text(doctorData.expert, style: primaryTextStyle(color: secondaryTextColor)),
                  ],
                ),
              ),
            ],
          ).paddingBottom(30).visible(doctorData.expert.isNotEmpty),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(locale.value.experience, style: boldTextStyle(size: 16)),
              16.height,
              Container(
                padding: const EdgeInsets.all(16),
                decoration: boxDecorationDefault(color: context.cardColor),
                child: Row(
                  children: [
                    CachedImageWidget(url: Assets.iconsIcExperience, color: context.primaryColor, width: 14, height: 14),
                    12.width,
                    Text(doctorData.experience, style: primaryTextStyle(color: secondaryTextColor)),
                  ],
                ),
              ),
            ],
          ).visible(doctorData.experience.isNotEmpty),
        ],
      ),
    );
  }
}
