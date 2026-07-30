import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import '../../../../main.dart';
import '../../../../utils/app_common.dart';
import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../utils/colors.dart';
import '../../auth/other/notification_screen.dart';

class GreetingsComponent extends StatelessWidget {
  const GreetingsComponent({super.key});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: Get.width,
      child: Row(
        children: [
          Obx(
            () => CachedImageWidget(
              url: loginUserData.value.profileImage,
              fit: BoxFit.cover,
              width: 48,
              height: 48,
              circle: true,
            ).paddingRight(8).visible(loginUserData.value.profileImage.contains("http")),
          ),
          Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Obx(
                () => Text(
                  '👋 ${locale.value.hey}, ${isLoggedIn.value ? loginUserData.value.userName.validate() : locale.value.guest.validate()}',
                  style: primaryTextStyle(color: white, size: 20),
                ),
              ),
              Obx(
                () => GestureDetector(
                  onLongPress: () {
                    loginUserData.value.address.copyToClipboard();
                  },
                  child: Row(
                    children: [
                      const CachedImageWidget(
                        url: Assets.imagesLocationPin,
                        height: 14,
                      ),
                      8.width,
                      Text(loginUserData.value.address, maxLines: 1, overflow: TextOverflow.ellipsis, style: secondaryTextStyle(color: white, size: 14)).flexible(),
                    ],
                  ),
                ).paddingTop(4).visible(loginUserData.value.address.isNotEmpty),
              ),
            ],
          ).expand(),
          16.width,
          GestureDetector(
            onTap: () {
              doIfLoggedIn(() {
                Get.to(() => NotificationScreen());
              });
            },
            behavior: HitTestBehavior.translucent,
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                const CachedImageWidget(
                  url: Assets.navigationIcNotifyOutlined,
                  color: Colors.white,
                  height: 24,
                ),
                Positioned(
                  top: -8 + -(3 * unreadNotificationCount.value.toString().length).toDouble(),
                  right: -4 + -(3 * unreadNotificationCount.value.toString().length).toDouble(),
                  child: Obx(
                    () => Container(
                      padding: const EdgeInsets.all(6),
                      decoration: boxDecorationDefault(color: appColorSecondary, shape: BoxShape.circle),
                      child: Text(
                        unreadNotificationCount.value.toString(),
                        style: secondaryTextStyle(color: white, size: 8),
                      ),
                    ).visible(unreadNotificationCount.value > 0),
                  ),
                ),
              ],
            ),
          ),
        ],
      ).paddingSymmetric(horizontal: 24),
    );
  }
}
