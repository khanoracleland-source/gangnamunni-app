-dontwarn proguard.annotation.Keep
-dontwarn proguard.annotation.KeepClassMembers

# Keep annotations
-keepattributes *Annotation*

# Gson (to prevent DateTypeAdapter R8 crash)
-keep class com.google.gson.internal.bind.DateTypeAdapter { *; }
-keep class com.google.gson.** { *; }
-dontwarn com.google.gson.**
