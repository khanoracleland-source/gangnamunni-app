# CareBaby Platform - Development Scenario

## Project Overview

Beauty Hospital Platform (HMS + Gangnamunni + Gnuboard)
- Domain: https://hms.seoulbeauty.app
- Server: contabo1 (5.104.82.35), Rocky Linux 9.7
- GitHub: https://github.com/appdosa1004-cyber/carebaby-platform

---

## Phase 1: Infrastructure Setup (COMPLETED)

### 1.1 Server Consolidation
- [x] PC files transferred to server (24,900+ files)
- [x] hairwang DB migrated to localhost (175 tables)
- [x] data/dbconfig.php + root dbconfig.php updated to localhost
- [x] G5_MYSQL_PORT added to data/dbconfig.php
- [x] Apache /hairwang alias configured
- [x] GitHub repo created (carebaby-platform, private)
- [x] gangnamunni standalone removed, merged into hairwang_full/gangnamunni/

### 1.2 Verified Working
- [x] HMS Laravel: https://hms.seoulbeauty.app/ (200 OK)
- [x] Gangnamunni: https://hms.seoulbeauty.app/hairwang/gangnamunni/ (200 OK)
- [x] DB connection: localhost hairwang (175 tables)
- [x] PHP 8.2 via PHP-FPM

---

## Phase 2: Gangnamunni Service Enhancement (IN PROGRESS)

### 2.1 Core Features (Completed)
- [x] Hospital listing with search/filter (99 hospitals)
- [x] Hospital detail pages
- [x] Review system (editor with Quill.js)
- [x] Review CRUD API (10 endpoints)
- [x] Tips/articles display (20 tips)
- [x] Category filtering (11 categories)

### 2.2 Auth Integration (Completed)
- [x] Gnuboard session sharing via init.php
- [x] Login/logout through Gnuboard member system (77 members)
- [x] API auth enforcement (CSRF, rate limiting)

### 2.3 Pending Improvements
- [ ] Replace hairwang.com URLs in DB content with new domain
- [ ] Image upload path configuration for new server
- [ ] Hospital detail page API integration
- [ ] Booking system integration with HMS
- [ ] Favorites/wishlist functionality
- [ ] Mobile responsive optimization

---

## Phase 3: HMS + Gangnamunni Integration (PLANNED)

### 3.1 HMS Backend Integration
- [ ] Connect gangnamunni hospitals to HMS doctor/clinic system
- [ ] Map gangnamunni_hospitals to HMS hospital model
- [ ] Integrate HMS appointment system with gangnamunni booking
- [ ] Share user authentication (HMS Laravel Sanctum + Gnuboard session)

### 3.2 API Unification
- [ ] Create unified API gateway (/api/v1/)
- [ ] Hospital data: HMS + gangnamunni merged
- [ ] Booking: HMS appointment system
- [ ] Reviews: gangnamunni review system
- [ ] Auth: HMS Sanctum tokens

### 3.3 Frontend Unification
- [ ] Gangnamunni-style UI for HMS pages
- [ ] Tailwind CSS theming
- [ ] Shared navigation/header/footer
- [ ] Korean localization complete

---

## Phase 4: SNS/Payment Integration (CODE COMPLETE)

### 4.1 SNS Login (Code Ready)
- [x] 6 providers: Naver, Kakao, Google, Facebook, Twitter, Payco
- [x] API key-based auth + IP whitelist
- [x] JWT token authentication
- [ ] Production API keys setup
- [ ] OAuth callback URL configuration for new domain

### 4.2 Payment (Code Ready)
- [x] 5 PG providers: INIpay, LG U+, KCP, KakaoPay, NicePay
- [x] Order/cart/payment workflow
- [ ] PG merchant keys configuration
- [ ] Test transaction verification

### 4.3 Push Notifications (Code Ready)
- [x] Firebase FCM HTTP v1 API
- [x] JWT token generation + RSA signing
- [x] Push dashboard (new_http_v1_mars/)
- [ ] Firebase service account JSON deployment
- [ ] Topic subscription setup

---

## Phase 5: Mobile App (NOT STARTED)

### 5.1 Flutter App
- [ ] Flutter environment setup
- [ ] WebView + native hybrid architecture
- [ ] Hospital search/detail screens
- [ ] Booking flow
- [ ] Review editor (WebView)
- [ ] Push notification integration
- [ ] Kakao/Naver login
- [ ] Build APK/IPA

---

## Phase 6: Production Launch

### 6.1 Pre-launch
- [ ] SSL certificate (proper, not CA cert)
- [ ] Domain configuration (final)
- [ ] CDN setup (Cloudflare optimization)
- [ ] Database backup automation
- [ ] Error monitoring (log rotation)
- [ ] Load testing

### 6.2 Launch
- [ ] Beta test with limited users
- [ ] Bug fixes from feedback
- [ ] Performance optimization
- [ ] Official launch

---

## Development Environment

### Server Access
```bash
# SSH
ssh root@5.104.82.35  # password: <REMOVED_SSH_PASSWORD>

# Working Directory
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas

# Claude CLI
claude

# PHP
php82 [script.php]
```

### URLs
- HMS Admin: https://hms.seoulbeauty.app/admin (admin@hms.com / 123456)
- Gangnamunni: https://hms.seoulbeauty.app/hairwang/gangnamunni/
- Gnuboard Admin: https://hms.seoulbeauty.app/hairwang/adm/
- FCM Dashboard: https://hms.seoulbeauty.app/hairwang/new_http_v1_mars/
- Dev Dashboard: https://hms.seoulbeauty.app/hairwang/gangnamunni/dev_dashboard.php

### Database
```
Host: localhost
User: root
Password: <REMOVED_DB_PASSWORD>
DB: hairwang (175 tables) / hms_saas (HMS)
```

### GitHub
```bash
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git add -A && git commit -m "description" && git push
```

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 10 (PHP 8.2) + Gnuboard 5 |
| Frontend | Tailwind CSS + Vanilla JS |
| Editor | Quill.js 1.3.7 |
| Database | MariaDB 10.x |
| Mobile | Flutter (planned) |
| Server | Rocky Linux 9.7 + Apache 2.4 |
| Auth | Laravel Sanctum + Gnuboard Session |
| Payment | PortOne (INIpay, KakaoPay, etc.) |
| Push | Firebase FCM HTTP v1 |
| Maps | Kakao Maps API |
| CDN | Cloudflare |

---

Updated: 2026-02-08
Author: Claude Opus 4.6
