=== Recognition ===
Contributors: jsswebsolutions
Tags: face recognition, login, authentication, biometric, security
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
Donate link: https://www.jsswebsolutions.com/donate
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first face recognition login for WordPress. No third-party APIs, fully offline operation.

== Description ==

Face Recognition Login is a WordPress plugin that allows users to securely log in using facial recognition technology while maintaining complete privacy. All processing happens locally on your server.

**Key Features:**

* **Privacy-First**: No biometric data ever leaves your server. All face recognition is done locally using TensorFlow.js and face-api.js.
* **Offline Operation**: No external APIs or cloud services required.
* **Easy Enrollment**: Users can easily enroll their face from their profile.
* **Multiple Devices**: Support for enrolling faces from multiple devices (webcam, phone camera, etc.).
* **Security Features**: Liveness detection, rate limiting, brute force protection.
* **GDPR Friendly**: Users can export and delete their biometric data.
* **Accessible**: Works with keyboard navigation and screen readers.
* **Customizable**: Configurable matching threshold and UI settings.

**How It Works:**

1. Users enroll their face by taking multiple photos with their webcam.
2. The plugin creates a mathematical "descriptor" (not an image) and stores it in your database.
3. On login, users click "Login with Face" and look at their camera.
4. The plugin generates a new descriptor and compares it to enrolled descriptors.
5. If the match is close enough (within threshold), the user is logged in.

== Installation ==

1. Upload the `recognition` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under "Recognition" in the admin menu
4. Users can enroll their face from their profile or after logging in

**Requirements:**

* HTTPS is required for camera access (except localhost)
* WordPress 6.0+
* PHP 8.0+
* Modern browser (Chrome, Firefox, Safari, Edge)

== Frequently Asked Questions ==

= Does this store photos of my face? =

No. The plugin extracts a mathematical "descriptor" from your face, which is a string of 128 numbers. This cannot be reverse-engineered into a photo.

= Is this secure? =

Yes. The plugin uses industry-standard face recognition models, liveness detection to prevent photo attacks, rate limiting, and optional encryption.

= What happens if recognition fails? =

Users can always fall back to traditional username/password login (if enabled by admin).

= Can I use this on HTTP? =

Camera access requires HTTPS in most browsers. You can disable HTTPS requirement in settings, but recognition won't work on HTTP sites (except localhost).

= Does this work on mobile? =

Yes, as long as the browser supports getUserMedia API and HTTPS is enabled.

== Changelog ==

= 1.0.0 =
* Initial public release of Recognition.
* Privacy-first face recognition login for WordPress. No third-party APIs, fully offline operation.
* Local-only face authentication using `face-api.js`.
* AES-256-CBC encryption of biometric descriptors at rest.
* Liveness detection via eye-aspect-ratio.
* Per-user multi-device enrollment, with `wp_users`-scoped data export & erase (GDPR).
* Admin dashboard with stats, logs, users, settings, and license pages.
* User dashboard for managing face profiles.
* License manager for premium add-on gating (server-side, defence-in-depth).
* Premium gate with server-enforced sanitisation of premium settings.
* Server-Sent Events plumbing (used by the QR add-on).
* Audit log dashboard chart (now powered by Logger).
* Authentication logging with rate limiting and brute force protection.
* Composer scripts for `lint`, `test` and `ci`.

== Upgrade Notice ==

= 1.0.0 =
First public release.
