# Stripe Push Provisioning
-dontwarn com.stripe.android.pushProvisioning.PushProvisioningActivity$g
-dontwarn com.stripe.android.pushProvisioning.PushProvisioningActivityStarter$Args
-dontwarn com.stripe.android.pushProvisioning.PushProvisioningActivityStarter$Error
-dontwarn com.stripe.android.pushProvisioning.PushProvisioningActivityStarter
-dontwarn com.stripe.android.pushProvisioning.PushProvisioningEphemeralKeyProvider
-dontwarn proguard.annotation.Keep
-dontwarn proguard.annotation.KeepClassMembers

# Keep annotations
-keepattributes *Annotation*

# Razorpay
-dontwarn com.razorpay.**
-keep class com.razorpay.** {*;}
-optimizations !method/inlining/
-keepclasseswithmembers class * {
  public void onPayment*(...);
}

# Gson (to prevent DateTypeAdapter R8 crash)
-keep class com.google.gson.internal.bind.DateTypeAdapter { *; }
-keep class com.google.gson.** { *; }
-dontwarn com.google.gson.**

# PhonePE
-dontwarn com.phonepe.phonepe_payment_sdk.PhonePePaymentSdk