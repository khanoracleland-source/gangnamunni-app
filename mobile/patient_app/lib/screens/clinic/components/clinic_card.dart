import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/colors.dart';

import '../../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/app_common.dart';
import '../../../utils/common_base.dart';
import '../../service/service_list_controller.dart';
import '../clinic_detail_screen.dart';
import '../clinic_list_controller.dart';
import '../model/clinic_detail_model.dart';
import '../model/clinics_res_model.dart';

class ClinicCard extends StatelessWidget {
  final Clinic clinicData;

  ClinicCard({super.key, required this.clinicData});

  final ClinicListController clinicListCont = Get.find();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => GestureDetector(
        onTap: () {
          /// Store selected clinic in global variable
          if (clinicData.id == clinicListCont.selectedClinic.value.id) {
            /// Deselect, If again tap on same clinic
            clinicListCont.selectedClinic(Clinic(clinicSession: ClinicSession()));
            currentSelectedClinic(clinicListCont.selectedClinic.value);
            log('CURRENT SELECTED CLINIC ID==> ${currentSelectedClinic.value.id}');
            log('CURRENT SELECTED CLINIC NAME==> ${currentSelectedClinic.value.name}');
          } else {
            clinicListCont.selectedClinic(clinicData);
            currentSelectedClinic(clinicListCont.selectedClinic.value);
            log('CURRENT SELECTED CLINIC ID==> ${currentSelectedClinic.value.id}');
            log('CURRENT SELECTED CLINIC NAME==> ${currentSelectedClinic.value.name}');
          }
        },
        child: Container(
          decoration: boxDecorationDefault(color: context.cardColor, borderRadius: radius(8)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  CachedImageWidget(
                    url: clinicData.clinicImage,
                    width: Get.width,
                    fit: BoxFit.cover,
                    topLeftRadius: 8,
                    topRightRadius: 8,
                    height: Get.height * 0.24,
                  ),
                  Positioned(
                    left: 0,
                    right: 0,
                    child: Container(
                      color: clinicData.id == clinicListCont.selectedClinic.value.id ? appColorPrimary.withValues(alpha: 0.4) : null,
                      width: Get.width,
                      height: Get.height * 0.24,
                    ).cornerRadiusWithClipRRectOnly(topLeft: 8, topRight: 8),
                  ),
                  Positioned(
                    top: -10,
                    right: -10,
                    child: commonLeadingWid(
                      imgPath: Assets.imagesConfirm,
                      color: whiteTextColor,
                      size: 8,
                    ).circularLightPrimaryBg(color: appColorPrimary, padding: 8),
                  ).visible(clinicData.id == clinicListCont.selectedClinic.value.id),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        clinicData.name,
                        style: boldTextStyle(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ).expand(),
                    ],
                  ).paddingTop(16).visible(clinicData.name.trim().isNotEmpty),
                  GestureDetector(
                    onTap: () {
                      launchMap(clinicData.address);
                    },
                    behavior: HitTestBehavior.translucent,
                    child: Row(
                      children: [
                        const CachedImageWidget(url: Assets.iconsIcLocation, color: iconColor, width: 16, height: 16),
                        12.width,
                        Text(
                          clinicData.address,
                          style: secondaryTextStyle(),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ).expand(),
                      ],
                    ),
                  ).paddingTop(12).visible(clinicData.address.trim().isNotEmpty),
                  Row(
                    children: [
                      GestureDetector(
                        onTap: () {
                          launchCall(clinicData.contactNumber);
                        },
                        behavior: HitTestBehavior.translucent,
                        child: Row(
                          children: [
                            const CachedImageWidget(url: Assets.iconsIcCall, color: iconColor, width: 14, height: 14),
                            12.width,
                            Text(clinicData.contactNumber, style: primaryTextStyle(color: appColorPrimary)),
                          ],
                        ),
                      ).expand().visible(clinicData.contactNumber.trim().isNotEmpty),
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
                  ).paddingTop(8),
                  TextButton(
                    onPressed: () {
                      /// Store selected clinic in global variable
                      currentSelectedClinic(clinicData);
                      Get.delete<ServiceListController>();
                      Get.to(() => ClinicDetailScreen(), arguments: clinicData);
                    },
                    style: const ButtonStyle(
                      padding: WidgetStatePropertyAll(EdgeInsets.zero),
                      overlayColor: WidgetStatePropertyAll(lightSecondaryColor),
                    ),
                    child: Text(locale.value.viewDetail, style: boldTextStyle(color: appColorSecondary, size: 12)),
                  ),
                  8.height,
                ],
              ).paddingSymmetric(horizontal: 16),
            ],
          ),
        ),
      ),
    );
  }
}
