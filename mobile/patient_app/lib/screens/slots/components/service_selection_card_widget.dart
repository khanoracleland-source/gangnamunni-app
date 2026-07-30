import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/constants.dart';
import '../../../utils/price_widget.dart';
import '../../service/model/service_list_model.dart';

class ServiceSelectionCardWidget extends StatelessWidget {
  final ServiceElement serviceElement;
  final void Function()? onTap;
  final bool isSelected;

  const ServiceSelectionCardWidget({super.key, required this.serviceElement, this.onTap, this.isSelected = false});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: boxDecorationDefault(color: context.cardColor),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                Container(
                  width: Get.height * 0.12,
                  height: Get.height * 0.12,
                  decoration: boxDecorationDefault(),
                  child: CachedImageWidget(url: serviceElement.serviceImage, fit: BoxFit.cover, radius: 6),
                ),
                if (serviceElement.isVideoConsultancy)
                  Positioned(
                    bottom: 0,
                    right: 0,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: boxDecorationDefault(
                        color: completedStatusColor,
                        borderRadius: BorderRadius.only(topLeft: radiusCircular(), bottomRight: radiusCircular(6)),
                        border: Border(left: BorderSide(color: context.cardColor, width: 6), top: BorderSide(color: context.cardColor, width: 6)),
                      ),
                      child: Row(
                        children: [
                          CachedImageWidget(
                            url: Assets.imagesVideoCamera,
                            fit: BoxFit.fitHeight,
                            height: 8,
                            color: context.cardColor,
                          ),
                          6.width,
                          Text(locale.value.video, style: boldTextStyle(size: 12, color: context.cardColor)),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
            16.width,
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  serviceElement.name,
                  overflow: TextOverflow.ellipsis,
                  style: boldTextStyle(size: 16),
                ),
                12.height,
                Marquee(
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (serviceElement.isDiscount) PriceWidget(price: serviceElement.payableAmount, size: 18).paddingRight(6),
                      PriceWidget(
                        price: serviceElement.assignDoctor.isNotEmpty ? serviceElement.assignDoctor.firstWhere((e) => e.serviceId == serviceElement.systemServiceId).priceDetail.servicePrice + serviceElement.totalInclusiveTax : serviceElement.charges,
                        isLineThroughEnabled: serviceElement.isDiscount ? true : false,
                        size: serviceElement.isDiscount ? 14 : 18,
                        color: serviceElement.isDiscount ? textSecondaryColorGlobal : appColorPrimary,
                      ),
                      if (serviceElement.isDiscount)
                        if (serviceElement.discountType == TaxType.PERCENTAGE)
                          Text(
                            '${serviceElement.discountValue}% ${locale.value.off}',
                            style: boldTextStyle(color: greenColor, size: 14),
                          ).paddingLeft(8)
                        else if (serviceElement.discountType == TaxType.FIXED)
                          PriceWidget(
                            price: serviceElement.discountValue,
                            color: greenColor,
                            size: 14,
                            isDiscountedPrice: true,
                          ).paddingLeft(6),
                    ],
                  ),
                ),
              ],
            ).expand(),
          ],
        ),
      ),
    );
  }
}
