import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/app_common.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../../../utils/view_all_label_component.dart';
import '../../clinic/model/clinics_res_model.dart';
import '../service_detail_controller.dart';

class ServiceDetailClinicsComponent extends StatelessWidget {
  final ServiceDetailController serviceDetailController;
  final Function(Clinic)? onClickViewDetail;
  final void Function(Clinic)? onCardTap;

  const ServiceDetailClinicsComponent({super.key, required this.serviceDetailController, this.onCardTap, this.onClickViewDetail});

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Column(
        children: [
          16.height,
          ViewAllLabel(
            label: locale.value.chooseClinic,
            isShowAll: false,
          ),
          Obx(
            () => AnimatedListView(
              physics: const NeverScrollableScrollPhysics(),
              padding: EdgeInsets.zero,
              shrinkWrap: true,
              itemCount: serviceDetailController.serviceData.value.clinics.length,
              itemBuilder: (context, index) {
                final Clinic clinicData = serviceDetailController.serviceData.value.clinics[index];
                return Obx(
                  () => GestureDetector(
                    onTap: () {
                      onCardTap?.call(clinicData);
                    },
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        Container(
                          alignment: Alignment.center,
                          padding: const EdgeInsets.all(16),
                          decoration: boxDecorationDefault(
                            borderRadius: BorderRadius.circular(6),
                            color: context.cardColor,
                          ),
                          child: Row(
                            children: [
                              CachedImageWidget(
                                url: clinicData.clinicImage,
                                width: 100,
                                height: 100,
                                radius: 6,
                                fit: BoxFit.cover,
                              ),
                              16.width,
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(clinicData.name, overflow: TextOverflow.ellipsis, maxLines: 1, style: boldTextStyle(size: 14, color: isDarkMode.value ? null : darkGrayTextColor)).flexible(),
                                    ],
                                  ),
                                  GestureDetector(
                                    onTap: () {
                                      launchMap(clinicData.address);
                                    },
                                    behavior: HitTestBehavior.opaque,
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        const CachedImageWidget(url: Assets.iconsIcLocation, color: iconColor, width: 16, height: 16),
                                        12.width,
                                        Text(
                                          clinicData.address,
                                          style: secondaryTextStyle(),
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                        ).flexible(),
                                      ],
                                    ),
                                  ).paddingTop(8).visible(clinicData.address.trim().isNotEmpty),
                                  Row(
                                    children: [
                                      GestureDetector(
                                        onTap: () {
                                          launchCall(clinicData.contactNumber);
                                        },
                                        behavior: HitTestBehavior.opaque,
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const CachedImageWidget(url: Assets.iconsIcCall, color: iconColor, width: 14, height: 14),
                                            12.width,
                                            Text(clinicData.contactNumber, style: primaryTextStyle(color: appColorPrimary)),
                                          ],
                                        ),
                                      ).paddingTop(8).visible(clinicData.contactNumber.trim().isNotEmpty),
                                    ],
                                  ),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      TextButton(
                                        style: const ButtonStyle(padding: WidgetStatePropertyAll(EdgeInsets.zero)),
                                        onPressed: () {
                                          onClickViewDetail?.call(clinicData);
                                        },
                                        child: Text(
                                          locale.value.viewDetail,
                                          style: boldTextStyle(size: 14, fontFamily: fontFamilyWeight700, color: appColorSecondary),
                                        ).paddingSymmetric(horizontal: 8),
                                      ),
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
                                      ).paddingLeft(4),
                                    ],
                                  ),
                                ],
                              ).expand(),
                              12.width,
                            ],
                          ),
                        ).paddingBottom(16),
                        Positioned(
                          top: -10,
                          right: -10,
                          child: commonLeadingWid(
                            imgPath: Assets.imagesConfirm,
                            color: whiteTextColor,
                            size: 8,
                          ).circularLightPrimaryBg(color: appColorPrimary, padding: 8),
                        ).visible(clinicData.id == serviceDetailController.selectedClinic.value.id),
                      ],
                    ),
                  ),
                ).paddingBottom(16);
              },
            ),
          ),
        ],
      ).paddingSymmetric(horizontal: 16),
    );
  }
}
