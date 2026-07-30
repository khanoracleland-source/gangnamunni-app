import 'package:get/get_rx/src/rx_types/rx_types.dart';

class IncidentResponse {
  bool status;
  IncidentData? data;

  IncidentResponse({
    this.status = false,
    this.data,
  });

  factory IncidentResponse.fromJson(Map<String, dynamic> json) {
    return IncidentResponse(
      status: json["status"] is bool ? json["status"] : false,
      data: json["data"] is Map<String, dynamic> ? IncidentData.fromJson(json["data"]) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "status": status,
      "data": data?.toJson(),
    };
  }
}

class IncidentData {
  int currentPage;
  List<Incident> incidents;
  String firstPageUrl;
  int from;
  int lastPage;
  String lastPageUrl;
  List<Link> links;
  String? nextPageUrl;
  String path;
  int perPage;
  String? prevPageUrl;
  int to;
  int total;

  IncidentData({
    this.currentPage = 1,
    this.incidents = const [],
    this.firstPageUrl = "",
    this.from = 0,
    this.lastPage = 1,
    this.lastPageUrl = "",
    this.links = const [],
    this.nextPageUrl,
    this.path = "",
    this.perPage = 25,
    this.prevPageUrl,
    this.to = 0,
    this.total = 0,
  });

  factory IncidentData.fromJson(Map<String, dynamic> json) {
    return IncidentData(
      currentPage: json["current_page"] ?? 1,
      incidents: json["data"] is List ? List<Incident>.from(json["data"].map((x) => Incident.fromJson(x))) : [],
      firstPageUrl: json["first_page_url"] ?? "",
      from: json["from"] ?? 0,
      lastPage: json["last_page"] ?? 1,
      lastPageUrl: json["last_page_url"] ?? "",
      links: json["links"] is List ? List<Link>.from(json["links"].map((x) => Link.fromJson(x))) : [],
      nextPageUrl: json["next_page_url"],
      path: json["path"] ?? "",
      perPage: json["per_page"] ?? 25,
      prevPageUrl: json["prev_page_url"],
      to: json["to"] ?? 0,
      total: json["total"] ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "current_page": currentPage,
      "data": incidents.map((x) => x.toJson()).toList(),
      "first_page_url": firstPageUrl,
      "from": from,
      "last_page": lastPage,
      "last_page_url": lastPageUrl,
      "links": links.map((x) => x.toJson()).toList(),
      "next_page_url": nextPageUrl,
      "path": path,
      "per_page": perPage,
      "prev_page_url": prevPageUrl,
      "to": to,
      "total": total,
    };
  }
}

class Incident {
  int id;
  int userId;
  String title;
  String description;
  String phone;
  String email;
  int status;
  int createdBy;
  int updatedBy;
  String createdAt;
  String? reply;
  String updatedAt;
  String statusName;
  String incidenceTypeName;
  String incidentDate;
  String? incidentCloseDate;
  String? profileImage;
  String? name;
  String? fileUrl;

  Incident({
    this.id = -1,
    this.userId = -1,
    this.title = "",
    this.description = "",
    this.phone = "",
    this.email = "",
    this.status = 0,
    this.createdBy = 0,
    this.updatedBy = 0,
    this.createdAt = "",
    this.updatedAt = "",
    this.statusName = "",
    this.incidenceTypeName = "",
    this.incidentDate = "",
    this.incidentCloseDate,
    this.profileImage,
    this.name,
    this.reply,
    required this.fileUrl,
  });

  factory Incident.fromJson(Map<String, dynamic> json) {
    return Incident(
      id: json["id"] is int ? json["id"] : -1,
      userId: json["user_id"] is int ? json["user_id"] : -1,
      title: json["title"] is String ? json["title"] : "",
      description: json["description"] is String ? json["description"] : "",
      phone: json["phone"] is String ? json["phone"] : "",
      email: json["email"] is String ? json["email"] : "",
      status: json["status"] is int ? json["status"] : -1,
      createdBy: json["created_by"] is int ? json["created_by"] : -1,
      updatedBy: json["updated_by"] is int ? json["updated_by"] : -1,
      createdAt: json["created_at"] is String ? json["created_at"] : "",
      updatedAt: json["updatedAt"] is String ? json["updatedAt"] : "",
      statusName: json["status_name"] ?? "",
      incidenceTypeName: json["incidence_type_name"] ?? "",
      incidentDate: json["incident_date"] is String ? json["incident_date"] : "",
      incidentCloseDate: json["incident_closed_date"] is String ? json["incident_closed_date"] : "",
      profileImage: json["profile_image"] is String ? json["profile_image"] : "",
      name: json["name"] is String ? json["name"] : "",
      reply: json['reply'] is String ? json['reply'] : '',
      fileUrl: json['file_url'] is String ? json['file_url'] : '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "id": id,
      "user_id": userId,
      "title": title,
      "description": description,
      "phone": phone,
      "email": email,
      "status": status,
      "created_by": createdBy,
      "updated_by": updatedBy,
      "created_at": createdAt,
      "updated_at": updatedAt,
      "status_name": statusName,
      "incidence_type_name": incidenceTypeName,
      "incident_date": incidentDate,
      "incident_closed_date": incidentCloseDate,
      "profile_image": profileImage,
      "name": name,
      'file_url' : fileUrl,
    };
  }
}

class Link {
  String? url;
  String label;
  bool active;

  Link({
    this.url,
    this.label = "",
    this.active = false,
  });

  factory Link.fromJson(Map<String, dynamic> json) {
    return Link(
      url: json["url"],
      label: json["label"] ?? "",
      active: json["active"] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "url": url,
      "label": label,
      "active": active,
    };
  }
}

class IncidentListResult {
  final RxList<Incident> incidents;
  final String message;

  IncidentListResult({required this.incidents, required this.message});
}
