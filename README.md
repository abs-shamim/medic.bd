# MedicBD

**MedicBD** is a raw PHP + MySQL doctor, hospital, specialty, and healthcare directory platform built for Bangladesh. It supports clean English and Bangla URLs, doctor and hospital profiles, specialty pages, a full admin panel with role-based permissions, a user dashboard for doctors/hospital owners, an e-prescription module, a structured contact/request system, XML sitemaps, and dynamic `robots.txt` management — all from Site Settings.

No framework is used — routing, templating, and data access are all plain PHP with a PDO/MySQL backend.

---

## Table of Contents

- [Key Features](#key-features)
- [How It Works](#how-it-works)
- [Feature Guide](#feature-guide)
  - [Homepage](#homepage)
  - [Doctor Directory](#doctor-directory)
  - [Hospital Directory](#hospital-directory)
  - [Specialties](#specialties)
  - [Doctor Profile](#doctor-profile)
  - [Hospital Profile](#hospital-profile)
  - [Contact & Request System](#contact--request-system)
  - [Reviews](#reviews)
  - [Blog (optional module)](#blog-optional-module)
  - [Static / Legal Pages](#static--legal-pages)
  - [User Dashboard](#user-dashboard-doctors--hospital-owners)
  - [Admin Panel](#admin-panel)
  - [Prescription Module](#prescription-module)
  - [Multi-language (English / Bangla)](#multi-language-english--bangla)
  - [SEO](#seo)
- [Requirements](#requirements)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Default Admin Access](#default-admin-access)
- [Public URL Examples](#public-url-examples)
- [XML Sitemap](#xml-sitemap)
- [Dynamic Robots.txt](#dynamic-robotstxt)
- [Crawler & AI Bot Controls](#crawler--ai-bot-controls)
- [Cloudflare Managed Robots.txt](#cloudflare-managed-robotstxt)
- [Admin Settings](#admin-settings)
- [File Permissions](#file-permissions)
- [Troubleshooting](#troubleshooting)
- [Production Checklist](#production-checklist)
- [License](#license)

---

## Key Features

- **Doctor & hospital directories** — browsable by division → district → area (thana) → specialty (doctors) or hospital type (hospitals), with full profile pages
- **Specialty pages** with specialty-based doctor browsing and admin-authored articles
- **Structured contact & request system** — 9 typed request forms that route into an admin review queue; nothing changes a live doctor/hospital record until an admin approves it
- **Doctor review system** with admin moderation before reviews go public
- **Admin panel** with role-based, per-feature permissions — manage doctors, hospitals, specialties, locations, blog, static pages, moderators, contact requests, featured listings, social publishing, and site-wide settings
- **CSV bulk import** for doctors and hospitals, including auto-creation of missing specialties/locations and automatic image-to-WebP conversion
- **User dashboard** — for registered doctors and hospital owners to claim profiles, request updates, manage chambers, add doctors to a hospital roster, and (once claimed) use the prescription module
- **E-prescription module** — a self-contained prescription writer with per-doctor medicine/clinical libraries, patient directory, and print layout
- **Social publishing integration** (Buffer.com) to push doctor/content posts to connected social channels
- **English and Bangla** public URL support, with bilingual database content and parallel SEO metadata
- **SEO-friendly clean URLs**, JSON-LD structured data, XML sitemap index with paginated sitemap files, and a fully dynamic `robots.txt`
- **Image uploads with automatic WebP conversion** to keep stored files small
- **Meta/SEO settings** — Open Graph images, verification codes, schema.org settings
- **Configurable crawler and AI bot controls** from the admin panel

---

## How It Works

**Routing.** There is no router library — `index.php` requires `route.php`, which inspects the request path and dispatches to the right controller file. In order, `route.php` handles: sitemap URLs, language detection (`en` vs `bn` from the URL prefix), SEO meta computation for the matched route, forcing `noindex` on any URL that carries a query string (so filtered/search URLs never get indexed), static routes (`/doctors`, `/hospitals`, `/specialties`, `/contact`, `/blog`), blog category/post routes, database-backed static/CMS page routes, specialty article routes, the doctor review route, legacy nested doctor URLs (redirected to the current URL shape), the directory drill-down routes, single doctor/hospital profile routes, the homepage, and finally a 404. `.htaccess` does the Apache-level work: rewriting `robots.txt` → `robots.php` and sitemap file names to `sitemap.php`, blocking access to `/config/` and to `route.php`/`sitemap.php`/`robots.php` directly, and excluding `/admin/` and `/user/` from the frontend router so those keep their own plain file-based URLs.

**Self-healing schema.** Many admin and frontend files check `INFORMATION_SCHEMA` before reading or writing a column, and auto-`ALTER TABLE` a missing column into existence the first time it's needed (for example, when a new bilingual `_bn` field or a new SEO field is introduced). This means most feature additions don't require a strict, manually-run migration step — the schema catches up automatically the first time that code path runs.

**Bilingual content.** Most core tables (doctors, hospitals, specialties, divisions, districts, thanas, etc.) carry a base column and a parallel `_bn` column, e.g. `name` / `name_bn`. `lang_text($english, $bangla)` picks the right one at render time based on `CURRENT_LANG`. UI copy (labels, buttons, headings) instead comes from `languages/en.php` / `languages/bn.php` via `__t($key, $fallback)`.

**Nothing writes to a live record directly from the public site.** Contact-form submissions, profile claims, and profile-update requests submitted through the public site or the user dashboard are all stored as pending rows (`contacts`, `profile_claims`, `profile_update_requests`) — an admin has to review and approve them in the admin panel before a doctor or hospital record actually changes.

---

## Feature Guide

### Homepage

`index.php` renders a hero with a dynamic search box (toggle between Doctors/Hospitals, specialty + city selects, JS-driven redirect to the matching directory URL), a stat strip (doctor/hospital/specialty counts), a Popular Specialties grid, Featured Doctors and Featured Hospitals lists, and an About section. Hero text/image, colors, and list limits are all configurable from Site Settings, with translation fallbacks for every string.

### Doctor Directory

`doctors.php` (thin controller) + `doctor-directory/*` (bootstrap, data fetchers, query builder, availability filtering, page state, SEO, view). The browsing flow drills down:

```text
/doctors/                              → division list + all doctors
/doctors/{division}/                   → district list
/doctors/{district}/                   → specialty list (district-wide)
/doctors/{district}/{specialty}/       → doctor list
/doctors/{district}/{thana}/           → specialty list (scoped to that area)
/doctors/{district}/{thana}/{specialty}/  → doctor list (scoped to area + specialty)
```

Filter lists only ever show options that actually have matching doctors (empty divisions/districts/specialties are hidden automatically), keyword search and pagination are built in, and legacy 4-segment URLs (`/doctors/{division}/{district}/{specialty}`) still resolve via redirect for old links/bookmarks.

### Hospital Directory

`hospitals.php` + `hospital-directory/*` — the same division → district → area drill-down as the doctor directory, but the fourth dimension is **hospital type** instead of specialty, e.g. `/hospitals/dhaka/private-hospital`. Same search, pagination, and "only show options with matches" behavior.

### Specialties

`specialties.php` lists all specialties; `specialty.php` renders one specialty's article/description plus the doctors who practice it.

### Doctor Profile

`doctor.php` renders a tabbed profile page (**Info**, **Experience**, **Reviews**) assembled from section partials in `doctor/`:

- **Info tab** — chamber schedule, about, achievements & memberships, contact/address, consultation options
- **Experience tab** — education & training, clinical expertise
- **Reviews tab** — the doctor's approved reviews
- Always shown — hero header, related doctors, a sidebar with chamber/hospital contact info and a "Claim this profile" link

Each field supports an admin-set "auto" or "manual" SEO mode — by default the page auto-generates a Name–Specialty–District title/description, which an admin can override manually per doctor. The page also builds full JSON-LD Physician schema, Open Graph tags (with a photo-card → manual OG image → profile photo → site default fallback chain), and tracks profile views for ranking/featured-listing purposes. A downloadable "business card" doctor photo (with embedded metadata) can be generated from the profile.

### Hospital Profile

`hospital.php` renders a single-page (non-tabbed) profile: hero, about, available facilities, services & facilities, departments, the hospital's affiliated doctors (reusing the same doctor-card component as the directories), hospital video, ratings & reviews, and a request-appointment / contact section.

### Contact & Request System

`contact.php` is a single form whose fields change based on a **request type** selector, with 9 built-in types: Add Doctor Profile, Update Doctor Profile, Add Hospital Profile, Update Hospital Profile, Claim Doctor Profile, Claim Hospital Profile, Report Incorrect Information, Technical Support, and Other. Every type's field list (text/email/phone/URL/textarea/select/division/district/checkbox group/file upload/heading, each with its own required/optional rule) is defined once in `includes/contact-form-schema.php` and shared by both the public form and the admin inbox, so the admin view (`admin/contact-messages.php`) always renders submissions back into readable labels automatically. Submissions are stored with a status and review flag for the admin queue — none of them touch a live doctor/hospital record on their own.

### Reviews

`write-review.php` lets a visitor submit a rating/review for a doctor. Reviews are held for admin moderation (`admin/reviews.php`) and only appear publicly — on the doctor's Reviews tab and on hospital pages that list affiliated doctors' ratings — once approved.

### Blog (optional module)

`blog.php` / `blog-post.php` provide category-filtered listing and single-post pages with JSON-LD article schema. The module is auto-detected: `route.php` only wires up blog routes if the blog files actually exist, so the site runs fine with the blog entirely removed.

### Static / Legal Pages

`static-page.php` renders admin-managed CMS pages (About, Privacy Policy, Terms, Support, etc.) by slug, each with its own meta title/description and canonical URL. Deletions can be scheduled and are finalized by a cron job even if no admin revisits the page afterward.

### User Dashboard (doctors & hospital owners)

The `user/` panel is built for **doctors and hospital owners only** — there is no general "patient account" type. Flow: register → search for and claim the matching doctor/hospital record → an admin approves the claim and links it to the account → the user can then request profile edits, manage chambers, and (hospital owners) manage the hospital's doctor roster. Every edit made here is stored as a pending request and only applied to the live record after admin approval.

- **Register / login** — self-service signup; new accounts start as `pending_verification`
- **Claim a profile** — search existing doctor/hospital records and submit a claim
- **Dashboard** — overview of the linked profile and request statuses
- **Profile update requests** — request edits to the claimed doctor/hospital profile
- **Chambers** — add/manage visiting locations and schedules
- **Hospital doctors** (hospital-owner accounts only) — add a doctor to the hospital's roster with full clinical details, submitted as a pending request
- **Settings** — account settings

### Admin Panel

The `admin/` panel is gated by `admin_users` with five roles (`super_admin`, `admin`, `moderator`, `editor`, `support`) and a granular JSON permission system — each feature area (doctors, hospitals, specialties, locations, reviews, contacts, users, claims, settings, moderators) has its own view/create/edit/delete/manage flags, checked per-page via `require_admin_permission()`. A `super_admin` bypasses all checks, and admin actions are recorded to an activity log.

What can be managed:

- **Doctors** — full CRUD across 8 form sections (basic info, contact/location, professional details, status/display, image/biography, clinical profile, SEO, hospital/chamber affiliation & schedule), plus **CSV bulk import** with duplicate detection, auto-creation of missing specialties/locations, phone-number normalization, and automatic WebP conversion of imported photos
- **Hospitals** — same CRUD + CSV import pattern as doctors
- **Specialties & Locations** — manage specialties and the division/district/thana tree
- **Featured listings** — feature a doctor sitewide or in a specific district/area context, with history tracking
- **Blog** — posts, categories, and image uploads, with a choice of two rich-text/page-builder editors
- **Directory SEO articles** — write custom intro/SEO copy for specific district/thana/specialty directory landing pages
- **Static/legal pages** — CMS page editor with scheduled deletion support
- **Reviews** — moderation queue for public doctor reviews
- **Contact requests** — inbox for all 9 contact-form types, plus **profile claims** and **profile update requests** approval workflow
- **Users & moderators** — manage registered doctor/hospital-owner accounts and admin/moderator roles & permissions
- **Social publishing** — connect a Buffer.com account, map channels, and post/schedule doctor or content posts to social platforms; failed posts are retried by a cron job
- **Site Settings** — see [Admin Settings](#admin-settings) below

### Prescription Module

`prescription/` is a self-contained e-prescription writer available to any user-panel account with an **approved doctor claim** (it reuses the same `user/includes/auth.php` login/claim check as the main dashboard). Every record is scoped to the logged-in doctor.

- Write, edit, view, and print prescriptions (print view excludes private doctor notes)
- A patient directory with per-patient history
- 13 independently managed libraries (medicine names, strength, dosage, frequency, duration, medicine instructions, tests/investigations, chief complaints, diagnosis, medical history, examination findings, advice) — each doctor gets shared "demo" starter data that they can hide/restore without affecting other doctors
- AJAX-powered search for patients, medicines, and library items

It isn't currently linked from the main user dashboard navigation but is reachable directly at `/prescription/` once a doctor account is claimed and approved.

### Multi-language (English / Bangla)

Language is detected purely from the URL: a bare `bn` or `bn/...` prefix switches `CURRENT_LANG` to `bn`, anything else is `en`. `languages/en.php` always loads as the base translation set; on Bangla pages, `languages/bn.php` is merged over it so any Bangla string that hasn't been translated yet quietly falls back to English in the UI. A separate, stricter translation set is used for SEO/meta text so that meta titles/descriptions never silently fall back to English on Bangla pages. `front_url($path, $lang)` automatically adds the `bn/` prefix when building links for the Bangla version.

### SEO

- Bilingual, dynamically-built meta titles and descriptions for every route pattern (home, directories, profiles, static pages)
- JSON-LD structured data (Physician schema on doctor pages, breadcrumbs, article schema on blog posts)
- Open Graph and Twitter card tags with sensible image fallbacks
- Canonical URLs, and automatic `noindex` on any filtered/search URL
- XML sitemap index and dynamic, per-crawler `robots.txt` — see below

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Apache | with `mod_rewrite` enabled |
| PHP extension | PDO MySQL |
| PHP extension | GD (recommended, for image/WebP conversion) |
| PHP extension | Imagick (optional fallback) |

---

## Project Structure

```text
index.php                 Front controller — loads route.php, falls through to the homepage
route.php                 Main frontend router: clean URLs, language detection, SEO, sitemap dispatch
contact.php                Public contact/request form (9 dynamic request types)
write-review.php           Public doctor review submission form
claim-profile.php          Public profile-claim entry point (links from the doctor sidebar)
doctor.php / doctors.php   Doctor profile page / doctor directory listing
hospital.php / hospitals.php  Hospital profile page / hospital directory listing
specialty.php / specialties.php  Specialty profile page / specialty listing
static-page.php            Renders admin-managed CMS pages by slug
sitemap.php                XML sitemap generator
robots.php                 Dynamic robots.txt generator
blog.php / blog-post.php   Blog listing and post pages (optional, auto-detected module)

doctor/                    Section partials for the doctor profile page (hero, chambers,
                            about, education, reviews, related doctors, etc.)
admin/                     Admin panel (doctors, hospitals, specialties, locations, blog,
                            static pages, moderators, contact requests, claims, social
                            publishing, settings) — see admin/doctors/ and admin/includes/
user/                      User-facing dashboard (auth, claim, profile update, chambers,
                            hospital doctor roster) — see user/pages/ and user/auth/
doctor-directory/          Doctor directory query/filter/SEO logic
hospital-directory/        Hospital directory query/filter/SEO logic
includes/                  Shared helpers: functions.php, header.php, footer.php,
                            doctor-card.php / hospital-card.php, contact form schema,
                            blog functions, social posting helpers
config/                    Database and application configuration
assets/                    CSS, images, and uploaded files (assets/uploads/{doctors,
                            hospitals, blog, locations, specialties, contact-requests})
languages/                 en.php / bn.php translation strings
prescription/              E-prescription module for claimed doctor accounts, with its
                            own Library/ (medicine & clinical libraries) and SQL files
cron/                      Scheduled maintenance scripts (static page cleanup, failed
                            social post retry)
ajax/                      AJAX endpoints (division/district lookups, hospital search)
patches/                   Drop-in updated copies of a few admin files (staged fixes)
```

---

## Installation

1. Upload all project files to your web root, e.g. `public_html/`.
2. Import the project database into MySQL.
3. Open `config/config.php` and set your database credentials and application URL:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   define('DB_CHARSET', 'utf8mb4');

   define('APP_URL', 'https://medic.bd');
   ```

4. Make sure these folders are writable by PHP (required for uploads):

   ```text
   assets/uploads/
   assets/images/
   ```

5. Upload the included `.htaccess` file to the project root.
6. Open the website and confirm clean URLs work correctly.
7. If you're using the prescription module, import `prescription/database.sql` (plus any upgrade `.sql` files in that folder) into the same database.

---

## Default Admin Access

The super-admin login is controlled by environment variables, read in `config/config.php`:

```text
ADMIN_EMAIL
ADMIN_PASSWORD
```

Set these on your server (or in your local environment) before first login. A super-admin account bypasses all permission checks and can manage other admin/moderator accounts and their permissions from **Users** in the admin panel.

---

## Public URL Examples

```text
/
/bn
/doctors
/doctors/{district}
/doctors/{district}/{specialty}
/doctors/{district}/{thana}/{specialty}
/hospitals
/hospitals/{district}
/hospitals/{district}/{hospital-type}
/specialties
/specialty/{slug}
/bn/specialty/{slug}
/doctor/{slug}
/doctor/{slug}/write-review
/hospital/{slug}
/contact
```

Legacy nested doctor-directory URLs are also supported through `route.php`:

```text
/doctors/{division}/{district}/{specialty}
/bn/doctors/{division}/{district}/{specialty}
```

---

## XML Sitemap

Main sitemap URL:

```text
https://medic.bd/sitemap.xml
```

The sitemap index includes paginated sitemap sections for:

- Main public pages
- Doctor profiles
- Hospital profiles
- Specialty articles
- Doctor directory pages (division / district / area)
- Hospital directory pages
- Blog posts (if the blog module is present)
- Location-based directory pages

Query-string/filtered URLs, login/dashboard/admin pages, and review-submission forms are intentionally excluded. Do not block `/sitemap.xml` in `robots.txt`.

---

## Dynamic Robots.txt

Public URL:

```text
https://medic.bd/robots.txt
```

Actual generator file:

```text
/robots.php
```

`.htaccess` must rewrite the public URL to the generator **before** existing-file checks:

```apache
RewriteRule ^robots\.txt$ robots.php [L,QSA]
```

The generator reads its rules from **Admin Panel → Site Settings → Robots.txt Control**, where you can configure:

- Public crawling access
- Blocked paths and allowed exception paths
- Sitemap URL
- Content Signal setting
- Individual crawler blocks

Typical protected paths:

```text
/admin/
/user/
/ajax/
/config/
/includes/
```

> **Security note:** `robots.txt` only *requests* that compliant crawlers avoid certain URLs — it does not protect private files or folders. Protect sensitive areas with login/session checks, server-side access rules, correct file permissions, database credentials kept outside public exposure, and firewall rules (e.g. Cloudflare) where needed.

---

## Crawler & AI Bot Controls

The dynamic robots system can block selected crawlers individually, grouped as Search Engine, AI Search, and AI Training bots, including:

```text
Googlebot, bingbot, YandexBot, Baiduspider, DuckDuckBot
OAI-SearchBot, PerplexityBot, Applebot
GPTBot, ClaudeBot, Google-Extended, CCBot, Bytespider
Amazonbot, Applebot-Extended, meta-externalagent
CloudflareBrowserRenderingCrawler
```

Normal Google Search indexing uses `Googlebot`, **not** `Google-Extended` — blocking `Google-Extended` does not block Google Search indexing.

---

## Cloudflare Managed Robots.txt

If Cloudflare's Managed `robots.txt` or AI Crawl Control is enabled, Cloudflare may prepend its own crawler rules to the response at `https://medic.bd/robots.txt`. This can create duplicate bot rules if the same crawlers are also blocked from the MedicBD admin panel. Pick one approach:

- Keep Cloudflare Managed robots.txt enabled and turn off the duplicate bot checkboxes in MedicBD Site Settings, **or**
- Disable Cloudflare Managed robots.txt and manage all crawler rules from MedicBD Site Settings only.

---

## Admin Settings

The Site Settings page (backed by the `site_settings` table) is organized into tabs:

- Website Identity, Contact Information, Social Links
- SEO Settings, Robots.txt Control
- Homepage Settings, Directory Settings
- User Settings
- Email & SMTP, SMS & WhatsApp API
- Analytics & Tracking
- Design Settings, Header & Footer
- Legal Pages
- Security & System

---

## File Permissions

Recommended:

```text
Folders: 755
Files:   644
```

Upload directories may need to be more permissive depending on your host:

```text
assets/uploads/   755 or 775
assets/images/    755 or 775
```

Avoid `777` unless your hosting provider specifically requires it for short-term troubleshooting.

---

## Troubleshooting

**Clean URLs show 404**
- `.htaccess` is in the correct domain root
- Apache `mod_rewrite` is enabled
- Your hosting allows `.htaccess` overrides
- The domain document root points to the folder containing `index.php`

**`/robots.txt` shows an old version**
- `robots.php` is in the root folder
- The rewrite rule appears before existing-file checks
- Any old physical `robots.txt` file has been renamed or removed
- Cloudflare cache has been purged, and Cloudflare Managed robots.txt isn't serving a cached response

**`/robots.php` opens directly instead of only via `/robots.txt`**

Block direct access in `.htaccess`:

```apache
RewriteCond %{THE_REQUEST} \s/+robots\.php(?:[?\s]) [NC]
RewriteRule ^robots\.php$ - [F,L]
```

The public crawler URL should always remain `https://medic.bd/robots.txt`.

**Images do not upload**
- PHP upload size limit
- `assets/uploads/` write permission
- GD or Imagick availability
- Maximum file size configured in Site Settings

---

## Production Checklist

- [ ] HTTPS is active
- [ ] `APP_URL` uses the correct production domain
- [ ] Database debug output is disabled
- [ ] `/config/` is protected from public access
- [ ] Admin login uses strong, unique passwords
- [ ] XML sitemap works and is submitted to Search Console / Bing Webmaster Tools
- [ ] `robots.txt` uses the correct sitemap URL
- [ ] Cloudflare cache settings are reviewed
- [ ] Backups are stored outside the public web directory

---

## License

This project is private software for MedicBD. Do not distribute, resell, or publish the source code without permission.
