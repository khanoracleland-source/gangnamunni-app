import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/utils/colors.dart';
import 'package:nb_utils/nb_utils.dart';

import '../generated/assets.dart';
import '../main.dart';
import 'cached_image_widget.dart';

class AppDialogueComponent extends StatelessWidget {
  final double? dialogueHeight;
  final String? confirmationImage;

  final Color? confirmationImageColor;
  final VoidCallback onConfirm;
  final String? titleText;
  final String? subTitleText;
  final String? confirmText;
  final String? cancelText;
  final BorderRadiusGeometry? borderRadius;

  final Widget? child;

  const AppDialogueComponent({
    super.key,
    this.dialogueHeight,
    required this.onConfirm,
    this.confirmationImage,
    this.titleText,
    this.subTitleText,
    this.confirmText,
    this.cancelText,
    this.confirmationImageColor,
    this.child,
    this.borderRadius,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.all(16),
        height: dialogueHeight,
        decoration: boxDecorationDefault(
          color: context.cardColor,
          borderRadius: borderRadius,
        ),
        child: AnimatedCrossFade(
          crossFadeState: CrossFadeState.showFirst,
          duration: const Duration(milliseconds: 600), // Fixed duration unit
          firstChild: Column(
            mainAxisSize: MainAxisSize.min, // Ensures height adjusts dynamically
            children: [
              12.height,
              child ??
                  CachedImageWidget(
                    url: confirmationImage ?? Assets.iconsIcConfirmation,
                    height: 35,
                    width: 35,
                    color: confirmationImage != null ? confirmationImageColor : appColorPrimary,
                  ),
              20.height,
              Text(
                titleText ?? locale.value.doYouWantToPerformThisAction,
                style: boldTextStyle(size: 16),
                textAlign: TextAlign.center,
              ),
              if (subTitleText.validate().isNotEmpty) ...[
                8.height,
                Text(
                  subTitleText.validate(),
                  style: secondaryTextStyle(),
                  textAlign: TextAlign.center,
                ),
              ],
              24.height,
              Row(
                spacing: 16,
                children: [
                  AppButton(
                    width: Get.width,
                    text: cancelText ?? locale.value.cancel,
                    shapeBorder: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    color: context.scaffoldBackgroundColor,
                    textStyle: boldTextStyle(),
                    onTap: () => Get.back(),
                  ).expand(),
                  AppButton(
                    width: Get.width,
                    text: confirmText ?? locale.value.confirm,
                    color: appColorSecondary,
                    shapeBorder: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    textStyle: boldTextStyle(color: Colors.white),
                    onTap: () {
                      Get.back();
                      onConfirm.call();
                    },
                  ).expand(),
                ],
              ),
            ],
          ),
          secondChild: const SizedBox.shrink(), // Cleaner alternative to Offstage()
        ),
      ),
    );
  }
}
