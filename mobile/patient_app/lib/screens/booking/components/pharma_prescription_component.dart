import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../main.dart';
import '../../../utils/common_base.dart';
import '../../../utils/price_widget.dart';
import '../../../utils/view_all_label_component.dart';
import '../encounter_detail_controller.dart';
import '../model/encounter_detail_model.dart';
import 'pharma_view_all_meds_screen.dart';

class PharmaPrescriptionComponent extends StatelessWidget {
  const PharmaPrescriptionComponent({
    super.key,
    required this.encounterDetailCont,
  });

  final EncounterDetailController encounterDetailCont;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        16.height,
        ViewAllLabel(
          label: locale.value.medicines,
          isShowAll: true,
          onTap: () {
            Get.to(() => PharmaViewAllMedsScreen(encounterDetailCont: encounterDetailCont));
          },
        ).paddingOnly(left: 16, right: 8),
        AnimatedWrap(
          runSpacing: 8,
          itemCount: encounterDetailCont.encounterDetail.value.prescriptions.take(2).length,
          itemBuilder: (ctx, index) {
            final Prescriptions prescriptionsData = encounterDetailCont.encounterDetail.value.prescriptions[index];

            return MedicineCardWidget(prescriptionsData: prescriptionsData).paddingSymmetric(horizontal: 16);
          },
        ),
        ViewAllLabel(
          label: locale.value.prescription,
          isShowAll: false,
          onTap: () {
            Get.to(() => PharmaViewAllMedsScreen(encounterDetailCont: encounterDetailCont));
          },
        ).paddingOnly(left: 16, right: 8),
        Container(
          padding: const EdgeInsets.all(16),
          margin: const EdgeInsets.only(bottom: 12),
          decoration: boxDecorationDefault(
            color: context.cardColor,
            borderRadius: BorderRadius.circular(6),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    locale.value.total,
                    style: secondaryTextStyle(size: 14),
                  ),
                  PriceWidget(
                    price: encounterDetailCont.encounterDetail.value.prescriptionAmount,
                    size: 14,
                    isSemiBoldText: true,
                  ),
                ],
              ),
              8.height,
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    locale.value.exclusiveTax,
                    style: secondaryTextStyle(size: 14),
                  ),
                  PriceWidget(
                    price: encounterDetailCont.encounterDetail.value.prescriptionExclusiveTax,
                    size: 14,
                    isSemiBoldText: true,
                  ),
                ],
              ),
              8.height,
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    locale.value.grandTotal,
                    style: secondaryTextStyle(size: 14),
                  ),
                  PriceWidget(
                    price: encounterDetailCont.encounterDetail.value.prescriptionExclusiveTax + encounterDetailCont.encounterDetail.value.prescriptionAmount,
                    size: 14,
                    isSemiBoldText: true,
                  ),
                ],
              ),
              8.height,
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    locale.value.prescriptionStatus,
                    style: secondaryTextStyle(size: 14),
                  ),
                  Text(
                    getPrescriptionStatus(status: encounterDetailCont.encounterDetail.value.prescriptionStatus),
                    overflow: TextOverflow.ellipsis,
                    style: boldTextStyle(size: 14, color: getPrescriptionStatusColor(status: encounterDetailCont.encounterDetail.value.prescriptionStatus)),
                  ),
                ],
              ),
              8.height,
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    locale.value.prescriptionPaymentStatus,
                    style: secondaryTextStyle(size: 14),
                    overflow: TextOverflow.ellipsis,
                    maxLines: 2,
                  ),
                  Text(
                    getPrescriptionPaymentStatus(status: encounterDetailCont.encounterDetail.value.prescriptionPaymentStatus),
                    overflow: TextOverflow.ellipsis,
                    style: boldTextStyle(size: 14, color: getPrescriptionPaymentStatusColor(paymentStatus: encounterDetailCont.encounterDetail.value.prescriptionPaymentStatus)),
                  ),
                ],
              ),
            ],
          ),
        ).paddingSymmetric(horizontal: 16),
      ],
    );
  }
}
