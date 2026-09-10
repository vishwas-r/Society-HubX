# Decentralized Per-Society Push Notification Architecture (Firebase FCM v1)

## 1. Executive Summary

This architecture implements **100% free, decentralized, per-society push notifications** for **Society-GovernX** and the **Society HubX Mobile App**.

### Key Highlights:
- **Zero Central Infrastructure**: You (the platform developer) host no push relay servers, pay zero monthly fees, and store no resident device tokens centrally.
- **Complete Tenant Isolation**: Each apartment society sets up its own free Google Firebase project. Each society's WordPress site stores only its own residents' device tokens in its local database.
- **Modern FCM v1 API**: Uses Google's official FCM HTTP v1 API with pure PHP OAuth2 signing (zero heavy Composer dependencies).
- **Cross-Platform Support**: Delivers instant alerts to Android devices natively and iOS devices via APNs forwarding.

---

## 2. Architecture & Data Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           SOCIETY ADMIN                                 │
│  1. Creates free Firebase Project (https://console.firebase.google.com) │
│  2. Generates Firebase Service Account JSON (Private Key)               │
│  3. Uploads JSON in WordPress: Society Settings -> Push Notifications   │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       SOCIETY WORDPRESS PLUGIN                          │
│  - Securely stores Project ID, Client Email & RSA Private Key           │
│  - Exposes Public Sender ID via REST API: /notifications/config        │
│  - Generates Google OAuth2 JWTs via pure PHP (openssl_sign)             │
│  - Dispatches FCM v1 payloads via wp_remote_post()                     │
└─────────────────────────────────────────────────────────────────────────┘
            ▲                                               │
            │ 2. Registers Device Token                     │ 3. Dispatches
            │    (POST /notifications/register-token)       │    FCM v1
            │                                               │    Payload
┌───────────────────────┐                                   ▼
│   RESIDENT'S DEVICE   │                        ┌──────────────────────┐
│  (HubX Mobile App)    │                        │  GOOGLE FCM v1 CLOUD │
│                       │                        │  (Free, Unlimited)   │
│ 1. Fetches Sender ID  │                        └──────────────────────┘
│    & generates token  │                                   │
└───────────────────────┘                                   ├───────────────────┐
                                                            ▼                   ▼
                                                  ┌──────────────────┐ ┌────────────────┐
                                                  │ ANDROID DEVICES  │ │ APPLE APNs     │
                                                  │ (Google Play Svc)│ │ (iOS Devices)  │
                                                  └──────────────────┘ └────────────────┘
```

---

## 3. Database Schema

A dedicated database table is created per society to store resident device tokens:

### Table: `wp_shubx_device_tokens`

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY` | Primary key |
| `user_id` | `BIGINT(20) UNSIGNED NULL` | WordPress User ID (if logged in) |
| `flat_no` | `VARCHAR(50) NOT NULL` | Assigned unit (e.g. `A-302`, `Villa-14`) |
| `device_token` | `TEXT NOT NULL` | FCM Registration Token |
| `platform` | `VARCHAR(20) NOT NULL` | `android`, `ios`, or `web` |
| `device_name` | `VARCHAR(100) NULL` | E.g. `Samsung Galaxy S24`, `iPhone 16` |
| `app_version` | `VARCHAR(20) NULL` | E.g. `1.0.0` |
| `is_active` | `TINYINT(1) DEFAULT 1` | Active token flag (deactivated on logout/uninstall) |
| `created_at` | `DATETIME NOT NULL` | Registration timestamp |
| `updated_at` | `DATETIME NOT NULL` | Last refresh timestamp |

*Index*: `(flat_no, is_active)`, `(user_id, is_active)`, `(platform)`.

---

## 4. WordPress Backend Implementation

### 4.1 Admin Settings Interface
Located in: **WordPress Admin -> Society Settings -> Push Notifications Tab**

#### Configurable Options:
1. **Enable Push Notifications** (`shubx51_fcm_enabled`): `0` or `1`.
2. **Service Account JSON Uploader** (`shubx51_fcm_service_account`):
   - Accepts the `.json` file generated from Google Cloud / Firebase Console.
   - Automatically parses and populates:
     - `project_id`
     - `client_email`
     - `private_key` (stored encrypted / protected)
     - `sender_id` (Google Project Number)
3. **Emergency SOS Sound Override**: Enables looping high-priority alarm sound on resident devices.
4. **Test Push Button**: Allows the admin to send an immediate ping to their own device or flat to confirm delivery.

---

### 4.2 Lightweight Pure PHP FCM v1 Dispatcher (Zero External Libraries)

Google FCM v1 requires a short-lived Google OAuth2 Access Token. Instead of pulling in 50MB of `google/apiclient` Composer dependencies, we generate and sign the JWT using PHP's native `openssl_sign()` with RS256:

```php
class SHUBX51_FCM_Service {

    /**
     * Get or refresh short-lived Google OAuth2 Access Token (cached 55 mins).
     */
    public static function get_access_token() {
        $cached = get_transient( 'shubx51_fcm_access_token' );
        if ( $cached ) {
            return $cached;
        }

        $client_email = get_option( 'shubx51_fcm_client_email' );
        $private_key  = get_option( 'shubx51_fcm_private_key' );

        if ( empty( $client_email ) || empty( $private_key ) ) {
            return new WP_Error( 'fcm_not_configured', 'FCM credentials are not configured.' );
        }

        $now = time();
        $jwt_header  = rtrim( strtr( base64_encode( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ), '+/', '-_' ), '=' );
        $jwt_payload = rtrim( strtr( base64_encode( json_encode( array(
            'iss'   => $client_email,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ) ) ), '+/', '-_' ), '=' );

        $signature = '';
        openssl_sign( "{$jwt_header}.{$jwt_payload}", $signature, $private_key, 'SHA256' );
        $jwt_signature = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );

        $jwt_assertion = "{$jwt_header}.{$jwt_payload}.{$jwt_signature}";

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'timeout' => 15,
            'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt_assertion,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            return new WP_Error( 'fcm_auth_failed', 'Failed to retrieve FCM OAuth2 token.' );
        }

        set_transient( 'shubx51_fcm_access_token', $data['access_token'], 55 * MINUTE_IN_SECONDS );
        return $data['access_token'];
    }

    /**
     * Send push notification to a specific device or flat.
     */
    public static function send_notification( $token, $title, $body, $data = array(), $priority = 'high' ) {
        $project_id = get_option( 'shubx51_fcm_project_id' );
        $access_token = self::get_access_token();

        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";

        $message = array(
            'token' => $token,
            'notification' => array(
                'title' => $title,
                'body'  => $body,
            ),
            'data' => array_map( 'strval', $data ),
            'android' => array(
                'priority' => ( $priority === 'high' ) ? 'HIGH' : 'NORMAL',
                'notification' => array(
                    'sound'        => ( $data['type'] ?? '' ) === 'emergency_sos' ? 'alarm_sound' : 'default',
                    'channel_id'   => ( $data['type'] ?? '' ) === 'emergency_sos' ? 'emergency_channel' : 'general_alerts',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ),
            ),
            'apns' => array(
                'payload' => array(
                    'aps' => array(
                        'sound' => ( $data['type'] ?? '' ) === 'emergency_sos' ? 'alarm.wav' : 'default',
                        'badge' => 1,
                    ),
                ),
            ),
        );

        return wp_remote_post( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json; UTF-8',
            ),
            'body' => json_encode( array( 'message' => $message ) ),
        ) );
    }
}
```

---

### 4.3 REST API Endpoints

#### 1. Discovery / Notification Configuration
* **Route**: `GET /wp-json/society-hubx/v1/notifications/config`
* **Access**: Public
* **Response**:
  ```json
  {
    "success": true,
    "fcm_enabled": true,
    "sender_id": "847291039482",
    "project_id": "greenvalley-hubx"
  }
  ```

#### 2. Device Token Registration
* **Route**: `POST /wp-json/society-hubx/v1/notifications/register-token`
* **Access**: Authenticated Resident / Public with flat association
* **Payload**:
  ```json
  {
    "device_token": "fcm_token_string_here...",
    "platform": "android",
    "device_name": "Google Pixel 8",
    "flat_no": "A-302",
    "app_version": "1.0.0"
  }
  ```
* **Response**:
  ```json
  {
    "success": true,
    "message": "Device token successfully registered for Flat A-302."
  }
  ```

#### 3. Test Push
* **Route**: `POST /wp-json/society-hubx/v1/notifications/test-push`
* **Access**: Admin only (`manage_options`)
* **Payload**: `{ "target": "my_device" | "flat_no" }`

---

## 5. Event Triggers & Payloads

| Event | Target Recipients | Priority | Sound / Channel | Actionable Payload |
| :--- | :--- | :--- | :--- | :--- |
| 🚨 **Emergency SOS** | All Security Guards, Admin, Adjacent Flats | `HIGH` | `alarm_sound` / Siren (Critical) | `{ "type": "emergency_sos", "flat_no": "A-302", "sos_type": "Medical" }` |
| 🚪 **Visitor Gate Check-in** | Resident(s) of the target flat | `HIGH` | `doorbell` / Urgent | `{ "type": "visitor_approval", "visitor_name": "Delivery Agent", "log_id": "45" }` *(Approve / Deny buttons directly on notification)* |
| 🛠️ **Helpdesk Update** | Ticket Creator (Resident) | `NORMAL` | `default` | `{ "type": "ticket_update", "ticket_id": "102", "otp": "5892" }` |
| 💳 **Maintenance Bill** | Flat Resident(s) | `NORMAL` | `default` | `{ "type": "invoice_due", "invoice_id": "INV-2026-09", "amount": "4500" }` |
| 📢 **Pinned Notice** | All Society Residents | `NORMAL` | `default` | `{ "type": "announcement", "notice_id": "12" }` |

---

## 6. Mobile App Integration (HubX Mobile App)

### 6.1 Lifecycle Flow on Mobile
1. **On App Start / Society Connection**:
   - The app reads the society's `sender_id` via `GET /notifications/config`.
   - On Android / iOS, the app initializes the Firebase Messaging client for that `sender_id`.
2. **Token Generation & Sync**:
   - Device requests FCM token.
   - Device checks if token is already registered in local `AsyncStorage`. If changed, dispatches `POST /notifications/register-token`.
3. **Foreground / Background Handlers**:
   - **Foreground**: Shows an in-app banner or toast.
   - **Background**: Displays native system tray notification with action buttons (e.g. `[APPROVE]`, `[DENY]`).
   - **Notification Tap**: Automatically navigates directly to the relevant screen (`VisitorPassScreen`, `HelpdeskScreen`, or `EmergencySOSModal`).

---

## 7. Platform Specifics: Android vs. iOS

### Android (100% Native & Frictionless)
- Works out of the box with zero external dependencies.
- Google Play Services handles delivery natively for any society's Firebase project.
- Supports custom sound channels, vibration patterns, and lock-screen heads-up alerts.

### iOS (Apple Push Notification service - APNs)
- **Apple Constraint**: Apple strictly requires all push notifications to go through APNs. Google's FCM server translates your message to APNs before delivering to an iPhone.
- **Firebase Requirement**: In Firebase Console under **Project Settings -> Cloud Messaging -> Apple app configuration**, Firebase prompts for an **APNs Auth Key (`.p8` file)** from the Apple Developer Account.
- **Handling Model**:
  - **White-Labeled Society Apps**: If a society publishes its own app under its own Apple Developer Account, they upload their `.p8` key directly to their own Firebase project.
  - **Shared HubX App on iOS**: If societies share a single published App Store app, the plugin allows an optional fallback setting (e.g. free Expo push relay token) so iOS users receive alerts without requiring every individual society to purchase a $99/year Apple Developer membership.

---

## 8. Step-by-Step Society Admin Guide (3-Minute Setup)

1. Open [Firebase Console](https://console.firebase.google.com) and click **"Add Project"** (e.g. `Green-Valley-HubX`).
2. Navigate to **Project Settings (⚙️) -> Service Accounts**.
3. Click **"Generate new private key"** -> Download the `.json` file.
4. Log into WordPress Admin -> **Society HubX -> Society Settings -> Push Notifications**.
5. Click **"Upload Firebase Service Account JSON"** and select the downloaded file.
6. Click **"Send Test Push Notification"** — your setup is 100% verified and active!
