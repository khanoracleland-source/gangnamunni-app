import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/colors.dart';
import '../../utils/common_base.dart';
import 'doctor_detail_controller.dart';

class SearchDoctorServiceWidget extends StatelessWidget {
  final String? hintText;
  final Function(String)? onFieldSubmitted;
  final Function()? onTap;
  final Function()? onClearButton;
  final DoctorDetailController doctorDetailController;

  const SearchDoctorServiceWidget({
    super.key,
    this.hintText,
    this.onTap,
    this.onFieldSubmitted,
    this.onClearButton,
    required this.doctorDetailController,
  });

  @override
  Widget build(BuildContext context) {
    return AppTextField(
      controller: doctorDetailController.searchCont,
      textFieldType: TextFieldType.OTHER,
      textInputAction: TextInputAction.done,
      textStyle: primaryTextStyle(decorationColor: appColorPrimary),
      onTap: onTap,
      onFieldSubmitted: onFieldSubmitted,
      onChanged: (p0) {
        doctorDetailController.isSearchText(doctorDetailController.searchCont.text.trim().isNotEmpty);
        doctorDetailController.searchStream.add(p0);
      },
      suffix: Obx(
        () => appCloseIconButton(
          context,
          onPressed: () {
            if (onClearButton != null) {
              onClearButton!.call();
            }
            hideKeyboard(context);
            doctorDetailController.searchCont.clear();
            doctorDetailController.isSearchText(doctorDetailController.searchCont.text.trim().isNotEmpty);
            doctorDetailController.servicesPage(1);
            doctorDetailController.getServiceList();
          },
          size: 11,
        ).visible(doctorDetailController.isSearchText.value),
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
