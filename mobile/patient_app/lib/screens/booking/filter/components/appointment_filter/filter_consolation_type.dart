import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../filter_controller.dart';

class FilterConsolationType extends StatelessWidget {
  final FilterController filterCont;

  const FilterConsolationType({super.key, required this.filterCont});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      return AnimatedWrap(
        children: List.generate(filterCont.consolationTypeList.length, (index) {
          final String consolationType = filterCont.consolationTypeList[index];
          return InkWell(
            onTap: () {
              filterCont.selectedConsolationType(consolationType);
            },
            child: Stack(
              children: [
                Container(
                  margin: const EdgeInsets.all(6),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: boxDecorationDefault(
                    color: context.cardColor,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        consolationType,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: primaryTextStyle(),
                      ).paddingSymmetric(horizontal: 8),
                    ],
                  ),
                ),
                if (filterCont.selectedConsolationType.value == consolationType)
                  Positioned(
                    top: 0,
                    right: 0,
                    child: Container(
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.green,
                      ),
                      padding: const EdgeInsets.all(4),
                      child: const Icon(Icons.check, color: Colors.white, size: 14),
                    ),
                  ),
              ],
            ),
          );
        }),
      );
    });
  }
}
