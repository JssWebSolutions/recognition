<div align="center">

# 🛡️ Recognition — Face Recognition Login for WordPress

### Privacy-first, offline face recognition login. No third-party APIs. Your face never leaves your server.

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPLv2%2B-success?logo=gnu&logoColor=white)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Stable Tag](https://img.shields.io/badge/Stable%20Tag-1.0.0-blue?logo=tag&logoColor=white)](#changelog)
[![Tested up to WP 7.1](https://img.shields.io/badge/Tested%20up%20to-WP%207.1-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![Privacy First](https://img.shields.io/badge/Privacy-100%25%20Local-success?logo=shield&logoColor=white)](#privacy--security)
[![Offline Operation](https://img.shields.io/badge/Operation-Offline%20%2F%20No%20APIs-blueviolet?logo=server&logoColor=white)](#how-it-works)
[![Made With](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F-red)](#contributors)

[**Live Demo & Buy Now**](https://www.jsswebsolutions.com/recognition/) · [**GitHub Repo**](https://github.com/JssWebSolutions/recognition) · [**License Activation**](https://license.jsswebsolutions.com/) · [**Report a Bug**](https://github.com/JssWebSolutions/recognition/issues)

</div>

---

## 📑 Table of Contents

- [Overview](#-overview)
- [Badges](#-badges)
- [Key Features](#-key-features)
- [How It Works](#-how-it-works)
- [Screenshots](#-screenshots)
- [Premium Add-ons](#-premium-add-ons)
  - [WooCommerce Add-on](#-woocommerce-add-on)
  - [QR Login Add-on](#-qr-login-add-on)
- [Installation](#-installation)
- [Requirements](#-requirements)
- [Privacy & Security](#-privacy--security)
- [GDPR Compliance](#-gdpr-compliance)
- [Frequently Asked Questions](#-frequently-asked-questions)
- [Developer Documentation](#-developer-documentation)
- [Changelog](#-changelog)
- [Roadmap](#-roadmap)
- [Support](#-support)
- [Contributing](#-contributing)
- [License](#-license)
- [Links](#-links)

---

## 🧠 Overview

**Recognition** is a privacy-first WordPress plugin that lets your users log in with their **face** — no passwords, no third-party APIs, no cloud services. All biometric processing happens **locally on your server** using [TensorFlow.js](https://www.tensorflow.org/js) and [face-api.js](https://github.com/justadudewhohacks/face-api.js).

Instead of storing a photo, the plugin extracts a **128-number mathematical descriptor** from the user's face and stores it encrypted at rest. On login, the plugin generates a new descriptor and compares it locally — if it matches, the user is signed in.

> 🔐 **Your face never leaves your server.** Period.

Recognition ships with a powerful **license manager** that gates premium add-ons (WooCommerce, QR Login, and future extensions) through our licensing portal at [license.jsswebsolutions.com](https://license.jsswebsolutions.com/).

---

## 🏷️ Badges

| Badge | Purpose |
| --- | --- |
| ![Version](https://img.shields.io/badge/Version-1.0.0-blue) | Current stable release |
| ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress) | Minimum WordPress version |
| ![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php) | Minimum PHP version |
| ![License](https://img.shields.io/badge/License-GPLv2%2B-orange?logo=gnu) | License type |
| ![Privacy](https://img.shields.io/badge/Privacy-100%25%20Local-success) | Local-only processing |
| ![Offline](https://img.shields.io/badge/Offline-Yes-blueviolet) | No external API calls |
| ![Build](https://img.shields.io/badge/Build-Passing-brightgreen?logo=github-actions) | CI status |
| ![Code Style](https://img.shields.io/badge/Code%20Style-WPCS-blue) | WordPress Coding Standards |
| ![Downloads](https://img.shields.io/github/downloads/JssWebSolutions/recognition/total?logo=github) | GitHub downloads |
| ![Stars](https://img.shields.io/github/stars/JssWebSolutions/recognition?style=social) | GitHub stars |
| ![Forks](https://img.shields.io/github/forks/JssWebSolutions/recognition?style=social) | GitHub forks |
| ![Issues](https://img.shields.io/github/issues/JssWebSolutions/recognition) | Open issues |
| ![Last Commit](https://img.shields.io/github/last-commit/JssWebSolutions/recognition) | Repo activity |
| ![Made With](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F-red) | Made with love |

> 💡 Generate more custom badges at [shields.io/badges](https://shields.io/badges).

---

## ✨ Key Features

| Feature | Description |
| --- | --- |
| 🔒 **Privacy-First** | Biometric data is never sent to any third party. All processing is local. |
| 🌐 **Offline Operation** | No external APIs, no cloud dependencies, no monthly fees. |
| 👤 **Easy Enrollment** | Users enroll their face from their profile in seconds. |
| 📱 **Multi-Device** | Enroll from webcam, phone camera, or any `getUserMedia`-capable device. |
| 👁️ **Liveness Detection** | Eye-aspect-ratio (EAR) algorithm prevents photo and replay attacks. |
| 🛡️ **AES-256 Encryption** | Descriptors are encrypted at rest with `AES-256-CBC`. |
| 🚦 **Rate Limiting** | Per-IP and per-user rate limits protect against brute force attacks. |
| 🧰 **Brute Force Protection** | Automatic lockouts and audit logging for failed attempts. |
| 🇪🇺 **GDPR Tools** | Built-in **Export** and **Erase** tools for biometric data. |
| ♿ **Accessible** | Full keyboard navigation and screen-reader support. |
| 🎛️ **Configurable Threshold** | Tune the matching threshold (0.30–0.70) per site. |
| 📊 **Admin Dashboard** | Stats, logs, users, and settings in one place. |
| 🪪 **License Manager** | Server-side license validation for premium add-ons. |
| 🧩 **Premium Gates** | Server-enforced sanitisation of premium-only settings. |
| 🧪 **WPCS Compliant** | Follows the WordPress Coding Standards. |
| 🌎 **Translation Ready** | `.pot` file shipped in `/languages`. |

---

## ⚙️ How It Works

```text
   ┌─────────────────┐                  ┌─────────────────┐
   │  User enrolls   │                  │  User logs in   │
   │  (signup page)  │                  │  (login page)   │
   └────────┬────────┘                  └────────┬────────┘
            │                                    │
            ▼                                    ▼
   ┌─────────────────┐                  ┌─────────────────┐
   │  Webcam capture │                  │  Webcam capture │
   │  (5–10 frames)  │                  │  (1 frame)      │
   └────────┬────────┘                  └────────┬────────┘
            │                                    │
            ▼                                    ▼
   ┌─────────────────┐                  ┌─────────────────┐
   │  face-api.js    │                  │  face-api.js    │
   │  → descriptor   │                  │  → descriptor   │
   │  (128 numbers)  │                  │  (128 numbers)  │
   └────────┬────────┘                  └────────┬────────┘
            │                                    │
            ▼                                    ▼
   ┌─────────────────┐                  ┌─────────────────┐
   │  AES-256-CBC    │                  │  Compare with   │
   │  encrypt + save │                  │  enrolled desc. │
   │  in WP DB       │                  │  (Euclidean)    │
   └─────────────────┘                  └────────┬────────┘
                                                 │
                                          ┌──────┴──────┐
                                          ▼             ▼
                                  ┌──────────┐   ┌──────────┐
                                  │  Match!  │   │ No match │
                                  │ Log user │   │ Fallback │
                                  │   in     │   │ to pwd?  │
                                  └──────────┘   └──────────┘
```

1. **Enrollment** — The user grants camera access and the plugin captures 5–10 frames.
2. **Descriptor extraction** — `face-api.js` converts each frame into a 128-number vector.
3. **Encryption & storage** — The descriptor is encrypted with `AES-256-CBC` and stored in the local WordPress database. **No image is ever stored.**
4. **Login** — On subsequent logins, the user clicks **"Login with Face"** and a single descriptor is generated.
5. **Matching** — The new descriptor is compared to the enrolled descriptors using Euclidean distance.
6. **Decision** — If the distance is below the configured threshold, the user is signed in. Liveness detection prevents photo attacks.

---

## 📸 Screenshots

> Live previews coming soon on the [landing page](https://www.jsswebsolutions.com/recognition/).

1. **Login page** with the new **"Login with Face"** button.
2. **Face enrollment** screen with a live webcam preview.
3. **Admin dashboard** with stats, recent activity, and license status.
4. **Settings page** with the matching-threshold slider.
5. **User profile** face-management section.
6. **Audit log** of authentication events.

---

## 🧩 Premium Add-ons

The Recognition plugin is **free and open-source under GPLv2+**. Premium functionality is delivered through licensed add-ons that hook into the core.

### 🛒 WooCommerce Add-on

[![Buy on Website](https://img.shields.io/badge/Buy-Our%20Website-success?logo=shopping-cart)](https://www.jsswebsolutions.com/recognition/) [![View on GitHub](https://img.shields.io/badge/View-GitHub-181717?logo=github)](https://github.com/JssWebSolutions/recognition)

Extend Recognition with full **WooCommerce integration** for passwordless customer login, checkout verification, and high-value order protection.

**Highlights**

- 🪪 **Passwordless WooCommerce login** — customers sign in with their face instead of a password.
- 🛒 **Checkout face verification** — verify the buyer's identity at checkout.
- 💰 **High-value order protection** — auto-require verification for orders above a configurable threshold.
- 📱 **Trusted devices** — skip verification on devices the customer has trusted.
- 📜 **Order verification logs** — every verification attempt is logged.
- 👤 **My Account dashboard** — customers manage trusted devices & history.
- 📊 **Admin reports** — comprehensive analytics for store admins.
- 🔌 **REST API** — full REST endpoints for third-party integrations.
- 🧱 **Scalable architecture** — built with hooks & filters for easy extension.

> Source folder: `frl-woocommerce-addon/`
> Requires: WordPress 6.0+, PHP 8.0+, WooCommerce 7.0+, Recognition core.

[See add-on folder →](https://github.com/JssWebSolutions/recognition/tree/main/frl-woocommerce-addon)

### 📱 QR Login Add-on

[![Buy on Website](https://img.shields.io/badge/Buy-Our%20Website-success?logo=shopping-cart)](https://www.jsswebsolutions.com/recognition/) [![View on GitHub](https://img.shields.io/badge/View-GitHub-181717?logo=github)](https://github.com/JssWebSolutions/recognition)

Sign in on devices without a webcam — like TVs, kiosks, or shared computers — by scanning a QR code with your phone.

**Highlights**

- 🔐 **256-bit cryptographic tokens** (`random_bytes()` with `wp_generate_password()` fallback).
- 🧠 **State machine** lifecycle (`pending → scanned → authenticating → authenticated → completed`) prevents race conditions & replay attacks.
- 📡 **Server-Sent Events** for instant desktop notification, with **AJAX polling** fallback.
- 🎨 **Branded modal** with site logo, custom accent colour, and an animated success state.
- ⏱️ **Countdown timer** with automatic QR rotation on expiry.
- 🚦 **Per-IP rate limiting** using WordPress transients.
- 🔁 **One-shot replay protection** for both desktop and mobile tokens.
- 📚 **Full audit log** in a dedicated database table.
- 🛠️ **Admin screens** for Settings, Logs, Sessions, and Statistics.
- 👤 **Customer self-service** in WordPress profile and (optionally) WooCommerce My Account.
- 🧪 **~20 actions and filters** for developers.
- 🌎 **Translation-ready** with a `.pot` file under `languages/`.

> Source folder: `frl-qr-login-addon/`
> Requires: WordPress 5.8+, PHP 7.4+ (PHP 8.1+ recommended), Recognition core.

[See add-on folder →](https://github.com/JssWebSolutions/recognition/tree/main/frl-qr-login-addon)

---

## 📥 Installation

### From the website (recommended)

1. Purchase a license at [jsswebsolutions.com/recognition](https://www.jsswebsolutions.com/recognition/).
2. Download the plugin zip from your customer dashboard.
3. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
4. Upload the zip, click **Install Now**, then **Activate**.
5. Visit **Recognition** in the admin menu to configure settings.
6. Activate your license at [license.jsswebsolutions.com](https://license.jsswebsolutions.com/) and enter the key in **Recognition → License**.

### From GitHub (development)

```bash
cd wp-content/plugins/
git clone https://github.com/JssWebSolutions/recognition.git
```

Then activate **Recognition** in the **Plugins** screen.

### Manual

1. Download the latest release from the [GitHub Releases](https://github.com/JssWebSolutions/recognition/releases) page.
2. Unzip into `wp-content/plugins/recognition/`.
3. Activate via the **Plugins** screen.

---

## 🧪 Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.0 |
| PHP | 8.0 |
| MySQL / MariaDB | 5.7 / 10.3 |
| Browser | Chrome 90+, Firefox 88+, Safari 14+, Edge 90+ |
| HTTPS | Required for camera access (except `localhost`) |
| Memory limit | 256 MB recommended |
| Max execution time | 60 s recommended |

---

## 🔐 Privacy & Security

- **Local processing** — `face-api.js` runs entirely in the user's browser; descriptors are computed client-side and only the resulting 128-number vector is sent to your server.
- **No third-party APIs** — Recognition never calls any external service, including Google, AWS, or Microsoft.
- **No images stored** — Only the 128-number descriptor is stored, not the image.
- **AES-256-CBC encryption** — Descriptors are encrypted at rest with a per-site key.
- **Liveness detection** — Eye-aspect-ratio analysis detects photo attacks.
- **Rate limiting** — Per-IP and per-user limits prevent brute force attacks.
- **Audit log** — Every authentication attempt is recorded with timestamp, IP, and result.
- **CSP-friendly** — No third-party scripts or `eval()` in the public bundle.

---

## 🇪🇺 GDPR Compliance

Recognition is designed to be GDPR-friendly out of the box:

- **Right to access** — Users can **export** their own biometric data from their profile.
- **Right to erasure** — Users can **delete** their own biometric data with one click.
- **Data minimisation** — Only a 128-number vector is stored, which cannot be reverse-engineered into a face.
- **Local storage** — Data never leaves the EU jurisdiction of your server.
- **Privacy by design** — No tracking, no analytics, no third-party calls.

Admins can also bulk-export or bulk-delete any user's biometric data from **Recognition → Users**.

---

## ❓ Frequently Asked Questions

### Does this store photos of my face?

**No.** The plugin only stores a mathematical descriptor (128 numbers) generated by `face-api.js`. This cannot be reverse-engineered into a photo.

### Is this secure?

**Yes.** The plugin uses industry-standard models, liveness detection, rate limiting, optional encryption, and a comprehensive audit log.

### What happens if recognition fails?

Users can always fall back to traditional username/password login (if enabled by admin) or use the QR add-on from a phone.

### Can I use this on HTTP?

Camera access requires HTTPS in most browsers. You can disable the HTTPS requirement in settings, but recognition **will not** work on plain HTTP sites (except `localhost`).

### Does this work on mobile?

**Yes**, on any modern browser that supports the `getUserMedia` API over HTTPS.

### Does this work without an internet connection?

**Yes** — once loaded, the plugin runs entirely offline. There are no third-party API calls.

### Can I disable face login for specific users?

**Yes** — admins can disable face login per-user from the user-edit screen, and users can disable it from their own profile.

### Where do I get a license key?

After purchasing an add-on, your license key is delivered by email and is also visible in your account dashboard at [license.jsswebsolutions.com](https://license.jsswebsolutions.com/).

### Can I get a refund?

Please see our refund policy at [jsswebsolutions.com/recognition](https://www.jsswebsolutions.com/recognition/).

---

## 🧑‍💻 Developer Documentation

Recognition is built with extensibility in mind. The plugin ships with:

- **PSR-4 autoloader** under `includes/`
- **~40+ actions and filters** for hooking into the lifecycle
- **Server-Sent Events (SSE)** channel used by the QR add-on
- **REST API** endpoints for headless integrations
- **Composer scripts** for `lint`, `test`, and `ci`
- **WordPress Coding Standards** compliance

```php
// Example: hook into a successful face login
add_action( 'frl_user_authenticated', function ( $user_id, $method ) {
    error_log( "User {$user_id} authenticated via {$method}" );
}, 10, 2 );
```

Run the dev tooling:

```bash
composer install
composer run lint
composer run test
composer run ci
```

---

## 📜 Changelog

### 1.0.0 — 2026-08-21

🎉 **First public release.**

- Privacy-first face recognition login for WordPress — no third-party APIs, fully offline.
- Local-only face authentication using `face-api.js`.
- AES-256-CBC encryption of biometric descriptors at rest.
- Liveness detection via eye-aspect-ratio.
- Per-user multi-device enrollment, with `wp_users`-scoped data export & erase (GDPR).
- Admin dashboard with stats, logs, users, settings, and license pages.
- User dashboard for managing face profiles.
- License manager for premium add-on gating (server-side, defence-in-depth).
- Premium gate with server-enforced sanitisation of premium settings.
- Server-Sent Events plumbing (used by the QR add-on).
- Audit log dashboard chart (powered by `Logger`).
- Authentication logging with rate limiting and brute force protection.
- Composer scripts for `lint`, `test`, and `ci`.
- WooCommerce and QR Login add-ons bundled in this repository.

---

## 🗺️ Roadmap

- [ ] 🧠 Multi-face enrollment (family accounts)
- [ ] 📲 Native iOS / Android SDKs
- [ ] 🌐 WebAuthn / Passkey bridge
- [ ] 🧾 Webhook events for authentication
- [ ] 🪪 License seat management
- [ ] 🧪 Fuzz testing suite

---

## 💬 Support

- 🐛 **Bug reports** — Open an [issue on GitHub](https://github.com/JssWebSolutions/recognition/issues).
- 💡 **Feature requests** — Open an [issue on GitHub](https://github.com/JssWebSolutions/recognition/issues) with the `enhancement` label.
- 📧 **Email** — [support@jsswebsolutions.com](mailto:support@jsswebsolutions.com)
- 🌐 **Website** — [jsswebsolutions.com/recognition](https://www.jsswebsolutions.com/recognition/)

When reporting a bug, please include:

1. WordPress version (`Dashboard → At a Glance`)
2. PHP version
3. Recognition plugin version
4. Steps to reproduce
5. A copy of the relevant entry from **Recognition → Logs**

---

## 🤝 Contributing

Contributions are welcome! 🎉

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Commit your changes: `git commit -m "Add my feature"`.
4. Push to the branch: `git push origin feature/my-feature`.
5. Open a [Pull Request](https://github.com/JssWebSolutions/recognition/pulls).

Please run `composer run ci` before opening a PR to ensure lint, tests, and coding standards pass.

---

## 📄 License

This plugin is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but **WITHOUT ANY WARRANTY**; without even the implied warranty of **MERCHANTABILITY** or **FITNESS FOR A PARTICULAR PURPOSE**. See the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html) for more details.

---

## 🔗 Links

| Link | URL |
| --- | --- |
| 🛒 **Buy / Landing page** | <https://www.jsswebsolutions.com/recognition/> |
| 💻 **GitHub repository** | <https://github.com/JssWebSolutions/recognition> |
| 🐛 **Issue tracker** | <https://github.com/JssWebSolutions/recognition/issues> |
| 📦 **Releases** | <https://github.com/JssWebSolutions/recognition/releases> |
| 🪪 **License portal** | <https://license.jsswebsolutions.com/> |
| 🛒 **WooCommerce add-on** | <https://www.jsswebsolutions.com/frl-woocommerce-addon/> |
| 📱 **QR Login add-on** | <https://www.jsswebsolutions.com/frl-qr-login-addon/> |
| 🪪 **License Website** | <https://license.jsswebsolutions.com/> |
| 🏢 **Author website** | <https://www.jsswebsolutions.com> |
| 📧 **Support email** | [support@jsswebsolutions.com](mailto:support@jsswebsolutions.com) |
| 💖 **Donate** | <https://www.jsswebsolutions.com/donate> |

---

<div align="center">

Made with ❤️ by **[JSS Web Solutions](https://www.jsswebsolutions.com)** — Building the privacy-first WordPress ecosystem.

</div>
