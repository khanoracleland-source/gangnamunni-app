import 'languages.dart';

class LanguageHi extends BaseLanguage {
  @override
  String get language => 'भाषा';

  @override
  String get badRequest => '400 गलत अनुरोध';

  @override
  String get forbidden => '403 निषिद्ध';

  @override
  String get pageNotFound => '404 पृष्ठ नहीं मिला';

  @override
  String get tooManyRequests => '429: बहुत सारे अनुरोध';

  @override
  String get internalServerError => '500 आंतरिक सर्वर त्रुटि';

  @override
  String get badGateway => '502 खराब गेटवे';

  @override
  String get serviceUnavailable => '503 सेवा उपलब्ध नहीं';

  @override
  String get gatewayTimeout => '504 गेटवे समय समाप्त';

  @override
  String get hey => 'अरे';

  @override
  String get hello => 'नमस्ते';

  @override
  String get thisFieldIsRequired => 'यह फ़ील्ड आवश्यक है';

  @override
  String get contactNumber => 'संपर्क संख्या';

  @override
  String get gallery => 'गैलरी';

  @override
  String get camera => 'कैमरा';

  @override
  String get editProfile => 'प्रोफ़ाइल संपादित करें';

  @override
  String get update => 'अद्यतन';

  @override
  String get reload => 'पुनः लोड करें';

  @override
  String get address => 'पता';

  @override
  String get viewAll => 'सभी को देखें';

  @override
  String get pressBackAgainToExitApp => 'एग्जिट ऐप के लिए फिर से वापस दबाएं';

  @override
  String get invalidUrl => 'असामान्य यूआरएल';

  @override
  String get cancel => 'रद्द करना';

  @override
  String get delete => 'मिटाना';

  @override
  String get deleteAccountConfirmation => 'आपका खाता स्थायी रूप से हटा दिया जाएगा। आपका डेटा फिर से बहाल नहीं किया जाएगा।';

  @override
  String get demoUserCannotBeGrantedForThis => 'इस कार्रवाई के लिए डेमो उपयोगकर्ता प्रदान नहीं किया जा सकता है';

  @override
  String get somethingWentWrong => 'कुछ गलत हो गया';

  @override
  String get yourInternetIsNotWorking => 'आपका इंटरनेट काम नहीं कर रहा है';

  @override
  String get profileUpdatedSuccessfully => 'प्रोफाइल को सफलतापूर्वक अपडेट किया गया';

  @override
  String get wouldYouLikeToSetProfilePhotoAs => 'क्या आप इस चित्र को अपनी प्रोफ़ाइल फोटो के रूप में सेट करना चाहेंगे?';

  @override
  String get yourOldPasswordDoesnT => 'आपका पुराना पासवर्ड सही नहीं है!';

  @override
  String get yourNewPasswordDoesnT => 'आपका नया पासवर्ड पुष्टि पासवर्ड से मेल नहीं खाता है!';

  @override
  String get location => 'जगह';

  @override
  String get yes => 'हाँ';

  @override
  String get submit => 'जमा करना';

  @override
  String get firstName => 'पहला नाम';

  @override
  String get lastName => 'उपनाम';

  @override
  String get changePassword => 'पासवर्ड बदलें';

  @override
  String get yourNewPasswordMust => 'आपका नया पासवर्ड आपके पिछले पासवर्ड से अलग होना चाहिए';

  @override
  String get password => 'पासवर्ड';

  @override
  String get newPassword => 'नया पासवर्ड';

  @override
  String get confirmNewPassword => 'नए पासवर्ड की पुष्टि करें';

  @override
  String get email => 'ईमेल';

  @override
  String get mainStreet => 'मुख्य मार्ग';

  @override
  String get toResetYourNew => 'अपना नया पासवर्ड रीसेट करने के लिए कृपया अपना ईमेल पता दर्ज करें';

  @override
  String get stayTunedNoNew => 'बने रहें! कोई नए संदेश नहीं।';

  @override
  String get noNewNotificationsAt => 'इस समय कोई नई सूचनाएं नहीं हैं। अपडेट होने पर हम आपको पोस्ट करते रहेंगे।';

  @override
  String get signIn => 'दाखिल करना';

  @override
  String get explore => 'अन्वेषण करना';

  @override
  String get settings => 'समायोजन';

  @override
  String get rateApp => 'एप्प का मूल्यांकन';

  @override
  String get aboutApp => 'ऐप के बारे में';

  @override
  String get logout => 'लॉग आउट';

  @override
  String get rememberMe => 'मुझे याद करो';

  @override
  String get forgotPassword => 'पासवर्ड भूल गए?';

  @override
  String get forgotPasswordTitle => 'पासवर्ड भूल गए';

  @override
  String get registerNow => 'अभी पंजीकरण करें';

  @override
  String get createYourAccount => 'अपना खाता बनाएं';

  @override
  String get createYourAccountFor => 'बेहतर अनुभव के लिए अपना खाता बनाएं';

  @override
  String get signUp => 'साइन अप करें';

  @override
  String get alreadyHaveAnAccount => 'क्या आपके पास पहले से एक खाता मौजूद है?';

  @override
  String get yourPasswordHasBeen => 'आपका पासवर्ड सफलतापूर्वक रीसेट कर दिया गया है';

  @override
  String get youCanNowLog => 'अब आप अपने नए पासवर्ड के साथ अपने नए खाते में लॉग इन कर सकते हैं';

  @override
  String get done => 'हो गया';

  @override
  String get pleaseAcceptTermsAnd => 'कृपया नियम और शर्तें स्वीकार करें';

  @override
  String get deleteAccount => 'खाता हटा दो';

  @override
  String get eG => 'उदा।';

  @override
  String get merry => 'प्रमुदित';

  @override
  String get doe => 'हरिणी';

  @override
  String get welcomeBackToThe => 'वापस आपका स्वागत है';

  @override
  String get welcomeToThe => 'आपका स्वागत है';

  @override
  String get doYouWantToLogout => 'क्या आप लॉगआउट करना चाहते हैं?';

  @override
  String get appTheme => 'ऐप थीम';

  @override
  String get guest => 'अतिथि';

  @override
  String get notifications => 'अधिसूचना';

  @override
  String get contactUs => 'संपर्क करें';

  @override
  String get getInTouchWithSupport => 'सहायता से संपर्क करें';

  @override
  String get newUpdate => 'नई अपडेट';

  @override
  String get anUpdateTo => 'के लिए एक अद्यतन';

  @override
  String get isAvailableGoTo => 'उपलब्ध है। प्ले स्टोर पर जाएं और ऐप का नया संस्करण डाउनलोड करें।';

  @override
  String get later => 'बाद में';

  @override
  String get closeApp => 'बंद अनुप्रयोग';

  @override
  String get updateNow => 'अभी अद्यतन करें';

  @override
  String get signInFailed => 'भाग लेना विफल हुआ';

  @override
  String get userCancelled => 'उपयोगकर्ता रद्द कर दिया';

  @override
  String get appleSigninIsNot => 'आपके डिवाइस के लिए Apple साइनइन उपलब्ध नहीं है';

  @override
  String get eventStatus => 'घटना स्थिति';

  @override
  String get eventAddedSuccessfully => 'घटना ने सफलतापूर्वक जोड़ा';

  @override
  String get notRegistered => 'पंजीकृत नहीं है?';

  @override
  String get signInWithGoogle => 'Google के साथ साइन इन करें';

  @override
  String get signInWithApple => 'Apple के साथ साइन इन करें';

  @override
  String get orSignInWith => 'या के साथ साइन इन करें';

  @override
  String get ohNoYouAreLeaving => 'अरे नहीं, आप जा रहे हैं!';

  @override
  String get oldPassword => 'पुराना पासवर्ड';

  @override
  String get oldAndNewPassword => 'पुराना और नया पासवर्ड समान हैं।';

  @override
  String get personalizeYourProfile => 'अपनी प्रोफ़ाइल को निजीकृत करें';

  @override
  String get themeAndMore => 'थीम और अधिक';

  @override
  String get showSomeLoveShare => 'कुछ प्यार दिखाओ, साझा करें!';

  @override
  String get privacyPolicyTerms => 'गोपनीयता नीति, नियम और शर्तें';

  @override
  String get securelyLogOutOfAccount => 'सुरक्षित रूप से खाते से बाहर लॉग आउट करें';

  @override
  String get termsOfService => 'सेवा की शर्तें';

  @override
  String get successfully => 'सफलतापूर्वक';

  @override
  String get clearAll => 'सभी साफ करें';

  @override
  String get notificationDeleted => 'अधिसूचना हटा दी गई';

  @override
  String get doYouWantToRemoveNotification => 'क्या आप अधिसूचना निकालना चाहते हैं';

  @override
  String get doYouWantToClearAllNotification => 'क्या आप अधिसूचना को स्पष्ट करना चाहते हैं';

  @override
  String get locationPermissionDenied => 'स्थान की अनुमति से वंचित';

  @override
  String get enableLocation => 'स्थान सक्षम करें';

  @override
  String get permissionDeniedPermanently => 'अनुमति ने स्थायी रूप से इनकार किया';

  @override
  String get chooseYourLocation => 'अपना स्थान चुनें';

  @override
  String get setAddress => 'सेट पता';

  @override
  String get sorryUserCannotSignin => 'क्षमा करें उपयोगकर्ता साइन इन नहीं कर सकता';

  @override
  String get iAgreeToThe => 'मैं करने के लिए सहमत हूं';

  @override
  String get logIn => 'लॉग इन करें';

  @override
  String get doYouConfirmThisAppointment => 'क्या आप इस अपॉइंटमेंट की पुष्टि करते हैं?';

  @override
  String get confirmAppointment => 'अपॉइंटमेंट की पुष्टि करें';

  @override
  String get iHaveReadAll => 'मैंने सभी विवरण पढ़े हैं और फॉर्म भरा है और मैं इस अपॉइंटमेंट की पुष्टि करूंगा';

  @override
  String get confirm => 'पुष्टि करें';

  @override
  String get doYouConfirmThisPayment => 'क्या आप इस भुगतान की पुष्टि करते हैं?';

  @override
  String get exploreTopClinicsWithAdvancedServicesTailored => "अपनी आवश्यकताओं के अनुरूप उन्नत सेवाओं के साथ शीर्ष क्लीनिक का अन्वेषण करें";

  @override
  String get discoverYourIdealClinicWithOurPersonalizedSea => "हमारी व्यक्तिगत खोज के साथ अपने आदर्श क्लिनिक की खोज करें।आएँ शुरू करें!";

  @override
  String get weHaveEmailedYourPasswordResetLink => "हमने आपका पासवर्ड रीसेट लिंक ईमेल किया है!";

  @override
  String get resetYourPassword => "अपना पासवर्ड रीसेट करें";

  @override
  String get enterYourEmailAddressToResetYourNewPassword => "अपना नया पासवर्ड रीसेट करने के लिए अपना ईमेल पता दर्ज करें।";

  @override
  String get sendCode => "कोड भेजो";

  @override
  String get edit => "संपादन करना";

  @override
  String get gender => "लिंग";

  @override
  String get profile => "प्रोफ़ाइल";

  @override
  String get encounters => "एन्काउंटर्स";

  @override
  String get seeYourEncounterData => "अपना एन्काउंटर डेटा देखें";

  @override
  String get version => "संस्करण";

  @override
  String get userNotCreated => "उपयोगकर्ता नहीं बनाया गया";

  @override
  String get notAMember => "सदस्य नहीं हैं?";

  @override
  String get registerYourAccountForBetterExperience => "बेहतर अनुभव के लिए अपना खाता पंजीकृत करें";

  @override
  String get termsConditions => "नियम एवं शर्तें";

  @override
  String get and => "और";

  @override
  String get privacyPolicy => "गोपनीयता नीति";

  @override
  String get appointment => "अपॉइंटमेंट";

  @override
  String get doctor => "चिकित्सक";

  @override
  String get payment => "भुगतान";

  @override
  String get doYouWantToCancelAppointment => "क्या आप अपॉइंटमेंट रद्द करना चाहते हैं?";

  @override
  String get videoCallLinkIsNotFound => "वीडियो कॉल लिंक नहीं मिला!";

  @override
  String get thisIsNotAOnlineService => "यह एक ऑनलाइन सेवा नहीं है!";

  @override
  String get oppsThisAppointmentIsNotConfirmedYet => "Opps!इस अपॉइंटमेंट की अभी तक पुष्टि नहीं हुई है!";

  @override
  String get oppsThisAppointmentHasBeenCancelled => "Opps!यह अपॉइंटमेंट रद्द कर दी गई है!";

  @override
  String get oppsThisAppointmentHasBeenCompleted => "Opps!यह अपॉइंटमेंट पूरी हो चुकी है!";

  @override
  String get noTimeSlotsAvailable => "कोई समय स्लॉट उपलब्ध नहीं है";

  @override
  String get chooseTime => "समय चुनें";

  @override
  String get rescheduleBooking => "पुनर्निर्मित बुकिंग";

  @override
  String get searchForService => "सेवा के लिए खोजें";

  @override
  String get statusListIsEmpty => "स्थिति सूची खाली है";

  @override
  String get thereAreNoStatusListedAtTheMomentStayTunedFor => "फिलहाल कोई स्थिति सूचीबद्ध नहीं है।अधिक विकल्पों के लिए बने रहें।";

  @override
  String get chooseDate => "तिथि चुनें";

  @override
  String get doYouWantToChangeTheTimeSlotOfThisAppointment => "क्या आप इस अपॉइंटमेंट का टाइम स्लॉट बदलना चाहते हैं?";

  @override
  String get no => "नहीं";

  @override
  String get somethingWentWrongPleaseTryAgainLater => "कुछ गलत हो गया।कृपया बाद में पुन: प्रयास करें।";

  @override
  String get doYouWantToRemoveThisReview => "क्या आप इस समीक्षा को हटाना चाहते हैं?";

  @override
  String get encounterDetail => "एन्काउंटर विवरण";

  @override
  String get view => "देखना";

  @override
  String get doctorName => "डॉक्टर का नाम";

  @override
  String get active => "सक्रिय";

  @override
  String get closed => "बंद किया हुआ";

  @override
  String get clinicName => "क्लिनिक नाम";

  @override
  String get description => "विवरण";

  @override
  String get payNow => "अब भुगतान करें";

  @override
  String get medicalReport => "चिकित्सा विवरण";

  @override
  String get paymentDetail => "भुगतान विवरण";

  @override
  String get price => "कीमत";

  @override
  String get discount => "छूट";

  @override
  String get off => "कम";

  @override
  String get subtotal => "उप-योग";

  @override
  String get tax => "कर";

  @override
  String get total => "कुल";

  @override
  String get yourReview => "आपकी समीक्षा";

  @override
  String get by => "द्वारा";

  @override
  String get youHaventRatedYet => "आपने अभी तक रेट नहीं किया है";

  @override
  String get yourFeedbackWillImproveOurService => "आपकी प्रतिक्रिया हमारी सेवा में सुधार करेगी।";

  @override
  String get writeHere => "यहाँ लिखें..";

  @override
  String get writeYourFeedbackHere => "अपनी प्रतिक्रिया यहाँ लिखें ..";

  @override
  String get pleaseSelectRatings => "कृपया रेटिंग का चयन करें";

  @override
  String get all => "सभी";

  @override
  String get upcoming => "आगामी";

  @override
  String get completed => "पुरा होना।";

  @override
  String get appointmentCancelSuccessfully => "अपॉइंटमेंट सफलतापूर्वक रद्द करें";

  @override
  String get appointments => "अपॉइंटमेंट";

  @override
  String get noAppointmentsFound => "कोई अपॉइंटमेंट नहीं मिली";

  @override
  String get thereAreCurrentlyNoAppointmentsAvailableStart => "वर्तमान में कोई अपॉइंटमेंट उपलब्ध नहीं है।अब अपनी अगली अपॉइंटमेंट बुक करना शुरू करें।";

  @override
  String get encounter => "सामना करना";

  @override
  String get basicInformation => "मूल जानकारी";

  @override
  String get problems => "समस्या";

  @override
  String get observations => "टिप्पणियों";

  @override
  String get notes => "टिप्पणियाँ";

  @override
  String get prescription => "नुस्खा";

  @override
  String get frequency => "आवृत्ति";

  @override
  String get days => "दिन";

  @override
  String get otherInformation => "अन्य सूचना";

  @override
  String get patientSoap => "रोगी साबुन";

  @override
  String get category => "वर्ग";

  @override
  String get noCategoryFound => "कोई श्रेणी नहीं मिली";

  @override
  String get viewDetail => "विस्तार से देखें";

  @override
  String get noServicesFoundAtAMoment => "एक पल में कोई सेवा नहीं मिली";

  @override
  String get looksLikeThereIsNoServicesForThis => "ऐसा लगता है कि इसके लिए कोई सेवा नहीं है";

  @override
  String get wellKeepYouPostedWhenTheresAnUpdate => "जब हम अपडेट करते हैं तो हम आपको पोस्ट करते रहेंगे।";

  @override
  String get services => "सेवाएं";

  @override
  String get sessions => "सत्र";

  @override
  String get clinicSessionsInformation => "क्लिनिक सत्र सूचना";

  @override
  String get servicesAvailable => "उपलब्ध सेवाएँ";

  @override
  String get noServicesAvailable => "कोई सेवा उपलब्ध नहीं है";

  @override
  String get doctors => "डॉक्टरों";

  @override
  String get noSystemServicesFoundAtAMoment => "एक पल में कोई सिस्टम सेवाएं नहीं मिली";

  @override
  String get looksLikeThereIsNoSystemServicesForThis => "ऐसा लगता है कि इसके लिए कोई सिस्टम सेवाएं नहीं हैं";

  @override
  String get appointmentsSummary => "अपॉइंटमेंट सारांश";

  @override
  String get date => "तारीख";

  @override
  String get time => "समय";

  @override
  String get service => "सेवा";

  @override
  String get clinic => "क्लिनिक";

  @override
  String get proceed => "आगे बढ़ना";

  @override
  String get video => "वीडियो";

  @override
  String get bookingForm => "बुकिंग फॉर्म";

  @override
  String get bookingInfo => "बुकिंग जानकारी";

  @override
  String get serviceName => "सेवा का नाम";

  @override
  String get chooseService => "सेवा चुनें";

  @override
  String get serviceListIsEmpty => "सेवा सूची खाली है।";

  @override
  String get thereAreNoServicesListedAtTheMomentStayTunedF => "फिलहाल कोई सेवाएं सूचीबद्ध नहीं हैं।अधिक सेवा प्रसाद के लिए बने रहें।";

  @override
  String get kindlyChooseAServiceFirst => "कृपया पहले एक सेवा चुनें";

  @override
  String get chooseClinic => "क्लिनिक चुनें";

  @override
  String get searchForClinic => "क्लिनिक के लिए खोजें";

  @override
  String get clinicListIsEmpty => "क्लिनिक सूची खाली है।";

  @override
  String get thereAreNoClinicsListedAtTheMomentStayTunedFo => "फिलहाल कोई क्लीनिक सूचीबद्ध नहीं है।अधिक क्लीनिकों के लिए बने रहें।";

  @override
  String get kindlyChooseAClinicFirst => "कृपया पहले एक क्लिनिक चुनें";

  @override
  String get chooseDoctor => "डॉक्टर चुनें";

  @override
  String get searchForDoctor => "डॉक्टर के लिए खोजें";

  @override
  String get thereAreNoDoctorsListedAtTheMomentStayTunedFo => "फिलहाल कोई डॉक्टर सूचीबद्ध नहीं हैं।अधिक विकल्पों के लिए बने रहें।";

  @override
  String get addMedicalHistory => "चिकित्सा इतिहास जोड़ें";

  @override
  String get paymentDetails => "भुगतान विवरण";

  @override
  String get asPerDoctorCharges => "डॉक्टर के शुल्क के अनुसार";

  @override
  String get next => "अगला";

  @override
  String get personalizedHealthPlansForYourJourney => "अपनी यात्रा के लिए व्यक्तिगत स्वास्थ्य योजनाएं";

  @override
  String get stayOnTrackAndSetPersonalGoals => "ट्रैक पर रहें और व्यक्तिगत लक्ष्य निर्धारित करें";

  @override
  String get discoverAndGetSupportWithin24Hours => "खोज करें और 24 घंटे के भीतर समर्थन प्राप्त करें";

  @override
  String get customizeHealthPlansForATailoredApproachAlign => "एक अनुरूप दृष्टिकोण के लिए स्वास्थ्य योजनाओं को अनुकूलित करें, अपनी आवश्यकताओं के साथ प्रत्येक पहलू को संरेखित करें।";

  @override
  String get focusOnYourPathSetClearGoalsAndStrideForwardW => "अपने मार्ग पर ध्यान दें, स्पष्ट लक्ष्य निर्धारित करें, और दृढ़ संकल्प और उद्देश्य के साथ आगे बढ़ें।";

  @override
  String get exploreFindSolutionsAndReceiveAssistanceSwift => "अन्वेषण करें, समाधान खोजें, और सहायता प्राप्त करें तेजी से आपका समर्थन नेटवर्क 24 घंटे के भीतर तैयार है।";

  @override
  String get transactionIsInProcess => 'लेन-देन प्रक्रिया में है...';

  @override
  String get enterYourMsisdnHere => 'यहां अपना एमएसआईएसडीएन दर्ज करें';

  @override
  String get pleaseCheckThePayment => 'कृपया जांच लें कि भुगतान अनुरोध आपके नंबर पर भेजा गया है';

  @override
  String get ambiguous => 'अस्पष्ट';

  @override
  String get success => 'सफलता';

  @override
  String get incorrectPin => 'ग़लत पिन';

  @override
  String get exceedsWithdrawalAmountLimit => 'निकासी राशि सीमा से अधिक / निकासी राशि सीमा से अधिक';

  @override
  String get inProcess => 'प्रक्रिया में';

  @override
  String get transactionTimedOut => 'लेन-देन का समय समाप्त हो गया';

  @override
  String get notEnoughBalance => 'पर्याप्त संतुलन नहीं';

  @override
  String get refused => 'अस्वीकार करना';

  @override
  String get doNotHonor => 'सम्मान मत कर';

  @override
  String get transactionNotPermittedTo => 'भुगतानकर्ता को लेनदेन की अनुमति नहीं है';

  @override
  String get transactionIdIsInvalid => 'लेन-देन आईडी अमान्य है';

  @override
  String get errorWhileFetchingEncryption => 'एन्क्रिप्शन कुंजी लाते समय त्रुटि';

  @override
  String get transactionExpired => 'लेन-देन समाप्त हो गया';

  @override
  String get invalidAmount => 'अवैध राशि';

  @override
  String get transactionNotFound => 'लेनदेन नहीं मिला';

  @override
  String get successfullyFetchedEncryptionKey => 'एन्क्रिप्शन कुंजी सफलतापूर्वक प्राप्त की गई';

  @override
  String get theTransactionIsStill => 'लेन-देन अभी भी संसाधित हो रहा है और अस्पष्ट स्थिति में है। कृपया लेन-देन की स्थिति जानने के लिए लेन-देन संबंधी पूछताछ करें।';

  @override
  String get transactionIsSuccessful => 'लेन-देन सफल है';

  @override
  String get incorrectPinHasBeen => 'ग़लत पिन दर्ज किया गया है';

  @override
  String get theUserHasExceeded => 'उपयोगकर्ता ने अपने वॉलेट द्वारा अनुमत लेनदेन सीमा पार कर ली है';

  @override
  String get theAmountUserIs => 'उपयोगकर्ता जिस राशि को स्थानांतरित करने का प्रयास कर रहा है वह अनुमत न्यूनतम राशि से कम है';

  @override
  String get userDidnTEnterThePin => 'उपयोगकर्ता ने पिन दर्ज नहीं किया';

  @override
  String get transactionInPendingState => 'लेनदेन लंबित स्थिति में. कृपया कुछ देर बाद जांचें';

  @override
  String get userWalletDoesNot => 'उपयोगकर्ता के वॉलेट में देय राशि को कवर करने के लिए पर्याप्त धन नहीं है';

  @override
  String get theTransactionWasRefused => 'लेन-देन से इनकार कर दिया गया';

  @override
  String get encryptionKeyHasBeen => 'एन्क्रिप्शन कुंजी सफलतापूर्वक प्राप्त कर ली गई है';

  @override
  String get transactionHasBeenExpired => 'लेन-देन समाप्त हो गया है';

  @override
  String get payeeIsAlreadyInitiated => 'भुगतानकर्ता पहले से ही मंथन के लिए शुरू किया गया है या प्रतिबंधित है या एयरटेल मनी प्लेटफॉर्म पर पंजीकृत नहीं है';

  @override
  String get theTransactionWasNot => 'लेन-देन नहीं मिला.';

  @override
  String get thisIsAGeneric => 'यह एक सामान्य इनकार है जिसके कई संभावित कारण हैं';

  @override
  String get theTransactionWasTimed => 'लेन-देन का समय समाप्त हो गया था.';

  @override
  String get xSignatureAndPayloadDid => 'एक्स-हस्ताक्षर और पेलोड मेल नहीं खाते';

  @override
  String get couldNotFetchEncryption => 'एन्क्रिप्शन कुंजी नहीं लायी जा सकी';

  @override
  String get transactionFailed => 'लेन - देन विफल';

  @override
  String get transactionCancelled => 'लेन-देन रद्द कर दिया गया';

  @override
  String get paymentSuccess => "भुगतान की सफलता";

  @override
  String get redirectingToBookings => "बुकिंग के लिए पुनर्निर्देशन ..";

  @override
  String get pleaseConfirmYourAppointmentByCheckingTheBox => "कृपया बॉक्स की जाँच करके अपनी अपॉइंटमेंट की पुष्टि करें";

  @override
  String get appointmentDetail => "अपॉइंटमेंट विवरण";

  @override
  String get reschedule => "पुनर्निर्धारित";

  @override
  String get invoice => "चालान";

  @override
  String get dateTime => "दिनांक समय";

  @override
  String get appointmentStatus => "अपॉइंटमेंट की स्थिति";

  @override
  String get paymentStatus => "भुगतान की स्थिति";

  @override
  String get subjective => "व्यक्तिपरक";

  @override
  String get objective => "उद्देश्य";

  @override
  String get assessment => "आकलन";

  @override
  String get plan => "योजना";

  @override
  String get bodyChart => "निकाय चार्ट";

  @override
  String get doctorsAvailable => "डॉक्टर उपलब्ध हैं";

  @override
  String get noDoctorsAvailable => "कोई डॉक्टर उपलब्ध नहीं है";

  @override
  String get photosAvailable => "तस्वीरें उपलब्ध हैं";

  @override
  String get noPhotosAvailable => "कोई फ़ोटो उपलब्ध नहीं है";

  @override
  String get looksLikeThereIsNoServicesListedOnThisClinicW => "ऐसा लगता है कि इस क्लिनिक पर कोई सेवाएं सूचीबद्ध नहीं हैं, जब कोई अपडेट होता है तो हम आपको पोस्ट करते रहेंगे।";

  @override
  String get session => "सत्र";

  @override
  String get unavailable => "अनुपलब्ध";

  @override
  String get lblBreak => "तोड़ना";

  @override
  String get clinicDetail => "क्लिनिक विवरण";

  @override
  String get pincode => "पिन कोड";

  @override
  String get readMore => "और पढ़ें";

  @override
  String get readLess => "कम पढ़ें";

  @override
  String get noGalleryFoundAtAMoment => "एक पल में कोई गैलरी नहीं मिली";

  @override
  String get looksLikeThereIsNoGalleryForThisClinicWellKee => "ऐसा लगता है कि इस क्लिनिक के लिए कोई गैलरी नहीं है, जब कोई अपडेट होगा तो हम आपको पोस्ट करते रहेंगे।";

  @override
  String get clinics => "क्लिनिक";

  @override
  String get availableClinicsFor => "के लिए उपलब्ध क्लीनिक";

  @override
  String get noClinicsFoundAtAMoment => "एक पल में कोई क्लीनिक नहीं मिला";

  @override
  String get looksLikeThereIsNoClinicForThisServiceWellKee => "ऐसा लगता है कि इस सेवा के लिए कोई क्लिनिक नहीं है, जब कोई अपडेट होगा तो हम आपको पोस्ट करते रहेंगे।";

  @override
  String get searchClinicHere => "यहां खोज क्लिनिक";

  @override
  String get home => "घर";

  @override
  String get aboutMyself => "खुद के बारे में";

  @override
  String get about => "के बारे में";

  @override
  String get contactInfo => "संपर्क सूचना";

  @override
  String get specialization => "विशेषज्ञता";

  @override
  String get experience => "अनुभव";

  @override
  String get experienceSpecializationContactInfo => "अनुभव, विशेषज्ञता, संपर्क जानकारी";

  @override
  String get reviews => "समीक्षा";

  @override
  String get noReviewsAvailable => "कोई समीक्षा उपलब्ध नहीं है";

  @override
  String get qualification => "योग्यता";

  @override
  String get qualificationInDetail => "विस्तार से योग्यता";

  @override
  String get year => "वर्ष";

  @override
  String get degree => "डिग्री";

  @override
  String get university => "विश्वविद्यालय";

  @override
  String get noQualificationsFound => "कोई योग्यता नहीं मिली!";

  @override
  String get looksLikeThereAreNoQualificationsAddedByThisD => "ऐसा लगता है कि इस डॉक्टर द्वारा कोई योग्यता नहीं है।";

  @override
  String get totalAppointmentsDone => "कुल अपॉइंटमेंट";

  @override
  String get looksLikeThereIsNoServicesProvidedByThisDocto => "ऐसा लगता है कि इस डॉक्टर द्वारा प्रदान की गई कोई सेवाएं नहीं हैं, जब कोई अपडेट होता है तो हम आपको पोस्ट करते रहेंगे।";

  @override
  String get doctorDetail => "चिकित्सक विवरण";

  @override
  String get socialMedia => "सामाजिक मीडिया";

  @override
  String get noDoctorsFoundAtAMoment => "एक पल में कोई डॉक्टर नहीं मिला";

  @override
  String get looksLikeThereIsNoDoctorsForThisClinicWellKee => "ऐसा लगता है कि इस क्लिनिक के लिए कोई डॉक्टर नहीं है, जब कोई अपडेट होगा तो हम आपको पोस्ट करते रहेंगे।";

  @override
  String get noReviewsFoundAtAMoment => "एक पल में कोई समीक्षा नहीं मिली";

  @override
  String get looksLikeThereIsNoReviewsWellKeepYouPostedWhe => "ऐसा लगता है कि कोई समीक्षा नहीं है, अपडेट होने पर हम आपको पोस्ट करते रहेंगे।";

  @override
  String get searchHere => "यहां तलाश करो";

  @override
  String get searchDoctorHere => "यहां खोज डॉक्टर";

  @override
  String get noEncountersFound => "कोई एन्काउंटर नहीं मिला!";

  @override
  String get looksLikeThereIsNoEncountersWellKeepYouPosted => "ऐसा लगता है कि कोई एन्काउंटर नहीं है, अपडेट होने पर हम आपको पोस्ट करते रहेंगे।";

  @override
  String get clinicsNearYou => "आप के पास क्लीनिक";

  @override
  String get upcomingAppointments => "आगामी अपॉइंटमेंट";

  @override
  String get great => "महान!";

  @override
  String get bookingSuccessful => "बुकिंग सफल";

  @override
  String get yourAppointmentHasBeenBookedSuccessfully => "आपकी अपॉइंटमेंट सफलतापूर्वक बुक की गई है";

  @override
  String get totalPayment => "कुल भुगतान";

  @override
  String get goToAppointments => "अपॉइंटमेंट के लिए जाना";

  @override
  String get noteForCashPaymentPurposesDontUseThePayNowBut =>
      "नोट: नकद भुगतान उद्देश्यों के लिए, 'पे नाउ' बटन का उपयोग न करें।यदि आप नकदी के साथ भुगतान करना चाहते हैं, तो आप मैन्युअल रूप से डॉक्टर को नकद दे सकते हैं और डॉक्टर की ओर से अपनी अपॉइंटमेंट को पूरा कर सकते हैं।";

  @override
  String get choosePaymentMethod => "भुगतान का तरीका चुनें";

  @override
  String get chooseOurConvenientPaymentOptionAndUnlockUnli => "हमारे सुविधाजनक भुगतान विकल्प चुनें और अनन्य विशेषाधिकारों के लिए असीमित पहुंच को अनलॉक करें।";

  @override
  String get doYouWantToReplaceThePreviousServiceWithTheCu => "क्या आप पिछली सेवा को वर्तमान एक के साथ बदलना चाहते हैं?";

  @override
  String get bookNow => "अभी बुक करें";

  @override
  String get aboutService => "सेवा के बारे में";

  @override
  String get advancePayableAmount => "अग्रिम देय राशि";

  @override
  String get advancePaidAmount => "अग्रिम भुगतान राशि";

  @override
  String get remainingPayableAmount => "शेष देय राशि";

  @override
  String get walletHistory => "बटुए का इतिहास";

  @override
  String get noWalletDataFound => "कोई वॉलेट डेटा नहीं मिला!";

  @override
  String get oppsNoWalletDataFoundAtAMoment => "Opps!एक पल में कोई वॉलेट डेटा नहीं मिला।";

  @override
  String get walletBalance => "बटुआ शेष";

  @override
  String get addFiles => 'फाइलें जोड़ो';

  @override
  String get file => 'फ़ाइल';

  @override
  String get apply => 'लागू करें';

  @override
  String get filterBy => 'द्वार';

  @override
  String get priceRange => 'मूल्य सीमा';

  @override
  String get reset => 'रीसेट';

  @override
  String get serviceType => 'सेवा प्रकार';

  @override
  String get dateOfBirth => 'जन्मतिथि';

  @override
  String get passwordLengthShouldBe8To14Characters => 'पासवर्ड की लंबाई 8 से 14 अक्षर होनी चाहिए';

  @override
  String get noteInCaseYouFailToMakeTheAdvancePaymentYouWi =>
      '* नोट: यदि आप अग्रिम भुगतान करने में विफल रहते हैं, तो आपको नीचे दिए गए ""अभी भुगतान करें"" बटन पर क्लिक करके अग्रिम देय राशि का भुगतान करना होगा। अन्यथा, आपकी अपॉइंटमेंट पर कार्रवाई नहीं की जाएगी, या आपको नई अपॉइंटमेंट बुक करने की आवश्यकता होगी।';

  @override
  String get serviceTotal => 'सेवा कुल';

  @override
  String get remainingAmount => 'बाकी अमाउंट';

  @override
  String get refundableAmount => 'वापसीयोग्य राशि';

  @override
  String get appointmentId => 'अपॉइंटमेंट आईडी:';

  @override
  String get youDontHaveEnoughBalanceToCompleteThePaymentU => 'आपके पास अपने वॉलेट का उपयोग करके भुगतान पूरा करने के लिए पर्याप्त शेष नहीं है।';

  @override
  String get advancePayment => 'अग्रिम भुगतान';

  @override
  String get doYouWantToPerformThisAction => 'क्या आप यह क्रिया करना चाहते हैं?';

  @override
  String get bookedFor => 'के लिए बुक किया गया';

  @override
  String get addPatient => 'रोगी जोड़ें';

  @override
  String get relation => 'रिश्ता';

  @override
  String get save => 'बचाना';

  @override
  String get managePatient => 'रोगी का प्रबंधन करें';

  @override
  String get otherPatient => 'अन्य रोगी';

  @override
  String get manageOtherPatient => 'अन्य रोगी का प्रबंधन करें';

  @override
  String get genderWithColon => 'लिंग:';

  @override
  String get contactNumberWithColon => 'संपर्क संख्या:';

  @override
  String get dobWithColon => 'डी-ओ-बी:';

  @override
  String get noPatientsFound => 'कोई मरीज़ नहीं मिला';

  @override
  String get editPatient => 'रोगी संपादित करें';

  @override
  String get doYouWantToDeleteYourOtherPatientsProfile => 'क्या आप अपने दूसरे मरीज़ की प्रोफ़ाइल हटाना चाहते हैं?';

  @override
  String get birthdateIsRequired => 'जन्मतिथि आवश्यक है';

  @override
  String get selectBirthdate => 'जन्मतिथि चुनें';

  @override
  String get bookedForWithColon => 'इसके लिए बुक किया गया: ';

  @override
  String get inClinic => 'क्लिनिक में';

  @override
  String get online => 'ऑनलाइन';

  @override
  String get pending => 'लंबित';

  @override
  String get confirmed => 'की पुष्टि';

  @override
  String get checkIn => 'चेक इन';

  @override
  String get cancelled => 'रद्द कर दिया गया';

  @override
  String get advancePaid => 'अग्रिम भुगतान';

  @override
  String get paid => 'चुकाया गया';

  @override
  String get advanceRefunded => 'अग्रिम धन वापसी';

  @override
  String get refunded => 'वापसी की गई है';

  @override
  String get failed => 'असफल';

  @override
  String get ourPopularDoctor => 'हमारे लोकप्रिय डॉक्टर';

  @override
  String get ourPopularSevices => 'हमारी लोकप्रिय सेवाएं';

  @override
  String get ourPopularClinics => 'हमारी लोकप्रिय क्लिनिक';

  @override
  String get newAppointmentBooked => 'नई अपॉइंटमेंट बुक की गई';

  @override
  String get appointmentCompleted => 'अपॉइंटमेंट पूर्ण';

  @override
  String get appointmentRejected => 'अपॉइंटमेंट अस्वीकृत';

  @override
  String get appointmentCancelled => 'अपॉइंटमेंट रद्द कर दी गई';

  @override
  String get appointmentRescheduled => 'अपॉइंटमेंट पुनर्निर्धारित';

  @override
  String get appointmentAccepted => 'अपॉइंटमेंट स्वीकृत';

  @override
  String get forgetEmailPassword => 'ईमेल पासवर्ड भूल जाओ';

  @override
  String get parents => 'अभिभावक';

  @override
  String get brother => 'भाई';

  @override
  String get siblings => 'भाई-बहन';

  @override
  String get spouse => 'जीवनसाथी';

  @override
  String get relative => 'रिश्तेदार';

  @override
  String get deleteConfirmation => 'पुष्टिकरण हटाएँ';

  @override
  String get patientUpdatedSuccessfully => 'मरीज़ का अद्यतनीकरण सफलतापूर्वक हुआ';

  @override
  String get patientAddedSuccessfully => 'मरीज़ सफलतापूर्वक जोड़ा गया';

  @override
  String get recordDeletedSuccessfully => 'रिकॉर्ड सफलतापूर्वक हटा दिया गया';

  @override
  String get male => 'पुरुष';

  @override
  String get female => 'महिला';

  @override
  String get other => 'अन्य';

  @override
  String get others => 'अन्य';

  @override
  String get appliedInclusiveTaxes => "लागू समावेशी कर";

  @override
  String get includesInclusiveTax => "समावेशी कर शामिल";

  @override
  String get inclusiveTaxes => "समावेशी कर";

  @override
  String get servicePrice => "सेवा मूल्य";

  @override
  String get inclusiveTax => "समावेशी कर";

  @override
  String get appliedExclusiveTaxes => "लागू विशेष कर";

  @override
  String get exclusiveTax => "विशेष कर";

  @override
  String get appliedTaxes => "लागू कर";

  @override
  String cancellationChargesWillBeAppliedForCancellationWithin(String amount, String hours) => "$hours घंटे के भीतर रद्द करने पर $amount की रद्दीकरण शुल्क लागू होगी।";

  @override
  String get cancelAppointment => "अपॉइंटमेंट रद्द करें";

  @override
  String get goBack => "वापस जाएं";

  @override
  String cancellationFeesWillBeAppliedIfYouCancelWithinHoursOfScheduledTime(String hours, bool isCancellationChargesEnabled) =>
      "क्या आप इस अपॉइंटमेंट को रद्द करना चाहते हैं? ${isCancellationChargesEnabled ? 'निर्धारित समय से $hours घंटे के भीतर रद्द करने पर रद्दीकरण शुल्क लागू होगा' : ''}";

  @override
  String get reason => "कारण";

  @override
  String get continueText => "जारी रखें";

  @override
  String get wouldYouLikeToProceedAndConfirmPayment => "क्या आप आगे बढ़ना और भुगतान की पुष्टि करना चाहेंगे?";

  @override
  String get cancellationFee => "रद्दीकरण शुल्क";

  @override
  String get yourAppointmentHasBeenSuccessfullyCancelled => "आपकी अपॉइंटमेंट सफलतापूर्वक रद्द कर दी गई है";

  @override
  String get appointmentRefundWillBeProcessedWithingHoursIfApplicable => "यदि लागू हो, तो अपॉइंटमेंट की धनवापसी 24 घंटे के भीतर संसाधित की जाएगी।";

  @override
  String get noteCheckYourAppointmentHistoryForRefundDetailsIfApplicable => "*नोट: यदि लागू हो, तो धनवापसी के विवरण के लिए अपने अपॉइंटमेंट इतिहास की जांच करें।";

  @override
  String get ok => "ठीक है";

  @override
  String get hintReason => "उदा. मैंने अपना मन बदल लिया, आदि।";

  @override
  String get medicalHistory => "चिकित्सा इतिहास";

  @override
  String get clinicClosed => "क्लिनिक बंद है";

  @override
  String get satisfactionToCustomer => "ग्राहक संतुष्टि";

  @override
  String get totalVerifiedPatients => "कुल सत्यापित डॉक्टर";

  @override
  String get encounterId => "मुलाकात आईडी: ";

  @override
  String get dateIsNotSelected => 'दिनांक चयनित नहीं है';

  @override
  String get selectClinic => 'क्लिनिक का चयन करें';

  @override
  String get selectService => 'सेवा का चयन करें';

  @override
  String get quicklyBookYourAppointmentNow => 'अभी तुरंत अपना अपॉइंटमेंट बुक करें';

  @override
  String get noDataFound => 'डाटा प्राप्त नहीं हुआ';

  @override
  String get filterService => 'सेवा';

  @override
  String get filterRating => 'रेटिंग';

  @override
  String get filterCategory => 'श्रेणी';

  @override
  String get incidentManagement => 'घटना का प्रबंधन';

  @override
  String get requestHelpForAnyMistakeHappen => 'किसी भी गलती के लिए सहायता का अनुरोध करें';

  @override
  String get otp => 'ओटीपी';

  @override
  String get verify => 'सत्यापित करें';

  @override
  String get closedOn => 'बंद';

  @override
  String get viewDetails => 'विवरण देखें';

  @override
  String get title => 'शीर्षक';

  @override
  String get enterYourDetailDescriptionForYourComplaint => 'अपनी शिकायत के लिए अपना विस्तृत विवरण दर्ज करें';

  @override
  String get phoneNumber => 'फ़ोन नंबर';

  @override
  String get chooseImage => 'छवि चुनें';

  @override
  String get browse => 'ब्राउज़';

  @override
  String get noQueryYet => 'अभी तक कोई प्रश्न नहीं';

  @override
  String get add => 'जोड़ना';

  @override
  String get toSubmitYourProblemsSimplyPressAddButtonAndExplainYourConcern => 'अपनी समस्याएं प्रस्तुत करने के लिए बस जोड़ें बटन दबाएं और अपनी चिंता बताएं';

  @override
  String get tryToAnotherWay => 'किसी अन्य तरीके से प्रयास करें';

  @override
  String get pleaseEnterValid6digitOTP => 'कृपया वैध 6-अंकीय OTP दर्ज करें';

  @override
  String get otpFromAuthenticatorApp => 'प्रमाणक ऐप से OTP';

  @override
  String get open => 'खुला';

  @override
  String get close => 'बंद करना';

  @override
  String get pleaseEnterValidEmail => 'कृपया मान्य ईमेल दर्ज करें';

  @override
  String get pleaseEnterOTP => 'कृपया ओटीपी दर्ज करें';

  @override
  String get passwordMustIncludeSpacialCharacter => 'पासवर्ड में कम से कम एक विशेष वर्ण अवश्य शामिल होना चाहिए';

  @override
  String get passwordMustIncludeAtLeastOneLowercaseCharacter => 'पासवर्ड में कम से कम एक लोअरकेस वर्ण अवश्य शामिल होना चाहिए';

  @override
  String get passwordMustIncludeAtLeastOneNumber => 'पासवर्ड में कम से कम एक अंक होना आवश्यक है';

  @override
  String get passwordMustIncludeAtLeastOneCapitalCharacter => 'पासवर्ड में कम से कम एक बड़ा अक्षर अवश्य होना चाहिए';

  @override
  String get addFile => 'फ़ाइल जोड़ें';

  @override
  String get addMedicalReport => 'चिकित्सा रिपोर्ट जोड़ें';

  @override
  String get optional => '(वैकल्पिक)';

  @override
  String get reply => 'जवाब';

  @override
  String get passwordIsRequired => "पासवर्ड आवश्यक है";

  @override
  String get passwordDoesNotMeetRequirements => "पासवर्ड आवश्यक शर्तों को पूरा नहीं करता है";

  @override
  String get passwordTooShort => "पासवर्ड कम से कम 8 अक्षरों का होना चाहिए";

  @override
  String get emailHasAlreadyBeenTaken => 'ईमेल पहले ही ली जा चुकी है।';

  @override
  String get markAsClosed => 'बंद के रूप में चिह्नित करें';

  @override
  String get hideMessage => 'संदेश छुपाएं';

  @override
  String get showMessage => 'संदेश दिखाएँ';

  @override
  String get createdBy => 'के द्वारा बनाई गई';

  @override
  String get incident => 'घटना';

  @override
  String get reject => 'अस्वीकार करना';

  @override
  String get successfullyAdded => 'सफलतापूर्वक जोड़ा गया';

  @override
  String get otpSentToEmail => "आपके ईमेल पर ओटीपी भेजा गया है, कृपया जारी रखने के लिए सत्यापित करें";

  @override
  String get rejected => "अस्वीकृत";

  @override
  String get incidenceReportReply => "घटना रिपोर्ट का उत्तर";

  @override
  String get resendOTP => 'OTP पुनः भेजें';

  @override
  String get quantity => "मात्रा";

  @override
  String get instruction => "निर्देश";

  @override
  String get updateTo => 'इस अपडेट करें';

  @override
  String get aVertionUpdateIsAvailable => 'रोमांचक सुविधाओं के साथ एक नया संस्करण उपलब्ध है!रोमांचक सुविधाओं के साथ एक नया संस्करण उपलब्ध है!';

  @override
  String get available => 'उपलब्ध';

  @override
  String get inAppUpdate => 'ऐप अपडेट में';

  @override
  String get consultationType => 'परामर्श प्रकार';

  @override
  String get bookWith => 'के साथ बुक करें';

  @override
  String get addOtherPatient => "अन्य रोगी जोड़ें";

  @override
  String get readAll => 'सभी पढ़ें';

  @override
  String get doYouWantToReadAllNotification => 'क्या आप सभी सूचनाएँ पढ़ना चाहते हैं?';

  @override
  String get bookingWith => 'बुकिंग के साथ';

  @override
  String get enterYourPhoneNumber => 'अपना फोन नंबर दर्ज करें';

  @override
  String get pleaseEnterPhoneNumber => 'कृपया फ़ोन नंबर दर्ज करें';

  @override
  String get pleaseEnterValidPhoneNumber => 'कृपया मान्य फ़ोन नंबर दर्ज करें';

  @override
  String get pleaseSelectService => 'कृपया सेवा चुनें';

  @override
  String get pleaseSelectDoctor => 'कृपया डॉक्टर चुनें';

  @override
  String get pleaseSelectClinic => 'कृपया क्लिनिक चुनें';

  @override
  String get bookingFor => 'बुकिंग के लिए';

  @override
  String get selectedFileShouldBeLessThan5MB => 'कृपया 5 MB से छोटी छवि चुनें';

  @override
  String get noFileSelected => 'कोई फ़ाइल चयनित नहीं';

  @override
  String errorImageTooLarge(String size) => 'कृपया 2 एमबी से छोटी छवि चुनें। (चुना गया: $size एमबी)';

  @override
  String get selectFirstDate => 'पहली तारीख चुनें';

  @override
  String get selectLastDate => 'अंतिम तिथि चुनें';

  @override
  String get form => 'रूप';

  @override
  String get dosage => 'खुराक';

  @override
  String get medicines => 'दवाइयाँ';

  @override
  String get prescriptions => 'प्रिस्क्रिप्शन';

  @override
  String get prescriptionAmount => 'प्रिस्क्रिप्शन राशि';

  @override
  String get prescriptionExclusiveTax => 'प्रिस्क्रिप्शन एक्सक्लूसिव टैक्स';

  @override
  String get prescriptionStatus => 'प्रिस्क्रिप्शन स्थिति';

  @override
  String get prescriptionPaymentStatus => 'प्रिस्क्रिप्शन भुगतान स्थिति';

  @override
  String get unpaid => 'अवैतनिक';

  @override
  String get bedDetails => 'बिस्तर का विवरण';

  @override
  String get roomNumber => 'कमरा संख्या:';

  @override
  String get bedType => 'बिस्तर का प्रकार:';

  @override
  String get assignDate => 'आवंटन तिथि:';

  @override
  String get dischargeDate => 'छुट्टी की तारीख:';

  @override
  String get bedPrice => 'बिस्तर की कीमत';

  @override
  String get perDay => 'प्रति दिन';

  @override
  String get bedHistory => 'बिस्तर का इतिहास';

  @override
  String get seeYourBedHistory => 'अपना बिस्तर इतिहास देखें';

  @override
  String get noBedHistoryFound => 'कोई बिस्तर इतिहास नहीं मिला';

  @override
  String get looksNoBedHistoryFound => 'ऐसा लगता है कि कोई बिस्तर इतिहास नहीं मिला है, जब कोई अपडेट होगा तो हम आपको सूचित करेंगे।';

  @override
  String get bedNumber => 'बिस्तर संख्या';

  @override
  String get perDayCharge => 'प्रति दिन शुल्क';

  @override
  String get totalCharges => 'कुल शुल्क';

  @override
  String get grandTotal => 'कुल योग';
}
