import 'package:flutter/material.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';

import '../screens/home/model/dashboard_res_model.dart';
import '../utils/constants.dart';
import '../utils/price_widget.dart';

class AppoitmentDetailAppliedTaxListBottomSheet extends StatelessWidget {
  final List<TaxPercentage> taxes;
  final String? title;

  const AppoitmentDetailAppliedTaxListBottomSheet({super.key, required this.taxes, this.title});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(vertical: 30),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title ?? locale.value.appliedTaxes, style: boldTextStyle(size: 14)).paddingSymmetric(horizontal: 16),
          8.height,
          AnimatedListView(
            itemCount: taxes.length,
            padding: const EdgeInsets.all(8),
            shrinkWrap: true,
            listAnimationType: ListAnimationType.FadeIn,
            physics: const NeverScrollableScrollPhysics(),
            itemBuilder: (_, index) {
              final TaxPercentage data = taxes[index];

              return Padding(
                padding: const EdgeInsets.all(8.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    if (data.type == TaxType.PERCENT) Row(
                            children: [
                              Text(data.title.validate(), style: primaryTextStyle()),
                              4.width,
                              Text("(${data.value.validate()}%)", style: primaryTextStyle(color: context.primaryColor)),
                            ],
                          ).expand() else Text(data.title.validate(), style: primaryTextStyle()).expand(),
                    PriceWidget(price: data.amount.validate()),
                  ],
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
