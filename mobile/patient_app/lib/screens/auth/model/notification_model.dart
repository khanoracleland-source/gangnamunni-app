class NotificationRes {
  List<NotificationData> notificationData;
  int allUnreadCount;
  String message;
  bool status;

  NotificationRes({
    this.notificationData = const <NotificationData>[],
    this.allUnreadCount = -1,
    this.message = "",
    this.status = false,
  });

  factory NotificationRes.fromJson(Map<String, dynamic> json) {
    return NotificationRes(
      notificationData: json['notification_data'] is List ? List<NotificationData>.from(json['notification_data'].map((x) => NotificationData.fromJson(x))) : [],
      allUnreadCount: json['all_unread_count'] is int ? json['all_unread_count'] : -1,
      message: json['message'] is String ? json['message'] : "",
      status: json['status'] is bool ? json['status'] : false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'notification_data': notificationData.map((e) => e.toJson()).toList(),
      'all_unread_count': allUnreadCount,
      'message': message,
      'status': status,
    };
  }
}

class NotificationData {
  String id;
  String type;
  String notifiableType;
  int notifiableId;
  NotificationModel data;
  String readAt;
  String createdAt;
  String updatedAt;

  NotificationData({
    this.id = "",
    this.type = "",
    this.notifiableType = "",
    this.notifiableId = -1,
    required this.data,
    this.readAt = "",
    this.createdAt = "",
    this.updatedAt = "",
  });

  factory NotificationData.fromJson(Map<String, dynamic> json) {
    return NotificationData(
      id: json['id'] is String ? json['id'] : "",
      type: json['type'] is String ? json['type'] : "",
      notifiableType: json['notifiable_type'] is String ? json['notifiable_type'] : "",
      notifiableId: json['notifiable_id'] is int ? json['notifiable_id'] : -1,
      data: json['data'] is Map ? NotificationModel.fromJson(json['data']) : NotificationModel.fromJson({}),
      readAt: json['read_at'] is String ? json['read_at'] : "",
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      updatedAt: json['updated_at'] is String ? json['updated_at'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'notifiable_type': notifiableType,
      'notifiable_id': notifiableId,
      'data': data.toJson(),
      'read_at': readAt,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}

class NotificationModel {
  String subject;
  NotificationDetail notificationDetail;

  NotificationModel({
    this.subject = "",
    required this.notificationDetail,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      subject: json['subject'] is String ? json['subject'] : "",
      notificationDetail: json['data'] is Map ? NotificationDetail.fromJson(json['data']) : NotificationDetail.fromJson({}),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'subject': subject,
      'data': notificationDetail.toJson(),
    };
  }
}

class NotificationDetail {
  String notificationType;
  String loggedInUserFullname;
  String loggedInUserRole;
  String companyName;
  String companyContactInfo;
  String type;
  int id;
  dynamic description;
  int userId;
  String userName;
  int doctorId;
  String doctorName;
  String appointmentDate;
  String appointmentTime;
  int appointmentduration;
  String appointmentServicesNames;
  String siteUrl;
  String notificationGroup;
  String notificationMsg;
  Incidence incidence;
  String? message;

  NotificationDetail({
    this.notificationType = "",
    this.loggedInUserFullname = "",
    this.loggedInUserRole = "",
    this.companyName = "",
    this.companyContactInfo = "",
    this.type = "",
    this.id = -1,
    this.description,
    this.userId = -1,
    this.userName = "",
    this.doctorId = -1,
    this.doctorName = "",
    this.appointmentDate = "",
    this.appointmentTime = "",
    this.appointmentduration = -1,
    this.appointmentServicesNames = "",
    this.siteUrl = "",
    this.notificationGroup = "",
    this.notificationMsg = "",
    required this.incidence,
    this.message,
  });

  factory NotificationDetail.fromJson(Map<String, dynamic> json) {
    return NotificationDetail(
      notificationType: json['notification_type'] is String ? json['notification_type'] : "",
      loggedInUserFullname: json['logged_in_user_fullname'] is String ? json['logged_in_user_fullname'] : "",
      loggedInUserRole: json['logged_in_user_role'] is String ? json['logged_in_user_role'] : "",
      companyName: json['company_name'] is String ? json['company_name'] : "",
      companyContactInfo: json['company_contact_info'] is String ? json['company_contact_info'] : "",
      type: json['type'] is String ? json['type'] : "",
      id: json['id'] is int ? json['id'] : -1,
      description: json['description'],
      userId: json['user_id'] is int ? json['user_id'] : -1,
      userName: json['user_name'] is String ? json['user_name'] : "",
      doctorId: json['doctor_id'] is int ? json['doctor_id'] : -1,
      doctorName: json['doctor_name'] is String ? json['doctor_name'] : "",
      appointmentDate: json['appointment_date'] is String ? json['appointment_date'] : "",
      appointmentTime: json['appointment_time'] is String ? json['appointment_time'] : "",
      appointmentduration: json['appointmentduration'] is int ? json['appointmentduration'] : -1,
      appointmentServicesNames: json['appointment_services_names'] is String ? json['appointment_services_names'] : "",
      siteUrl: json['site_url'] is String ? json['site_url'] : "",
      notificationGroup: json['notification_group'] is String ? json['notification_group'] : "",
      notificationMsg: json['notification_msg'] is String
          ? json['notification_msg']
          : json['notification_message'] is String
              ? json['notification_message']
              : "",
      incidence: json['incidence'] is Map ? Incidence.fromJson(json['incidence']) : Incidence(),
      message: json['message'] is String ? json['message'] : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'notification_type': notificationType,
      'logged_in_user_fullname': loggedInUserFullname,
      'logged_in_user_role': loggedInUserRole,
      'company_name': companyName,
      'company_contact_info': companyContactInfo,
      'type': type,
      'id': id,
      'description': description,
      'user_id': userId,
      'user_name': userName,
      'doctor_id': doctorId,
      'doctor_name': doctorName,
      'appointment_date': appointmentDate,
      'appointment_time': appointmentTime,
      'appointmentduration': appointmentduration,
      'appointment_services_names': appointmentServicesNames,
      'site_url': siteUrl,
      'notification_group': notificationGroup,
      'notification_msg': notificationMsg,
      'incidence': incidence.toJson(),
    };
  }
}

class Incidence {
  int id;
  int userId;
  String title;
  String description;
  String phone;
  String email;
  String reply;
  String userName;

  Incidence({
    this.id = -1,
    this.userId = -1,
    this.title = "",
    this.description = "",
    this.phone = "",
    this.email = "",
    this.reply = "",
    this.userName = "",
  });

  factory Incidence.fromJson(Map<String, dynamic> json) {
    return Incidence(
      id: json['id'] is int ? json['id'] : -1,
      userId: json['user_id'] is int ? json['user_id'] : -1,
      title: json['title'] is String ? json['title'] : "",
      description: json['description'] is String ? json['description'] : "",
      phone: json['phone'] is String ? json['phone'] : "",
      email: json['email'] is String ? json['email'] : "",
      reply: json['reply'] is String ? json['reply'] : "",
      userName: json['user_name'] is String ? json['user_name'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'title': title,
      'description': description,
      'phone': phone,
      'email': email,
      'reply': reply,
      'user_name': userName,
    };
  }
}
