import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/common_base.dart';
import '../dashboard_controller.dart';

class PhoneNumberBottomSheet extends StatelessWidget {
  final Function(String)? onConfirm;

  PhoneNumberBottomSheet({super.key, this.onConfirm});

  final DashboardController dashboardController = Get.put(DashboardController());

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Get.context!.scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          16.height,
          CachedImageWidget(
            url: Assets.iconsIcPhone,
            color: context.primaryColor,
            height: 70,
            width: 70,
          ),
          16.height,
          Text(
            locale.value.enterYourPhoneNumber,
            style: primaryTextStyle(),
          ),
          16.height,
          Form(
            key: dashboardController.phoneFormKey,
            child: AppTextField(
              title: locale.value.phoneNumber,
              textStyle: primaryTextStyle(size: 12),
              controller: dashboardController.phoneController,
              autoFocus: true,
              textFieldType: TextFieldType.PHONE,
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return locale.value.pleaseEnterPhoneNumber;
                }
                if (value.trim().length < 8) {
                  return locale.value.pleaseEnterValidPhoneNumber;
                }
                return null;
              },
              decoration: inputDecoration(
                Get.context!,
                fillColor: Get.context!.cardColor,
                filled: true,
                hintText: "${locale.value.eG} 123456789",
              ),
              suffix: commonLeadingWid(
                imgPath: Assets.iconsIcPhone,
                size: 14,
              ).paddingAll(14),
            ),
          ),
          16.height,
          Row(
            children: [
              Expanded(
                child: AppButton(
                  text: locale.value.cancel,
                  color: Colors.grey[300],
                  textColor: Colors.black,
                  onTap: () {
                    Get.back();
                  },
                ),
              ),
              12.width,
              Expanded(
                child: AppButton(
                  text: locale.value.confirm,
                  onTap: () {
                    if (dashboardController.phoneFormKey.currentState!.validate()) {
                      String phone = dashboardController.phoneController.text.trim();
                      Get.back(); // close the sheet
                      onConfirm?.call(phone); // return phone to caller
                    }
                  },
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
