# Android push notifications

The backend uses Firebase Cloud Messaging HTTP v1. Legacy `FCM_SERVER_KEY`
configuration is not used.

## Server setup

1. In Firebase Console, enable Cloud Messaging for the Android application.
2. Create a service account key and save it outside Git at:
   `hospital-api/storage/app/firebase/service-account.json`.
3. Add the following values to `hospital-api/.env`:

   ```dotenv
   FIREBASE_PROJECT_ID=your-firebase-project-id
   FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
   FIREBASE_ANDROID_CHANNEL_ID=general
   ```

4. Run:

   ```bash
   php artisan migrate
   php artisan config:clear
   ```

Never commit the service-account JSON. Rotate any service-account key that has
previously been committed to Git.

## Android application

The Android Firebase project must contain the application package used by the
APK. Put its `google-services.json` in the Android app module and enable:

```kotlin
implementation(platform("com.google.firebase:firebase-bom:latest.release"))
implementation("com.google.firebase:firebase-messaging")
```

On Android 13 and later, request `POST_NOTIFICATIONS` at runtime. Create a
notification channel whose ID is the same as `FIREBASE_ANDROID_CHANNEL_ID`.

After login and whenever Firebase refreshes the token, call:

```http
POST /api/notifications/tokens/register
Authorization: Bearer {sanctum-token}
Content-Type: application/json

{
  "device_id": "stable-installation-id",
  "fcm_token": "firebase-registration-token",
  "app_version": "1.0.0",
  "os_version": "15",
  "device_model": "Pixel 8"
}
```

`user_id` and `platform` are intentionally ignored by the API. They are derived
from the authenticated user and fixed to Android.

On logout call:

```http
POST /api/notifications/tokens/unregister
Authorization: Bearer {sanctum-token}
Content-Type: application/json

{
  "device_id": "stable-installation-id"
}
```

## Sending a test notification

Only users with the `admin` or `demo_admin` role can send:

```http
POST /api/notifications/send
Authorization: Bearer {admin-sanctum-token}
Content-Type: application/json

{
  "user_id": 123,
  "type": "test",
  "title": "Test notification",
  "body": "Android push is working",
  "priority": "high",
  "data": {
    "screen": "notifications"
  }
}
```

The receiving user can list and mark their own notifications through the
authenticated `/api/notifications` endpoints.
