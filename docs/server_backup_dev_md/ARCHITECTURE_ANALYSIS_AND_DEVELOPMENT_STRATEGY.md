# Architecture Analysis & Development Strategy
## Frezka vs KiviCare for 강남언니-style Platform

**Analysis Date:** 2025-12-08
**Analyst:** System Architect
**Purpose:** Determine optimal development approach for dermatology/plastic surgery clinic platform

---

## Executive Summary

### Quick Recommendation

**Primary Development Base:** KiviCare Laravel v1.9.0
**Supplementary Reference:** Frezka Admin Panel v4.0.3
**Development Approach:** Hybrid (KiviCare base + Frezka modules)

### Key Decision Factors

| Factor | KiviCare | Frezka | Winner |
|--------|----------|--------|--------|
| Medical Domain Fit | Healthcare specialized | Beauty/salon | **KiviCare** |
| Technology Stack | Laravel 11 + PHP 8.2 | Laravel 10 + PHP 8.0.2 | **KiviCare** |
| Module Architecture | 36 modules (medical focus) | 35 modules (service focus) | **KiviCare** |
| Database Schema | Medical records, prescriptions | Reviews, packages | **Split** |
| Payment Infrastructure | Stripe, Razorpay, PayPal | Same + more gateways | **Frezka** |
| Review/Rating System | Basic | Advanced | **Frezka** |

---

## Part 1: Technology Stack Comparison

### 1.1 Core Framework Analysis

#### KiviCare Laravel v1.9.0

```json
{
  "laravel": "^11.0",
  "php": "^8.2",
  "advantages": [
    "Latest Laravel version (better performance)",
    "Modern PHP 8.2 features (enums, readonly properties)",
    "Updated dependencies (less security vulnerabilities)",
    "Better type safety and IDE support"
  ]
}
```

**Key Dependencies:**
- Laravel Sanctum 4.0 (latest API auth)
- Spatie Media Library 11.11 (latest)
- Spatie Permission 6.1 (latest)
- Spatie Backup 9.3 (latest)
- Yajra Datatables 11.0 (Laravel 11 compatible)

#### Frezka Admin Panel v4.0.3

```json
{
  "laravel": "^10.10",
  "php": "^8.0.2",
  "advantages": [
    "Stable Laravel 10 LTS support",
    "More payment gateway integrations",
    "Advanced review/rating features",
    "Mature wallet system"
  ]
}
```

**Key Dependencies:**
- Laravel Sanctum 3.3
- Spatie Media Library 10.15
- Spatie Permission 6.1
- Spatie Backup 8.4
- Yajra Datatables 10.11

### 1.2 Frontend Stack (Identical)

Both projects use:
- Vue 3.2.45
- Bootstrap 5.3.x
- Pinia 2.0.35
- FullCalendar 6.1.7
- Vee-Validate 4.x
- Vue i18n 9.2.2

**Conclusion:** Frontend migration would be seamless between projects.

---

## Part 2: Database Schema Analysis

### 2.1 Core Schema Overlap (85% Compatible)

**Common Tables:**
```
users                    ✅ Both (structure 90% identical)
addresses                ✅ Both (polymorphic relation)
permissions/roles        ✅ Both (Spatie RBAC)
settings                 ✅ Both (key-value config)
media                    ✅ Both (Spatie Media)
activity_log             ✅ Both (audit trail)
notifications            ✅ Both (user notifications)
wallets                  ✅ Both (digital wallet)
wallet_transactions      ✅ Both (transaction log)
```

### 2.2 KiviCare Unique Tables (Medical Domain)

```sql
-- Medical Records
appointments             -- Enhanced with medical context
patient_encounters       -- SOAP notes, vital signs
encounter_prescriptions  -- Prescription management
encounter_medical_history -- Patient medical history
encounter_medical_reports -- Lab results, imaging
billing_records          -- Medical billing
billing_items            -- Itemized charges

-- Clinic Management
clinics                  -- Multi-clinic support
doctors                  -- Doctor profiles
doctor_sessions          -- Availability scheduling
doctor_clinic_mapping    -- Many-to-many clinic assignment
doctor_service_mapping   -- Service specialization
doctor_ratings           -- Patient reviews
clinic_gallery           -- Facility photos

-- Hospital Operations
beds                     -- Bed inventory
bed_types                -- Room classifications
bed_allocations          -- Patient bed assignments
patient_vitals           -- Vital signs tracking
body_charts              -- Anatomical diagrams
```

**Medical Domain Value:** 10/10 - Complete hospital management

### 2.3 Frezka Unique Tables (Service/Commerce Focus)

```sql
-- Service Management
services                 -- Service catalog
service_branches         -- Multi-location services
service_employees        -- Staff assignments
booking_services         -- Multi-service bookings
booking_packages         -- Package deals

-- Commerce
products                 -- E-commerce products
product_variations       -- SKU management
orders                   -- Order processing
carts                    -- Shopping cart
product_reviews          -- Customer reviews
wishlists                -- Saved products

-- Staff Management
branch_employees         -- Employee-branch mapping
employee_ratings         -- Customer ratings
employee_commissions     -- Commission tracking
employee_earnings        -- Payroll

-- Marketing
promotions               -- Discount campaigns
coupons                  -- Promo codes
tips                     -- Gratuity system
subscriptions            -- Recurring revenue
```

**E-commerce Value:** 9/10 - Advanced retail features

### 2.4 Schema Compatibility Matrix

| Feature Domain | KiviCare | Frezka | Merge Difficulty |
|----------------|----------|--------|------------------|
| User Management | ✅ | ✅ | Easy (identical) |
| Appointment/Booking | ✅ Medical | ✅ Service | Medium (merge fields) |
| Payment/Wallet | ✅ | ✅ | Easy (same structure) |
| Reviews/Ratings | ✅ Doctor | ✅ Service+Staff | Medium (unify tables) |
| Service Catalog | ✅ Medical | ✅ Beauty | Easy (compatible) |
| Multi-location | ✅ Clinics | ✅ Branches | Easy (same concept) |
| Medical Records | ✅ | ❌ | N/A (KiviCare only) |
| E-commerce | ❌ | ✅ | N/A (Frezka only) |

---

## Part 3: Module Structure Analysis

### 3.1 KiviCare Modules (36)

**Medical Core (12 modules):**
```
Appointment/          # Appointment + Encounter + Prescription + Billing
Clinic/               # Clinic + Doctor + Service + Session management
Bed/                  # Hospital bed management
Vital/                # Patient vital signs
Customer/             # Patient management (includes OtherPatient)
Service/              # Medical service catalog
QuickBooking/         # Walk-in appointment system
RequestService/       # Service request handling
CustomForm/           # Dynamic medical forms
Observation/          # Clinical observations
Problems/             # Medical problem lists
EncounterTemplate/    # Reusable encounter templates
```

**Business Operations (8 modules):**
```
Commission/           # Revenue sharing
Earning/              # Income tracking
Wallet/               # Digital wallet
Promotion/            # Discounts/coupons
Tax/                  # Tax calculation
Subscriptions/        # SaaS subscription
MultiVendor/          # Multi-tenant support
Product/              # E-commerce (Bagisto integration)
```

**Platform Infrastructure (16 modules):**
```
Constant/             # System constants
Currency/             # Multi-currency
CustomField/          # Dynamic fields
Location/             # Country/State/City
Logistic/             # Delivery zones
World/                # Global data
Blog/                 # Content management
FAQ/                  # Knowledge base
Page/                 # CMS pages
Slider/               # Homepage sliders
Tag/                  # Tagging system
Frontend/             # Public website
FrontendSetting/      # Theme customization
Language/             # i18n support
NotificationTemplate/ # Email/SMS templates
Installer/            # Setup wizard
Plugins/              # Plugin system
```

### 3.2 Frezka Modules (35)

**Service Core (10 modules):**
```
Booking/              # Appointment system
Service/              # Service catalog
Employee/             # Staff management
Package/              # Service bundles
QuickBooking/         # Fast booking
Category/             # Service categorization
BussinessHour/        # Operating hours
Holiday/              # Business closures
Location/             # Multi-location
Branch/               # Branch management (via core app)
```

**Commerce (6 modules):**
```
Product/              # E-commerce products
Customer/             # Customer CRM
Promotion/            # Marketing campaigns
Tip/                  # Gratuity system
Subscriptions/        # Recurring payments
Bank/                 # Banking integration
```

**Financial (4 modules):**
```
Wallet/               # Digital wallet
Commission/           # Revenue sharing
Earning/              # Earnings tracking
Tax/                  # Tax management
```

**Platform (15 modules):**
```
Frontend/             # Public website
FrontendSetting/      # Customization
Blog/                 # Content
Page/                 # CMS
Slider/               # Banners
FAQ/                  # Knowledge base
Language/             # i18n
NotificationTemplate/ # Notifications
Currency/             # Multi-currency
World/                # Global data
CustomField/          # Dynamic fields
Constant/             # System constants
Installer/            # Setup
LikeModule/           # Social features
Logistic/             # Delivery
MenuBuilder/          # Dynamic menus
```

### 3.3 Module Compatibility Analysis

```
Compatible (Can use either):
✅ Blog, FAQ, Page, Slider              - Identical functionality
✅ Language, Currency, World            - Same structure
✅ Frontend, FrontendSetting            - Similar approach
✅ NotificationTemplate, CustomField    - Compatible design
✅ Constant, Installer                  - Standard patterns

KiviCare Advantage:
⭐ Appointment (medical features)       - SOAP notes, prescriptions
⭐ Clinic (hospital management)         - Multi-clinic, doctor sessions
⭐ Bed (hospital operations)            - Bed allocation
⭐ Vital (health tracking)              - Patient vitals
⭐ CustomForm (dynamic forms)           - Medical form builder

Frezka Advantage:
⭐ Service (advanced packages)          - Service bundles
⭐ Employee (staff features)            - Commission, ratings
⭐ Package (bundling)                   - Package deals
⭐ Promotion (marketing)                - Advanced campaigns
⭐ Product (e-commerce)                 - Full retail system
⭐ LikeModule (social)                  - Social engagement
```

---

## Part 4: 강남언니 Platform Requirements Analysis

### 4.1 Core Features Needed

**Priority 1 (MVP):**
```
✅ Clinic/Hospital Registration         - KiviCare strong
✅ Doctor/Staff Profiles                - KiviCare excellent
✅ Service/Procedure Catalog            - Both strong
✅ Online Appointment Booking           - Both excellent
✅ Payment Processing                   - Both support
✅ Reviews & Ratings                    - Frezka more advanced
✅ Multi-location Support               - Both strong
✅ Mobile API                           - Both Sanctum-based
```

**Priority 2 (Growth):**
```
⚠️ Before/After Photo Gallery          - Need custom
⚠️ Procedure Ranking System            - Need custom
⚠️ Advertising Platform                - Need custom (CPC/CPV)
⚠️ Korean PG Integration               - Need custom (PortOne)
⚠️ Korean Map API                      - Need custom (Kakao/Naver)
⚠️ Medical Advertising Compliance      - Need custom
⚠️ Korean Localization                 - Need translation
```

**Priority 3 (Advanced):**
```
🔧 AI Recommendation Engine            - Future development
🔧 Video Consultation                  - Partial (meet link support)
🔧 Insurance Integration               - Future development
🔧 Telemedicine Compliance             - Future development
```

### 4.2 Feature Gap Analysis

| Feature | KiviCare | Frezka | Gap |
|---------|----------|--------|-----|
| Medical Records | ✅ Full | ❌ None | KiviCare advantage |
| Service Packages | ⚠️ Basic | ✅ Advanced | Frezka advantage |
| Reviews System | ⚠️ Basic | ✅ Advanced | Frezka advantage |
| E-commerce | ⚠️ Basic | ✅ Full | Frezka advantage |
| Advertising Platform | ❌ | ❌ | **Need custom** |
| Korean Payment | ❌ | ❌ | **Need custom** |
| Korean Maps | ❌ | ❌ | **Need custom** |
| Ranking Algorithm | ❌ | ❌ | **Need custom** |

---

## Part 5: Development Strategy Recommendations

### 5.1 Recommended Approach: Hybrid Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    HYBRID PLATFORM ARCHITECTURE                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              PRIMARY BASE: KiviCare v1.9.0                │   │
│  │  ✅ Laravel 11 + PHP 8.2 (modern stack)                   │   │
│  │  ✅ Medical domain models (Clinic, Doctor, Appointment)   │   │
│  │  ✅ Multi-clinic architecture                             │   │
│  │  ✅ Medical records infrastructure                        │   │
│  │  ✅ RBAC with medical roles                               │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │         IMPORTED MODULES FROM Frezka v4.0.3               │   │
│  │  📦 Advanced Review/Rating System                         │   │
│  │  📦 Service Package Module                                │   │
│  │  📦 Employee Commission System                            │   │
│  │  📦 Enhanced Wallet Features                              │   │
│  │  📦 E-commerce Product Module                             │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              CUSTOM DEVELOPMENT (Korean Market)           │   │
│  │  🇰🇷 Korean Localization (완전한 한국어 지원)              │   │
│  │  💳 PortOne PG Integration (포트원 결제)                  │   │
│  │  🗺️ Kakao/Naver Map API (한국 지도)                       │   │
│  │  📊 Advertising Platform (광고 플랫폼)                     │   │
│  │  🏆 Ranking Algorithm (랭킹 시스템)                        │   │
│  │  ⚖️ Medical Ad Compliance (의료광고 심의)                 │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Migration Strategy

**Phase 1: Setup KiviCare Base (Week 1-2)**
```bash
# 1. Install KiviCare
git clone kivicare-laravel-web-v1.9.0 carebaby-platform
cd carebaby-platform
composer install
npm install

# 2. Configure for Korean market
cp .env.example .env
# Set Korean timezone, locale
APP_TIMEZONE=Asia/Seoul
APP_LOCALE=ko

# 3. Database setup
php artisan migrate:fresh
php artisan db:seed

# 4. Verify core functionality
php artisan serve
```

**Phase 2: Import Frezka Modules (Week 3-4)**
```bash
# Strategy: Copy specific modules to KiviCare Modules directory

# Module import list:
Modules/Employee/      # Enhanced staff management
Modules/Package/       # Service packages
Modules/Product/       # E-commerce (if needed)

# Update composer.json with any Frezka-specific dependencies
# Resolve namespace conflicts
# Migrate database schemas
# Test module integration
```

**Phase 3: Korean Customization (Week 5-8)**
```
✅ Korean language files (lang/ko/)
✅ PortOne payment gateway
✅ Kakao Maps integration
✅ Korean address format
✅ Korean phone number validation
✅ Korean business registration
```

**Phase 4: Custom Features (Week 9-16)**
```
✅ Advertising platform module
✅ Ranking algorithm
✅ Before/after gallery
✅ Medical compliance features
✅ Advanced search/filter
```

### 5.3 Can They Be Developed Separately?

**Answer: Yes, but NOT recommended**

**Separate Development Scenarios:**

**Scenario A: Both in Current Locations**
```
✅ Pros:
   - Can reference both codebases
   - No merge conflicts
   - Easy to copy modules

❌ Cons:
   - Code duplication
   - Difficult to maintain
   - Cannot leverage shared infrastructure
   - Wasted development effort
```

**Scenario B: Separate Projects for Different Purposes**
```
KiviCare → Internal clinic management (admin only)
Frezka → Public-facing booking platform

✅ Pros:
   - Clear separation of concerns
   - Different user bases

❌ Cons:
   - Data synchronization complexity
   - Duplicate user management
   - Increased infrastructure cost
```

**Recommended: Single Merged Project**
```
✅ Pros:
   - Unified codebase
   - Shared authentication
   - Single database
   - Easier maintenance
   - Better performance

⚠️ Cons (manageable):
   - Initial merge effort
   - Potential conflicts (resolvable)
   - Larger codebase
```

### 5.4 Merge Strategy

**Option 1: Git Subtree Merge (Recommended)**
```bash
# Start with KiviCare as base
cd kivicare-laravel-web-v1.9.0

# Add Frezka as remote
git remote add frezka ../frezka_admin_panel_v4.0.3
git fetch frezka

# Selective module merge
git read-tree --prefix=Modules/FrezkaEmployee/ -u frezka/main:Modules/Employee
git read-tree --prefix=Modules/FrezkaPackage/ -u frezka/main:Modules/Package

# Commit merged modules
git commit -m "Import Frezka modules: Employee, Package"
```

**Option 2: Manual Module Copy (Safer for beginners)**
```bash
# 1. Identify target modules from Frezka
Modules/Employee/
Modules/Package/
Modules/Product/    # If e-commerce needed

# 2. Copy to KiviCare Modules/
cp -r ../frezka_admin_panel_v4.0.3/Modules/Employee ./Modules/FrezkaEmployee

# 3. Rename namespaces
# From: Modules\Employee
# To:   Modules\FrezkaEmployee

# 4. Update module.json
{
  "name": "FrezkaEmployee",
  "alias": "frezkaemployee",
  ...
}

# 5. Register in modules_statuses.json
{
  "FrezkaEmployee": true
}

# 6. Run migrations
php artisan module:migrate FrezkaEmployee
```

### 5.5 Which Should Be the Main/Base Project?

**Recommendation: KiviCare Laravel v1.9.0**

**Rationale:**

**1. Technology Stack Superiority**
```
Laravel 11 vs Laravel 10:
✅ Better performance (optimized query builder)
✅ Modern PHP 8.2 features
✅ Longer LTS support
✅ Better security updates
✅ Future-proof
```

**2. Domain Alignment**
```
Medical/Healthcare vs Beauty/Salon:
✅ KiviCare = Hospitals/Clinics (강남언니 context)
✅ Better terminology (Doctor, Patient, Clinic)
✅ Medical record infrastructure
✅ More professional perception
```

**3. Database Schema**
```
✅ More comprehensive (114 models vs ~50)
✅ Better normalized structure
✅ Medical-specific tables ready
✅ Scalable architecture
```

**4. Module Architecture**
```
✅ More modular (36 modules)
✅ Better separation of concerns
✅ Medical workflow support
✅ Extensible plugin system
```

**5. Scalability**
```
✅ Multi-vendor architecture
✅ Role-based access (6 roles vs 4)
✅ Multi-clinic support built-in
✅ Better suited for marketplace model
```

**What to Import from Frezka:**

```
Priority 1 (Essential):
📦 Employee Module       → Enhanced staff management
📦 Package Module        → Service bundling
📦 Advanced Reviews      → Better rating system

Priority 2 (Nice to Have):
📦 Product Module        → If e-commerce needed
📦 Promotion Module      → If marketing features needed
📦 MenuBuilder           → Dynamic navigation

Priority 3 (Optional):
📦 LikeModule           → Social features
📦 Specific UI Components → If design is better
```

---

## Part 6: Technical Integration Plan

### 6.1 Module Compatibility Matrix

| Frezka Module | KiviCare Equivalent | Merge Strategy |
|---------------|---------------------|----------------|
| Employee | ❌ None | **Import entire module** |
| Package | ⚠️ Basic service bundles | **Replace with Frezka** |
| Product | ⚠️ Basic product | **Import if e-commerce needed** |
| Promotion | ✅ Similar | **Merge features** |
| Wallet | ✅ Similar | **Keep KiviCare, add features** |
| Commission | ✅ Identical | **Keep KiviCare** |
| Earning | ✅ Identical | **Keep KiviCare** |
| Service | ✅ Similar | **Keep KiviCare** |
| Booking | ✅ Appointment | **Keep KiviCare** |
| Customer | ✅ Similar | **Keep KiviCare** |

### 6.2 Database Merge Strategy

**Approach: Additive Schema Extension**

```sql
-- Keep all KiviCare tables
-- Add Frezka-specific tables with prefix

-- Example: Employee features
CREATE TABLE frezka_employee_settings (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,  -- Link to existing users table
    commission_rate DECIMAL(5,2),
    is_featured BOOLEAN,
    specialties JSON,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Example: Package system
CREATE TABLE service_packages (
    id BIGINT PRIMARY KEY,
    name VARCHAR(200),
    description TEXT,
    base_price DECIMAL(10,2),
    discount_percentage DECIMAL(5,2),
    created_at TIMESTAMP
);

CREATE TABLE service_package_items (
    id BIGINT PRIMARY KEY,
    package_id BIGINT,
    service_id BIGINT,  -- Link to KiviCare services
    quantity INT,
    FOREIGN KEY (package_id) REFERENCES service_packages(id),
    FOREIGN KEY (service_id) REFERENCES clinic_services(id)
);
```

### 6.3 API Endpoint Strategy

**Approach: Versioned API with Backward Compatibility**

```
/api/v1/  → KiviCare original endpoints
/api/v2/  → Enhanced endpoints with Frezka features
/api/v3/  → Custom Korean market features

Examples:
/api/v1/appointments      → Original KiviCare
/api/v2/appointments      → + Frezka package support
/api/v3/appointments      → + Korean payment, maps

/api/v1/services          → Medical services
/api/v2/services          → + Service packages
/api/v3/services          → + Ranking, ads
```

### 6.4 Code Organization

```
app/
├── Models/                    # Core models (KiviCare)
├── Services/
│   ├── KiviCare/              # Original services
│   ├── Frezka/                # Imported services
│   └── Custom/                # Korean market services
│       ├── PortOnePayment.php
│       ├── KakaoMapService.php
│       └── RankingService.php
Modules/
├── Appointment/               # KiviCare (keep)
├── Clinic/                    # KiviCare (keep)
├── FrezkaEmployee/            # Imported from Frezka
├── FrezkaPackage/             # Imported from Frezka
├── Advertising/               # Custom (new)
├── Ranking/                   # Custom (new)
└── KoreanCompliance/          # Custom (new)
```

---

## Part 7: Korean Market Customization

### 7.1 Korean Localization Scope

**Translation Requirements:**

```
Core Application:
├── Authentication (login, register, password reset)
├── Navigation menus
├── Dashboard labels
├── Form fields and validation messages
├── Success/error messages
├── Email templates
├── SMS templates

Medical Domain:
├── Clinic/Hospital terminology
├── Doctor specialties
├── Medical procedures (dermatology, plastic surgery)
├── Appointment status labels
├── Prescription terms
├── Billing terminology

Korean Specific:
├── Address format (도로명주소, 지번주소)
├── Phone number format (010-XXXX-XXXX)
├── Business registration (사업자등록번호)
├── Medical license (의사 면허번호)
├── Privacy policy (개인정보처리방침)
```

**Estimated Translation Volume:**
- KiviCare: ~1,200 strings
- Frezka modules: ~300 strings
- **Total: ~1,500 strings**

**Timeline:** 10-15 business days with professional translator

### 7.2 Korean Payment Integration (PortOne)

**Implementation Plan:**

```php
// config/portone.php
return [
    'imp_code' => env('PORTONE_IMP_CODE'),
    'api_key' => env('PORTONE_API_KEY'),
    'api_secret' => env('PORTONE_API_SECRET'),
    'pg_providers' => [
        'html5_inicis',  // KG이니시스
        'kcp',           // NHN KCP
        'uplus',         // LG U+
        'tosspayments',  // 토스페이먼츠
    ],
];

// app/Services/Custom/PortOnePayment.php
class PortOnePayment implements PaymentGateway
{
    public function requestPayment(Order $order): array
    {
        // 결제 요청 로직
    }

    public function verifyPayment(string $impUid): bool
    {
        // 결제 검증 (웹훅 처리)
    }

    public function refund(string $impUid, int $amount): bool
    {
        // 환불 처리
    }
}

// Integration with Wallet
public function chargeWallet(User $user, int $amount)
{
    $payment = $this->portone->requestPayment([
        'amount' => $amount,
        'buyer_name' => $user->full_name,
        'buyer_email' => $user->email,
        'buyer_tel' => $user->mobile,
    ]);

    if ($payment['success']) {
        Wallet::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'charge',
            'reference' => $payment['imp_uid'],
        ]);
    }

    return $payment;
}
```

**Timeline:** 5-7 days (including testing)

### 7.3 Korean Map Integration

**Option A: Kakao Maps (Recommended)**
```javascript
// resources/js/components/KakaoMap.vue
<template>
  <div id="kakao-map" style="width:100%;height:400px;"></div>
</template>

<script>
export default {
  mounted() {
    kakao.maps.load(() => {
      const container = document.getElementById('kakao-map');
      const options = {
        center: new kakao.maps.LatLng(lat, lng),
        level: 3
      };
      const map = new kakao.maps.Map(container, options);

      // Add clinic marker
      const marker = new kakao.maps.Marker({
        position: new kakao.maps.LatLng(lat, lng)
      });
      marker.setMap(map);
    });
  }
}
</script>
```

**Option B: Naver Maps**
```javascript
// Alternative if Kakao not available
const map = new naver.maps.Map('map', {
  center: new naver.maps.LatLng(lat, lng),
  zoom: 15
});
```

**Address Search Integration:**
```php
// Use Kakao Local API for address search
public function searchAddress(string $query): array
{
    $response = Http::get('https://dapi.kakao.com/v2/local/search/address.json', [
        'query' => $query,
        'Authorization' => 'KakaoAK ' . config('services.kakao.rest_api_key'),
    ]);

    return $response->json()['documents'];
}
```

**Timeline:** 3-4 days

---

## Part 8: Custom Development Requirements

### 8.1 Advertising Platform Module

**Core Features:**

```
1. Ad Slot Management
   - Define ad positions (메인 상단, 검색 결과 상위)
   - Set base CPV/CPC rates
   - Manage concurrent ad limits

2. Campaign Management
   - Create/edit campaigns
   - Set budget and daily caps
   - Choose target services/locations
   - Schedule start/end dates

3. Ad Wallet System
   - Charge wallet (PortOne integration)
   - Deduct costs (CPV/CPC)
   - Refund unused budget
   - Transaction history

4. Event Tracking
   - Record impressions (view events)
   - Record clicks
   - Detect fraud (IP/user duplication)
   - Calculate costs in real-time

5. Analytics Dashboard
   - Campaign performance metrics
   - Cost breakdown
   - CTR (Click-Through Rate)
   - ROI calculations
```

**Database Schema:**

```sql
-- Ad Slots
CREATE TABLE ad_slots (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL COMMENT '광고 슬롯명',
    position VARCHAR(50) NOT NULL COMMENT '위치 코드',
    description TEXT COMMENT '설명',
    max_concurrent INT DEFAULT 1 COMMENT '동시 노출 수',
    base_cpv DECIMAL(10,2) DEFAULT 0 COMMENT '기본 CPV 단가',
    base_cpc DECIMAL(10,2) DEFAULT 0 COMMENT '기본 CPC 단가',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Campaigns
CREATE TABLE ad_campaigns (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    clinic_id BIGINT NOT NULL COMMENT '병원 ID',
    slot_id BIGINT NOT NULL COMMENT '광고 슬롯',
    name VARCHAR(200) NOT NULL COMMENT '캠페인명',
    budget DECIMAL(12,2) NOT NULL COMMENT '총 예산',
    daily_cap DECIMAL(10,2) COMMENT '일일 상한',
    cpv_rate DECIMAL(10,2) DEFAULT 0 COMMENT 'CPV 단가',
    cpc_rate DECIMAL(10,2) DEFAULT 0 COMMENT 'CPC 단가',
    target_services JSON COMMENT '타겟 시술',
    target_regions JSON COMMENT '타겟 지역',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'active', 'paused', 'completed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id),
    FOREIGN KEY (slot_id) REFERENCES ad_slots(id)
);

-- Ad Wallet
CREATE TABLE ad_wallets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    clinic_id BIGINT UNIQUE NOT NULL,
    balance DECIMAL(12,2) DEFAULT 0 COMMENT '잔액',
    total_charged DECIMAL(12,2) DEFAULT 0 COMMENT '총 충전액',
    total_spent DECIMAL(12,2) DEFAULT 0 COMMENT '총 사용액',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id)
);

-- Transactions
CREATE TABLE ad_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    wallet_id BIGINT NOT NULL,
    type ENUM('charge', 'deduct', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50) COMMENT '참조 타입',
    reference_id BIGINT COMMENT '참조 ID',
    description VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES ad_wallets(id)
);

-- Impressions
CREATE TABLE ad_impressions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    campaign_id BIGINT NOT NULL,
    user_key VARCHAR(100) COMMENT '유저 식별키',
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer VARCHAR(500),
    is_valid BOOLEAN DEFAULT TRUE COMMENT '유효성',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_created (campaign_id, created_at),
    FOREIGN KEY (campaign_id) REFERENCES ad_campaigns(id)
);

-- Clicks
CREATE TABLE ad_clicks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    campaign_id BIGINT NOT NULL,
    impression_id BIGINT COMMENT '노출 ID',
    user_key VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_valid BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_created (campaign_id, created_at),
    FOREIGN KEY (campaign_id) REFERENCES ad_campaigns(id),
    FOREIGN KEY (impression_id) REFERENCES ad_impressions(id)
);
```

**Development Timeline:** 10-14 days

### 8.2 Ranking Algorithm Module

**Ranking Formula:**

```
clinic_score =
    (w1 × booking_count_normalized) +
    (w2 × revenue_normalized) +
    (w3 × review_count_normalized) +
    (w4 × avg_rating_normalized) +
    (w5 × recency_score) +
    (w6 × ad_boost_score)

Where:
w1 = 0.25 (booking weight)
w2 = 0.20 (revenue weight)
w3 = 0.20 (review count weight)
w4 = 0.25 (rating weight)
w5 = 0.05 (recency weight)
w6 = 0.05 (ad boost weight)

Normalization: min-max scaling to [0,1]
```

**Implementation:**

```php
// app/Services/Custom/RankingService.php
class RankingService
{
    protected $weights = [
        'booking' => 0.25,
        'revenue' => 0.20,
        'review_count' => 0.20,
        'rating' => 0.25,
        'recency' => 0.05,
        'ad_boost' => 0.05,
    ];

    public function calculateScore(Clinic $clinic, string $period = 'monthly'): float
    {
        $metrics = $this->getMetrics($clinic, $period);

        $score =
            ($this->weights['booking'] * $metrics['booking_normalized']) +
            ($this->weights['revenue'] * $metrics['revenue_normalized']) +
            ($this->weights['review_count'] * $metrics['review_count_normalized']) +
            ($this->weights['rating'] * $metrics['rating_normalized']) +
            ($this->weights['recency'] * $metrics['recency_score']) +
            ($this->weights['ad_boost'] * $metrics['ad_boost_score']);

        return round($score, 4);
    }

    protected function getMetrics(Clinic $clinic, string $period): array
    {
        $dateRange = $this->getDateRange($period);

        return [
            'booking_count' => $clinic->appointments()
                ->whereBetween('created_at', $dateRange)
                ->count(),
            'total_revenue' => $clinic->appointments()
                ->whereBetween('created_at', $dateRange)
                ->sum('total_amount'),
            'review_count' => $clinic->ratings()
                ->whereBetween('created_at', $dateRange)
                ->count(),
            'avg_rating' => $clinic->ratings()
                ->whereBetween('created_at', $dateRange)
                ->avg('rating'),
            'recency_score' => $this->calculateRecencyScore($clinic),
            'ad_boost_score' => $this->calculateAdBoostScore($clinic),
        ];
    }

    protected function normalize(float $value, float $min, float $max): float
    {
        if ($max === $min) return 0;
        return ($value - $min) / ($max - $min);
    }
}
```

**Database Schema:**

```sql
CREATE TABLE ranking_metrics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    clinic_id BIGINT NOT NULL,
    service_id BIGINT COMMENT '시술별 랭킹',
    period VARCHAR(20) NOT NULL COMMENT 'daily, weekly, monthly',
    period_date DATE NOT NULL,
    booking_count INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    review_count INT DEFAULT 0,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    recency_score DECIMAL(5,2) DEFAULT 0,
    ad_boost_score DECIMAL(5,2) DEFAULT 0,
    final_score DECIMAL(10,4) NOT NULL,
    rank_position INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_clinic_period (clinic_id, service_id, period, period_date),
    INDEX idx_rank (period, period_date, rank_position),
    FOREIGN KEY (clinic_id) REFERENCES clinics(id),
    FOREIGN KEY (service_id) REFERENCES clinic_services(id)
);

CREATE TABLE ranking_weights (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    factor_name VARCHAR(50) NOT NULL UNIQUE,
    weight DECIMAL(5,3) NOT NULL,
    description VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed default weights
INSERT INTO ranking_weights (factor_name, weight, description) VALUES
('booking', 0.250, '예약 건수 가중치'),
('revenue', 0.200, '매출 가중치'),
('review_count', 0.200, '후기 수 가중치'),
('rating', 0.250, '평점 가중치'),
('recency', 0.050, '최신성 가중치'),
('ad_boost', 0.050, '광고 부스트 가중치');
```

**Scheduled Job:**

```php
// app/Console/Commands/UpdateClinicRankings.php
class UpdateClinicRankings extends Command
{
    protected $signature = 'ranking:update {period=daily}';

    public function handle(RankingService $rankingService)
    {
        $period = $this->argument('period');
        $clinics = Clinic::where('is_active', true)->get();

        foreach ($clinics as $clinic) {
            $score = $rankingService->calculateScore($clinic, $period);

            RankingMetric::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'period' => $period,
                    'period_date' => now()->toDateString(),
                ],
                [
                    'final_score' => $score,
                    // ... other metrics
                ]
            );
        }

        // Assign rank positions
        $this->assignRankings($period);
    }
}

// Schedule in app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('ranking:update daily')->daily();
    $schedule->command('ranking:update weekly')->weekly();
    $schedule->command('ranking:update monthly')->monthly();
}
```

**Development Timeline:** 5-7 days

---

## Part 9: Development Timeline & Cost

### 9.1 Detailed Timeline

**Phase 1: Foundation (2 weeks)**
```
Week 1:
  - KiviCare installation and configuration
  - Database setup and migration
  - Environment configuration for Korean market
  - Initial testing of core features

Week 2:
  - Import selected Frezka modules
  - Resolve namespace conflicts
  - Database schema integration
  - Module testing
```

**Phase 2: Korean Localization (2 weeks)**
```
Week 3:
  - Translation file structure setup
  - Core UI translation (500 strings)
  - Korean address/phone format
  - Timezone and currency settings

Week 4:
  - Complete translation (1,000+ strings)
  - Email/SMS template translation
  - Testing and quality assurance
  - Localization refinement
```

**Phase 3: Payment & Maps (1.5 weeks)**
```
Week 5-6:
  - PortOne SDK integration
  - Payment flow implementation
  - Kakao Maps integration
  - Address search functionality
  - Payment testing (sandbox)
```

**Phase 4: Custom Features (3 weeks)**
```
Week 7-8:
  - Advertising platform module
  - Ad wallet system
  - Campaign management UI
  - Event tracking system

Week 9:
  - Ranking algorithm
  - Metrics calculation
  - Scheduled jobs
  - Admin dashboard for ads
```

**Phase 5: Testing & Deployment (1.5 weeks)**
```
Week 10:
  - Integration testing
  - Performance optimization
  - Security audit
  - Bug fixing

Week 11:
  - User acceptance testing
  - Documentation
  - Deployment preparation
  - Final review
```

**Total Timeline: 10-11 weeks (2.5-3 months)**

### 9.2 Cost Estimation

**Development Costs (Korean Senior Developer Rate: ₩500,000/day)**

| Phase | Days | Cost (KRW) | Cost (USD) |
|-------|------|------------|------------|
| Foundation Setup | 10 | ₩5,000,000 | $3,750 |
| Korean Localization | 10 | ₩5,000,000 | $3,750 |
| Payment & Maps | 7 | ₩3,500,000 | $2,625 |
| Advertising Platform | 15 | ₩7,500,000 | $5,625 |
| Ranking System | 7 | ₩3,500,000 | $2,625 |
| Testing & Deployment | 8 | ₩4,000,000 | $3,000 |
| **Total Development** | **57 days** | **₩28,500,000** | **$21,375** |

**Additional Costs:**

| Item | Cost (KRW) | Cost (USD) |
|------|------------|------------|
| Professional Translation | ₩2,000,000 | $1,500 |
| PortOne Integration Fee | ₩0 (free tier) | $0 |
| Kakao Maps API | ₩0 (free tier) | $0 |
| Server (3 months) | ₩600,000 | $450 |
| SSL Certificate | ₩100,000 | $75 |
| **Total Additional** | **₩2,700,000** | **$2,025** |

**Grand Total: ₩31,200,000 (approx. $23,400)**

### 9.3 Risk Mitigation

**Technical Risks:**

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| Module conflicts | Medium | Medium | Thorough testing, namespace isolation |
| Payment integration issues | Low | High | Use sandbox, gradual rollout |
| Performance degradation | Medium | Medium | Caching, query optimization |
| Korean market compliance | High | High | Legal consultation, compliance audit |

**Business Risks:**

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| Medical advertising regulations | High | Critical | Lawyer review, conservative approach |
| PG fee structure changes | Low | Medium | Contract review, alternative PGs |
| Competitor response | Medium | Medium | Focus on unique features |

---

## Part 10: Final Recommendations

### 10.1 Decision Matrix

```
┌─────────────────────────────────────────────────────────────────┐
│                        FINAL DECISION                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  PRIMARY BASE:      KiviCare Laravel v1.9.0                      │
│  REASON:            - Modern tech stack (Laravel 11, PHP 8.2)   │
│                     - Medical domain alignment                   │
│                     - Better database architecture               │
│                     - More scalable module system                │
│                     - Future-proof                               │
│                                                                  │
│  IMPORT FROM FREZKA:                                             │
│  ✅ Employee Module    (enhanced staff management)              │
│  ✅ Package Module     (service bundling)                       │
│  ✅ Review System      (advanced rating features)               │
│  ⚠️  Product Module     (only if e-commerce needed)              │
│                                                                  │
│  CUSTOM DEVELOPMENT:                                             │
│  🇰🇷 Korean localization (완전한 한국어 지원)                     │
│  💳 PortOne payment integration                                 │
│  🗺️ Kakao/Naver Maps                                            │
│  📊 Advertising platform (CPC/CPV)                              │
│  🏆 Ranking algorithm                                           │
│  ⚖️ Medical advertising compliance                              │
│                                                                  │
│  DEVELOPMENT APPROACH:                                           │
│  📍 Single merged project (NOT separate)                        │
│  📍 Hybrid architecture                                          │
│  📍 Modular additions (no core modifications)                   │
│  📍 API versioning (v1, v2, v3)                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 10.2 Can They Be Developed Separately?

**ANSWER: Technically YES, Practically NO**

**Scenario 1: Completely Separate Projects**
```
✅ Possible: Yes
❌ Recommended: NO

Reasons Against:
- Duplicate user management
- Data synchronization complexity
- Double infrastructure cost
- Inefficient development
- Maintenance nightmare
```

**Scenario 2: Keep Both, Reference Only**
```
✅ Possible: Yes
✅ Recommended: For reference only

Use Case:
- Keep both folders as read-only reference
- Copy specific modules as needed
- Main development in single merged project
```

**Scenario 3: Single Merged Project (RECOMMENDED)**
```
✅ Possible: Yes
✅ Recommended: STRONGLY YES

Benefits:
- Unified codebase
- Shared authentication
- Single database
- Easier maintenance
- Better performance
- Clear architecture
```

### 10.3 Development Location

**Recommended Structure:**

```
H:\carebaby_project\
├── server_backup_dev\
│   ├── frezka_admin_panel_v4.0.3\       # Archive (reference only)
│   ├── kivicare-laravel-web-v1.9.0\     # Archive (reference only)
│   └── ARCHITECTURE_ANALYSIS_AND_DEVELOPMENT_STRATEGY.md
│
└── carebaby_platform\                    # MAIN DEVELOPMENT PROJECT
    ├── app\
    ├── Modules\
    │   ├── Appointment\                  # From KiviCare
    │   ├── Clinic\                       # From KiviCare
    │   ├── FrezkaEmployee\               # Imported from Frezka
    │   ├── FrezkaPackage\                # Imported from Frezka
    │   ├── Advertising\                  # Custom
    │   ├── Ranking\                      # Custom
    │   └── KoreanCompliance\             # Custom
    ├── database\
    ├── resources\
    ├── .env
    └── composer.json
```

### 10.4 Step-by-Step Action Plan

**Day 1: Project Setup**
```bash
# 1. Create main project directory
cd H:\carebaby_project
mkdir carebaby_platform

# 2. Copy KiviCare as base
xcopy kivicare-laravel-web-v1.9.0 carebaby_platform /E /I /H

# 3. Initialize Git
cd carebaby_platform
git init
git add .
git commit -m "Initial commit: KiviCare base"

# 4. Configure environment
cp .env.example .env
# Edit .env for Korean settings
```

**Day 2-3: Module Import**
```bash
# 1. Copy Frezka modules
xcopy ..\server_backup_dev\frezka_admin_panel_v4.0.3\Modules\Employee Modules\FrezkaEmployee /E /I
xcopy ..\server_backup_dev\frezka_admin_panel_v4.0.3\Modules\Package Modules\FrezkaPackage /E /I

# 2. Update namespaces
# Search/replace in all imported files:
#   Modules\Employee → Modules\FrezkaEmployee
#   Modules\Package → Modules\FrezkaPackage

# 3. Update module.json files
# 4. Register in modules_statuses.json
# 5. Run migrations
php artisan module:migrate
```

**Week 1-2: Foundation**
```bash
# Setup and testing
composer install
npm install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve

# Verify:
- Login works
- Dashboard loads
- Appointments can be created
- Imported modules are active
```

**Week 3-4: Korean Localization**
```bash
# Create Korean language files
mkdir lang/ko
# Copy from en/ and translate

# Update config/app.php
'locale' => 'ko',
'timezone' => 'Asia/Seoul',
```

**Week 5-6: Payment & Maps**
```bash
composer require portone/server-sdk
# Implement PortOnePayment service
# Integrate Kakao Maps
```

**Week 7-9: Custom Features**
```bash
php artisan module:make Advertising
php artisan module:make Ranking
# Implement according to specifications
```

**Week 10-11: Testing & Launch**
```bash
# Testing
php artisan test
# Performance optimization
# Security audit
# Deploy
```

---

## Conclusion

### Summary

**Base Project:** KiviCare Laravel v1.9.0
- Modern technology stack (Laravel 11, PHP 8.2)
- Medical domain alignment
- Superior database architecture
- Better scalability

**Imported Modules:** From Frezka v4.0.3
- Employee management
- Service packages
- Advanced reviews

**Custom Development:**
- Korean localization (완전한 한국어 지원)
- PortOne payment integration
- Kakao/Naver Maps
- Advertising platform (CPC/CPV)
- Ranking algorithm
- Compliance features

**Development Approach:**
- Single merged project (NOT separate)
- Hybrid modular architecture
- 10-11 weeks development timeline
- Estimated cost: ₩31,200,000 (~$23,400)

**Final Answer to Your Questions:**

1. **Can they be developed separately?**
   - Technically: YES
   - Recommended: NO
   - Best approach: Single merged project

2. **Should they be merged?**
   - **YES, strongly recommended**
   - Hybrid architecture using best of both

3. **Which is more suitable as base?**
   - **KiviCare Laravel v1.9.0**
   - Modern stack, medical alignment, better scalability

---

**Document Version:** 1.0
**Last Updated:** 2025-12-08
**Next Review:** After Phase 1 completion
