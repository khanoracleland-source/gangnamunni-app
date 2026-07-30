import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../../../generated/assets.dart';
import '../../../../../main.dart';
import '../../../../../utils/colors.dart';
import '../../../../../utils/common_base.dart';
import '../../filter_controller.dart';

class FilterSearchClinicComponent extends StatelessWidget {
  final String? hintText;
  final Function(String)? onFieldSubmitted;
  final Function()? onTap;
  final Function()? onClearButton;
  final FilterController filterClinicController;

  const FilterSearchClinicComponent({
    super.key,
    this.hintText,
    this.onTap,
    this.onFieldSubmitted,
    this.onClearButton,
    required this.filterClinicController,
  });

  @override
  Widget build(BuildContext context) {
    return AppTextField(
      controller: filterClinicController.searchClinicCont,
      textFieldType: TextFieldType.OTHER,
      textInputAction: TextInputAction.done,
      textStyle: primaryTextStyle(decorationColor: appColorPrimary),
      onTap: onTap,
      onFieldSubmitted: onFieldSubmitted,
      onChanged: (p0) {
        filterClinicController.isSearchClinicText(filterClinicController.searchClinicCont.text.trim().isNotEmpty);
        filterClinicController.searchClinicStream.add(p0);
      },
      suffix: Obx(
        () => appCloseIconButton(
          context,
          onPressed: () {
            if (onClearButton != null) {
              onClearButton!.call();
            }
            hideKeyboard(context);
            filterClinicController.searchClinicCont.clear();
            filterClinicController.isSearchClinicText(filterClinicController.searchClinicCont.text.trim().isNotEmpty);
            filterClinicController.clinicPage(1);
            filterClinicController.getClinicsList();
          },
          size: 11,
        ).visible(filterClinicController.isSearchClinicText.value),
      ),
      decoration: inputDecorationWithOutBorder(
        context,
        hintText: hintText ?? locale.value.searchClinicHere,
        filled: true,
        fillColor: context.cardColor,
        prefixIcon: commonLeadingWid(imgPath: Assets.iconsIcSearch, size: 18).paddingAll(14),
      ),
    );
  }
}
