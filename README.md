MedicBD
MedicBD is a raw PHP and MySQL doctor, hospital, specialty, and healthcare directory website for Bangladesh.
The project supports clean English and Bangla URLs, doctor and hospital profiles, specialty pages, XML sitemaps, an admin panel, user dashboard areas, and dynamic `robots.txt` management from Site Settings.
---
Main Features
Doctor directory with profile pages
Hospital directory with profile pages
Specialty pages and specialty-based browsing
English and Bangla public URL support
SEO-friendly clean URLs
XML sitemap index and paginated sitemap files
Dynamic `robots.txt`
Admin panel site settings
User dashboard area
Image uploads and WebP conversion
Meta settings, Open Graph image support, verification codes, and schema settings
Configurable crawler and AI bot controls
---
Requirements
PHP 8.0 or newer
MySQL 5.7+ or MariaDB 10.4+
Apache with `mod_rewrite` enabled
PHP PDO MySQL extension
GD extension recommended for image conversion
Optional: Imagick extension for image conversion fallback
---
Installation
Upload all project files to your web root, for example:
```text
   public_html/
   ```
Import the project database into MySQL.
Open the database configuration file:
```text
   config/config.php
   ```
Set your database credentials and application URL.
Example:
```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   define('DB_CHARSET', 'utf8mb4');

   define('APP_URL', 'https://medic.bd');
   ```
Make sure these folders are writable by PHP when uploads are enabled:
```text
   assets/images/
   uploads/
   ```
Upload the included `.htaccess` file to the project root.
Open the website and confirm that clean URLs work.
---
Important Root Files
```text
index.php              Public frontend router
route.php              Legacy premium doctor profile router
sitemap.php            XML sitemap generator
robots.php             Dynamic robots.txt generator
.htaccess              Apache rewrite rules
config/config.php      Database and application configuration
```
---
Public URL Examples
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
```
Legacy premium doctor profile URLs are routed through `route.php`.
Example:
```text
/doctors/dhaka/mirpur/gynecologist/doctor-name
/bn/doctors/dhaka/mirpur/gynecologist/doctor-name
```
---
XML Sitemap
The main sitemap URL is:
```text
https://medic.bd/sitemap.xml
```
The sitemap index can include paginated sitemap routes for:
Main public pages
Doctor profiles
Hospital profiles
Specialty articles
Doctor directory pages
Hospital directory pages
Location-based directory pages
Do not block `/sitemap.xml` in `robots.txt`.
---
Dynamic Robots.txt
The public URL is:
```text
https://medic.bd/robots.txt
```
The actual generator file is:
```text
/robots.php
```
The `.htaccess` file must contain this rule before existing-file checks:
```apache
RewriteRule ^robots\.txt$ robots.php [L,QSA]
```
The generator reads values from:
```text
Admin Panel > Site Settings > Robots.txt Control
```
From that settings area, you can control:
Public crawling access
Paths blocked from crawl
Allowed exception paths
Sitemap URL
Content Signal setting
Individual crawler blocks
Typical protected paths are:
```text
/admin/
/user/
/ajax/
/config/
/includes/
```
Important Security Note
`robots.txt` only requests that compliant crawlers avoid URLs. It does not protect private files or folders.
Protect sensitive folders using:
Login and session checks
Server-side access rules
Correct file permissions
Database credentials outside public exposure
Cloudflare or hosting firewall rules when needed
---
Crawler and AI Bot Settings
The dynamic robots system can block selected crawlers, including:
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
Normal Google Search indexing uses `Googlebot`, not `Google-Extended`.
Blocking `Google-Extended` does not normally block Google Search indexing.
---
Cloudflare Managed Robots.txt
If Cloudflare Managed robots.txt or AI Crawl Control is enabled, Cloudflare may prepend its own crawler rules to the response at:
```text
https://medic.bd/robots.txt
```
This can create duplicate bot rules if the same crawlers are also blocked from the MedicBD admin panel.
Choose one approach:
Keep Cloudflare Managed robots.txt enabled and turn off duplicate bot checkboxes in the MedicBD admin panel.
Disable Cloudflare Managed robots.txt and manage crawler rules only from MedicBD Site Settings.
---
Admin Settings
The Site Settings page uses the `site_settings` database table.
It includes sections for:
Website identity
Contact information
Social links
SEO settings
Robots.txt control
Homepage settings
Directory settings
Appointment settings
User settings
SMTP configuration
SMS and WhatsApp settings
Analytics and tracking
Design settings
Header and footer
Legal pages
Security and system settings
---
File Permissions
Recommended permissions:
```text
Folders: 755
Files:   644
```
For upload directories, your hosting environment may require writable permissions:
```text
assets/images/   755 or 775
uploads/         755 or 775
```
Avoid using `777` unless your hosting provider specifically requires it for a short troubleshooting period.
---
Troubleshooting
Clean URLs show 404
Check:
`.htaccess` is in the correct domain root
Apache `mod_rewrite` is enabled
Your hosting allows `.htaccess` overrides
The domain document root points to the folder containing `index.php`
`/robots.txt` shows an old version
Check:
`robots.php` is in the root folder
The rewrite rule appears before existing file checks
An old physical `robots.txt` file is renamed or removed if necessary
Cloudflare cache has been purged
Cloudflare Managed robots.txt is not adding an older cached response
`/robots.php` opens directly
The `.htaccess` rule can block direct access:
```apache
RewriteCond %{THE_REQUEST} \s/+robots\.php(?:[?\s]) [NC]
RewriteRule ^robots\.php$ - [F,L]
```
The public crawler URL should remain:
```text
https://medic.bd/robots.txt
```
Images do not upload
Check:
PHP upload limit
`assets/images/` write permission
GD or Imagick availability
Maximum file size in Site Settings configuration
---
Production Checklist
Before launching, confirm:
HTTPS is active
`APP_URL` uses the correct production domain
Database debug output is disabled
`/config/` is protected from public access
Admin login uses strong passwords
XML sitemap works
`robots.txt` uses the correct sitemap URL
Cloudflare cache settings are reviewed
Google Search Console property is verified
Backups are stored outside the public web directory
---
License
This project is private software for MedicBD.
Do not distribute, resell, or publish the source code without permission.