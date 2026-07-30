import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/cached_image_widget.dart';
import 'package:kivicare_patient/screens/doctor/model/doctor_list_res.dart';

import '../../../../generated/assets.dart';
import '../../../utils/app_common.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../doctor_detail_screen.dart';
import '../doctor_list_controller.dart';

class DoctorCard extends StatelessWidget {
  final Doctor doctorData;

  DoctorCard({super.key, required this.doctorData});

  final DoctorListController doctorsListCont = Get.find();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => GestureDetector(
        onTap: () {
          /// Store selected doctor in global variable
          if (doctorData.doctorId == doctorsListCont.selectedDoctor.value.doctorId) {
            /// Deselect, If again tap on same doctor
            doctorsListCont.selectedDoctor(Doctor());
            currentSelectedDoctor(doctorsListCont.selectedDoctor.value);
            log('CURRENT SELECTED CLINIC ID==> ${currentSelectedDoctor.value.doctorId}');
            log('CURRENT SELECTED CLINIC NAME==> ${currentSelectedDoctor.value.fullName}');
          } else {
            doctorsListCont.selectedDoctor(doctorData);
            currentSelectedDoctor(doctorsListCont.selectedDoctor.value);
            log('CURRENT SELECTED CLINIC ID==> ${currentSelectedDoctor.value.doctorId}');
            log('CURRENT SELECTED CLINIC NAME==> ${currentSelectedDoctor.value.fullName}');
          }
        },
        child: Container(
          decoration: boxDecorationDefault(color: transparentColor),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              CachedImageWidget(
                url: doctorData.profileImage,
                height: 190,
                fit: BoxFit.cover,
                width: Get.width / 2 - 24,
              ).cornerRadiusWithClipRRect(defaultRadius),
              const Positioned(
                top: 12,
                left: 12,
                child: CachedImageWidget(url: Assets.iconsIcOnline, height: 16, width: 16),
              ),
              Positioned(
                left: 0,
                right: 0,
                child: Container(
                  color: doctorData.doctorId == doctorsListCont.selectedDoctor.value.doctorId ? appColorPrimary.withValues(alpha: 0.4) : null,
                  width: Get.width,
                  height: 190,
                ).cornerRadiusWithClipRRect(8),
              ),
              Positioned(
                top: -10,
                right: -10,
                child: commonLeadingWid(
                  imgPath: Assets.imagesConfirm,
                  color: whiteTextColor,
                  size: 8,
                ).circularLightPrimaryBg(color: appColorPrimary, padding: 8),
              ).visible(doctorData.doctorId == doctorsListCont.selectedDoctor.value.doctorId),
              Positioned(
                bottom: 16,
                right: 16,
                left: 16,
                child: GestureDetector(
                  onTap: () {
                    Get.to(() => DoctorDetailScreen(), arguments: doctorData);
                  },
                  child: Container(
                    decoration: boxDecorationDefault(color: context.cardColor, borderRadius: radius(defaultRadius - 4)),
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            if (doctorData.fullName.isNotEmpty)
                              Text(
                                doctorData.fullName,
                                style: primaryTextStyle(),
                              ).paddingBottom(6),
                            if (doctorData.expert.isNotEmpty) Text(doctorData.expert, style: secondaryTextStyle(), maxLines: 1, overflow: TextOverflow.ellipsis),
                          ],
                        ).expand(),
                        1.width,
                        Image.asset(Assets.iconsIcInfo, height: 20, width: 20),
                      ],
                    ),
                  ),
                ),
              ).visible(doctorData.fullName.isNotEmpty || doctorData.expert.isNotEmpty),
            ],
          ),
        ),
      ),
    );
  }
}
