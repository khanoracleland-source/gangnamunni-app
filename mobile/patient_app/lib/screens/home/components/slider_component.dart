import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/utils/colors.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../components/cached_image_widget.dart';
import '../../../utils/common_base.dart';
import '../../../utils/constants.dart';
import '../../category/category_screen.dart';
import '../../service/service_detail_screen.dart';
import '../home_controller.dart';

class SliderComponent extends StatefulWidget {
  const SliderComponent({super.key});

  @override
  State<SliderComponent> createState() => _SliderComponentState();
}

class _SliderComponentState extends State<SliderComponent> {
  final HomeController homeScreenController = Get.find();
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startAutoScroll();
  }

  void _startAutoScroll() {
    _timer?.cancel(); // Cancel any existing timer
    _timer = Timer.periodic(const Duration(seconds: 5), (Timer timer) {
      if (homeScreenController.dashboardData.value.slider.isNotEmpty) {
        int nextPage = homeScreenController.sliderCurrentPage.value + 1;
        if (nextPage >= homeScreenController.dashboardData.value.slider.length) {
          nextPage = 0; // Loop back to the first page
        }
        homeScreenController.sliderPageController.animateToPage(
          nextPage,
          duration: const Duration(milliseconds: 600),
          curve: Curves.easeInOutCubic,
        );
        homeScreenController.sliderCurrentPage(nextPage);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel(); // Cancel timer to avoid memory leaks
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final sliderList = homeScreenController.dashboardData.value.slider;

      if (sliderList.isEmpty) {
        return const Offstage();
      }

      return SizedBox(
        height: 200,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            PageView.builder(
              controller: homeScreenController.sliderPageController,
              onPageChanged: (int page) {
                hideKeyboard(context);
                homeScreenController.sliderCurrentPage(page);
              },
              itemCount: sliderList.length,
              itemBuilder: (context, index) {
                final sliderItem = sliderList[index];
                return GestureDetector(
                  onTap: () {
                    if (sliderItem.link.isURL) {
                      commonLaunchUrl(
                        sliderItem.link,
                        launchMode: LaunchMode.externalApplication,
                      );
                    } else if (sliderItem.type == BannerType.CATEGORY) {
                      Get.to(() => CategoryScreen(), duration: const Duration(milliseconds: 800));
                    } else if (sliderItem.type == BannerType.SERVICE) {
                      Get.to(() => ServiceDetailScreen(), arguments: sliderItem.linkId);
                    }
                  },
                  behavior: HitTestBehavior.translucent,
                  child: Container(
                    color: Colors.transparent,
                    width: Get.width,
                    child: CachedImageWidget(
                      url: sliderItem.sliderImage,
                      fit: BoxFit.fitWidth,
                      usePlaceholderIfUrlEmpty: false,
                      width: Get.width,
                    ),
                  ),
                );
              },
            ),
            Positioned(
              bottom: -16,
              left: 0,
              right: 0,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List<Widget>.generate(
                  sliderList.length,
                  (index) {
                    return InkWell(
                      onTap: () {
                        homeScreenController.sliderPageController.animateToPage(
                          index,
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeIn,
                        );
                      },
                      child: Obx(
                        () => Container(
                          height: 10,
                          width: homeScreenController.sliderCurrentPage.value == index ? 20 : 10,
                          margin: const EdgeInsets.all(3),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: white),
                            color: homeScreenController.sliderCurrentPage.value == index ? appColorSecondary : appColorPrimary,
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ).paddingTop(30);
    });
  }
}
