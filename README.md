# MedicBD

**MedicBD** is a raw PHP + MySQL doctor, hospital, specialty, and healthcare directory platform built for Bangladesh. It supports clean English and Bangla URLs, doctor and hospital profiles, specialty pages, a full admin panel, a user dashboard for doctors/hospital owners, a structured contact/request system, XML sitemaps, and dynamic `robots.txt` management — all from Site Settings.

---

## Table of Contents

- [Key Features](#key-features)
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

- **Doctor & hospital directories** — browsable by division, district, area, and specialty, with full profile pages
- **Specialty pages** with specialty-based doctor browsing
- **Structured contact & request system** — visitors submit typed requests (Add/Update Doctor Profile, Add/Update Hospital Profile, Claim Doctor/Hospital Profile, Report Incorrect Information, Technical Support, Other) that route into an admin review queue; nothing changes a live doctor/hospital record until an admin approves it
- **Admin panel** — manage doctors, hospitals, specialties, locations, blog, static pages, moderators, contact requests, and site-wide settings
- **User dashboard** — for registered doctors and hospital owners to claim profiles, request updates, manage chambers, and (where enabled) issue prescriptions
- **English and Bangla** public URL support
- **SEO-friendly clean URLs**, XML sitemap index with paginated sitemap files, and a fully dynamic `robots.txt`
- **Image uploads with automatic WebP conversion** to keep stored files small
- **Meta/SEO settings** — Open Graph images, verification codes, schema.org settings
- **Configurable crawler and AI bot controls** from the admin panel

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
doctor.php / doctors.php   Doctor profile page / doctor directory listing
hospital.php / hospitals.php  Hospital profile page / hospital directory listing
specialty.php / specialties.php  Specialty profile page / specialty listing
sitemap.php                XML sitemap generator
robots.php                 Dynamic robots.txt generator
blog.php / blog-post.php   Blog listing and post pages (optional module)

admin/                     Admin panel (doctors, hospitals, specialties, locations,
                            blog, static pages, moderators, contact requests, settings)
user/                      User-facing dashboard (login, register, claim profile,
                            profile update, chambers)
doctor-directory/          Doctor directory query/filter logic
hospital-directory/        Hospital directory query/filter logic
includes/                  Shared helpers: functions.php, header.php, footer.php,
                            contact form schema, address lookups
config/                    Database and application configuration
assets/                    CSS, JS, images, and uploaded files
languages/                 en.php / bn.php translation strings
prescription/              Prescription module for verified doctor accounts
cron/                      Scheduled maintenance scripts
ajax/                      AJAX endpoints (e.g. division → district lookups)
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

---

## Default Admin Access

The super-admin login is controlled by environment variables, read in `config/config.php`:

```text
ADMIN_EMAIL
ADMIN_PASSWORD
```

Set these on your server (or in your local environment) before first login. A super-admin account bypasses all permission checks and can manage other admin/moderator accounts from **Users** in the admin panel.

---

## Public URL Examples

```text
/
/bn
/doctors
/hospitals
/specialties
/specialty/{slug}
/bn/specialty/{slug}
/doctor/{slug}
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
- Location-based directory pages

Do not block `/sitemap.xml` in `robots.txt`.

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

The dynamic robots system can block selected crawlers individually, including:

```text
Amazonbot
Applebot-Extended
Bytespider
CCBot
ClaudeBot
CloudflareBrowserRenderingCrawler
Google-Extended
GPTBot
meta-externalagent
```

Normal Google Search indexing uses `Googlebot`, **not** `Google-Extended` — blocking `Google-Extended` does not block Google Search indexing.

---

## Cloudflare Managed Robots.txt

If Cloudflare's Managed `robots.txt` or AI Crawl Control is enabled, Cloudflare may prepend its own crawler rules to the response at `https://medic.bd/robots.txt`. This can create duplicate bot rules if the same crawlers are also blocked from the MedicBD admin panel. Pick one approach:

- Keep Cloudflare Managed robots.txt enabled and turn off the duplicate bot checkboxes in MedicBD Site Settings, **or**
- Disable Cloudflare Managed robots.txt and manage all crawler rules from MedicBD Site Settings only.

---

## Admin Settings

The Site Settings page (backed by the `site_settings` table) covers:

- Website identity, contact information, social links
- SEO settings and robots.txt control
- Homepage, directory, and appointment settings
- User settings
- SMTP, SMS, and WhatsApp configuration
- Analytics and tracking
- Design settings, header and footer
- Legal pages
- Security and system settings

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
