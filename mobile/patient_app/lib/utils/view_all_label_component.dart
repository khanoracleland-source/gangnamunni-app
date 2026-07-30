import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/colors.dart';
import 'package:kivicare_patient/utils/constants.dart';

import '../main.dart';

class ViewAllLabel extends StatelessWidget {
  final String label;
  final String? trailingText;
  final List? list;
  final VoidCallback? onTap;
  final int? labelSize;
  final bool isShowAll;
  final Color? trailingTextColor;
  final int? maxLines;
  final TextOverflow? textOverflow;
  final bool expandedText;

  const ViewAllLabel({
    super.key,
    required this.label,
    this.onTap,
    this.labelSize,
    this.list,
    this.isShowAll = true,
    this.trailingText,
    this.trailingTextColor,
    this.maxLines,
    this.textOverflow,
    this.expandedText = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: boldTextStyle(size: labelSize ?? Constants.labelTextSize),
            maxLines: maxLines ?? 1,
            overflow: textOverflow ?? TextOverflow.ellipsis,
          ),
        ),
        if (isShowAll)
          TextButton(
            onPressed: (list == null ? true : isViewAllVisible(list!))
                ? () {
              onTap?.call();
            }
                : null,
            child: (list == null ? true : isViewAllVisible(list!))
                ? Text(
              trailingText ?? locale.value.viewAll,
              style: boldTextStyle(color: trailingTextColor ?? appColorSecondary, size: 14),
            )
                : const SizedBox(),
          )
        else
          46.height,
      ],
    );
  }
}

bool isViewAllVisible(List list) => list.length >= 4;
