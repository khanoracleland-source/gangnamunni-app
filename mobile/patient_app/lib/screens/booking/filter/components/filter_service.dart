import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/service/model/service_list_model.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../../../components/loader_widget.dart';
import '../../../../../main.dart';
import '../../../../../utils/empty_error_state_widget.dart';
import '../filter_controller.dart';

class FilterServiceComponent extends StatelessWidget {
  final FilterController filterCont;

  const FilterServiceComponent({super.key, required this.filterCont});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Obx(
          () => SnapHelperWidget(
            future: filterCont.serviceListFuture.value,
            errorBuilder: (error) {
              return AnimatedScrollView(
                padding: const EdgeInsets.all(16),
                children: [
                  NoDataWidget(
                    title: error,
                    retryText: locale.value.reload,
                    imageWidget: const ErrorStateWidget(),
                    onRetry: () {
                      filterCont.servicePage(1);
                      filterCont.getServicesList();
                    },
                  ),
                ],
              ).paddingSymmetric(horizontal: 32);
            },
            loadingWidget: const LoaderWidget(),
            onSuccess: (data) {
              if (data.isEmpty) {
                return NoDataWidget(
                  title: locale.value.noServicesFoundAtAMoment,
                  retryText: locale.value.reload,
                  onRetry: () {
                    filterCont.servicePage(1);
                    filterCont.getServicesList();
                  },
                );
              } else {
                return Obx(
                  () => Stack(
                    children: [
                      AnimatedScrollView(
                        children: [
                          AnimatedWrap(
                            children: List.generate(filterCont.serviceList.length, (index) {
                              final ServiceElement service = filterCont.serviceList[index];
                              return InkWell(
                                onTap: () {
                                  // filterCont.selectedServiceId(service);
                                  filterCont.selectedServiceId(service.id);
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
                                            service.name,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: primaryTextStyle(
                                              size: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    if (filterCont.selectedServiceId.value == service.id)
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
                          ),
                        ],
                        onNextPage: () async {
                          if (!filterCont.isServiceLoading.value) {
                            filterCont.servicePage(filterCont.servicePage.value + 1);
                            filterCont.getClinicsList();
                          }
                        },
                        onSwipeRefresh: () async {
                          filterCont.clinicPage(1);
                          return filterCont.getServicesList(showLoader: false);
                        },
                      ),
                      if (filterCont.isServiceLoading.isTrue) const LoaderWidget(),
                    ],
                  ),
                );
              }
            },
          ),
        ).paddingOnly(bottom: 16, left: 16, right: 16).expand(),
      ],
    );
  }
}
