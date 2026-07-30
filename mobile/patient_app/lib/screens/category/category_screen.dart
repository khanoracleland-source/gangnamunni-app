import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_scaffold.dart';
import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/empty_error_state_widget.dart';
import 'category_list_controller.dart';
import 'components/category_card.dart';

class CategoryScreen extends StatelessWidget {
  CategoryScreen({super.key});

  final CategoryListController categoryListController = Get.put(CategoryListController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.category,
      appBarVerticalSize: Get.height * 0.12,
      isLoading: categoryListController.isLoading,
      body: Obx(
        () => SnapHelperWidget(
          future: categoryListController.categoryListFuture.value,
          errorBuilder: (error) {
            return NoDataWidget(
              title: error,
              retryText: locale.value.reload,
              imageWidget: const ErrorStateWidget(),
              onRetry: () {
                categoryListController.page(1);
                categoryListController.getCategoryList();
              },
            ).paddingSymmetric(horizontal: 32);
          },
          loadingWidget: categoryListController.isLoading.value ? const Offstage() : const LoaderWidget(),
          onSuccess: (category) {
            if (category.isEmpty) {
              return NoDataWidget(
                title: locale.value.noCategoryFound,
                retryText: locale.value.reload,
                onRetry: () {
                  categoryListController.page(1);
                  categoryListController.getCategoryList();
                },
              );
            }

            return AnimatedScrollView(
              onSwipeRefresh: () async {
                categoryListController.page(1);
                return categoryListController.getCategoryList();
              },
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              onNextPage: () {
                if (!categoryListController.isLastPage.value) {
                  categoryListController.page(categoryListController.page.value + 1);
                  categoryListController.getCategoryList();
                }
              },
              children: [
                AnimatedWrap(
                  runSpacing: 16,
                  spacing: 16,
                  itemCount: category.length,
                  crossAxisAlignment: WrapCrossAlignment.start,
                  alignment: WrapAlignment.start,
                  itemBuilder: (_, index) {
                    return CategoryCard(category: category[index]);
                  },
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
