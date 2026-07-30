import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../main.dart';
import '../../../utils/app_common.dart';
import '../../../utils/colors.dart';
import '../model/encounter_detail_model.dart';

class MedicineCardWidget extends StatelessWidget {
  const MedicineCardWidget({
    super.key,
    required this.prescriptionsData,
  });

  final Prescriptions prescriptionsData;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      margin: const EdgeInsets.only(bottom: 12),
      decoration: boxDecorationDefault(
        color: context.cardColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.start,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                prescriptionsData.medicine.name,
                style: boldTextStyle(size: 14, color: isDarkMode.value ? null : darkGrayTextColor),
              ).expand(),
            ],
          ),
          16.height,
          Divider(color: isDarkMode.value ? borderColor.withValues(alpha: 0.2) : context.dividerColor.withValues(alpha: 0.2), height: 1),
          16.height,
          Row(
            mainAxisAlignment: MainAxisAlignment.start,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: '${locale.value.dosage}: ',
                      style: primaryTextStyle(color: dividerColor, size: 12),
                    ),
                    TextSpan(
                      text: prescriptionsData.medicine.dosage,
                      style: secondaryTextStyle(size: 12, weight: FontWeight.w600, color: isDarkMode.value ? null : darkGrayTextColor),
                    ),
                  ],
                ),
              ).expand(),
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: '${locale.value.form}: ',
                      style: primaryTextStyle(color: dividerColor, size: 12),
                    ),
                    TextSpan(
                      text: prescriptionsData.medicine.form.name,
                      style: secondaryTextStyle(size: 12, weight: FontWeight.w600, color: isDarkMode.value ? null : darkGrayTextColor),
                    ),
                  ],
                ),
              ).expand()
            ],
          ),
          8.height,
          Row(
            mainAxisAlignment: MainAxisAlignment.start,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: '${locale.value.quantity}: ',
                      style: primaryTextStyle(color: dividerColor, size: 12),
                    ),
                    TextSpan(
                      text: prescriptionsData.quantity.toString(),
                      style: secondaryTextStyle(size: 12, weight: FontWeight.w600, color: isDarkMode.value ? null : darkGrayTextColor),
                    ),
                  ],
                ),
              ).expand(),
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: '${locale.value.days}: ',
                      style: primaryTextStyle(color: dividerColor, size: 12),
                    ),
                    TextSpan(
                      text: prescriptionsData.duration,
                      style: secondaryTextStyle(size: 12, weight: FontWeight.w600, color: isDarkMode.value ? null : darkGrayTextColor),
                    ),
                  ],
                ),
              ).expand()
            ],
          ),
          8.height,
          Row(
            mainAxisAlignment: MainAxisAlignment.start,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: '${locale.value.frequency}: ',
                      style: primaryTextStyle(color: dividerColor, size: 12),
                    ),
                    TextSpan(
                      text: prescriptionsData.frequency,
                      style: secondaryTextStyle(size: 12, weight: FontWeight.w600, color: isDarkMode.value ? null : darkGrayTextColor),
                    ),
                  ],
                ),
              ).expand(),
            ],
          ),
          8.height,
          Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: '${locale.value.instruction}: ',
                  style: primaryTextStyle(color: dividerColor, size: 12),
                ),
                TextSpan(
                  text: prescriptionsData.instruction,
                  style: secondaryTextStyle(size: 12, weight: FontWeight.w600, color: isDarkMode.value ? null : darkGrayTextColor),
                ),
              ],
            ),
          )
        ],
      ),
    );
  }
}
