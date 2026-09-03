<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:xhtml="http://www.w3.org/1999/xhtml"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <html>
            <head>
                <title>XML Sitemap</title>
                <script type="text/javascript">
                    <![CDATA[
                    function sitemapSiteName() {
                        var host = window.location.hostname || 'Website';
                        host = host.replace(/^www\./i, '');
                        return host || 'Website';
                    }

                    document.addEventListener('DOMContentLoaded', function () {
                        var siteName = sitemapSiteName();
                        document.title = siteName + ' XML Sitemap';

                        var heading = document.getElementById('dynamic-site-name');
                        if (heading) {
                            heading.textContent = siteName + ' XML Sitemap';
                        }

                        var description = document.getElementById('dynamic-site-description');
                        if (description) {
                            description.textContent =
                                'Automatically generated XML sitemap for ' + siteName +
                                '. It includes public pages, specialty articles, doctor profiles, ' +
                                'hospital profiles, doctor directory pages and hospital directory pages.';
                        }
                    });
                    ]]>
                </script>
                <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

                <style>
                    * {
                        box-sizing: border-box;
                    }

                    body {
                        margin: 0;
                        background: #f1f5f9;
                        color: #17212f;
                        font-family: Arial, Helvetica, sans-serif;
                    }

                    .top-bar {
                        height: 7px;
                        background: linear-gradient(90deg, #0f172a, #0f766e, #0284c7);
                    }

                    .container {
                        width: min(100% - 32px, 1220px);
                        margin: 28px auto 44px;
                    }

                    .hero {
                        padding: 30px;
                        border-radius: 18px 18px 0 0;
                        background: linear-gradient(135deg, #0f172a 0%, #134e4a 52%, #0369a1 100%);
                        color: #ffffff;
                        box-shadow: 0 16px 38px rgba(15, 23, 42, 0.16);
                    }

                    h1 {
                        margin: 0 0 8px;
                        font-size: clamp(25px, 4vw, 34px);
                        line-height: 1.2;
                    }

                    .hero p {
                        max-width: 860px;
                        margin: 0;
                        color: rgba(255, 255, 255, 0.9);
                        font-size: 14px;
                        line-height: 1.7;
                    }

                    .badges {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 10px;
                        margin-top: 18px;
                    }

                    .badge {
                        padding: 7px 11px;
                        border: 1px solid rgba(255, 255, 255, 0.26);
                        border-radius: 999px;
                        background: rgba(255, 255, 255, 0.12);
                        color: #ffffff;
                        font-size: 12px;
                        font-weight: 700;
                    }

                    .card {
                        overflow: hidden;
                        border: 1px solid #dbe5ee;
                        border-top: 0;
                        border-radius: 0 0 18px 18px;
                        background: #ffffff;
                        box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
                    }

                    .notice {
                        padding: 15px 18px;
                        border-bottom: 1px solid #e5edf5;
                        background: #f8fafc;
                        color: #526171;
                        font-size: 14px;
                        line-height: 1.65;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    th {
                        padding: 13px 15px;
                        background: #0f172a;
                        color: #ffffff;
                        font-size: 13px;
                        text-align: left;
                        white-space: nowrap;
                    }

                    td {
                        padding: 13px 15px;
                        border-bottom: 1px solid #edf2f7;
                        font-size: 14px;
                        vertical-align: top;
                    }

                    tr:last-child td {
                        border-bottom: 0;
                    }

                    tbody tr:hover td {
                        background: #fbfdff;
                    }

                    a {
                        color: #0277bd;
                        font-weight: 700;
                        text-decoration: none;
                        overflow-wrap: anywhere;
                    }

                    a:hover {
                        text-decoration: underline;
                    }

                    .muted {
                        color: #64748b;
                        white-space: nowrap;
                    }

                    .pill {
                        display: inline-block;
                        padding: 4px 9px;
                        border-radius: 999px;
                        font-size: 12px;
                        font-weight: 700;
                        white-space: nowrap;
                    }

                    .frequency {
                        background: #dcfce7;
                        color: #166534;
                    }

                    .priority {
                        background: #e0f2fe;
                        color: #075985;
                    }

                    .alternates {
                        display: flex;
                        flex-direction: column;
                        gap: 6px;
                        min-width: 260px;
                    }

                    .alternate-row {
                        display: flex;
                        align-items: flex-start;
                        gap: 8px;
                        line-height: 1.35;
                    }

                    .lang {
                        min-width: 70px;
                        padding: 4px 8px;
                        border-radius: 999px;
                        background: #eef2f7;
                        color: #334155;
                        font-size: 11px;
                        font-weight: 700;
                        text-align: center;
                        white-space: nowrap;
                    }

                    .alternate-row a {
                        font-size: 12px;
                        font-weight: 600;
                    }

                    .empty {
                        padding: 34px 20px;
                        color: #64748b;
                        text-align: center;
                    }

                    @media (max-width: 760px) {
                        .container {
                            width: min(100% - 20px, 1220px);
                            margin: 18px auto 30px;
                        }

                        .hero {
                            padding: 22px;
                            border-radius: 14px 14px 0 0;
                        }

                        table,
                        tbody,
                        tr,
                        td {
                            display: block;
                        }

                        thead {
                            display: none;
                        }

                        tr {
                            padding: 8px 0;
                            border-bottom: 1px solid #edf2f7;
                        }

                        tr:last-child {
                            border-bottom: 0;
                        }

                        td {
                            padding: 7px 13px;
                            border: 0;
                        }

                        td::before {
                            display: block;
                            margin-bottom: 4px;
                            color: #334155;
                            font-size: 11px;
                            font-weight: 700;
                            text-transform: uppercase;
                        }

                        td.loc::before {
                            content: "URL";
                        }

                        td.date::before {
                            content: "Last Modified";
                        }

                        td.frequency-cell::before {
                            content: "Frequency";
                        }

                        td.priority-cell::before {
                            content: "Priority";
                        }

                        td.lang-cell::before {
                            content: "Language Versions";
                        }

                        .muted {
                            white-space: normal;
                        }

                        .alternates {
                            min-width: 0;
                        }
                    }
                </style>
            </head>

            <body>
                <div class="top-bar"></div>

                <div class="container">
                    <section class="hero">
                        <h1 id="dynamic-site-name">XML Sitemap</h1>
                        <p>
                            This sitemap includes public static pages, specialty article pages,
                            doctor profiles, hospital profiles, valid doctor directory results and
                            valid hospital directory results. Directory URLs are generated only
                            when the matching query returns real records.
                        </p>

                        <div class="badges">
                            <xsl:choose>
                                <xsl:when test="s:sitemapindex">
                                    <span class="badge">
                                        Sitemap Files:
                                        <xsl:value-of select="count(s:sitemapindex/s:sitemap)"/>
                                    </span>
                                </xsl:when>

                                <xsl:otherwise>
                                    <span class="badge">
                                        URLs in This File:
                                        <xsl:value-of select="count(s:urlset/s:url)"/>
                                    </span>

                                    <span class="badge">
                                        Hreflang Links:
                                        <xsl:value-of select="count(s:urlset/s:url/xhtml:link)"/>
                                    </span>

                                    <span class="badge">
                                        Maximum: 1,000 URLs per file
                                    </span>
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                    </section>

                    <section class="card">
                        <xsl:choose>
                            <xsl:when test="s:sitemapindex">
                                <div class="notice">
                                    This sitemap index links to all generated sitemap groups:
                                    static pages, specialty article pages, doctor profiles,
                                    hospital profiles, doctor directory pages and hospital directory pages.
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sitemap File</th>
                                            <th>Last Modified</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <xsl:for-each select="s:sitemapindex/s:sitemap">
                                            <tr>
                                                <td class="loc">
                                                    <a href="{s:loc}">
                                                        <xsl:value-of select="s:loc"/>
                                                    </a>
                                                </td>

                                                <td class="date muted">
                                                    <xsl:value-of select="s:lastmod"/>
                                                </td>
                                            </tr>
                                        </xsl:for-each>
                                    </tbody>
                                </table>
                            </xsl:when>

                            <xsl:when test="s:urlset/s:url">
                                <div class="notice">
                                    Every URL below is a crawlable public page. Specialty article URLs,
                                    doctor directory URLs and hospital directory URLs are included only
                                    when they are valid public records.
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th>URL</th>
                                            <th>Last Modified</th>
                                            <th>Frequency</th>
                                            <th>Priority</th>
                                            <th>Language Versions</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <xsl:for-each select="s:urlset/s:url">
                                            <tr>
                                                <td class="loc">
                                                    <a href="{s:loc}">
                                                        <xsl:value-of select="s:loc"/>
                                                    </a>
                                                </td>

                                                <td class="date muted">
                                                    <xsl:value-of select="s:lastmod"/>
                                                </td>

                                                <td class="frequency-cell">
                                                    <span class="pill frequency">
                                                        <xsl:value-of select="s:changefreq"/>
                                                    </span>
                                                </td>

                                                <td class="priority-cell">
                                                    <span class="pill priority">
                                                        <xsl:value-of select="s:priority"/>
                                                    </span>
                                                </td>

                                                <td class="lang-cell">
                                                    <xsl:choose>
                                                        <xsl:when test="xhtml:link">
                                                            <div class="alternates">
                                                                <xsl:for-each select="xhtml:link">
                                                                    <div class="alternate-row">
                                                                        <span class="lang">
                                                                            <xsl:value-of select="@hreflang"/>
                                                                        </span>

                                                                        <a href="{@href}">
                                                                            <xsl:value-of select="@href"/>
                                                                        </a>
                                                                    </div>
                                                                </xsl:for-each>
                                                            </div>
                                                        </xsl:when>

                                                        <xsl:otherwise>
                                                            <span class="muted">No alternate language URL</span>
                                                        </xsl:otherwise>
                                                    </xsl:choose>
                                                </td>
                                            </tr>
                                        </xsl:for-each>
                                    </tbody>
                                </table>
                            </xsl:when>

                            <xsl:otherwise>
                                <div class="empty">
                                    No public sitemap records are available yet.
                                </div>
                            </xsl:otherwise>
                        </xsl:choose>
                    </section>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
