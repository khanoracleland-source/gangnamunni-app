import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/colors.dart';
import '../../../components/cached_image_widget.dart';

class ProfilePicWidget extends StatelessWidget {
  final double picSize;
  final String profileImage;
  final String heroTag;
  final String firstName;
  final String lastName;
  final String userName;
  final String subInfo;
  final Function()? onCameraTap;
  final Function()? onPicTap;
  final bool showOnlyPhoto;
  final bool showCameraIconOnCornar;

  const ProfilePicWidget({
    super.key,
    this.picSize = 130,
    required this.profileImage,
    required this.heroTag,
    this.firstName = "",
    this.lastName = "",
    this.userName = "",
    this.subInfo = "",
    this.onCameraTap,
    this.onPicTap,
    this.showCameraIconOnCornar = true,
    this.showOnlyPhoto = false,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Column(
          children: [
            Stack(
              children: [
                Align(
                  alignment: Alignment.topCenter,
                  child: GestureDetector(
                    onTap: onPicTap,
                    child: Stack(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: boxDecorationDefault(shape: BoxShape.circle, color: context.cardColor),
                          child: Hero(
                            tag: heroTag,
                            child: CachedImageWidget(
                              url: profileImage,
                              firstName: firstName,
                              lastName: lastName,
                              height: picSize,
                              width: picSize,
                              fit: BoxFit.cover,
                              circle: true,
                            ),
                          ),
                        ),
                        Positioned(
                          top: picSize * 3 / 4 + 6,
                          left: picSize * 3 / 4 + 6,
                          child: GestureDetector(
                            onTap: onCameraTap,
                            child: Container(
                              padding: const EdgeInsets.all(3),
                              decoration: boxDecorationDefault(shape: BoxShape.circle, color: Colors.white),
                              child: Container(
                                padding: const EdgeInsets.all(5),
                                decoration: boxDecorationDefault(shape: BoxShape.circle, color: appColorPrimary),
                                child: const Icon(
                                  Icons.camera_alt_outlined,
                                  size: 16,
                                  color: Colors.white,
                                ),
                              ),
                            ),
                          ),
                        ).visible(showCameraIconOnCornar),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            if (!showOnlyPhoto) ...[
              16.height,
              Text(userName, style: primaryTextStyle(size: 22)),
              4.height,
              Text(subInfo, style: secondaryTextStyle(size: 14)),
            ],
          ],
        ),
      ],
    );
  }
}
