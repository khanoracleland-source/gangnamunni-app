import 'package:flutter/material.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';

import '../main.dart';
import '../utils/colors.dart';

class CommonPdfPlaceHolder extends StatelessWidget {
  final String text;
  final double width;
  final double height;
  final String fileLink;

  const CommonPdfPlaceHolder({
    super.key,
    this.height = 90,
    this.width = 80,
    this.text = "file",
    this.fileLink = '',
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        if (fileLink.isNotEmpty) {
          viewFiles(fileLink);
        }
      },
      child: Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: context.cardColor,
          borderRadius: BorderRadius.circular(defaultRadius),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.file_copy_rounded,
              color: appColorPrimary,
              size: 32,
            ),
            12.height,
            Marquee(
              child: Text(
                text == 'File' ? locale.value.file : text,
                style: primaryTextStyle(),
                maxLines: 1,
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ).center(),
      ),
    );
  }
}
