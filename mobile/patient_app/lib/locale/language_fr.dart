import 'languages.dart';

class LanguageFr extends BaseLanguage {
  @override
  String get language => 'Langue';

  @override
  String get badRequest => '400: mauvaise demande';

  @override
  String get forbidden => '403: interdit';

  @override
  String get pageNotFound => '404 Page non trouvée';

  @override
  String get tooManyRequests => '429: trop de demandes';

  @override
  String get internalServerError => '500: Erreur du serveur interne';

  @override
  String get badGateway => '502 Mauvaise passerelle';

  @override
  String get serviceUnavailable => '503 Service Indisponible';

  @override
  String get gatewayTimeout => '504 portail expiré';

  @override
  String get hey => 'Hé';

  @override
  String get hello => 'Bonjour';

  @override
  String get thisFieldIsRequired => 'Ce champ est obligatoire';

  @override
  String get contactNumber => 'Numéro de contact';

  @override
  String get gallery => 'Galerie';

  @override
  String get camera => 'Caméra';

  @override
  String get editProfile => 'Editer le profil';

  @override
  String get update => 'Mise à jour';

  @override
  String get reload => 'Recharger';

  @override
  String get address => 'Adresse';

  @override
  String get viewAll => 'Voir tout';

  @override
  String get pressBackAgainToExitApp => "Appuyez à nouveau pour quitter l'application";

  @override
  String get invalidUrl => 'URL invalide';

  @override
  String get cancel => 'Annuler';

  @override
  String get delete => 'Supprimer';

  @override
  String get deleteAccountConfirmation => 'Votre compte sera supprimé en permanence. Vos données ne seront plus restaurées.';

  @override
  String get demoUserCannotBeGrantedForThis => "L'utilisateur de démonstration ne peut pas être accordé pour cette action";

  @override
  String get somethingWentWrong => "Quelque chose s'est mal passé";

  @override
  String get yourInternetIsNotWorking => 'Votre Internet ne fonctionne pas';

  @override
  String get profileUpdatedSuccessfully => 'Mise à jour du profil réussie';

  @override
  String get wouldYouLikeToSetProfilePhotoAs => 'Souhaitez-vous définir cette photo comme photo de profil?';

  @override
  String get yourOldPasswordDoesnT => 'Votre ancien mot de passe ne corrige pas!';

  @override
  String get yourNewPasswordDoesnT => 'Votre nouveau mot de passe ne correspond pas à confirmer le mot de passe!';

  @override
  String get location => 'Emplacement';

  @override
  String get yes => 'Oui';

  @override
  String get submit => 'Soumettre';

  @override
  String get firstName => 'Prénom';

  @override
  String get lastName => 'Nom de famille';

  @override
  String get changePassword => 'Changer le mot de passe';

  @override
  String get yourNewPasswordMust => 'Votre nouveau mot de passe doit être différent de votre mot de passe précédent';

  @override
  String get password => 'Mot de passe';

  @override
  String get newPassword => 'nouveau mot de passe';

  @override
  String get confirmNewPassword => 'Confirmer le nouveau mot de passe';

  @override
  String get email => 'E-mail';

  @override
  String get mainStreet => 'Rue principale';

  @override
  String get toResetYourNew => 'Pour réinitialiser votre nouveau mot de passe, veuillez saisir votre adresse e-mail';

  @override
  String get stayTunedNoNew => "Restez à l'écoute! Pas de nouvelles notifications.";

  @override
  String get noNewNotificationsAt => "Pas de nouvelles notifications pour le moment. Nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get signIn => 'Se connecter';

  @override
  String get explore => 'Explorer';

  @override
  String get settings => 'Paramètres';

  @override
  String get rateApp => 'Application de taux';

  @override
  String get aboutApp => 'À propos de l\'application';

  @override
  String get logout => 'Se déconnecter';

  @override
  String get rememberMe => 'Souviens-toi de moi';

  @override
  String get forgotPassword => 'Mot de passe oublié?';

  @override
  String get forgotPasswordTitle => 'Mot de passe oublié';

  @override
  String get registerNow => 'S\'inscrire maintenant';

  @override
  String get createYourAccount => 'Créez votre compte';

  @override
  String get createYourAccountFor => 'Créez votre compte pour une meilleure expérience';

  @override
  String get signUp => 'S\'inscrire';

  @override
  String get alreadyHaveAnAccount => 'Vous avez déjà un compte?';

  @override
  String get yourPasswordHasBeen => 'Votre mot de passe a été réinitialisé avec succès';

  @override
  String get youCanNowLog => 'Vous pouvez maintenant vous connecter à votre nouveau compte avec votre nouveau mot de passe';

  @override
  String get done => 'Fait';

  @override
  String get pleaseAcceptTermsAnd => 'Veuillez accepter les termes et conditions';

  @override
  String get deleteAccount => 'Supprimer le compte';

  @override
  String get eG => 'par exemple.';

  @override
  String get merry => 'Joyeux';

  @override
  String get doe => 'Biche';

  @override
  String get welcomeBackToThe => 'Bienvenue à la';

  @override
  String get welcomeToThe => 'Bienvenue à la';

  @override
  String get doYouWantToLogout => 'Voulez-vous vous connecter?';

  @override
  String get appTheme => 'Thème de l\'application';

  @override
  String get guest => 'Invité';

  @override
  String get notifications => 'Notifications';

  @override
  String get contactUs => 'Contactez-nous';

  @override
  String get getInTouchWithSupport => 'Contactez le support';

  @override
  String get newUpdate => 'Nouvelle mise à jour';

  @override
  String get anUpdateTo => 'Une mise à jour de';

  @override
  String get isAvailableGoTo => 'est disponible. Allez à Play Store et téléchargez la nouvelle version de l\'application.';

  @override
  String get later => 'Plus tard';

  @override
  String get closeApp => 'Fermer l\'application';

  @override
  String get updateNow => 'Mettez à jour maintenant';

  @override
  String get signInFailed => 'La connexion a échoué';

  @override
  String get userCancelled => 'Utilisateur annulé';

  @override
  String get appleSigninIsNot => 'Apple Signin n\'est pas disponible pour votre appareil';

  @override
  String get eventStatus => 'État de l\'événement';

  @override
  String get eventAddedSuccessfully => 'Événement ajouté avec succès';

  @override
  String get notRegistered => 'Non enregistré?';

  @override
  String get signInWithGoogle => 'Connectez-vous avec Google';

  @override
  String get signInWithApple => 'Connectez-vous avec Apple';

  @override
  String get orSignInWith => 'Ou se connecter avec';

  @override
  String get ohNoYouAreLeaving => 'Oh non, tu pars!';

  @override
  String get oldPassword => 'ancien mot de passe';

  @override
  String get oldAndNewPassword => 'Le mot de passe ancien et nouveau sont les mêmes.';

  @override
  String get personalizeYourProfile => 'Personnalisez votre profil';

  @override
  String get themeAndMore => 'Thème et plus';

  @override
  String get showSomeLoveShare => 'Montrez un peu d\'amour, partagez!';

  @override
  String get privacyPolicyTerms => 'Politique de confidentialité, Conditions générales';

  @override
  String get securelyLogOutOfAccount => 'Se déconnecter en toute sécurité';

  @override
  String get termsOfService => 'Conditions d\'utilisation';

  @override
  String get successfully => 'Avec succès';

  @override
  String get clearAll => 'Tout effacer';

  @override
  String get notificationDeleted => 'Notification supprimée';

  @override
  String get doYouWantToRemoveNotification => 'Voulez-vous supprimer la notification';

  @override
  String get doYouWantToClearAllNotification => 'Voulez-vous la notification de clairs';

  @override
  String get locationPermissionDenied => 'Permission de localisation refusée';

  @override
  String get enableLocation => 'location disponible';

  @override
  String get permissionDeniedPermanently => 'autorisation refusée en permanence';

  @override
  String get chooseYourLocation => 'Choisissez votre emplacement';

  @override
  String get setAddress => 'Adresse définie';

  @override
  String get sorryUserCannotSignin => 'Désolé l\'utilisateur ne peut pas se connecter';

  @override
  String get iAgreeToThe => 'je suis d\'accord avec le';

  @override
  String get logIn => 'SE CONNECTER';

  @override
  String get doYouConfirmThisAppointment => 'Confirmez-vous ce rendez-vous ?';

  @override
  String get confirmAppointment => 'Confirmer le rendez-vous';

  @override
  String get iHaveReadAll => 'J\'ai lu tous les détails et rempli le formulaire et je confirmerai ce rendez-vous avec';

  @override
  String get confirm => 'Confirmer';

  @override
  String get doYouConfirmThisPayment => 'Confirmez-vous ce paiement ?';

  @override
  String get exploreTopClinicsWithAdvancedServicesTailored => "Explorez les meilleures cliniques avec des services avancés adaptés à vos besoins";

  @override
  String get discoverYourIdealClinicWithOurPersonalizedSea => "Découvrez votre clinique idéale avec notre recherche personnalisée.Commençons!";

  @override
  String get weHaveEmailedYourPasswordResetLink => "Nous avons envoyé un e-mail à votre lien de réinitialisation de mot de passe!";

  @override
  String get resetYourPassword => "Réinitialisez votre mot de passe";

  @override
  String get enterYourEmailAddressToResetYourNewPassword => "Entrez votre adresse e-mail pour réinitialiser votre nouveau mot de passe.";

  @override
  String get sendCode => "Envoyer le code";

  @override
  String get edit => "MODIFIER";

  @override
  String get gender => "Genre";

  @override
  String get profile => "Profil";

  @override
  String get encounters => "Rencontres";

  @override
  String get seeYourEncounterData => "Voir vos données de rencontre";

  @override
  String get version => "Version";

  @override
  String get userNotCreated => "Utilisateur non créé";

  @override
  String get notAMember => "Pas un membre?";

  @override
  String get registerYourAccountForBetterExperience => "Enregistrez votre compte pour une meilleure expérience";

  @override
  String get termsConditions => "termes et conditions";

  @override
  String get and => "Et";

  @override
  String get privacyPolicy => "politique de confidentialité";

  @override
  String get appointment => "Rendez-vous";

  @override
  String get doctor => "Médecin";

  @override
  String get payment => "Paiement";

  @override
  String get doYouWantToCancelAppointment => "Voulez-vous annuler le rendez-vous?";

  @override
  String get videoCallLinkIsNotFound => "Le lien d'appel vidéo n'est pas trouvé!";

  @override
  String get thisIsNotAOnlineService => "Ce n'est pas un service en ligne!";

  @override
  String get oppsThisAppointmentIsNotConfirmedYet => "Opps!Ce rendez-vous n'est pas encore confirmé!";

  @override
  String get oppsThisAppointmentHasBeenCancelled => "Opps!Ce rendez-vous a été annulé!";

  @override
  String get oppsThisAppointmentHasBeenCompleted => "Opps!Ce rendez-vous est terminé!";

  @override
  String get noTimeSlotsAvailable => "Pas de plages horaires disponibles";

  @override
  String get chooseTime => "Choisir le temps";

  @override
  String get rescheduleBooking => "Réservation de reproduction";

  @override
  String get searchForService => "Rechercher un service";

  @override
  String get statusListIsEmpty => "La liste des statuts est vide";

  @override
  String get thereAreNoStatusListedAtTheMomentStayTunedFor => "Il n'y a aucun statut répertorié pour le moment.Restez à l'écoute pour plus d'options.";

  @override
  String get chooseDate => "Choisir la date";

  @override
  String get doYouWantToChangeTheTimeSlotOfThisAppointment => "Voulez-vous changer le créneau horaire de ce rendez-vous?";

  @override
  String get no => "Non";

  @override
  String get somethingWentWrongPleaseTryAgainLater => "Quelque chose s'est mal passé.Veuillez réessayer plus tard.";

  @override
  String get doYouWantToRemoveThisReview => "Voulez-vous supprimer cette critique?";

  @override
  String get encounterDetail => "Détail de rencontre";

  @override
  String get view => "Voir";

  @override
  String get doctorName => "Nom du médecin";

  @override
  String get active => "ACTIF";

  @override
  String get closed => "FERMÉ";

  @override
  String get clinicName => "Nom de la clinique";

  @override
  String get description => "Description";

  @override
  String get payNow => "Payez maintenant";

  @override
  String get medicalReport => "Rapport médical";

  @override
  String get paymentDetail => "Détail du paiement";

  @override
  String get price => "Prix";

  @override
  String get discount => "Rabais";

  @override
  String get off => "moins";

  @override
  String get subtotal => "Sous-total";

  @override
  String get tax => "Impôt";

  @override
  String get total => "Total";

  @override
  String get yourReview => "Votre avis";

  @override
  String get by => "Par";

  @override
  String get youHaventRatedYet => "Tu n'as pas encore évalué";

  @override
  String get yourFeedbackWillImproveOurService => "Vos commentaires amélioreront notre service.";

  @override
  String get writeHere => "Écrivez ici..";

  @override
  String get writeYourFeedbackHere => "Écrivez vos commentaires ici.";

  @override
  String get pleaseSelectRatings => "Veuillez sélectionner les notes";

  @override
  String get all => "Tout";

  @override
  String get upcoming => "A venir";

  @override
  String get completed => "Complété";

  @override
  String get appointmentCancelSuccessfully => "Rendez-vous annuler avec succès";

  @override
  String get appointments => "Nominations";

  @override
  String get noAppointmentsFound => "Aucun rendez-vous trouvé";

  @override
  String get thereAreCurrentlyNoAppointmentsAvailableStart => "Il n'y a actuellement aucun rendez-vous disponible.Commencez à réserver votre prochain rendez-vous maintenant.";

  @override
  String get encounter => "Rencontre";

  @override
  String get basicInformation => "Informations de base";

  @override
  String get problems => "Problèmes";

  @override
  String get observations => "Observations";

  @override
  String get notes => "Remarques";

  @override
  String get prescription => "Ordonnance";

  @override
  String get frequency => "Fréquence";

  @override
  String get days => "Jours";

  @override
  String get otherInformation => "les autres informations";

  @override
  String get patientSoap => "Savon pour les patients";

  @override
  String get category => "Catégorie";

  @override
  String get noCategoryFound => "Aucune catégorie trouvée";

  @override
  String get viewDetail => "Voir les détails";

  @override
  String get noServicesFoundAtAMoment => "Aucun service trouvé à un moment";

  @override
  String get looksLikeThereIsNoServicesForThis => "On dirait qu'il n'y a pas de services pour cela";

  @override
  String get wellKeepYouPostedWhenTheresAnUpdate => "Nous vous tiendrons au courant quand il y a une mise à jour.";

  @override
  String get services => "Prestations de service";

  @override
  String get sessions => "Séances";

  @override
  String get clinicSessionsInformation => "Informations sur les sessions de la clinique";

  @override
  String get servicesAvailable => "services disponibles";

  @override
  String get noServicesAvailable => "Aucun service disponible";

  @override
  String get doctors => "Médecins";

  @override
  String get noSystemServicesFoundAtAMoment => "Aucun service système trouvé à un moment";

  @override
  String get looksLikeThereIsNoSystemServicesForThis => "On dirait qu'il n'y a pas de services système pour cela";

  @override
  String get appointmentsSummary => "Résumé des nominations";

  @override
  String get date => "Date";

  @override
  String get time => "Temps";

  @override
  String get service => "Service";

  @override
  String get clinic => "Clinique";

  @override
  String get proceed => "Procéder";

  @override
  String get video => "Vidéo";

  @override
  String get bookingForm => "Formulaire de reservation";

  @override
  String get bookingInfo => "Informations de réservation";

  @override
  String get serviceName => "Nom du service";

  @override
  String get chooseService => "Choisissez le service";

  @override
  String get serviceListIsEmpty => "La liste des services est vide.";

  @override
  String get thereAreNoServicesListedAtTheMomentStayTunedF => "Aucun service n'est répertorié pour le moment.Restez à l'écoute pour plus d'offres de services.";

  @override
  String get kindlyChooseAServiceFirst => "Veuillez choisir d'abord un service";

  @override
  String get chooseClinic => "Choisissez la clinique";

  @override
  String get searchForClinic => "Recherche de clinique";

  @override
  String get clinicListIsEmpty => "La liste des cliniques est vide.";

  @override
  String get thereAreNoClinicsListedAtTheMomentStayTunedFo => "Aucune clinique n'est répertoriée pour le moment.Restez à l'écoute pour plus de cliniques.";

  @override
  String get kindlyChooseAClinicFirst => "Veuillez d'abord choisir une clinique";

  @override
  String get chooseDoctor => "Choisissez Docteur";

  @override
  String get searchForDoctor => "Rechercher un médecin";

  @override
  String get thereAreNoDoctorsListedAtTheMomentStayTunedFo => "Il n'y a pas de médecins répertoriés pour le moment.Restez à l'écoute pour plus d'options.";

  @override
  String get addMedicalHistory => "Ajouter des antécédents médicaux";

  @override
  String get paymentDetails => "Détails de paiement";

  @override
  String get asPerDoctorCharges => "selon les frais de médecin";

  @override
  String get next => "Suivant";

  @override
  String get personalizedHealthPlansForYourJourney => "Plans de santé personnalisés pour votre voyage";

  @override
  String get stayOnTrackAndSetPersonalGoals => "Restez sur la bonne voie et fixez des objectifs personnels";

  @override
  String get discoverAndGetSupportWithin24Hours => "Découvrez et obtenez un soutien dans les 24 heures";

  @override
  String get customizeHealthPlansForATailoredApproachAlign => "Personnalisez les plans de santé pour une approche sur mesure, en alignant chaque aspect avec vos besoins.";

  @override
  String get focusOnYourPathSetClearGoalsAndStrideForwardW => "Concentrez-vous sur votre chemin, fixez des objectifs clairs et avancez avec détermination et objectif.";

  @override
  String get exploreFindSolutionsAndReceiveAssistanceSwift => "Explorez, trouvez des solutions et recevez une assistance rapidement que votre réseau de soutien est prêt dans les 24 heures.";

  @override
  String get transactionIsInProcess => 'La transaction est en cours...';

  @override
  String get enterYourMsisdnHere => 'Entrez votre msisdn ici';

  @override
  String get pleaseCheckThePayment => 'Veuillez vérifier que la demande de paiement est envoyée à votre numéro';

  @override
  String get ambiguous => 'Ambiguë';

  @override
  String get success => 'Succès';

  @override
  String get incorrectPin => 'Épingle incorrecte';

  @override
  String get exceedsWithdrawalAmountLimit => 'Dépasse la ou les limites du montant de retrait / Limite du montant de retrait dépassée';

  @override
  String get inProcess => 'En cours';

  @override
  String get transactionTimedOut => 'Transaction expirée';

  @override
  String get notEnoughBalance => "Pas assez d'équilibre";

  @override
  String get refused => 'Refusé';

  @override
  String get doNotHonor => "N'honore pas";

  @override
  String get transactionNotPermittedTo => 'Transaction non autorisée au bénéficiaire';

  @override
  String get transactionIdIsInvalid => "L'ID de transaction n'est pas valide";

  @override
  String get errorWhileFetchingEncryption => 'Erreur lors de la récupération de la clé de cryptage';

  @override
  String get transactionExpired => 'Transaction expirée';

  @override
  String get invalidAmount => 'Montant invalide';

  @override
  String get transactionNotFound => 'Transaction introuvable';

  @override
  String get successfullyFetchedEncryptionKey => 'Clé de cryptage récupérée avec succès';

  @override
  String get theTransactionIsStill => 'La transaction est toujours en cours de traitement et est dans un état ambigu. Veuillez faire la demande de transaction pour récupérer le statut de la transaction.';

  @override
  String get transactionIsSuccessful => 'La transaction est réussie';

  @override
  String get incorrectPinHasBeen => 'Un code PIN incorrect a été saisi';

  @override
  String get theUserHasExceeded => "L'utilisateur a dépassé la limite de transaction autorisée par son portefeuille";

  @override
  String get theAmountUserIs => "Le montant que l'utilisateur tente de transférer est inférieur au montant minimum autorisé";

  @override
  String get userDidnTEnterThePin => "L'utilisateur n'a pas saisi le code PIN";

  @override
  String get transactionInPendingState => 'Transaction en attente. Veuillez vérifier après un certain temps';

  @override
  String get userWalletDoesNot => "Le portefeuille de l'utilisateur n'a pas assez d'argent pour couvrir le montant à payer";

  @override
  String get theTransactionWasRefused => 'La transaction a été refusée';

  @override
  String get encryptionKeyHasBeen => 'La clé de cryptage a été récupérée avec succès';

  @override
  String get transactionHasBeenExpired => 'La transaction a été expirée';

  @override
  String get payeeIsAlreadyInitiated => "Le bénéficiaire est déjà initié pour désabonnement, interdit ou n'est pas enregistré sur la plateforme Airtel Money";

  @override
  String get theTransactionWasNot => "La transaction n'a pas été trouvée.";

  @override
  String get thisIsAGeneric => "Il s'agit d'un refus générique qui a plusieurs causes possibles";

  @override
  String get theTransactionWasTimed => 'La transaction a expiré.';

  @override
  String get xSignatureAndPayloadDid => 'La signature X et la charge utile ne correspondent pas';

  @override
  String get couldNotFetchEncryption => 'Impossible de récupérer la clé de chiffrement';

  @override
  String get transactionFailed => 'La transaction a échoué';

  @override
  String get transactionCancelled => 'Transaction annulée';

  @override
  String get paymentSuccess => "Succès de paiement";

  @override
  String get redirectingToBookings => "Rediriger vers les réservations.";

  @override
  String get pleaseConfirmYourAppointmentByCheckingTheBox => "Veuillez confirmer votre rendez-vous en cochant la case";

  @override
  String get appointmentDetail => "Détail de rendez-vous";

  @override
  String get reschedule => "Reprogrammer";

  @override
  String get invoice => "Facture";

  @override
  String get dateTime => "Date et heure";

  @override
  String get appointmentStatus => "Statut de nomination";

  @override
  String get paymentStatus => "Statut de paiement";

  @override
  String get subjective => "Subjectif";

  @override
  String get objective => "Objectif";

  @override
  String get assessment => "Évaluation";

  @override
  String get plan => "Plan";

  @override
  String get bodyChart => "Graphique de carrosserie";

  @override
  String get doctorsAvailable => "Médecins disponibles";

  @override
  String get noDoctorsAvailable => "Pas de médecins disponibles";

  @override
  String get photosAvailable => "Photos disponibles";

  @override
  String get noPhotosAvailable => "Pas de photos disponibles";

  @override
  String get looksLikeThereIsNoServicesListedOnThisClinicW => "On dirait qu'il n'y a pas de services répertoriés dans cette clinique, nous vous tiendrons au courant lorsqu'il y a une mise à jour.";

  @override
  String get session => "Session";

  @override
  String get unavailable => "Indisponible";

  @override
  String get lblBreak => "Casser";

  @override
  String get clinicDetail => "Détail de la clinique";

  @override
  String get pincode => "Code PIN";

  @override
  String get readMore => "En savoir plus";

  @override
  String get readLess => "Lire moins";

  @override
  String get noGalleryFoundAtAMoment => "Aucune galerie trouvée à un moment";

  @override
  String get looksLikeThereIsNoGalleryForThisClinicWellKee => "On dirait qu'il n'y a pas de galerie pour cette clinique, nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get clinics => "Cliniques";

  @override
  String get availableClinicsFor => "Cliniques disponibles pour";

  @override
  String get noClinicsFoundAtAMoment => "Aucune clinique trouvée à un moment";

  @override
  String get looksLikeThereIsNoClinicForThisServiceWellKee => "On dirait qu'il n'y a pas de clinique pour ce service, nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get searchClinicHere => "Clinique de recherche ici";

  @override
  String get home => "Maison";

  @override
  String get aboutMyself => "À propos de moi";

  @override
  String get about => "À propos";

  @override
  String get contactInfo => "Informations de contact";

  @override
  String get specialization => "Spécialisation";

  @override
  String get experience => "Expérience";

  @override
  String get experienceSpecializationContactInfo => "Expérience, spécialisation, coordonnées";

  @override
  String get reviews => "Commentaires";

  @override
  String get noReviewsAvailable => "Aucune critique disponible";

  @override
  String get qualification => "Qualification";

  @override
  String get qualificationInDetail => "Qualification en détail";

  @override
  String get year => "Année";

  @override
  String get degree => "Degré";

  @override
  String get university => "Université";

  @override
  String get noQualificationsFound => "Aucune qualification trouvée!";

  @override
  String get looksLikeThereAreNoQualificationsAddedByThisD => "On dirait qu'il n'y a pas de qualifications ajoutées par ce médecin.";

  @override
  String get totalAppointmentsDone => "Les rendez-vous totaux effectués";

  @override
  String get looksLikeThereIsNoServicesProvidedByThisDocto => "On dirait qu'il n'y a pas de services fournis par ce médecin, nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get doctorDetail => "Détail du docteur";

  @override
  String get socialMedia => "Réseaux sociaux";

  @override
  String get noDoctorsFoundAtAMoment => "Pas de médecins trouvés à un moment";

  @override
  String get looksLikeThereIsNoDoctorsForThisClinicWellKee => "On dirait qu'il n'y a pas de médecins pour cette clinique, nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get noReviewsFoundAtAMoment => "Aucune critique trouvée à un moment";

  @override
  String get looksLikeThereIsNoReviewsWellKeepYouPostedWhe => "On dirait qu'il n'y a pas de critiques, nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get searchHere => "Cherche ici";

  @override
  String get searchDoctorHere => "Docteur de recherche ici";

  @override
  String get noEncountersFound => "Aucune rencontre trouvée!";

  @override
  String get looksLikeThereIsNoEncountersWellKeepYouPosted => "On dirait qu'il n'y a pas de rencontres, nous vous tiendrons au courant lorsqu'il y aura une mise à jour.";

  @override
  String get clinicsNearYou => "Cliniques près de chez vous";

  @override
  String get upcomingAppointments => "Rendez-vous à venir";

  @override
  String get great => "Super!";

  @override
  String get bookingSuccessful => "Réserver réussi";

  @override
  String get yourAppointmentHasBeenBookedSuccessfully => "Votre rendez-vous a été réservé avec succès";

  @override
  String get totalPayment => "Paiement total";

  @override
  String get goToAppointments => "Aller aux rendez-vous";

  @override
  String get noteForCashPaymentPurposesDontUseThePayNowBut =>
      "Remarque: À des fins de paiement en espèces, n'utilisez pas le bouton «Pay maintenant».Si vous souhaitez effectuer un paiement en espèces, vous pouvez donner manuellement l'argent au médecin et terminer votre rendez-vous par Doctor Side.";

  @override
  String get choosePaymentMethod => "Choisissez le mode de paiement";

  @override
  String get chooseOurConvenientPaymentOptionAndUnlockUnli => "Choisissez notre option de paiement pratique et débloquez un accès illimité aux privilèges exclusifs.";

  @override
  String get doYouWantToReplaceThePreviousServiceWithTheCu => "Voulez-vous remplacer le service précédent par celui actuel?";

  @override
  String get bookNow => "Reserve maintenant";

  @override
  String get aboutService => "À propos du service";

  @override
  String get advancePayableAmount => "Prévu à payer le montant";

  @override
  String get advancePaidAmount => "Montant rémunéré à l'avance";

  @override
  String get remainingPayableAmount => "Montant restant payable";

  @override
  String get walletHistory => "Histoire du portefeuille";

  @override
  String get noWalletDataFound => "Aucune donnée de portefeuille trouvée!";

  @override
  String get oppsNoWalletDataFoundAtAMoment => "Opps!Aucune donnée de portefeuille trouvée à un moment.";

  @override
  String get walletBalance => "Équilibre du portefeuille";

  @override
  String get addFiles => 'Ajouter des fichiers';

  @override
  String get file => 'Déposer';

  @override
  String get apply => 'Appliquer';

  @override
  String get filterBy => 'Filtrer par';

  @override
  String get priceRange => 'Plage de prix';

  @override
  String get reset => 'Réinitialiser';

  @override
  String get serviceType => 'Type de service';

  @override
  String get dateOfBirth => 'Date de naissance';

  @override
  String get passwordLengthShouldBe8To14Characters => 'La longueur du mot de passe doit être comprise entre 8 et 14 caractères';

  @override
  String get noteInCaseYouFailToMakeTheAdvancePaymentYouWi =>
      "* Remarque : Si vous ne parvenez pas à effectuer le paiement anticipé, vous devrez payer le montant de l'avance payable en cliquant sur le bouton « Payer maintenant » ci-dessous. Dans le cas contraire, votre rendez-vous ne sera pas traité ou vous devrez prendre un nouveau rendez-vous.";

  @override
  String get serviceTotal => 'Service total';

  @override
  String get remainingAmount => 'Montant restant';

  @override
  String get refundableAmount => 'Montant remboursable';

  @override
  String get appointmentId => 'Identifiant du rendez-vous:';

  @override
  String get youDontHaveEnoughBalanceToCompleteThePaymentU => "Vous n'avez pas suffisamment de solde pour effectuer le paiement en utilisant votre portefeuille.";

  @override
  String get advancePayment => 'Paiement anticipé';

  @override
  String get doYouWantToPerformThisAction => 'Voulez-vous effectuer cette action ?';

  @override
  String get bookedFor => 'Réservé pour';

  @override
  String get addPatient => 'Ajouter un patient';

  @override
  String get relation => 'Relation';

  @override
  String get save => 'Sauvegarder';

  @override
  String get managePatient => 'Gérer les patients';

  @override
  String get otherPatient => 'Autre patient';

  @override
  String get manageOtherPatient => 'Gérer un autre patient';

  @override
  String get genderWithColon => 'Genre:';

  @override
  String get contactNumberWithColon => 'Numéro de contact:';

  @override
  String get dobWithColon => 'D-O-B:';

  @override
  String get noPatientsFound => 'Aucun patient trouvé';

  @override
  String get editPatient => 'Modifier le patient';

  @override
  String get doYouWantToDeleteYourOtherPatientsProfile => 'Voulez-vous supprimer le profil de votre autre patient ?';

  @override
  String get birthdateIsRequired => 'La date de naissance est requise';

  @override
  String get selectBirthdate => 'Sélectionnez la date de naissance';

  @override
  String get bookedForWithColon => 'Réservé pour: ';

  @override
  String get inClinic => 'En clinique';

  @override
  String get online => 'En ligne';

  @override
  String get pending => 'En attente';

  @override
  String get confirmed => 'Confirmé';

  @override
  String get checkIn => 'Enregistrement';

  @override
  String get cancelled => 'Annulé';

  @override
  String get advancePaid => 'Avance payée';

  @override
  String get paid => 'Payé';

  @override
  String get advanceRefunded => 'Avance remboursée';

  @override
  String get refunded => 'Remboursé';

  @override
  String get failed => 'Échoué';

  @override
  String get ourPopularDoctor => 'Nos médecins populaires';

  @override
  String get ourPopularSevices => 'Nos services populaires';

  @override
  String get ourPopularClinics => 'Notre clinique populaire';

  @override
  String get newAppointmentBooked => 'Nouveau rendez-vous réservé';

  @override
  String get appointmentCompleted => 'Rendez-vous terminé';

  @override
  String get appointmentRejected => 'Rendez-vous refusé';

  @override
  String get appointmentCancelled => 'Rendez-vous annulé';

  @override
  String get appointmentRescheduled => 'Rendez-vous reporté';

  @override
  String get appointmentAccepted => 'Rendez-vous accepté';

  @override
  String get forgetEmailPassword => 'Oublier le mot de passe de messagerie';

  @override
  String get parents => 'Parents';

  @override
  String get brother => 'Frère';

  @override
  String get siblings => 'Frères et sœurs';

  @override
  String get spouse => 'Conjoint';

  @override
  String get relative => 'Relatif';

  @override
  String get deleteConfirmation => 'Confirmation de suppression';

  @override
  String get patientUpdatedSuccessfully => 'Patient mis à jour avec succès';

  @override
  String get patientAddedSuccessfully => 'Patient ajouté avec succès';

  @override
  String get recordDeletedSuccessfully => 'Enregistrement supprimé avec succès';

  @override
  String get male => 'Mâle';

  @override
  String get female => 'Femelle';

  @override
  String get other => 'Autre';

  @override
  String get others => 'Autres';

  @override
  String get appliedInclusiveTaxes => "Taxes inclusives appliquées";

  @override
  String get includesInclusiveTax => "Comprend la taxe inclusive";

  @override
  String get inclusiveTaxes => "Taxes inclusives";

  @override
  String get servicePrice => "Prix du service";

  @override
  String get inclusiveTax => "Taxe incluse";

  @override
  String get appliedExclusiveTaxes => "Taxes exclusives appliquées";

  @override
  String get exclusiveTax => "Taxe exclusive";

  @override
  String get appliedTaxes => "Taxes appliquées";

  @override
  String cancellationChargesWillBeAppliedForCancellationWithin(String amount, String hours) => "Stornogebühren von $amount werden für Stornierungen innerhalb von $hours Stunden erhoben.";

  @override
  String get cancelAppointment => "Annuler le rendez-vous";

  @override
  String get goBack => "Retourner";

  @override
  String cancellationFeesWillBeAppliedIfYouCancelWithinHoursOfScheduledTime(String hours, bool isCancellationChargesEnabled) =>
      "Voulez-vous annuler ce rendez-vous ? ${isCancellationChargesEnabled ? "Des frais d'annulation seront appliqués si vous annulez dans les $hours heures avant l'heure prévue" : ''}";

  @override
  String get reason => "Raison";

  @override
  String get continueText => "Continuer";

  @override
  String get wouldYouLikeToProceedAndConfirmPayment => "Souhaitez-vous continuer et confirmer le paiement ?";

  @override
  String get cancellationFee => "Frais d'annulation";

  @override
  String get yourAppointmentHasBeenSuccessfullyCancelled => "Votre rendez-vous a été annulé avec succès";

  @override
  String get appointmentRefundWillBeProcessedWithingHoursIfApplicable => "Le remboursement du rendez-vous sera traité dans les 24 heures, si applicable.";

  @override
  String get noteCheckYourAppointmentHistoryForRefundDetailsIfApplicable => "*Remarque : Vérifiez l'historique de vos rendez-vous pour les détails du remboursement, si applicable.";

  @override
  String get ok => "D'accord";

  @override
  String get hintReason => "par ex. J'ai changé d'avis, etc.";

  @override
  String get medicalHistory => "Historique médical";

  @override
  String get clinicClosed => "Clinique fermée";

  @override
  String get satisfactionToCustomer => "Satisfaction des clients";

  @override
  String get totalVerifiedPatients => "Total des médecins vérifiés";

  @override
  String get encounterId => "ID de rencontre: ";

  @override
  String get dateIsNotSelected => "La date n'est pas sélectionnée";

  @override
  String get selectClinic => 'Sélectionnez une clinique';

  @override
  String get selectService => 'Sélectionnez un service';

  @override
  String get quicklyBookYourAppointmentNow => 'Prenez rapidement votre rendez-vous maintenant';

  @override
  String get noDataFound => 'Aucune donnée trouvée';

  @override
  String get filterService => 'Service';

  @override
  String get filterRating => 'Évaluation';

  @override
  String get filterCategory => 'Catégorie';

  @override
  String get incidentManagement => 'Gestion des incidents';

  @override
  String get requestHelpForAnyMistakeHappen => "Demandez de l'aide si une erreur survient";

  @override
  String get otp => 'OTP';

  @override
  String get verify => 'Vérifier';

  @override
  String get closedOn => 'Fermé le';

  @override
  String get viewDetails => 'Voir les détails';

  @override
  String get title => 'Titre';

  @override
  String get enterYourDetailDescriptionForYourComplaint => 'Saisissez la description détaillée de votre réclamation';

  @override
  String get phoneNumber => 'Numéro de téléphone';

  @override
  String get chooseImage => 'Choisir une image';

  @override
  String get browse => 'Parcourir';

  @override
  String get noQueryYet => 'Aucune requête pour le moment';

  @override
  String get add => 'Ajouter';

  @override
  String get toSubmitYourProblemsSimplyPressAddButtonAndExplainYourConcern => 'Pour soumettre vos problèmes, appuyez simplement sur le bouton Ajouter et expliquez votre préoccupation';

  @override
  String get tryToAnotherWay => 'Essayez une autre méthode';

  @override
  String get pleaseEnterValid6digitOTP => 'Veuillez saisir un OTP valide à 6 chiffres';

  @override
  String get otpFromAuthenticatorApp => "OTP de l'application Authenticator";

  @override
  String get open => 'OUVRIR';

  @override
  String get close => 'FERMER';

  @override
  String get pleaseEnterValidEmail => 'Veuillez entrer un e-mail valide';

  @override
  String get pleaseEnterOTP => "Veuillez saisir l'OTP";

  @override
  String get passwordMustIncludeSpacialCharacter => 'Le mot de passe doit inclure au moins un caractère spécial';

  @override
  String get passwordMustIncludeAtLeastOneLowercaseCharacter => 'Le mot de passe doit inclure au moins un caractère minuscule';

  @override
  String get passwordMustIncludeAtLeastOneNumber => 'Le mot de passe doit inclure au moins un chiffre';

  @override
  String get passwordMustIncludeAtLeastOneCapitalCharacter => 'Le mot de passe doit inclure au moins un caractère majuscule';

  @override
  String get addFile => 'Ajouter un fichier';

  @override
  String get addMedicalReport => 'Ajouter un rapport médical';

  @override
  String get optional => '(Facultatif)';

  @override
  String get reply => 'Répondre';

  @override
  String get passwordIsRequired => "Le mot de passe est requis";

  @override
  String get passwordDoesNotMeetRequirements => "Le mot de passe ne répond pas aux exigences";

  @override
  String get passwordTooShort => "Le mot de passe doit contenir au moins 8 caractères";

  @override
  String get emailHasAlreadyBeenTaken => "L'email a déjà été pris.";

  @override
  String get markAsClosed => 'Marquer comme fermé';

  @override
  String get hideMessage => 'Masquer le message';

  @override
  String get showMessage => 'Afficher le message';

  @override
  String get createdBy => 'Créé par';

  @override
  String get incident => "Incident";

  @override
  String get reject => 'Rejeter';

  @override
  String get successfullyAdded => 'Ajouté avec succès';

  @override
  String get otpSentToEmail => "OTP envoyé à votre e-mail, veuillez vérifier pour continuer";

  @override
  String get rejected => "Rejeté";

  @override
  String get incidenceReportReply => "Réponse au rapport d'incident";

  @override
  String get resendOTP => "Renvoyer l'OTP";

  @override
  String get quantity => "Quantité";

  @override
  String get instruction => "Instruction";

  @override
  String get updateTo => 'Mise à jour vers';

  @override
  String get aVertionUpdateIsAvailable => 'Une version plus récente avec des fonctionnalités intéressantes est disponible !Une version plus récente avec des fonctionnalités intéressantes est disponible !';

  @override
  String get available => 'Disponible';

  @override
  String get inAppUpdate => "Mise à jour dans l'application";

  @override
  String get consultationType => 'Type de consultation';

  @override
  String get bookWith => 'Réserver avec';

  @override
  String get addOtherPatient => "Ajouter un autre patient";

  @override
  String get readAll => 'Tout lire';

  @override
  String get doYouWantToReadAllNotification => 'Voulez-vous lire toutes les notifications ?';

  @override
  String get bookingWith => 'Réservation avec';

  @override
  String get enterYourPhoneNumber => 'Entrez votre numéro de téléphone';

  @override
  String get pleaseEnterPhoneNumber => 'Veuillez entrer le numéro de téléphone';

  @override
  String get pleaseEnterValidPhoneNumber => 'Veuillez entrer un numéro de téléphone valide';

  @override
  get pleaseSelectService => 'Veuillez sélectionner un service';

  @override
  String get pleaseSelectDoctor => 'Veuillez sélectionner un médecin';

  @override
  String get pleaseSelectClinic => 'Veuillez sélectionner une clinique';

  @override
  String get bookingFor => 'Réservation pour';

  @override
  String get selectedFileShouldBeLessThan5MB => 'veuillez sélectionner une image inférieure à 5 Mo';

  @override
  String get noFileSelected => 'aucun fichier sélectionné';

  @override
  String errorImageTooLarge(String size) => 'Veuillez sélectionner une image inférieure à 2 Mo. (Sélectionné : $size Mo)';

  @override
  String get selectFirstDate => 'Sélectionnez la première date';

  @override
  String get selectLastDate => 'Sélectionnez la dernière date';

  @override
  String get form => 'Forme';

  @override
  String get dosage => 'Dosage';

  @override
  String get medicines => 'Médicaments';

  @override
  String get prescriptions => 'Ordonnances';

  @override
  String get prescriptionAmount => 'Montant de l’ordonnance';

  @override
  String get prescriptionExclusiveTax => 'Taxe exclusive sur l’ordonnance';

  @override
  String get prescriptionStatus => 'Statut de l’ordonnance';

  @override
  String get prescriptionPaymentStatus => 'Statut du paiement de l’ordonnance';

  @override
  String get unpaid => 'Impayé';

  @override
  String get bedDetails => 'Détails du lit';

  @override
  String get roomNumber => 'Numéro de chambre:';

  @override
  String get bedType => 'Type de lit:';

  @override
  String get assignDate => 'Date d\'attribution:';

  @override
  String get dischargeDate => 'Date de sortie:';

  @override
  String get bedPrice => 'Prix du lit';

  @override
  String get perDay => 'par jour';

  @override
  String get bedHistory => 'Histoire du lit';

  @override
  String get seeYourBedHistory => "Consultez l'historique de votre lit";

  @override
  String get noBedHistoryFound => 'Aucun historique de lit trouvé';

  @override
  String get looksNoBedHistoryFound => "Il semble qu'aucun historique de lit n'ait été trouvé. Nous vous tiendrons au courant dès qu'une mise à jour sera disponible.";

  @override
  String get bedNumber => 'Numéro de lit';

  @override
  String get perDayCharge => 'Frais journaliers';

  @override
  String get totalCharges => 'Frais totaux';

  @override
  String get grandTotal => 'Total';
}
