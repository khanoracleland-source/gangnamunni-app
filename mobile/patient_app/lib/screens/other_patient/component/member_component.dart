import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../../auth/model/login_response.dart';

class MemberComponent extends StatelessWidget {
  final double? width;
  final UserData memberData;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  const MemberComponent({
    super.key,
    this.width,
    required this.memberData,
    this.onEdit,
    this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: Get.width,
      decoration: boxDecorationDefault(color: context.cardColor),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      child: Column(
        spacing: 16,
        children: [
          Row(
            spacing: 16,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CachedImageWidget(
                url: memberData.profileImage,
                circle: true,
                height: 55,
                width: 55,
                fit: BoxFit.cover,
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 8,
                children: [
                  Text(memberData.fullName, style: boldTextStyle(size: 16)),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: boxDecorationDefault(
                      borderRadius: BorderRadius.circular(20),
                      color: lightSecondaryColor,
                    ),
                    child: Text(
                      getOtherPatientRelation(relation: memberData.relation),
                      style: boldTextStyle(color: pendingStatusColor, size: 12),
                    ),
                  ),
                ],
              ).expand(),
              Wrap(
                spacing: 16,
                children: [
                  InkWell(
                    onTap: onEdit,
                    child: commonLeadingWid(
                      imgPath: Assets.iconsIcEdit,
                      color: darkGrayGeneral,
                      size: 16,
                    ),
                  ),
                  InkWell(
                    onTap: onDelete,
                    child: commonLeadingWid(
                      imgPath: Assets.iconsIcTrash,
                      color: darkGrayGeneral,
                      size: 16,
                    ),
                  ),
                ],
              ),
            ],
          ),
          Container(
            width: Get.width,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            decoration: boxDecorationDefault(
              color: context.scaffoldBackgroundColor,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (memberData.gender.isNotEmpty)
                  Row(
                    children: [
                      Expanded(
                        child: Text("${locale.value.genderWithColon} ", style: secondaryTextStyle(size: 14)),
                      ),
                      Expanded(
                        child: Text(
                          getOtherPatientGender(gender: memberData.gender),
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(),
                        ),
                      ),
                    ],
                  ).paddingBottom(4),
                if (memberData.contactNumber.isNotEmpty)
                  Row(
                    children: [
                      Expanded(
                        child: Text("${locale.value.contactNumberWithColon} ", style: secondaryTextStyle(size: 14)),
                      ),
                      Expanded(
                        child: Text(
                          memberData.contactNumber,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(),
                        ),
                      ),
                    ],
                  ).paddingBottom(4).onTap(() {
                    launchCall(memberData.contactNumber);
                  }),
                if (memberData.birthDate.isNotEmpty)
                  Row(
                    children: [
                      Expanded(
                        child: Text("${locale.value.dobWithColon} ", style: secondaryTextStyle(size: 14)),
                      ),
                      Expanded(
                        child: Text(
                          memberData.birthDate,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
