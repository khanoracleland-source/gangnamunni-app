import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/colors.dart';

import '../../../../../utils/price_widget.dart';
import '../../filter_controller.dart';

class FilterPriceComponent extends StatelessWidget {
  final FilterController filterCont = Get.put(FilterController());

  FilterPriceComponent({super.key});

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Column(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(locale.value.priceRange, style: boldTextStyle()).paddingAll(16),
              Obx(
                () => RangeSlider(
                  min: 1,
                  max: 5000,
                  divisions: 5000 ~/ 10,
                  labels: RangeLabels(filterCont.rangeValues.value.start.toInt().toString(), filterCont.rangeValues.value.end.toInt().toString()),
                  values: filterCont.rangeValues.value,
                  activeColor: appColorPrimary,
                  overlayColor: const WidgetStatePropertyAll(appColorPrimary),
                  onChanged: (values) {
                    filterCont.rangeValues(values);
                    filterCont.setMaxPrice(values.end);
                    filterCont.setMinPrice(values.start);
                  },
                ),
              ),
              16.height,
              Marquee(
                child: Row(
                  children: [
                    Text("[ ", style: primaryTextStyle()),
                    PriceWidget(
                      price: filterCont.rangeValues.value.start.toInt(),
                      isBoldText: false,
                      color: textPrimaryColorGlobal,
                    ),
                    Text(" - ", style: primaryTextStyle()),
                    PriceWidget(
                      price: filterCont.rangeValues.value.end.toInt(),
                      isBoldText: false,
                      color: textPrimaryColorGlobal,
                    ),
                    Text(" ]", style: primaryTextStyle()),
                  ],
                ),
              ).center(),
            ],
          ).expand(),
        ],
      ),
    );
  }
}
