import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/auth/model/common_model.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../api/core_apis.dart';
import '../../../main.dart';
import '../../../utils/colors.dart';
import '../../../utils/constants.dart';
import '../../booking/appointments_controller.dart';
import '../incident_detail_screen.dart';
import '../incident_management_controller.dart';
import '../model/incident_response_model.dart';

class IncidentManagementCard extends StatelessWidget {
  final VoidCallback? onUpdateBooking;
  final Incident incidentData;
  final IncidentManagement incidentController;

  IncidentManagementCard({
    super.key,
    required this.incidentData,
    this.onUpdateBooking,
    required this.incidentController,
  });

  final AppointmentsController appointmentsController = Get.find();

  bool get isClosed => incidentData.incidenceTypeName.toLowerCase().contains("close");

  bool get isRejected => incidentData.incidenceTypeName.toLowerCase().contains("reject");

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        hideKeyboard(context);
        Get.to(
          IncidentDetailScreen(
            incident: incidentData,
            incidentController: incidentController,
            isClosed: isClosed,
            isRejected: isRejected,
          ),
        );
      },
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: boxDecorationDefault(
          color: context.cardColor,
          shape: BoxShape.rectangle,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  incidentData.incidentDate.dateInDDMMYYYYFormat,
                  style: boldTextStyle(size: 12, color: secondaryTextColor),
                ),
                if (isClosed)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.green,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      locale.value.closed,
                      style: boldTextStyle(color: Colors.white, size: 12),
                    ),
                  )
                else if (isRejected)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.deepOrange,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      locale.value.rejected,
                      style: boldTextStyle(color: Colors.white, size: 12),
                    ),
                  )
                else
                  DropdownButtonHideUnderline(
                    child: Container(
                      decoration: BoxDecoration(
                        color: incidentData.incidenceTypeName.toLowerCase() == 'open' ? context.primaryColor : Colors.red,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: DropdownButton<String>(
                        elevation: 1,
                        dropdownColor: context.scaffoldBackgroundColor,
                        borderRadius: BorderRadius.circular(defaultRadius),
                        isDense: true,
                        icon: const Icon(Icons.arrow_drop_down, color: Colors.white),
                        iconSize: 20,
                        value: incidentStatuses
                            .firstWhere(
                              (e) => incidentData.incidenceTypeName.toLowerCase().contains(e.slug),
                              orElse: () => CMNModel(slug: incidentData.incidenceTypeName.toLowerCase()),
                            )
                            .slug,
                        onChanged: (String? newValue) async {
                          if (newValue != null) {
                            incidentController.isLoading(true);

                            // Map selection value to status
                            final incidentTypeMap = {
                              "open": 1,
                              "closed": 2,
                              "reject": 3,
                            };

                            final incidentType = incidentTypeMap[newValue.toLowerCase().trim()];

                            if (incidentType != null) {
                              final request = {
                                "incident_type": incidentType,
                              };
                              incidentController.isLoading(true);
                              await CoreServiceApis.updateIncidentStatus(incidentId: incidentData.id, request: request).then((res) {
                                toast(res.message);
                                incidentController.incidencePage(1);
                                incidentController.getIncidents();
                              }).catchError((e) {
                                toast(e.toString());
                              }).whenComplete(() {
                                incidentController.isLoading(false);
                              });
                            } else {
                              incidentController.isLoading(false);
                              toast("Invalid incident type selected");
                            }
                          }
                        },
                        items: incidentStatusesDropDown.map((status) {
                          return DropdownMenuItem<String>(
                            value: status.slug,
                            child: Text(
                              status.name,
                              style: primaryTextStyle(size: 13),
                              overflow: TextOverflow.ellipsis,
                            ),
                          );
                        }).toList(),
                        selectedItemBuilder: (BuildContext context) {
                          return incidentStatuses.map((status) {
                            return Text(
                              status.name,
                              style: boldTextStyle(color: Colors.white, size: 12),
                            );
                          }).toList();
                        },
                      ),
                    ),
                  ),
              ],
            ),
            16.height,
            Text(
              incidentData.title,
              style: boldTextStyle(size: 18),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            8.height,
            Text(
              incidentData.description,
              style: primaryTextStyle(size: 14, color: secondaryTextColor),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            12.height,
            GestureDetector(
              onTap: () => {
                Get.to(
                  () => IncidentDetailScreen(
                    incident: incidentData,
                    incidentController: incidentController,
                    isClosed: isClosed,
                    isRejected: isRejected,
                  ),
                ),
              },
              child: Text(
                locale.value.viewDetail,
                style: boldTextStyle(color: Colors.red, size: 14),
              ),
            ),
            if (isClosed) ...[
              16.height,
              commonDivider,
              16.height,
              Row(
                children: [
                  Text("${locale.value.closedOn}: ", style: secondaryTextStyle()),
                  8.width,
                  Text(
                    incidentData.updatedAt.dateInDDMMYYYYFormat,
                    style: secondaryTextStyle(),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
