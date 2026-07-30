import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../main.dart';
import '../utils/colors.dart';

class AppCustomDialog extends StatelessWidget {
  final String title;
  final String? positiveText;
  final String? negativeText;
  final Function onTap;

  const AppCustomDialog({super.key, required this.title, this.positiveText, this.negativeText, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: Get.width,
      height: Get.height * 0.4,
      child: Column(
        children: [
          Container(
            height: 140.0,
            width: Get.width,
            decoration: BoxDecoration(color: appColorPrimary.withValues(alpha: 0.2)),
            alignment: Alignment.center,
            child: Container(
              decoration: BoxDecoration(color: appColorPrimary.withValues(alpha: 0.2), shape: BoxShape.circle),
              padding: const EdgeInsets.all(16),
              child: const Icon(Icons.warning_amber_rounded, color: appColorPrimary, size: 40),
            ),
          ).cornerRadiusWithClipRRectOnly(topLeft: defaultRadius.toInt(), topRight: defaultRadius.toInt()),
          Container(
            width: Get.width,
            color: Colors.transparent,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(title, style: boldTextStyle(size: 16), textAlign: TextAlign.center),
                30.height,
                Row(
                  children: [
                    AppButton(
                      elevation: 0,
                      shapeBorder: RoundedRectangleBorder(
                        borderRadius: radius(defaultRadius),
                        side: const BorderSide(color: viewLineColor),
                      ),
                      color: context.cardColor,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.close, color: textPrimaryColorGlobal, size: 20),
                          6.width,
                          Text(negativeText ?? 'No', style: boldTextStyle(color: textPrimaryColorGlobal)),
                        ],
                      ).fit(),
                      onTap: () {
                        Get.back();
                      },
                    ).expand(),
                    16.width,
                    AppButton(
                      elevation: 0,
                      color: appColorPrimary,
                      shapeBorder: RoundedRectangleBorder(borderRadius: radius(defaultRadius)),
                      onTap: onTap,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.done, color: Colors.white, size: 20),
                          6.width,
                          Text(positiveText ?? locale.value.yes, style: boldTextStyle(color: Colors.white)),
                        ],
                      ).fit(),
                    ).expand(),
                  ],
                ),
              ],
            ),
          ).cornerRadiusWithClipRRectOnly(bottomLeft: defaultRadius.toInt(), bottomRight: defaultRadius.toInt()).expand(),
        ],
      ),
    );
  }
}
