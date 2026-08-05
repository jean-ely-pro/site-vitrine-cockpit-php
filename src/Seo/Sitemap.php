<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * Lists the published pages for search engines.
 *
 * Only pages the site actually serves are listed: the repository has already
 * filtered out drafts.
 */
final class Sitemap
{
    /**
     * @param list<array<string, mixed>> $pages
     */
    public static function toXml(array $pages, string $siteUrl, string $homePageSlug): string
    {
        $base = rtrim($siteUrl, '/');
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($pages as $page) {

            $slug = $page['slug'] ?? null;

            if (!is_string($slug) || $slug === '') {
                continue;
            }

            // The home page is served at the root, not under its slug.
            $location = $slug === $homePageSlug ? "{$base}/" : "{$base}/{$slug}";
            $lines[] = '    <url>';
            $lines[] = '        <loc>'.htmlspecialchars($location, ENT_XML1).'</loc>';

            if (isset($page['_modified']) && is_numeric($page['_modified'])) {
                $lines[] = '        <lastmod>'.date('Y-m-d', (int) $page['_modified']).'</lastmod>';
            }

            $lines[] = '    </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    public static function robotsTxt(string $siteUrl): string
    {
        $base = rtrim($siteUrl, '/');

        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            // The admin holds no public content and must never be indexed.
            'Disallow: /admin',
            '',
            "Sitemap: {$base}/sitemap.xml",
            '',
        ]);
    }
}
