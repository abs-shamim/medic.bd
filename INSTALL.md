# MediCare Sitemap Installation

1. Upload `sitemap.php` and `sitemap.xsl` to the MediCare project root, beside `index.php`.
2. Copy the five rules from `htaccess-snippet.txt` into `.htaccess` directly after `RewriteEngine On`.
3. Update or create `robots.txt` with the supplied content.
4. Open these URLs after upload:
   - https://medic.bd/sitemap.xml
   - https://medic.bd/sitemap-doctors.xml
   - https://medic.bd/sitemap-hospitals.xml
   - https://medic.bd/sitemap-locations.xml
5. Submit only `https://medic.bd/sitemap.xml` in Google Search Console and Bing Webmaster Tools.

The sitemap includes only active records from the `doctors`, `hospitals`, `specialties`, `districts`, and `thanas` tables. It excludes login pages, dashboard pages, admin pages, review forms, query-string filters and empty location combinations.
