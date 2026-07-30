import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:percent_indicator/percent_indicator.dart';
import '../../components/cached_image_widget.dart';
import 'walkthrough_controller.dart';
import '../../utils/colors.dart';

class WalkthroughScreen extends StatelessWidget {
  WalkthroughScreen({super.key});
  final WalkthroughController walkthroughController = Get.put(WalkthroughController());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.scaffoldBackgroundColor,
      body: Stack(
        children: [
          PageView.builder(
            itemCount: walkthroughController.walkthroughDetails.length,
            controller: walkthroughController.pageController,
            onPageChanged: (int index) {
              walkthroughController.currentPage(index);
            },
            itemBuilder: (context, index) {
              return CachedImageWidget(
                url: walkthroughController.walkthroughDetails[index].image.validate(),
                fit: BoxFit.cover,
                width: Get.width,
                height: Get.height,
              );
            },
          ),
          Positioned(
            bottom: 0,
            width: Get.width,
            child: Obx(
              () => Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    walkthroughController.walkthroughDetails[walkthroughController.currentPage.value].title ?? "",
                    textAlign: TextAlign.center,
                    style: boldTextStyle(size: 24, color: white),
                  ).paddingSymmetric(horizontal: 54),
                  16.height,
                  Text(
                    walkthroughController.walkthroughDetails[walkthroughController.currentPage.value].subTitle ?? "",
                    textAlign: TextAlign.center,
                    style: secondaryTextStyle(size: 14, color: appScreenGreyBackground),
                  ).paddingSymmetric(horizontal: 54),
                  SizedBox(height: Get.height * 0.042),
                  Obx(
                    () => Column(
                      children: [
                        CircularPercentIndicator(
                          radius: (walkthroughController.skipBtnSize / 2) + 7,
                          lineWidth: 2.0,
                          percent: (walkthroughController.currentPage.value + 1) / walkthroughController.walkthroughDetails.length,
                          progressColor: appColorSecondary,
                          backgroundColor: transparentColor,
                          center: GestureDetector(
                            onTap: () {
                              walkthroughController.handleNext();
                            },
                            child: Container(
                              width: walkthroughController.skipBtnSize,
                              height: walkthroughController.skipBtnSize,
                              margin: const EdgeInsets.all(6),
                              padding: const EdgeInsets.all(10),
                              decoration: const BoxDecoration(shape: BoxShape.circle, color: appColorSecondary),
                              child: Center(
                                child: Icon(
                                  Icons.double_arrow_sharp,
                                  color: white,
                                  size: (walkthroughController.skipBtnSize / 2) + 7,
                                ),
                              ),
                            ),
                          ),
                        ),
                        SizedBox(height: Get.height * 0.042),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: List<Widget>.generate(
                            walkthroughController.walkthroughDetails.length,
                            (index) {
                              return InkWell(
                                onTap: () {
                                  walkthroughController.pageController.animateToPage(
                                    index,
                                    duration: const Duration(milliseconds: 300),
                                    curve: Curves.easeIn,
                                  );
                                },
                                child: Container(
                                  height: 8,
                                  width: walkthroughController.currentPage.value == index ? 35 : 8,
                                  margin: const EdgeInsets.all(5),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(20),
                                    color: walkthroughController.currentPage.value == index ? appColorPrimary : white,
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                    ).paddingSymmetric(horizontal: 16),
                  ),
                  SizedBox(height: Get.height * 0.02),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
