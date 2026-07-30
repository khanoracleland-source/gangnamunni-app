import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../filter_controller.dart';

class FilterServiceTypeComponent extends StatelessWidget {
  final FilterController filterCont;

  const FilterServiceTypeComponent({super.key, required this.filterCont});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Obx(
          () => AnimatedWrap(
            children: List.generate(filterCont.serviceTypeList.length, (index) {
              final statusData = filterCont.serviceTypeList[index];
              return InkWell(
                onTap: () {
                  filterCont.selectedServiceType(statusData['value']);
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  margin: const EdgeInsets.all(4),
                  decoration: boxDecorationDefault(
                    borderRadius: BorderRadius.circular(6),
                    color: filterCont.selectedServiceType == statusData['value'] ? const Color.fromRGBO(86, 112, 204, 1) : context.cardColor,
                  ),
                  child: Text(
                    statusData['title'].toString(),
                    style: primaryTextStyle(
                      size: 12,
                      color: filterCont.selectedServiceType == statusData['value'] ? white : null,
                    ),
                  ),
                ),
              );
            }),
          ),
        ).paddingAll(16).expand(),
      ],
    );
  }
}
