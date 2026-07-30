import 'package:flutter/material.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../utils/colors.dart';
import '../../../utils/price_widget.dart';
import '../model/patient_wallet_history_res.dart';

class WalletHistoryCardWid extends StatelessWidget {
  final WalletHistoryElement walletHistoryElement;
  const WalletHistoryCardWid({super.key, required this.walletHistoryElement});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                walletHistoryElement.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: boldTextStyle(size: 16),
              ).flexible(),
              Text(
                walletHistoryElement.transactionType,
                style: secondaryTextStyle(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              PriceWidget(
                price: walletHistoryElement.creditDebitAmount,
                color: walletHistoryElement.transactionType.toLowerCase().contains("debit") ? cancelStatusColor : completedStatusColor,
                size: 14,
              ).flexible(),
              Text(
                "${walletHistoryElement.date.dateInDMMMMyyyyFormat} ${walletHistoryElement.date.timeInHHmmAmPmFormat}",
                style: secondaryTextStyle(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ],
      ),
    );
  }
}
