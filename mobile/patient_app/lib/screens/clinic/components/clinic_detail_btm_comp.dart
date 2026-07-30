import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../../doctor/doctor_list_screen.dart';
import '../../service/services_list_screen.dart';
import '../clinic_detail_controller.dart';
import '../clinic_gallery_list_screen.dart';
import 'clinic_session_component.dart';

class ClinicDetailBtmComp extends StatelessWidget {
  final ClinicDetailController clinicDetailCont;

  const ClinicDetailBtmComp({super.key, required this.clinicDetailCont});

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      width: Get.width,
      child: Column(
        children: [
          SettingItemWidget(
            title: locale.value.sessions,
            decoration: boxDecorationDefault(color: context.cardColor),
            subTitle: locale.value.clinicSessionsInformation,
            splashColor: transparentColor,
            onTap: () {
              Get.to(() => ClinicSessionComponent(clinicDetailCont: clinicDetailCont));
            },
            titleTextStyle: boldTextStyle(size: 14),
            leading: commonLeadingWid(imgPath: Assets.iconsIcClock, color: appColorSecondary),
            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
            padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
          ),
          Obx(
            () {
              final hasServices = clinicDetailCont.activeServicesCount.value > 0;
              return Opacity(
                opacity: hasServices ? 1.0 : 0.6,
                child: IgnorePointer(
                  ignoring: !hasServices,
                  child: SettingItemWidget(
                    title: locale.value.services,
                    decoration: boxDecorationDefault(color: context.cardColor),
                    subTitle: hasServices ? "${locale.value.total} ${clinicDetailCont.activeServicesCount.value} ${locale.value.servicesAvailable}" : locale.value.noServicesAvailable,
                    splashColor: transparentColor,
                    onTap: () {
                      Get.to(() => ServiceListScreen(isFromClinicDetail: true), arguments: clinicDetailCont.clinicData.value.id);
                    },
                    titleTextStyle: boldTextStyle(size: 14),
                    leading: commonLeadingWid(imgPath: Assets.iconsIcServices, color: appColorSecondary),
                    trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
                    padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                  ).paddingTop(16),
                ),
              );
            },
          ),
          Obx(
            () {
              final hasDoctors = clinicDetailCont.activeDoctorsCount.value > 0;
              return Opacity(
                opacity: hasDoctors ? 1.0 : 0.6,
                child: IgnorePointer(
                  ignoring: !hasDoctors,
                  child: SettingItemWidget(
                    title: locale.value.doctors,
                    decoration: boxDecorationDefault(color: context.cardColor),
                    subTitle: hasDoctors ? "${locale.value.total} ${clinicDetailCont.activeDoctorsCount.value} ${locale.value.doctorsAvailable}" : locale.value.noDoctorsAvailable,
                    splashColor: transparentColor,
                    onTap: () {
                      Get.to(() => DoctorsListScreen(), arguments: clinicDetailCont.clinicData.value.id);
                    },
                    titleTextStyle: boldTextStyle(size: 14),
                    leading: commonLeadingWid(imgPath: Assets.iconsIcDoctor, color: appColorSecondary),
                    trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
                    padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                  ).paddingTop(16),
                ),
              );
            },
          ),
          Obx(
            () => SettingItemWidget(
              title: locale.value.gallery,
              decoration: boxDecorationDefault(color: context.cardColor),
              subTitle: clinicDetailCont.clinicData.value.totalGalleryImages != 0 ? "${locale.value.total} ${clinicDetailCont.clinicData.value.totalGalleryImages} ${locale.value.photosAvailable}" : locale.value.noPhotosAvailable,
              splashColor: transparentColor,
              onTap: () {
                Get.to(() => ClinicGalleryListScreen(), arguments: clinicDetailCont.clinicData.value.id);
              },
              titleTextStyle: boldTextStyle(size: 14),
              leading: commonLeadingWid(imgPath: Assets.iconsIcGallery, color: appColorSecondary),
              trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray),
              padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
            ).paddingTop(16),
          ),
        ],
      ),
    );
  }
}
