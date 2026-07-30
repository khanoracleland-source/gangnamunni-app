import 'package:flutter/material.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/screens/bed/model/bed_history_model.dart';
import 'package:kivicare_patient/utils/price_widget.dart';
import 'package:nb_utils/nb_utils.dart';

class BedHistoryComponent extends StatelessWidget {
  final BedHistoryData bedHistoryData;

  const BedHistoryComponent({super.key, required this.bedHistoryData});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: boxDecorationDefault(
        color: context.cardColor,
        borderRadius: radius(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(locale.value.bedDetails, style: boldTextStyle(size: 18)),
          12.height,
          Divider(),

          /// Bed Details
          _buildInfoRow(label: locale.value.bedType, value: bedHistoryData.bedTypeName),
          _buildInfoRow(label: '${locale.value.bedNumber}:', value: bedHistoryData.bedMasterName),
          _buildInfoRow(label: '${locale.value.perDayCharge}:', value: bedHistoryData.perBedCharge.validate().toStringAsFixed(2), amount: bedHistoryData.perBedCharge),
          _buildInfoRow(label: '${locale.value.totalCharges}:', value: bedHistoryData.charge.validate().toStringAsFixed(2), amount: bedHistoryData.charge),
          Divider(height: 24),

          /// Date Details
          _buildInfoRow(label: '${locale.value.assignDate}:', value: bedHistoryData.assignDate),
          _buildInfoRow(label: '${locale.value.dischargeDate}:', value: bedHistoryData.dischargeDate),
        ],
      ),
    );
  }

  /// Helper for key-value UI rows
  Widget _buildInfoRow({String label = '', String value = '', num amount = 0}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: boldTextStyle(size: 14)),
          amount == 0
              ? Text(
                  value.validate(),
                  style: secondaryTextStyle(color: textSecondaryColor),
                )
              : PriceWidget(
                  price: amount,
                  size: 10,
                  color: textSecondaryColor,
                ),
        ],
      ),
    );
  }
}
