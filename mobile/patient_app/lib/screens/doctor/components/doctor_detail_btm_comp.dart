import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../generated/assets.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../doctor_detail_controller.dart';
import 'about_doctor_component.dart';
import 'doctor_qualification_component.dart';
import '../doctor_review_screen.dart';
import 'doctor_services_component.dart';

class DoctorDetailBtmComp extends StatelessWidget {
  final DoctorDetailController doctorDetailCont;

  const DoctorDetailBtmComp({super.key, required this.doctorDetailCont});

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      width: Get.width,
      child: Column(
        children: [
          SettingItemWidget(
            title: locale.value.aboutMyself,
            decoration: boxDecorationDefault(color: context.cardColor),
            subTitle: locale.value.experienceSpecializationContactInfo,
            splashColor: transparentColor,
            onTap: () {
              Get.to(() => AboutDoctorComponent(doctorData: doctorDetailCont.doctorData.value));
            },
            titleTextStyle: boldTextStyle(size: 14),
            leading: commonLeadingWid(imgPath: Assets.iconsIcInfo, color: appColorSecondary),
            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
            padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
          ),
          Obx(
            () => SettingItemWidget(
              title: locale.value.services,
              decoration: boxDecorationDefault(color: context.cardColor),
              subTitle: doctorDetailCont.doctorData.value.totalServices != 0 ? "${locale.value.total} ${doctorDetailCont.doctorData.value.totalServices} ${locale.value.servicesAvailable}" : locale.value.noServicesAvailable,
              splashColor: transparentColor,
              onTap: () {
                Get.to(() => DoctorServicesComponent());
              },
              titleTextStyle: boldTextStyle(size: 14),
              leading: commonLeadingWid(imgPath: Assets.iconsIcServices, color: appColorSecondary),
              trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
              padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
            ).paddingTop(16),
          ),
          Obx(
            () => SettingItemWidget(
              title: locale.value.reviews,
              decoration: boxDecorationDefault(color: context.cardColor),
              subTitle: doctorDetailCont.doctorData.value.totalReviews != 0 ? "${locale.value.total} ${doctorDetailCont.doctorData.value.totalReviews} ${locale.value.reviews}" : locale.value.noReviewsAvailable,
              splashColor: transparentColor,
              onTap: () {
                Get.to(() => DoctorReviewScreen(), arguments: doctorDetailCont.doctorData.value.doctorId);
              },
              titleTextStyle: boldTextStyle(size: 14),
              leading: commonLeadingWid(imgPath: Assets.iconsIcStar, color: appColorSecondary),
              trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
              padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
            ).paddingTop(16),
          ),
          SettingItemWidget(
            title: locale.value.qualification,
            decoration: boxDecorationDefault(color: context.cardColor),
            subTitle: locale.value.qualificationInDetail,
            splashColor: transparentColor,
            onTap: () {
              Get.to(() => QualificationComponent(qualificationList: doctorDetailCont.doctorData.value.qualifications));
            },
            titleTextStyle: boldTextStyle(size: 14),
            leading: commonLeadingWid(imgPath: Assets.iconsIcQualification, color: appColorSecondary),
            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
            padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
          ).paddingTop(16),
        ],
      ),
    );
  }
}
