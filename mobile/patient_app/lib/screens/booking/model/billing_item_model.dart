import 'package:kivicare_patient/screens/home/model/dashboard_res_model.dart';

import '../../service/model/service_list_model.dart';

class BillingItem {
  int id;
  int billingId;
  int itemId;
  String itemName;
  num discountValue;
  String discountType;
  int quantity;
  num serviceAmount;
  num totalAmount;
  String createdAt;
  String updatedAt;
  ServiceElement? serviceDetail;

  List<TaxPercentage> inclusiveTaxList;
  num totalInclusiveTax;

  bool get isIncludesInclusiveTaxAvailable => totalInclusiveTax > 0 && inclusiveTaxList.isNotEmpty;

  BillingItem({
    this.id = -1,
    this.billingId = -1,
    this.itemId = -1,
    this.itemName = "",
    this.discountValue = -1,
    this.discountType = "",
    this.quantity = -1,
    this.serviceAmount = 0,
    this.totalAmount = 0,
    this.createdAt = "",
    this.updatedAt = "",
    this.serviceDetail,
    this.totalInclusiveTax = 0,
    this.inclusiveTaxList = const <TaxPercentage>[],
  });

  factory BillingItem.fromJson(Map<String, dynamic> json) {
    return BillingItem(
      id: json['id'] is int ? json['id'] : -1,
      billingId: json['billing_id'] is int ? json['billing_id'] : -1,
      itemId: json['item_id'] is int ? json['item_id'] : -1,
      itemName: json['item_name'] is String ? json['item_name'] : "",
      discountValue: json['discount_value'] is num ? json['discount_value'] : 0,
      discountType: json['discount_type'] is String ? json['discount_type'] : "",
      quantity: json['quantity'] is int ? json['quantity'] : -1,
      serviceAmount: json['service_amount'] is num ? json['service_amount'] : 0,
      totalAmount: json['total_amount'] is num ? json['total_amount'] : 0,
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
      serviceDetail: json['clinic_services'] is Map ? ServiceElement.fromJson(json['clinic_services']) : ServiceElement(),
      totalInclusiveTax: json['total_inclusive_tax'] is num ? json['total_inclusive_tax'] : 0,
      inclusiveTaxList: json['inclusive_tax_data'] is List ? List<TaxPercentage>.from(json['inclusive_tax_data'].map((x) => TaxPercentage.fromJson(x))) : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (!id.isNegative) 'id': id,
      'billing_id': billingId,
      'item_id': itemId,
      'quantity': quantity,
      'service_amount': serviceAmount,
      'total_amount': totalAmount,
      'total_inclusive_tax': totalInclusiveTax,
    };
  }
}

// class BillingItem {
//   int id;
//   String name;
//   num price;
//   int qty;
//   num serviceTotalAmount;

//   BillingItem({
//     this.id = -1,
//     this.name = "",
//     this.price = 0,
//     this.qty = 0,
//     this.serviceTotalAmount = 0,
//   });

//   factory BillingItem.fromJson(Map<String, dynamic> json) {
//     return BillingItem(
//       id: json['id'] is int ? json['id'] : -1,
//       name: json['name'] is String ? json['name'] : "",
//       price: json['price'] is num ? json['price'] : 0,
//       qty: json['qty'] is int ? json['qty'] :0,
//       serviceTotalAmount: json['service_total_amount'] is num ? json['service_total_amount'] : 0,
//     );
//   }

//   Map<String, dynamic> toJson() {
//     return {
//       'id': id,
//       'name': name,
//       'price': price,
//       'qty': qty,
//       'service_total_amount': serviceTotalAmount,
//     };
//   }
// }
