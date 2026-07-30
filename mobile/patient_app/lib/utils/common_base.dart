// ignore_for_file: body_might_complete_normally_catch_error, depend_on_referenced_packages

import 'dart:io';

import 'package:country_picker/country_picker.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:html/parser.dart';
import 'package:intl/intl.dart';
import 'package:kivicare_patient/utils/price_widget.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../components/new_update_dialog.dart';
import '../configs.dart';
import '../generated/assets.dart';
import '../main.dart';
import '../screens/auth/sign_in_sign_up/signin_screen.dart';
import 'app_common.dart';
import 'colors.dart';
import 'constants.dart';
import 'local_storage.dart';

Widget get commonDivider => Column(
      children: [
        Divider(
          height: 1,
          thickness: 1,
          color: isDarkMode.value ? borderColor.withValues(alpha: 0.1) : borderColor.withValues(alpha: 0.5),
        ),
      ],
    );

Widget get bottomSheetDivider => Column(
      children: [
        20.height,
        Divider(
          indent: 3,
          height: 0,
          color: isDarkMode.value ? borderColor.withValues(alpha: 0.2) : borderColor.withValues(alpha: 0.5),
        ),
        20.height,
      ],
    );

final fontFamilyWeight700 = GoogleFonts.interTight(fontWeight: FontWeight.w700).fontFamily;

Future<void> handleRate() async {
  if (isAndroid) {
    if (getStringAsync(APP_PLAY_STORE_URL).isNotEmpty) {
      commonLaunchUrl(getStringAsync(APP_PLAY_STORE_URL), launchMode: LaunchMode.externalApplication);
    } else {
      commonLaunchUrl('${getSocialMediaLink(LinkProvider.PLAY_STORE)}${await getPackageName()}', launchMode: LaunchMode.externalApplication);
    }
  } else if (isIOS) {
    if (getStringAsync(APP_APPSTORE_URL).isNotEmpty) {
      commonLaunchUrl(getStringAsync(APP_APPSTORE_URL), launchMode: LaunchMode.externalApplication);
    }
  }
}

void hideKeyBoardWithoutContext() {
  SystemChannels.textInput.invokeMethod('TextInput.hide');
}

void toggleThemeMode({required int themeId}) {
  if (themeId == THEME_MODE_SYSTEM) {
    Get.changeThemeMode(ThemeMode.system);
    isDarkMode(Get.isPlatformDarkMode);
  } else if (themeId == THEME_MODE_LIGHT) {
    Get.changeThemeMode(ThemeMode.light);
    isDarkMode(false);
  } else if (themeId == THEME_MODE_DARK) {
    Get.changeThemeMode(ThemeMode.dark);
    isDarkMode(true);
  }
  setValueToLocal(SettingsLocalConst.THEME_MODE, themeId);
  log('toggleDarkLightSwitch: $themeId');
  if (isDarkMode.value) {
    textPrimaryColorGlobal = Colors.white;
    textSecondaryColorGlobal = Colors.white70;
  } else {
    textPrimaryColorGlobal = primaryTextColor;
    textSecondaryColorGlobal = secondaryTextColor;
  }
}

List<LanguageDataModel> languageList() {
  return [
    LanguageDataModel(id: 1, name: 'English', languageCode: 'en', fullLanguageCode: 'en-US', flag: Assets.flagsIcUs),
    LanguageDataModel(id: 2, name: 'Hindi', languageCode: 'hi', fullLanguageCode: 'hi-IN', flag: Assets.flagsIcIn),
    LanguageDataModel(id: 3, name: 'Arabic', languageCode: 'ar', fullLanguageCode: 'ar-AR', flag: Assets.flagsIcAr),
    LanguageDataModel(id: 4, name: 'French', languageCode: 'fr', fullLanguageCode: 'fr-FR', flag: Assets.flagsIcFr),
    LanguageDataModel(id: 5, name: 'German', languageCode: 'de', fullLanguageCode: 'de-DE', flag: Assets.flagsIcDe),
    LanguageDataModel(id: 6, name: 'Korean', languageCode: 'ko', fullLanguageCode: 'ko-KR', flag: Assets.flagsIcUs),
  ];
}

Widget appCloseIconButton(BuildContext context, {required void Function() onPressed, double size = 12}) {
  return IconButton(
    iconSize: size,
    padding: EdgeInsets.zero,
    onPressed: onPressed,
    icon: Container(
      padding: EdgeInsets.all(size - 8),
      decoration: boxDecorationDefault(color: context.cardColor, borderRadius: BorderRadius.circular(size - 4), border: Border.all(color: iconColor)),
      child: Icon(
        Icons.close_rounded,
        size: size,
        color: iconColor,
      ),
    ),
  );
}

Widget commonLeadingWid({required String imgPath, IconData? icon, Color? color, double size = 20}) {
  return Image.asset(
    imgPath,
    width: size,
    height: size,
    color: color ?? appColorPrimary,
    fit: BoxFit.contain,
    errorBuilder: (context, error, stackTrace) => Icon(
      icon ?? Icons.now_wallpaper_outlined,
      size: size,
      color: color ?? appColorSecondary,
    ),
  );
}

Widget commonLeadingWidSVG({
  required String imgPath,
  IconData? icon,
  Color? color,
  double size = 20,
}) {
  if (imgPath.endsWith('.svg')) {
    // Handle SVG images
    return SvgPicture.asset(
      imgPath,
      width: size,
      height: size,
      placeholderBuilder: (context) => Icon(
        icon ?? Icons.image_not_supported_outlined,
        size: size,
        color: color ?? Colors.grey,
      ),
    );
  } else {
    // Handle raster images
    return Image.asset(
      imgPath,
      width: size,
      height: size,
      color: color ?? Colors.black,
      fit: BoxFit.contain,
      errorBuilder: (context, error, stackTrace) => Icon(
        icon ?? Icons.broken_image_outlined,
        size: size,
        color: color ?? Colors.grey,
      ),
    );
  }
}

Future<void> commonLaunchUrl(String address, {LaunchMode launchMode = LaunchMode.inAppWebView}) async {
  await launchUrl(Uri.parse(address), mode: launchMode).catchError((e) {
    toast('${locale.value.invalidUrl}: $address');
  });
}

void viewFiles(String url) {
  if (url.isNotEmpty) {
    commonLaunchUrl(url, launchMode: LaunchMode.externalApplication);
  }
}

void launchCall(String? url) {
  if (url.validate().isNotEmpty) {
    if (isIOS) {
      commonLaunchUrl('tel://${url!}', launchMode: LaunchMode.externalApplication);
    } else {
      commonLaunchUrl('tel:${url!}', launchMode: LaunchMode.externalApplication);
    }
  }
}

void launchMap(String? url) {
  if (url.validate().isNotEmpty) {
    final encodedQuery = Uri.encodeComponent(url.validate());
    final String newURL = (isIOS ? Constants.mapLinkForIOS : Constants.googleMapPrefix) + encodedQuery;
    commonLaunchUrl(newURL, launchMode: LaunchMode.externalApplication);
  }
}

void launchMail(String url) {
  if (url.validate().isNotEmpty) {
    launchUrl(mailTo(to: []), mode: LaunchMode.externalApplication);
  }
}

String parseHtmlString(String? htmlString) {
  return parse(parse(htmlString).body!.text).documentElement!.text;
}

///
/// Date format extension for format datetime in different formats,
/// e.g. 1) dd-MM-yyyy, 2) yyyy-MM-dd, etc...
///
extension DateData on String {
  /// Formats the given [DateTime] object in the [dd-MM-yy] format.
  ///
  /// Returns a string representing the formatted date.
  DateTime get dateInyyyyMMddFormat {
    try {
      return DateFormat(DateFormatConst.yyyy_MM_dd).parse(this);
    } catch (e) {
      return DateTime.now();
    }
  }

  String get dateInMMMMDyyyyFormat {
    try {
      return DateFormat(DateFormatConst.MMMM_D_yyyy).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get dateInEEEEDMMMMAtHHmmAmPmFormat {
    try {
      return DateFormat(DateFormatConst.EEEE_D_MMMM_At_HH_mm_a).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get dateInEEEMMMDyyyyAtHHmmFormat {
    try {
      return DateFormat(DateFormatConst.EEE_MMM_d_yyyy_At_HH_mm).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get dateInDMMMMyyyyFormat {
    try {
      return DateFormat(DateFormatConst.D_MMMM_yyyy).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get dateInDDMMYYYYFormat {
    try {
      return DateFormat(DateFormatConst.DD_MM_YYYY).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get dateInYYYYMMDDFormat {
    try {
      return DateFormat('yyyy-MM-dd').format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get monthMMMFormat {
    try {
      return dateInyyyyMMddHHmmFormat.month.toMonthName(isHalfName: true);
    } catch (e) {
      return "";
    }
  }

  String get dateInMMMMDyyyyAtHHmmAmPmFormat {
    try {
      return DateFormat(DateFormatConst.MMMM_D_yyyy_At_HH_mm_a).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  String get dateInddMMMyyyyHHmmAmPmFormat {
    try {
      return DateFormat(DateFormatConst.dd_MMM_yyyy_HH_mm_a).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      try {
        return "$dateInyyyyMMddHHmmFormat";
      } catch (e) {
        return this;
      }
    }
  }

  DateTime get dateInyyyyMMddHHmmFormat {
    try {
      return DateFormat(DateFormatConst.yyyy_MM_dd_HH_mm).parse(this);
    } catch (e) {
      try {
        try {
          if (DateTime.parse(this).isUtc) {
            return DateFormat(DateFormatConst.yyyy_MM_dd_HH_mm).parse(DateTime.parse(this).toLocal().toString());
          } else {
            return DateFormat(DateFormatConst.yyyy_MM_dd_HH_mm).parse(DateTime.parse(this).toString());
          }
        } catch (e) {
          log('dateInyyyyMMddHHmmFormat Check isUtc Error in $this: $e');
          return DateFormat(DateFormatConst.yyyy_MM_dd_HH_mm).parse(DateTime.parse(this).toString());
        }
      } catch (e) {
        log('dateInyyyyMMddHHmmFormat Error in $this: $e');
        return DateTime.now();
      }
    }
  }

  DateTime get dateInHHmm24HourFormat {
    return DateFormat(DateFormatConst.HH_mm24Hour).parse(this);
  }

  String get timeInHHmmAmPmFormat {
    try {
      return DateFormat(DateFormatConst.HH_mm12Hour).format(dateInyyyyMMddHHmmFormat);
    } catch (e) {
      return this;
    }
  }

  TimeOfDay get timeOfDay24Format {
    return TimeOfDay.fromDateTime(DateFormat(DateFormatConst.yyyy_MM_dd_HH_mm).parse(this));
  }

  String get amPMto24HourFormat {
    try {
      final format12 = DateFormat('h:mm a');
      final format24 = DateFormat('HH:mm');
      final time = format12.parse(this);
      return format24.format(time);
    } catch (e) {
      return "";
    }
  }

  String get format24HourtoAMPM {
    try {
      final format12 = DateFormat('h:mm a');
      final format24 = DateFormat('HH:mm');
      final time = format24.parse(this);
      return format12.format(time);
    } catch (e) {
      return "";
    }
  }

  String get formatAMPMto24Hour {
    try {
      final format12 = DateFormat('h:mm a'); // input format
      final format24 = DateFormat('HH:mm'); // desired output
      final time = format12.parse(this);
      return format24.format(time);
    } catch (e) {
      return "";
    }
  }

  bool get isValidTime {
    return DateTime.tryParse("1970-01-01 $this") != null;
  }

  bool get isValidDateTime {
    return DateTime.tryParse(this) != null;
  }

  bool get isAfterCurrentDateTime {
    return dateInyyyyMMddHHmmFormat.isAfter(DateTime.now());
  }

  bool get isToday {
    try {
      return "$dateInyyyyMMddFormat" == DateTime.now().formatDateYYYYmmdd();
    } catch (e) {
      return false;
    }
  }

  Duration toDuration() {
    final parts = split(':');
    try {
      if (parts.length == 2) {
        final hours = int.parse(parts[0]);
        final minutes = int.parse(parts[1]);
        return Duration(hours: hours, minutes: minutes);
      } else {
        return Duration.zero;
      }
    } catch (e) {
      return Duration.zero;
    }
  }

  String toFormattedDuration({bool showFullTitleHoursMinutes = false}) {
    try {
      final duration = toDuration();
      final hours = duration.inHours;
      final minutes = duration.inMinutes.remainder(60);

      String formattedDuration = '';
      if (hours > 0) {
        formattedDuration += "$hours ${showFullTitleHoursMinutes ? 'hour' : 'hr'} ";
      }
      if (minutes > 0) {
        formattedDuration += '$minutes ${showFullTitleHoursMinutes ? 'minute' : 'min'}';
      }
      return formattedDuration.trim();
    } catch (e) {
      return "";
    }
  }
}

extension DateExtension on DateTime {
  /// Formats the given [DateTime] object in the [dd-MM-yy] format.
  ///
  /// Returns a string representing the formatted date.
  String formatDateDDMMYY() {
    final formatter = DateFormat(DateFormatConst.DD_MM_YYYY);
    return formatter.format(this);
  }

  /// Formats the given [DateTime] object in the [DateFormatConst.yyyy_MM_dd] format.
  ///
  /// Returns a string representing the formatted date.
  String formatDateYYYYmmdd() {
    final formatter = DateFormat(DateFormatConst.yyyy_MM_dd);
    return formatter.format(this);
  }

  /// Formats the given [DateTime] object in the [DateFormatConst.yyyy_MM_dd_HH_mm] format.
  ///
  /// Returns a string representing the formatted date.
  String formatDateYYYYmmddHHmm() {
    final formatter = DateFormat(DateFormatConst.yyyy_MM_dd_HH_mm);
    return formatter.format(this);
  }

  /// Formats the given [DateTime] object in the [DateFormatConst.yyyy_MM_dd]+[DateFormatConst.HH_mm12Hour] format.
  ///
  /// Returns a string representing the formatted date.
  String formatDateddmmYYYYHHmmAMPM() {
    final formatter = DateFormat(DateFormatConst.DD_MM_YY);
    final timeInAMPM = DateFormat(DateFormatConst.HH_mm12Hour);
    return "${formatter.format(this)} ${timeInAMPM.format(this)}";
  }

  /*  /// Formats the given [DateTime] object in the [DateFormatConst.yyyy_MM_dd]+[DateFormatConst.HH_mm_a] format.
  ///
  /// Returns a string representing the formatted date.
  String formatDateddmmYYYYHHmmAMPM() {
    final formatter = DateFormat("dd-MM-yyyy");
    final timeInAMPM = DateFormat(DateFormatConst.HH_mm_a);
    return "${formatter.format(this)} ${timeInAMPM.format(this)}";
  } */

  /// Formats the given [DateTime] object in the [DateFormatConst.HH_mm12Hour] format.
  ///
  /// Returns a string representing the formatted date.
  String formatTimeHHmmAMPM() {
    final formatter = DateFormat(DateFormatConst.HH_mm12Hour);
    return formatter.format(this);
  }

  /// Returns Time Ago
  String get timeAgoWithLocalization => formatTime(millisecondsSinceEpoch);
}

/// Splits a date string in the format "dd/mm/yyyy" into its constituent parts and returns a [DateTime] object.
///
/// If the input string is not a valid date format, this method returns `null`.
///
/// Example usage:
///
/// ```dart
/// DateTime? myDate = getDateTimeFromAboveFormat('27/04/2023');
/// if (myDate != null) {
///   print(myDate); // Output: 2023-04-27 00:00:00.000
/// }
/// ```
///
DateTime? getDateTimeFromAboveFormat(String date) {
  if (date.isValidDateTime) {
    return DateTime.tryParse(date);
  } else {
    final List<String> dateParts = date.split('/');
    if (dateParts.length != 3) {
      log('getDateTimeFromAboveFormat => Invalid date format => DATE: $date');
      return null;
    }
    final int day = int.parse(dateParts[0]);
    final int month = int.parse(dateParts[1]);
    final int year = int.parse(dateParts[2]);
    return DateTime.tryParse('$year-$month-$day');
  }
}

extension TimeExtension on TimeOfDay {
  /// Formats the given [TimeOfDay] object in the [DateFormatConst.HH_mm24Hour] format.
  ///
  /// Returns a string representing the formatted time.
  String formatTimeHHmm24Hour() {
    final timeIn24Hour = DateFormat(DateFormatConst.HH_mm24Hour);
    final tempDateTime = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day, hour, minute);
    return timeIn24Hour.format(tempDateTime);
  }

  /// Formats the given [TimeOfDay] object in the [DateFormatConst.yyyy_MM_dd]+[DateFormatConst.HH_mm12Hour] format.
  ///
  /// Returns a string representing the formatted time.
  String formatTimeHHmmAMPM() {
    final timeInAMPM = DateFormat(DateFormatConst.HH_mm12Hour);
    final tempDateTime = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day, hour, minute);
    return timeInAMPM.format(tempDateTime);
  }
}

TextStyle get appButtonTextStyleGray => boldTextStyle(color: appColorSecondary, size: 14);

TextStyle get appButtonTextStyleWhite => boldTextStyle(color: Colors.white, size: 14);

TextStyle get appButtonPrimaryColorText => boldTextStyle(color: appColorPrimary);

TextStyle get appButtonFontColorText => boldTextStyle(color: Colors.grey, size: 14);

InputDecoration inputDecoration(
  BuildContext context, {
  Widget? prefixIcon,
  EdgeInsetsGeometry? contentPadding,
  BoxConstraints? prefixIconConstraints,
  BoxConstraints? suffixIconConstraints,
  Widget? suffixIcon,
  String? labelText,
  String? hintText,
  double? borderRadius,
  bool? filled,
  Color? fillColor,
}) {
  return InputDecoration(
    contentPadding: contentPadding ?? const EdgeInsets.only(left: 12, bottom: 10, top: 10, right: 10),
    labelText: labelText,
    hintText: hintText,
    hintStyle: primaryTextStyle(size: 12),
    labelStyle: primaryTextStyle(size: 12),
    alignLabelWithHint: true,
    prefixIcon: prefixIcon,
    prefixIconConstraints: prefixIconConstraints,
    suffixIcon: suffixIcon,
    suffixIconConstraints: suffixIconConstraints,
    enabledBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius / 2),
      borderSide: const BorderSide(color: borderColor, width: 0.0),
    ),
    focusedErrorBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius / 2),
      borderSide: const BorderSide(color: Colors.red, width: 0.0),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius / 2),
      borderSide: const BorderSide(color: Colors.red),
    ),
    errorMaxLines: 2,
    border: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius / 2),
      borderSide: const BorderSide(color: borderColor, width: 0.0),
    ),
    disabledBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius / 2),
      borderSide: const BorderSide(color: borderColor, width: 0.0),
    ),
    errorStyle: primaryTextStyle(color: Colors.red, size: 12),
    focusedBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius / 2),
      borderSide: const BorderSide(color: appColorPrimary, width: 0.0),
    ),
    filled: filled,
    fillColor: fillColor,
  );
}

InputDecoration inputDecorationWithOutBorder(BuildContext context, {Widget? prefixIcon, Widget? suffixIcon, String? labelText, String? hintText, double? borderRadius, bool? filled, Color? fillColor}) {
  return InputDecoration(
    contentPadding: const EdgeInsets.only(left: 12, bottom: 10, top: 10, right: 10),
    labelText: labelText,
    hintText: hintText,
    hintStyle: secondaryTextStyle(size: 12),
    labelStyle: secondaryTextStyle(size: 12),
    alignLabelWithHint: true,
    prefixIcon: prefixIcon,
    suffixIcon: suffixIcon,
    enabledBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius),
      borderSide: const BorderSide(color: Colors.transparent, width: 0.0),
    ),
    focusedErrorBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius),
      borderSide: const BorderSide(color: Colors.red, width: 0.0),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius),
      borderSide: const BorderSide(color: Colors.red),
    ),
    errorMaxLines: 2,
    border: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius),
      borderSide: const BorderSide(color: Colors.transparent, width: 0.0),
    ),
    disabledBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius),
      borderSide: const BorderSide(color: Colors.transparent, width: 0.0),
    ),
    errorStyle: primaryTextStyle(color: Colors.red, size: 12),
    focusedBorder: OutlineInputBorder(
      borderRadius: radius(borderRadius ?? defaultRadius),
      borderSide: const BorderSide(color: appColorPrimary, width: 0.0),
    ),
    filled: filled,
    fillColor: fillColor,
  );
}

Future<List<PlatformFile>> pickFiles({FileType type = FileType.custom}) async {
  List<PlatformFile> filePath0 = [];
  try {
    final FilePickerResult? filePickerResult = await FilePicker.platform.pickFiles(
      type: type,
      allowMultiple: true,
      allowedExtensions: ['jpg', 'pdf', 'doc', 'png', 'docx'],
      withData: true,
      onFileLoading: (FilePickerStatus status) => log(status),
    );
    if (filePickerResult != null) {
      if (Platform.isAndroid) {
        filePath0 = filePickerResult.files;
      } else {
        final Directory cacheDir = await getTemporaryDirectory();
        for (final PlatformFile file in filePickerResult.files) {
          if (file.bytes != null) {
            final String filePath = '${cacheDir.path}/${file.name}';
            final File cacheFile = File(filePath);
            await cacheFile.writeAsBytes(file.bytes!.toList());
            final PlatformFile cachedFile = PlatformFile(
              path: cacheFile.path,
              name: file.name,
              size: cacheFile.lengthSync(),
              bytes: Uint8List.fromList(cacheFile.readAsBytesSync()),
            );
            filePath0.add(cachedFile);
          }
        }
      }
    }
  } on PlatformException catch (e) {
    log('Unsupported operation$e');
  } catch (e) {
    log(e.toString());
  }
  return filePath0;
}

Widget backButton({Object? result}) {
  return IconButton(
    onPressed: () {
      Get.back(result: result);
    },
    icon: const Icon(Icons.arrow_back_ios_new_outlined, color: Colors.grey, size: 20),
  );
}

extension WidgetExt on Widget {
  Container shadow() {
    return Container(
      decoration: BoxDecoration(
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withValues(alpha: 0.2),
            spreadRadius: 2,
            blurRadius: 5,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: this,
    );
  }

  Container circularLightPrimaryBg({double? padding, Color? color}) {
    return Container(
      padding: EdgeInsets.all(padding ?? 12),
      decoration: boxDecorationDefault(shape: BoxShape.circle, color: color ?? (isDarkMode.value ? Colors.grey.withValues(alpha: 0.1) : extraLightPrimaryColor)),
      child: this,
    );
  }

  Widget position({
    bool? expand,
    double? size,
    double? left,
    double? right,
    double? bottom,
    double? top,
    double? height,
    double? width,
    Alignment? alignment,
  }) {
    if (alignment != null) {
      return Align(alignment: alignment, child: this);
    }
    if (expand ?? false) {
      Positioned(
        height: size,
        bottom: bottom,
        right: right,
        left: left,
        top: top,
        width: Get.width,
        child: this,
      );
    }
    return Positioned(
      height: size ?? height,
      width: size ?? width,
      bottom: bottom,
      right: right,
      left: left,
      top: top,
      child: this,
    );
  }
}

extension StrEtx on String {
  String get firstLetter => isNotEmpty ? this[0] : '';

  Widget iconImage({double? size, Color? color, BoxFit? fit}) {
    return Image.asset(
      this,
      height: size ?? 14,
      width: size ?? 14,
      fit: fit ?? BoxFit.cover,
      color: color ?? (isDarkMode.value ? Colors.white : darkGray),
      errorBuilder: (context, error, stackTrace) {
        return const SizedBox();
      },
    );
  }

  Widget showSvg({double? size, Color? color, double? width, double? height, bool? fit}) {
    if (fit ?? false) {
      return SvgPicture.asset(
        this,
        width: size ?? width ?? 35,
        height: size ?? height ?? 35,
      );
    }
    return SvgPicture.asset(
      this,
      width: size ?? width ?? 35,
      height: size ?? height ?? 35,
      fit: BoxFit.cover,
    );
  }

  String formatPhoneNumber(String phoneCode) {
    final String trimmedPhoneNumber = trim();

    if (trimmedPhoneNumber.startsWith(phoneCode)) {
      return trimmedPhoneNumber;
    } else {
      return '$phoneCode $trimmedPhoneNumber';
    }
  }

  (String, String) get extractPhoneCodeAndNumber {
    // Split the string by spaces and hyphens
    final List<String> parts = trim().split(RegExp(r'[\s-]+'));

    if (parts.length > 1) {
      // Assume the first part is the phone code
      final String phoneCode = parts[0].trim().replaceAll("+", '');
      // Join the remaining parts as the phone number
      final String phoneNumber = parts.sublist(1).join().trim();
      return (phoneCode, phoneNumber);
    } else {
      // If there's no separator, treat the whole string as the number
      return ('', trim());
    }
  }
}

void pickCountry(BuildContext context, {required Function(Country) onSelect}) {
  showCountryPicker(
    context: context,
    showPhoneCode: true,
    onSelect: onSelect,
    countryListTheme: CountryListThemeData(
      searchTextStyle: TextStyle(
        color: isDarkMode.value ? lightPrimaryColor : canvasColor,
      ),
      textStyle: TextStyle(
        fontSize: 16,
        color: isDarkMode.value ? lightPrimaryColor : canvasColor,
      ),
      inputDecoration: InputDecoration(
        // fillColor: context.cardColor,
        filled: true,
        labelText: 'Search',
        hintText: 'Search',
        hintStyle: TextStyle(color: textPrimaryColorGlobal),
        labelStyle: TextStyle(color: textPrimaryColorGlobal),
        prefixIcon: Icon(Icons.search, color: textPrimaryColorGlobal),
        border: OutlineInputBorder(
          borderSide: BorderSide(color: textPrimaryColorGlobal),
        ),
      ),
      borderRadius: BorderRadius.circular(8),
      backgroundColor: isDarkMode.value ? canvasColor : lightPrimaryColor,
    ),
  );
}

Future<void> showNewUpdateDialog(BuildContext context, {required int currentAppVersionCode}) async {
  final bool canClose = (isAndroid && currentAppVersionCode >= appConfigs.value.patientAndroidMinForceUpdateCode) || (isIOS && currentAppVersionCode >= appConfigs.value.patientIosMinForceUpdateCode);
  showInDialog(
    context,
    contentPadding: EdgeInsets.zero,
    barrierDismissible: canClose,
    builder: (_) {
      return PopScope(
        canPop: canClose,
        child: NewUpdateDialog(canClose: canClose),
      );
    },
  );
}

Future<void> showForceUpdateDialog(BuildContext context) async {
  if ((isAndroid && appConfigs.value.isForceUpdateforAndroid && appConfigs.value.patientAndroidLatestVersionUpdateCode > currentPackageinfo.value.versionCode.validate().toInt()) ||
      (isIOS && appConfigs.value.isForceUpdateforIos && appConfigs.value.patientIosLatestVersionUpdateCode > currentPackageinfo.value.versionCode.validate().toInt())) {
    showNewUpdateDialog(context, currentAppVersionCode: currentPackageinfo.value.versionCode.validate().toInt());
  }
}

void ifNotTester(VoidCallback callback) {
  if (loginUserData.value.email != Constants.DEFAULT_EMAIL) {
    callback.call();
  } else {
    toast(locale.value.demoUserCannotBeGrantedForThis);
  }
}

Future<void> doIfLoggedIn(VoidCallback callback) async {
  if (isLoggedIn.value) {
    callback.call();
  } else {
    final bool? res = await Get.to(
      () => SignInScreen(),
      binding: BindingsBuilder(
        () {
          setStatusBarColor(
            transparentColor,
            statusBarIconBrightness: Brightness.light,
            statusBarBrightness: Brightness.light,
          );
        },
      ),
    );
    log('doIfLoggedIn RES: $res');

    if (res ?? false) {
      callback.call();
    }
  }
}

Widget detailWidget({
  CrossAxisAlignment? crossAxisAlignment,
  String? title,
  String? value,
  Color? textColor,
  Widget? leadingWidget,
  Widget? trailingWidget,
  TextStyle? leadingTextStyle,
  TextStyle? trailingTextStyle,
}) {
  return Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    crossAxisAlignment: crossAxisAlignment ?? CrossAxisAlignment.start,
    children: [
      leadingWidget ?? Text(title.validate(), style: leadingTextStyle ?? secondaryTextStyle()).expand(),
      trailingWidget ?? Text(value.validate(), textAlign: TextAlign.right, style: trailingTextStyle ?? primaryTextStyle(size: 12, color: textColor)).expand(),
    ],
  ).paddingBottom(10).visible(trailingWidget != null || value.validate().isNotEmpty);
}

Widget detailWidgetPrice({
  String? title,
  Widget? leadingWidget,
  required num value,
  Color? textColor,
  bool isSemiBoldText = false,
  bool isBoldText = false,
  double paddingBottom = 0,
}) {
  return Padding(
    padding: EdgeInsets.only(bottom: paddingBottom),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        leadingWidget ?? Text(title ?? '', style: secondaryTextStyle()),
        PriceWidget(
          price: value,
          color: textColor ?? (isDarkMode.value ? Colors.white : darkGrayTextColor),
          size: isBoldText ? 14 : 12,
          isBoldText: isBoldText || isSemiBoldText,
        ),
      ],
    ),
  );
}

String getServiceType({required String serviceType}) {
  if (serviceType.toLowerCase().contains(ServiceTypeConst.inClinic)) {
    return locale.value.inClinic;
  } else if (serviceType.toLowerCase().contains(ServiceTypeConst.online)) {
    return locale.value.online;
  } else {
    return "";
  }
}

Color getBookingStatusColor({required String status}) {
  if (status.toLowerCase().contains(StatusConst.pending)) {
    return pendingStatusColor;
  } else if (status.toLowerCase().contains(StatusConst.confirmed)) {
    return confirmedStatusColor;
  } else if (status.toLowerCase().contains(StatusConst.checkIn)) {
    return checkInStatusColor;
  } else if (status.toLowerCase().contains(StatusConst.checkOut)) {
    return completedStatusColor;
  } else if (status.toLowerCase().contains(StatusConst.cancel)) {
    return cancelStatusColor;
  } else {
    return defaultStatusColor;
  }
}

String getBookingStatus({required String status}) {
  if (status.toLowerCase().contains(StatusConst.pending)) {
    return locale.value.pending;
  } else if (status.toLowerCase().contains(StatusConst.confirmed)) {
    return locale.value.confirmed;
  } else if (status.toLowerCase().contains(StatusConst.checkIn)) {
    return locale.value.checkIn;
  } else if (status.toLowerCase().contains(StatusConst.checkOut)) {
    return locale.value.completed;
  } else if (status.toLowerCase().contains(StatusConst.cancel)) {
    return locale.value.cancelled;
  } else {
    return "";
  }
}

Color getPriceStatusColor({required String paymentStatus}) {
  if (paymentStatus.toLowerCase().contains(PaymentStatus.pending)) {
    return pendingStatusColor;
  } else if (paymentStatus.toLowerCase().contains(PaymentStatus.ADVANCE_PAID)) {
    return completedStatusColor;
  } else if (paymentStatus.toLowerCase().contains(PaymentStatus.PAID)) {
    return completedStatusColor;
  } else if (paymentStatus.toLowerCase().contains(PaymentStatus.ADVANCE_REFUNDED)) {
    return confirmedStatusColor;
  } else if (paymentStatus.toLowerCase().contains(PaymentStatus.REFUNDED)) {
    return confirmedStatusColor;
  } else if (paymentStatus.toLowerCase().contains(PaymentStatus.failed)) {
    return cancelStatusColor;
  } else {
    return isDarkMode.value ? secondaryTextColor : defaultStatusColor;
  }
}

String getBookingPaymentStatus({required String status}) {
  if (status.toLowerCase().contains(PaymentStatus.pending)) {
    return locale.value.pending;
  } else if (status.toLowerCase().contains(PaymentStatus.ADVANCE_PAID)) {
    return locale.value.advancePaid;
  } else if (status.toLowerCase().contains(PaymentStatus.PAID)) {
    return locale.value.paid;
  } else if (status.toLowerCase().contains(PaymentStatus.ADVANCE_REFUNDED)) {
    return locale.value.advanceRefunded;
  } else if (status.toLowerCase().contains(PaymentStatus.REFUNDED)) {
    return locale.value.refunded;
  } else if (status.toLowerCase().contains(PaymentStatus.failed)) {
    return locale.value.failed;
  } else {
    return "";
  }
}

String getPrescriptionStatus({required String status}) {
  if (status.toLowerCase().contains(StatusConst.pending)) {
    return locale.value.pending;
  } else if (status.toLowerCase().contains(StatusConst.completed) || status.toLowerCase().contains(PaymentStatus.PAID)) {
    return locale.value.completed;
  } else {
    return status;
  }
}

Color getPrescriptionStatusColor({required String status}) {
  if (status.toLowerCase().contains(StatusConst.pending)) {
    return pendingStatusColor;
  } else if (status.toLowerCase().contains(StatusConst.completed)) {
    return completedStatusColor;
  } else {
    return confirmedStatusColor;
  }
}

String getPrescriptionPaymentStatus({required String status}) {
  if (status.toLowerCase().contains(PaymentStatus.pending) || status.toLowerCase().contains(PaymentStatus.UNPAID)) {
    return locale.value.unpaid;
  } else if (status.toLowerCase().contains(StatusConst.completed) || status.toLowerCase().contains(PaymentStatus.PAID)) {
    return locale.value.paid;
  } else {
    return status;
  }
}

Color getPrescriptionPaymentStatusColor({required String paymentStatus}) {
  if (paymentStatus.toLowerCase().contains(PaymentStatus.pending) || paymentStatus.toLowerCase().contains(PaymentStatus.UNPAID)) {
    return pendingStatusColor;
  } else if (paymentStatus.toLowerCase().contains(StatusConst.completed) || paymentStatus.toLowerCase().contains(PaymentStatus.PAID)) {
    return completedStatusColor;
  } else {
    return confirmedStatusColor;
  }
}

Color getClinicStatusColor({required String clinicStatus}) {
  if (clinicStatus.toLowerCase().contains(ClinicStatus.OPEN)) {
    return completedStatusColor;
  } else if (clinicStatus.toLowerCase().contains(ClinicStatus.CLOSE)) {
    return pendingStatusColor;
  } else {
    return defaultStatusColor;
  }
}

Color getClinicStatusLightColor({required String clinicStatus}) {
  if (clinicStatus.toLowerCase().contains(ClinicStatus.OPEN)) {
    return isDarkMode.value ? lightGreenColor.withValues(alpha: 0.1) : lightGreenColor;
  } else if (clinicStatus.toLowerCase().contains(ClinicStatus.CLOSE)) {
    return isDarkMode.value ? lightSecondaryColor.withValues(alpha: 0.1) : lightSecondaryColor;
  } else {
    return defaultStatusColor;
  }
}

String getClinicStatus({required String status}) {
  if (status.toLowerCase().contains(ClinicStatus.OPEN)) {
    return locale.value.open;
  } else if (status.toLowerCase().contains(ClinicStatus.CLOSE)) {
    return locale.value.close;
  } else {
    return "";
  }
}

Color getRatingBarColor(num starNumber) {
  if (starNumber >= 3.6 || starNumber >= 5) {
    return ratingFirstColor;
  } else if (starNumber >= 3) {
    return ratingSecondColor;
  } else if (starNumber >= 2) {
    return ratingFifthColor;
  } else if (starNumber >= 1) {
    return ratingThirdColor;
  }
  return ratingFourthColor;
}

String getAppointmentNotification({required String notification}) {
  if (notification.toLowerCase().contains(NotificationConst.newAppointment)) {
    return locale.value.newAppointmentBooked;
  } else if (notification.toLowerCase().contains(NotificationConst.checkoutAppointment)) {
    return locale.value.appointmentCompleted;
  } else if (notification.toLowerCase().contains(NotificationConst.rejectAppointment)) {
    return locale.value.appointmentRejected;
  } else if (notification.toLowerCase().contains(NotificationConst.cancelAppointment)) {
    return locale.value.appointmentCancelled;
  } else if (notification.toLowerCase().contains(NotificationConst.rescheduleAppointment)) {
    return locale.value.appointmentRescheduled;
  } else if (notification.toLowerCase().contains(NotificationConst.acceptAppointment)) {
    return locale.value.appointmentAccepted;
  } else if (notification.toLowerCase().contains(NotificationConst.changePassword)) {
    return locale.value.changePassword;
  } else if (notification.toLowerCase().contains(NotificationConst.forgetEmailPassword)) {
    return locale.value.forgetEmailPassword;
  } else if (notification.toLowerCase().contains(NotificationConst.incidence_reply)) {
    return locale.value.incidenceReportReply;
  } else {
    return "";
  }
}

String getOtherPatientRelation({required String relation}) {
  if (relation.toLowerCase().contains(RelationConstant.parents.toLowerCase())) {
    return locale.value.parents;
  } else if (relation.toLowerCase().contains(RelationConstant.siblings.toLowerCase())) {
    return locale.value.siblings;
  } else if (relation.toLowerCase().contains(RelationConstant.spouse.toLowerCase())) {
    return locale.value.spouse;
  } else if (relation.toLowerCase().contains(RelationConstant.others.toLowerCase())) {
    return locale.value.others;
  } else {
    return "";
  }
}

String getOtherPatientGender({required String gender}) {
  if (gender.toLowerCase() == GenderTypeConst.MALE.toLowerCase()) {
    return locale.value.male;
  } else if (gender.toLowerCase() == GenderTypeConst.FEMALE.toLowerCase()) {
    return locale.value.female;
  } else if (gender.toLowerCase() == GenderTypeConst.OTHER.toLowerCase()) {
    return locale.value.other;
  } else {
    return "";
  }
}

//
bool checkTimeDifference({required DateTime inputDateTime}) {
  final DateTime currentTime = DateTime.now();

  if (currentTime.isBefore(inputDateTime) && inputDateTime.difference(currentTime).inHours <= (appConfigs.value.cancellationChargeHours.validate())) {
    return true;
  }

  // Check if the current time is after the booking date and time
  if (currentTime.isAfter(inputDateTime)) {
    return false;
  }

  // Otherwise, it's more than 12 hours before the booking time
  return false;
}

String formatBookingDate(
  String? dateTime, {
  String format = DateFormatConst.DATE_FORMAT_1,
  bool isFromMicrosecondsSinceEpoch = false,
  bool isLanguageNeeded = true,
  bool isTime = false,
  bool showDateWithTime = false,
}) {
  final parsedDateTime = isFromMicrosecondsSinceEpoch ? DateTime.fromMicrosecondsSinceEpoch(dateTime.validate().toInt() * 1000) : DateTime.parse(dateTime.validate());

  return DateFormat(format).format(parsedDateTime);
}

extension TimeConversion on String {
  String get formatAMPMto24Hour {
    try {
      final format12 = DateFormat('h:mm a');
      final format24 = DateFormat('HH:mm');
      final time = format12.parse(this);
      return format24.format(time);
    } catch (e) {
      return "";
    }
  }
}
