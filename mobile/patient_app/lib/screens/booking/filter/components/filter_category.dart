import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/category/model/category_list_model.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../../../components/loader_widget.dart';
import '../../../../../generated/assets.dart';
import '../../../../../main.dart';
import '../../../../../utils/colors.dart';
import '../../../../../utils/common_base.dart';
import '../../../../../utils/empty_error_state_widget.dart';
import '../filter_controller.dart';

class FilterCategoryComponent extends StatelessWidget {
  final FilterController filterCont;

  const FilterCategoryComponent({super.key, required this.filterCont});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Obx(
          () => SnapHelperWidget(
            future: filterCont.categoryListFuture.value,
            errorBuilder: (error) {
              return AnimatedScrollView(
                padding: const EdgeInsets.all(16),
                children: [
                  NoDataWidget(
                    title: error,
                    retryText: locale.value.reload,
                    imageWidget: const ErrorStateWidget(),
                    onRetry: () {
                      filterCont.categoryPage(1);
                      filterCont.getCategoryList();
                    },
                  ),
                ],
              ).paddingSymmetric(horizontal: 32);
            },
            loadingWidget: const LoaderWidget(),
            onSuccess: (data) {
              if (data.isEmpty) {
                return NoDataWidget(
                  title: locale.value.noCategoryFound,
                  retryText: locale.value.reload,
                  onRetry: () {
                    filterCont.categoryPage(1);
                    filterCont.getCategoryList();
                  },
                );
              } else {
                return Obx(
                  () => Stack(
                    children: [
                      AnimatedScrollView(
                        children: [
                          AnimatedWrap(
                            children: List.generate(filterCont.categoryList.length, (index) {
                              final CategoryElement category = filterCont.categoryList[index];

                              final bool isSelected = filterCont.selectedCategoryData.value.id == category.id;

                              return InkWell(
                                onTap: () {
                                  filterCont.selectedCategoryData(category);
                                },
                                child: Stack(
                                  children: [
                                    Container(
                                      margin: const EdgeInsets.all(6),
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                      decoration: boxDecorationDefault(
                                        color: context.cardColor,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Column(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Text(
                                            category.name,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: primaryTextStyle(
                                              size: 12,
                                              color: isSelected ? context.primaryColor : null,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Positioned(
                                      top: 0,
                                      right: 0,
                                      child: commonLeadingWid(
                                        imgPath: Assets.imagesConfirm,
                                        color: whiteTextColor,
                                        size: 8,
                                      ).circularLightPrimaryBg(color: appColorPrimary, padding: 6),
                                    ).visible(isSelected),
                                  ],
                                ),
                              );
                            }),
                          ),
                        ],
                        onNextPage: () async {
                          if (!filterCont.isCategoryLoading.value) {
                            filterCont.categoryPage(filterCont.categoryPage.value + 1);
                            filterCont.getCategoryList();
                          }
                        },
                        onSwipeRefresh: () async {
                          filterCont.categoryPage(1);
                          return filterCont.getCategoryList(showLoader: false);
                        },
                      ),
                      if (filterCont.isCategoryLoading.isTrue) const LoaderWidget(),
                    ],
                  ),
                );
              }
            },
          ),
        ).paddingOnly(bottom: 16, left: 16, right: 16).expand(),
      ],
    );
  }
}
