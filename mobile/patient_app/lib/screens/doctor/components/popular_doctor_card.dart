import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../doctor_detail_screen.dart';
import '../model/doctor_list_res.dart';

class PopularDoctorCard extends StatelessWidget {
  const PopularDoctorCard({super.key, required this.doctorElement, this.isFromClinicDetail = false});

  final Doctor doctorElement;
  final bool isFromClinicDetail;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        Get.to(() => DoctorDetailScreen(), arguments: doctorElement);
      },
      child: Container(
        decoration: boxDecorationDefault(color: context.cardColor, borderRadius: radius(8)),
        width: Get.width / 2 - 24,
        child: Column(
          children: [
            Column(
              children: [
                CircleAvatar(
                  radius: 36,
                  backgroundImage: NetworkImage(doctorElement.profileImage), // Replace with your image asset
                ),
                10.height,

                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Text(
                        doctorElement.fullName,
                        style: boldTextStyle(size: 16),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis, // avoids overflow
                        textAlign: TextAlign.center,     // keep it centered
                      ),
                    ),
                    4.width,
                    const Icon(Icons.circle, color: Colors.green, size: 12),
                  ],
                ),

                4.height,
                Text(
                  doctorElement.expert,
                  style: primaryTextStyle(
                    color: Colors.grey,
                    size: 12,
                  ),
                ),
                10.height,

                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    ...List.generate(5, (index) {
                      final double rating = doctorElement.averageRating.toDouble();
                      if (index < rating.floor()) {
                        // Full star
                        return const Icon(Icons.star, color: Colors.amber, size: 20);
                      } else if (index < rating && rating - index >= 0.5) {
                        // Half star
                        return const Icon(Icons.star_half, color: Colors.amber, size: 20);
                      } else {
                        // Empty star
                        return Icon(Icons.star_border, color: Colors.grey[300], size: 20);
                      }
                    }),
                  ],
                ),
                10.height,
                Text(
                  '${doctorElement.totalAppointmemt} Patient Served',
                  style: primaryTextStyle(
                    color: Colors.blue,
                  ),
                ),
              ],
            ).paddingSymmetric(horizontal: 12, vertical: 12),
          ],
        ),
      ),
    );
  }
}
