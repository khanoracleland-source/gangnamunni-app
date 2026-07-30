
import '../network/network_utils.dart';
import '../screens/home/model/dashboard_res_model.dart';
import '../utils/api_end_points.dart';
import '../utils/app_common.dart';

class HomeServiceApis {
  static Future<DashboardRes> getDashboard({String? latitude, String? longitude}) async {
    if (isLoggedIn.value) {
      return DashboardRes.fromJson(
        await handleResponse(
          await buildHttpResponse(
            "${APIEndPoints.getDashboard}?user_id=${loginUserData.value.id}",
          ),
        ),
      );
    } else {
      return DashboardRes.fromJson(
        await handleResponse(
          await buildHttpResponse(APIEndPoints.getDashboard),
        ),
      );
    }
  }
}
