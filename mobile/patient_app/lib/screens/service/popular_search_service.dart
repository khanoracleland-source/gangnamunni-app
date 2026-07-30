import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/screens/service/service_list_controller.dart';
import 'package:kivicare_patient/utils/colors.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../generated/assets.dart';
import '../../utils/common_base.dart';

class PopularSearchServiceWidget extends StatelessWidget {
  final String? hintText;
  final Function(String)? onFieldSubmitted;
  final Function()? onTap;
  final Function()? onClearButton;
  final ServiceListController popularServiceController;

  const PopularSearchServiceWidget({
    super.key,
    this.hintText,
    this.onTap,
    this.onFieldSubmitted,
    this.onClearButton,
    required this.popularServiceController,
  });

  @override
  Widget build(BuildContext context) {
    return AppTextField(
      controller: popularServiceController.searchCont,
      textFieldType: TextFieldType.OTHER,
      textInputAction: TextInputAction.done,
      textStyle: primaryTextStyle(decorationColor: appColorPrimary),
      onTap: onTap,
      onFieldSubmitted: onFieldSubmitted,
      onChanged: (p0) {
        popularServiceController.isSearchText(popularServiceController.searchCont.text.trim().isNotEmpty);
        popularServiceController.searchStream.add(p0);
      },
      suffix: Obx(
            () => appCloseIconButton(
          context,
          onPressed: () {
            if (onClearButton != null) {
              onClearButton!.call();
            }
            hideKeyboard(context);
            popularServiceController.searchCont.clear();
            popularServiceController.isSearchText(popularServiceController.searchCont.text.trim().isNotEmpty);
            popularServiceController.page(1);
            popularServiceController.getServiceList();
          },
          size: 11,
        ).visible(popularServiceController.isSearchText.value),
      ),
      decoration: inputDecorationWithOutBorder(
        context,
        hintText: hintText ?? locale.value.searchHere,
        filled: true,
        fillColor: context.cardColor,
        prefixIcon: commonLeadingWid(imgPath: Assets.iconsIcSearch, icon: Icons.search_outlined, size: 18).paddingAll(14),
      ),
    );
  }
}
