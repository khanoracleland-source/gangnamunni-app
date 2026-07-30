import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/bottom_selection_widget.dart';
import '../../../components/loader_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/common_base.dart';
import '../../../utils/empty_error_state_widget.dart';

class BottomSelectionSheet extends StatelessWidget {
  final String title;
  final String? hintText;
  final String? noDataTitle;
  final String? noDataSubTitle;
  final String? errorText;
  final bool hasError;
  final bool isEmpty;
  final RxBool? isLoading;
  final void Function()? onRetry;
  final TextEditingController? searchTextCont;
  final Function(String)? onChanged;
  final Widget listWidget;
  final bool hideSearchBar;
  final double heightRatio;
  final Function(String)? searchApiCall;

  const BottomSelectionSheet({
    super.key,
    required this.title,
    this.hintText,
    required this.listWidget,
    this.noDataTitle,
    this.noDataSubTitle,
    this.errorText,
    this.searchTextCont,
    required this.hasError,
    required this.isEmpty,
    this.onRetry,
    this.onChanged,
    this.isLoading,
    this.hideSearchBar = false,
    this.heightRatio = 0.80,
    this.searchApiCall,
  });

  @override
  Widget build(BuildContext context) {
    return GetBuilder(
      init: BSScontroller(searchApiCall: searchApiCall),
      builder: (getxBSSCont) {
        return PopScope(
          canPop: !(isLoading == null ? false : isLoading!.value),
          onPopInvokedWithResult: (didPop, result) {
            handleCloseClick(context, getxBSSCont);
          },
          child: GestureDetector(
            onTap: () => hideKeyboard(context),
            behavior: HitTestBehavior.translucent,
            child: Container(
              width: Get.width,
              constraints: BoxConstraints(minWidth: Get.height * 0.65, maxHeight: Get.height * heightRatio),
              decoration: BoxDecoration(
                color: context.cardColor,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(30.0),
                  topRight: Radius.circular(30.0),
                ),
              ),
              child: Stack(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      54.height,
                      commonDivider.paddingSymmetric(vertical: 8),
                      AppTextField(
                        controller: getxBSSCont.searchCont,
                        textStyle: secondaryTextStyle(size: 14, color: textPrimaryColorGlobal),
                        textFieldType: TextFieldType.OTHER,
                        onChanged: (p0) {
                          onChanged?.call(p0);
                          getxBSSCont.isSearchText(getxBSSCont.searchCont.text.trim().isNotEmpty);
                          getxBSSCont.searchStream.add(p0);
                        },
                        decoration: inputDecorationWithOutBorder(
                          context,
                          hintText: hintText ?? "Search Here...",
                          prefixIcon: commonLeadingWid(imgPath: Assets.iconsIcSearch, size: 14).paddingAll(12),
                          filled: true,
                          fillColor: context.scaffoldBackgroundColor,
                          suffixIcon: Obx(
                            () => appCloseIconButton(
                              context,
                              onPressed: () {
                                handleCloseClick(context, getxBSSCont);
                              },
                              size: 11,
                            ).visible(getxBSSCont.isSearchText.value),
                          ),
                        ),
                      ).paddingTop(16).visible(!hideSearchBar),
                      16.height.visible(!hideSearchBar),
                      if (hasError)
                        Obx(
                          () => NoDataWidget(
                            title: errorText ?? locale.value.somethingWentWrong,
                            retryText: locale.value.reload,
                            imageWidget: const ErrorStateWidget(),
                            onRetry: isLoading == true.obs ? null : onRetry,
                          ).paddingSymmetric(horizontal: 32),
                        ).visible(!(isLoading == null ? false : isLoading!.value))
                      else
                        isEmpty
                            ? Obx(
                                () => NoDataWidget(
                                  title: noDataTitle ?? locale.value.noDataFound,
                                  subTitle: noDataSubTitle,
                                  titleTextStyle: primaryTextStyle(),
                                  retryText: locale.value.reload,
                                  imageWidget: const EmptyStateWidget(),
                                  onRetry: isLoading == true.obs ? null : onRetry,
                                ).paddingSymmetric(horizontal: 32).visible(!(isLoading == null ? false : isLoading!.value)),
                              )
                            : listWidget,
                      32.height,
                    ],
                  ).paddingSymmetric(horizontal: 30),
                  Positioned(
                    top: 22,
                    right: 0,
                    left: 0,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(title, style: primaryTextStyle(size: 18)).paddingOnly(left: 30, right: 30),
                        appCloseIconButton(
                          context,
                          onPressed: () {
                            if (!(isLoading == null ? false : isLoading!.value)) {
                              handleCloseClick(context, getxBSSCont);
                              Get.back();
                            }
                          },
                          size: 11,
                        ).paddingOnly(right: 16),
                      ],
                    ),
                  ),
                  Obx(() => const LoaderWidget().center().visible(isLoading == null ? false : isLoading!.value)),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  void handleCloseClick(BuildContext context, BSScontroller getxBSSCont) {
    if (searchApiCall != null && getxBSSCont.isSearchText.value) {
      searchApiCall?.call("");
    }
    hideKeyboard(context);
    getxBSSCont.searchCont.clear();
    getxBSSCont.isSearchText(getxBSSCont.searchCont.text.trim().isNotEmpty);
  }
}
