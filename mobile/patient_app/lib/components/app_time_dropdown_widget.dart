import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';

import '../utils/app_common.dart';
import '../utils/colors.dart';

class AppTimeDropDownWidget extends StatelessWidget {
  final String value;
  final double? height;
  final Color? bgColor;

  const AppTimeDropDownWidget({super.key, required this.value, this.height, this.bgColor});

  List<String> get splitValues => value.split(" ");

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height ?? 40.0,
      width: double.infinity,
      alignment: Alignment.center,
      decoration: boxDecorationDefault(color: bgColor ?? white, borderRadius: BorderRadius.circular(6)),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Center(
            child: Text(
              splitValues.isNotEmpty ? splitValues.first : "-",
              style: secondaryTextStyle(size: 12, color: value.isNotEmpty ? textPrimaryColorGlobal : appBodyColor),
            ),
          ).expand(),
          VerticalDivider(color: isDarkMode.value ? iconColor.withValues(alpha: 0.5) : iconColor.withValues(alpha: 0.5)),
          8.width,
          Text(
            value.isEmpty || splitValues.isEmpty ? "-" : value.split(" ").last,
            style: secondaryTextStyle(size: 12, color: value.isNotEmpty ? textPrimaryColorGlobal : appBodyColor),
          ),
          18.width,
        ],
      ),
    );
  }
}
