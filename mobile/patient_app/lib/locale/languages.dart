import 'package:flutter/material.dart';

abstract class BaseLanguage {
  static BaseLanguage of(BuildContext context) => Localizations.of<BaseLanguage>(context, BaseLanguage)!;

  String get language;

  String get badRequest;

  String get forbidden;

  String get pageNotFound;

  String get tooManyRequests;

  String get internalServerError;

  String get badGateway;

  String get serviceUnavailable;

  String get gatewayTimeout;

  String get hey;

  String get hello;

  String get thisFieldIsRequired;

  String get contactNumber;

  String get gallery;

  String get camera;

  String get editProfile;

  String get update;

  String get reload;

  String get address;

  String get viewAll;

  String get pressBackAgainToExitApp;

  String get invalidUrl;

  String get cancel;

  String get delete;

  String get deleteAccountConfirmation;

  String get demoUserCannotBeGrantedForThis;

  String get somethingWentWrong;

  String get yourInternetIsNotWorking;

  String get profileUpdatedSuccessfully;

  String get wouldYouLikeToSetProfilePhotoAs;

  String get yourOldPasswordDoesnT;

  String get yourNewPasswordDoesnT;

  String get location;

  String get yes;

  String get submit;

  String get firstName;

  String get lastName;

  String get changePassword;

  String get yourNewPasswordMust;

  String get password;

  String get newPassword;

  String get confirmNewPassword;

  String get email;

  String get mainStreet;

  String get toResetYourNew;

  String get stayTunedNoNew;

  String get noNewNotificationsAt;

  String get signIn;

  String get explore;

  String get settings;

  String get rateApp;

  String get aboutApp;

  String get logout;

  String get rememberMe;

  String get forgotPassword;

  String get forgotPasswordTitle;

  String get registerNow;

  String get createYourAccount;

  String get createYourAccountFor;

  String get signUp;

  String get alreadyHaveAnAccount;

  String get yourPasswordHasBeen;

  String get youCanNowLog;

  String get done;

  String get pleaseAcceptTermsAnd;

  String get deleteAccount;

  String get eG;

  String get merry;

  String get doe;

  String get welcomeBackToThe;

  String get welcomeToThe;

  String get doYouWantToLogout;

  String get appTheme;

  String get guest;

  String get notifications;

  String get contactUs;

  String get getInTouchWithSupport;

  String get newUpdate;

  String get anUpdateTo;

  String get isAvailableGoTo;

  String get later;

  String get closeApp;

  String get updateNow;

  String get signInFailed;

  String get userCancelled;

  String get appleSigninIsNot;

  String get eventStatus;

  String get eventAddedSuccessfully;

  String get notRegistered;

  String get signInWithGoogle;

  String get signInWithApple;

  String get orSignInWith;

  String get ohNoYouAreLeaving;

  String get oldPassword;

  String get oldAndNewPassword;

  String get personalizeYourProfile;

  String get themeAndMore;

  String get showSomeLoveShare;

  String get privacyPolicyTerms;

  String get securelyLogOutOfAccount;

  String get termsOfService;

  String get successfully;

  String get clearAll;

  String get notificationDeleted;

  String get doYouWantToRemoveNotification;

  String get doYouWantToClearAllNotification;

  String get locationPermissionDenied;

  String get enableLocation;

  String get permissionDeniedPermanently;

  String get chooseYourLocation;

  String get setAddress;

  String get sorryUserCannotSignin;

  String get iAgreeToThe;

  String get logIn;

  String get doYouConfirmThisAppointment;

  String get confirmAppointment;

  String get iHaveReadAll;

  String get confirm;

  String get doYouConfirmThisPayment;

  String get exploreTopClinicsWithAdvancedServicesTailored;

  String get discoverYourIdealClinicWithOurPersonalizedSea;

  String get weHaveEmailedYourPasswordResetLink;

  String get resetYourPassword;

  String get enterYourEmailAddressToResetYourNewPassword;

  String get sendCode;

  String get edit;

  String get gender;

  String get profile;

  String get encounters;

  String get seeYourEncounterData;

  String get version;

  String get userNotCreated;

  String get notAMember;

  String get registerYourAccountForBetterExperience;

  String get termsConditions;

  String get and;

  String get privacyPolicy;

  String get appointment;

  String get doctor;

  String get payment;

  String get doYouWantToCancelAppointment;

  String get videoCallLinkIsNotFound;

  String get thisIsNotAOnlineService;

  String get oppsThisAppointmentIsNotConfirmedYet;

  String get oppsThisAppointmentHasBeenCancelled;

  String get oppsThisAppointmentHasBeenCompleted;

  String get noTimeSlotsAvailable;

  String get chooseTime;

  String get rescheduleBooking;

  String get searchForService;

  String get statusListIsEmpty;

  String get thereAreNoStatusListedAtTheMomentStayTunedFor;

  String get chooseDate;

  String get doYouWantToChangeTheTimeSlotOfThisAppointment;

  String get no;

  String get somethingWentWrongPleaseTryAgainLater;

  String get doYouWantToRemoveThisReview;

  String get encounterDetail;

  String get view;

  String get doctorName;

  String get active;

  String get closed;

  String get clinicName;

  String get description;

  String get payNow;

  String get medicalReport;

  String get paymentDetail;

  String get price;

  String get discount;

  String get off;

  String get subtotal;

  String get tax;

  String get total;

  String get yourReview;

  String get by;

  String get youHaventRatedYet;

  String get yourFeedbackWillImproveOurService;

  String get writeHere;

  String get writeYourFeedbackHere;

  String get pleaseSelectRatings;

  String get all;

  String get upcoming;

  String get completed;

  String get appointmentCancelSuccessfully;

  String get appointments;

  String get noAppointmentsFound;

  String get thereAreCurrentlyNoAppointmentsAvailableStart;

  String get encounter;

  String get basicInformation;

  String get problems;

  String get observations;

  String get notes;

  String get prescription;

  String get frequency;

  String get days;

  String get otherInformation;

  String get patientSoap;

  String get category;

  String get noCategoryFound;

  String get viewDetail;

  String get noServicesFoundAtAMoment;

  String get looksLikeThereIsNoServicesForThis;

  String get wellKeepYouPostedWhenTheresAnUpdate;

  String get services;

  String get sessions;

  String get clinicSessionsInformation;

  String get servicesAvailable;

  String get noServicesAvailable;

  String get doctors;

  String get noSystemServicesFoundAtAMoment;

  String get looksLikeThereIsNoSystemServicesForThis;

  String get appointmentsSummary;

  String get date;

  String get time;

  String get service;

  String get clinic;

  String get proceed;

  String get video;

  String get bookingForm;

  String get bookingInfo;

  String get serviceName;

  String get chooseService;

  String get serviceListIsEmpty;

  String get thereAreNoServicesListedAtTheMomentStayTunedF;

  String get kindlyChooseAServiceFirst;

  String get chooseClinic;

  String get searchForClinic;

  String get clinicListIsEmpty;

  String get thereAreNoClinicsListedAtTheMomentStayTunedFo;

  String get kindlyChooseAClinicFirst;

  String get chooseDoctor;

  String get searchForDoctor;

  String get thereAreNoDoctorsListedAtTheMomentStayTunedFo;

  String get addMedicalHistory;

  String get paymentDetails;

  String get asPerDoctorCharges;

  String get next;

  String get personalizedHealthPlansForYourJourney;

  String get stayOnTrackAndSetPersonalGoals;

  String get discoverAndGetSupportWithin24Hours;

  String get customizeHealthPlansForATailoredApproachAlign;

  String get focusOnYourPathSetClearGoalsAndStrideForwardW;

  String get exploreFindSolutionsAndReceiveAssistanceSwift;

  String get transactionIsInProcess;

  String get enterYourMsisdnHere;

  String get pleaseCheckThePayment;

  String get ambiguous;

  String get success;

  String get incorrectPin;

  String get exceedsWithdrawalAmountLimit;

  String get inProcess;

  String get transactionTimedOut;

  String get notEnoughBalance;

  String get refused;

  String get doNotHonor;

  String get transactionNotPermittedTo;

  String get transactionIdIsInvalid;

  String get errorWhileFetchingEncryption;

  String get transactionExpired;

  String get invalidAmount;

  String get transactionNotFound;

  String get successfullyFetchedEncryptionKey;

  String get theTransactionIsStill;

  String get transactionIsSuccessful;

  String get incorrectPinHasBeen;

  String get theUserHasExceeded;

  String get theAmountUserIs;

  String get userDidnTEnterThePin;

  String get transactionInPendingState;

  String get userWalletDoesNot;

  String get theTransactionWasRefused;

  String get encryptionKeyHasBeen;

  String get transactionHasBeenExpired;

  String get payeeIsAlreadyInitiated;

  String get theTransactionWasNot;

  String get thisIsAGeneric;

  String get theTransactionWasTimed;

  String get xSignatureAndPayloadDid;

  String get couldNotFetchEncryption;

  String get transactionFailed;

  String get transactionCancelled;

  String get paymentSuccess;

  String get redirectingToBookings;

  String get pleaseConfirmYourAppointmentByCheckingTheBox;

  String get appointmentDetail;

  String get reschedule;

  String get invoice;

  String get dateTime;

  String get appointmentStatus;

  String get paymentStatus;

  String get subjective;

  String get objective;

  String get assessment;

  String get plan;

  String get bodyChart;

  String get doctorsAvailable;

  String get noDoctorsAvailable;

  String get photosAvailable;

  String get noPhotosAvailable;

  String get looksLikeThereIsNoServicesListedOnThisClinicW;

  String get session;

  String get unavailable;

  String get lblBreak;

  String get clinicDetail;

  String get pincode;

  String get readMore;

  String get readLess;

  String get noGalleryFoundAtAMoment;

  String get looksLikeThereIsNoGalleryForThisClinicWellKee;

  String get clinics;

  String get availableClinicsFor;

  String get noClinicsFoundAtAMoment;

  String get looksLikeThereIsNoClinicForThisServiceWellKee;

  String get searchClinicHere;

  String get home;

  String get aboutMyself;

  String get about;

  String get contactInfo;

  String get specialization;

  String get experience;

  String get experienceSpecializationContactInfo;

  String get reviews;

  String get noReviewsAvailable;

  String get qualification;

  String get qualificationInDetail;

  String get year;

  String get degree;

  String get university;

  String get noQualificationsFound;

  String get looksLikeThereAreNoQualificationsAddedByThisD;

  String get totalAppointmentsDone;

  String get looksLikeThereIsNoServicesProvidedByThisDocto;

  String get doctorDetail;

  String get socialMedia;

  String get noDoctorsFoundAtAMoment;

  String get looksLikeThereIsNoDoctorsForThisClinicWellKee;

  String get noReviewsFoundAtAMoment;

  String get looksLikeThereIsNoReviewsWellKeepYouPostedWhe;

  String get searchHere;

  String get searchDoctorHere;

  String get noEncountersFound;

  String get looksLikeThereIsNoEncountersWellKeepYouPosted;

  String get clinicsNearYou;

  String get upcomingAppointments;

  String get great;

  String get bookingSuccessful;

  String get yourAppointmentHasBeenBookedSuccessfully;

  String get totalPayment;

  String get goToAppointments;

  String get noteForCashPaymentPurposesDontUseThePayNowBut;

  String get choosePaymentMethod;

  String get chooseOurConvenientPaymentOptionAndUnlockUnli;

  String get doYouWantToReplaceThePreviousServiceWithTheCu;

  String get bookNow;

  String get aboutService;

  String get advancePayableAmount;

  String get advancePaidAmount;

  String get remainingPayableAmount;

  String get walletHistory;

  String get noWalletDataFound;

  String get oppsNoWalletDataFoundAtAMoment;

  String get walletBalance;

  String get addFiles;

  String get file;

  String get apply;

  String get filterBy;

  String get reset;

  String get priceRange;

  String get serviceType;

  String get dateOfBirth;

  String get passwordLengthShouldBe8To14Characters;

  String get noteInCaseYouFailToMakeTheAdvancePaymentYouWi;

  String get serviceTotal;

  String get remainingAmount;

  String get refundableAmount;

  String get appointmentId;

  String get youDontHaveEnoughBalanceToCompleteThePaymentU;

  String get advancePayment;

  String get doYouWantToPerformThisAction;

  String get bookedFor;

  String get addPatient;

  String get relation;

  String get save;

  String get managePatient;

  String get otherPatient;

  String get manageOtherPatient;

  String get genderWithColon;

  String get contactNumberWithColon;

  String get dobWithColon;

  String get noPatientsFound;

  String get editPatient;

  String get doYouWantToDeleteYourOtherPatientsProfile;

  String get birthdateIsRequired;

  String get selectBirthdate;

  String get bookedForWithColon;

  String get inClinic;

  String get online;

  String get pending;

  String get confirmed;

  String get checkIn;

  String get cancelled;

  String get advancePaid;

  String get paid;

  String get unpaid;

  String get advanceRefunded;

  String get refunded;

  String get failed;

  String get ourPopularDoctor;

  String get ourPopularSevices;

  String get ourPopularClinics;

  String get newAppointmentBooked;

  String get appointmentCompleted;

  String get appointmentRejected;

  String get appointmentCancelled;

  String get appointmentRescheduled;

  String get appointmentAccepted;

  String get forgetEmailPassword;

  String get parents;

  String get brother;

  String get siblings;

  String get spouse;

  String get relative;

  String get deleteConfirmation;

  String get patientUpdatedSuccessfully;

  String get patientAddedSuccessfully;

  String get recordDeletedSuccessfully;

  String get male;

  String get female;

  String get other;

  String get others;

  String get appliedInclusiveTaxes;

  String get includesInclusiveTax;

  String get inclusiveTaxes;

  String get servicePrice;

  String get inclusiveTax;

  String get appliedExclusiveTaxes;

  String get exclusiveTax;

  String get appliedTaxes;

  String cancellationChargesWillBeAppliedForCancellationWithin(String amount, String hours);

  String get cancelAppointment;

  String get goBack;

  String cancellationFeesWillBeAppliedIfYouCancelWithinHoursOfScheduledTime(String hours, bool isCancellationChargesEnabled);

  String get reason;

  String get continueText;

  String get wouldYouLikeToProceedAndConfirmPayment;

  String get cancellationFee;

  String get yourAppointmentHasBeenSuccessfullyCancelled;

  String get appointmentRefundWillBeProcessedWithingHoursIfApplicable;

  String get noteCheckYourAppointmentHistoryForRefundDetailsIfApplicable;

  String get ok;

  String get hintReason;

  String get medicalHistory;

  String get clinicClosed;

  String get satisfactionToCustomer;

  String get totalVerifiedPatients;

  String get encounterId;

  String get dateIsNotSelected;

  String get selectClinic;

  String get selectService;

  String get quicklyBookYourAppointmentNow;

  String get noDataFound;

  String get filterService;

  String get filterCategory;

  String get filterRating;

  String get incidentManagement;

  String get requestHelpForAnyMistakeHappen;

  String get otp;

  String get verify;

  String get closedOn;

  String get viewDetails;

  String get title;

  String get enterYourDetailDescriptionForYourComplaint;

  String get phoneNumber;

  String get chooseImage;

  String get browse;

  String get noQueryYet;

  String get add;

  String get toSubmitYourProblemsSimplyPressAddButtonAndExplainYourConcern;

  String get tryToAnotherWay;

  String get pleaseEnterValid6digitOTP;

  String get otpFromAuthenticatorApp;

  String get open;

  String get close;

  String get pleaseEnterValidEmail;

  String get pleaseEnterOTP;

  String get passwordMustIncludeSpacialCharacter;

  String get passwordMustIncludeAtLeastOneLowercaseCharacter;

  String get passwordMustIncludeAtLeastOneNumber;

  String get passwordMustIncludeAtLeastOneCapitalCharacter;

  String get addFile;

  String get addMedicalReport;

  String get optional;

  String get reply;

  String get passwordIsRequired;

  String get passwordDoesNotMeetRequirements;

  String get passwordTooShort;

  String get emailHasAlreadyBeenTaken;

  String get markAsClosed;

  String get hideMessage;

  String get showMessage;

  String get createdBy;

  String get incident;

  String get reject;

  String get successfullyAdded;

  String get otpSentToEmail;

  String get rejected;

  String get incidenceReportReply;

  String get resendOTP;

  String get quantity;

  String get instruction;

  String get updateTo;

  String get aVertionUpdateIsAvailable;

  String get available;

  String get inAppUpdate;

  String get consultationType;

  String get bookWith;

  String get addOtherPatient;

  String get readAll;

  String get doYouWantToReadAllNotification;

  String get bookingWith;

  String get enterYourPhoneNumber;

  String get pleaseEnterPhoneNumber;

  String get pleaseEnterValidPhoneNumber;

  String get pleaseSelectService;

  String get pleaseSelectDoctor;

  String get pleaseSelectClinic;

  String get bookingFor;

  String get selectedFileShouldBeLessThan5MB;

  String get noFileSelected;

  String errorImageTooLarge(String size);

  String get selectFirstDate;

  String get selectLastDate;

  String get form;

  String get dosage;

  String get medicines;

  String get prescriptions;

  String get prescriptionAmount => 'Prescription Amount';

  String get prescriptionExclusiveTax => 'Prescription Exclusive Tax';

  String get prescriptionStatus => 'Prescription Status';

  String get prescriptionPaymentStatus => 'Prescription Payment Status';

  String get bedDetails;

  String get roomNumber;

  String get bedType;

  String get assignDate;

  String get dischargeDate;

  String get bedPrice;

  String get perDay;

  String get bedHistory;

  String get seeYourBedHistory;

  String get noBedHistoryFound;

  String get looksNoBedHistoryFound;

  String get bedNumber;

  String get perDayCharge;

  String get totalCharges;

  String get grandTotal;
}
