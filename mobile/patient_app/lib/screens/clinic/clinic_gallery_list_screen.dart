import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../components/cached_image_widget.dart';
import '../../components/loader_widget.dart';
import '../../components/zoom_image_screen.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import 'clinic_gallery_list_controller.dart';
import 'model/clinic_gallery_model.dart';

class ClinicGalleryListScreen extends StatelessWidget {
  ClinicGalleryListScreen({super.key});

  final ClinicGalleryListController galleryListCont = Get.put(ClinicGalleryListController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.gallery,
      scaffoldBackgroundColor: context.scaffoldBackgroundColor,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: galleryListCont.isLoading,
      body: Obx(
        () => SnapHelperWidget(
          future: galleryListCont.galleryListFuture.value,
          errorBuilder: (error) {
            return NoDataWidget(
              title: error,
              retryText: locale.value.reload,
              imageWidget: const ErrorStateWidget(),
              onRetry: () {
                galleryListCont.page(1);
                galleryListCont.getGalleryList();
              },
            ).paddingSymmetric(horizontal: 32);
          },
          loadingWidget: galleryListCont.isLoading.value ? const Offstage() : const LoaderWidget(),
          onSuccess: (data) {
            if (galleryListCont.galleryList.isEmpty) {
              return NoDataWidget(
                title: locale.value.noGalleryFoundAtAMoment,
                subTitle: locale.value.looksLikeThereIsNoGalleryForThisClinicWellKee,
                titleTextStyle: primaryTextStyle(),
                imageWidget: const EmptyStateWidget(),
                retryText: locale.value.reload,
                onRetry: () {
                  galleryListCont.page(1);
                  galleryListCont.getGalleryList();
                },
              ).paddingSymmetric(horizontal: 32);
            }

            return AnimatedScrollView(
              padding: const EdgeInsets.all(16),
              children: [
                AnimatedWrap(
                  spacing: 16,
                  runSpacing: 16,
                  children: List.generate(
                    galleryListCont.galleryList.length,
                    (index) {
                      final GalleryData galleryData = galleryListCont.galleryList[index];
                      return CachedImageWidget(
                        url: galleryData.fullUrl,
                        height: 115,
                        fit: BoxFit.cover,
                        width: Get.width / 3 - 22,
                      ).cornerRadiusWithClipRRect(defaultRadius).onTap(() {
                        if (galleryData.fullUrl.validate().isNotEmpty) {
                          ZoomImageScreen(
                            galleryImages: galleryListCont.galleryList.map((e) => e.fullUrl.validate()).toList(),
                            index: index,
                          ).launch(context);
                        }
                      });
                    },
                  ),
                ),
              ],
              onNextPage: () async {
                if (!galleryListCont.isLastPage.value) {
                  galleryListCont.page(galleryListCont.page.value + 1);
                  galleryListCont.getGalleryList();
                }
              },
              onSwipeRefresh: () async {
                galleryListCont.page(1);
                return galleryListCont.getGalleryList(showLoader: false);
              },
            );
          },
        ),
      ),
    );
  }
}
