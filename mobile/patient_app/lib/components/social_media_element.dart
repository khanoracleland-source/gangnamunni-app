import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';

import '../utils/colors.dart';
import 'cached_image_widget.dart';

class SocialMediaElement extends StatelessWidget {
  final void Function() onPressed;
  final String iconPath;

  const SocialMediaElement({super.key, required this.onPressed, required this.iconPath});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      constraints: BoxConstraints.tight(const Size.fromRadius(18)),
      padding: EdgeInsets.zero,
      onPressed: onPressed,
      icon: Container(
        padding: const EdgeInsets.all(10),
        decoration: boxDecorationDefault(color: lightPrimaryColor, shape: BoxShape.circle),
        child: CachedImageWidget(
          url: iconPath,
          height: 14,
          fit: BoxFit.fitHeight,
        ),
      ),
    );
  }
}
