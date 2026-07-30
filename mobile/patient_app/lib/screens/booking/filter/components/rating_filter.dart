import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/colors.dart';
import '../filter_controller.dart';

class FilterRatingComponent extends StatelessWidget {
  final FilterController filterCont;

  const FilterRatingComponent({super.key, required this.filterCont});

  @override
  Widget build(BuildContext context) {
    return Obx(
          () => Column(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("Rating", style: boldTextStyle()).paddingAll(16),
              Obx(() => RangeSlider(
                  min: 1,
                  max: 5,
                  divisions: 4,
                  labels: RangeLabels(filterCont.rangeRatingValues.value.start.toInt().toString(), filterCont.rangeRatingValues.value.end.toInt().toString()),
                  values: filterCont.rangeRatingValues.value,
                  activeColor: appColorPrimary,
                  overlayColor: const WidgetStatePropertyAll(appColorPrimary),
                  onChanged: (values) {
                    filterCont.rangeRatingValues(values);
                    filterCont.setMaxRating(values.end);
                    filterCont.setMinRating(values.start);
                  },
                ),
              ),
              16.height,
              Marquee(
                child: Row(
                  children: [
                    Text("[ ", style: primaryTextStyle()),
                    Text(filterCont.rangeRatingValues.value.start.toString(), style: primaryTextStyle()),
                    Text(" - ", style: primaryTextStyle()),
                    Text(filterCont.rangeRatingValues.value.end.toString(), style: primaryTextStyle()),
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
