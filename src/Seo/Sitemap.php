<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * Lists the published addresses for search engines.
 *
 * Only what the site actually serves is listed: the repository has already
 * filtered out drafts.
 */
final class Sitemap
{
    /**
     * @param list<array{loc: string, lastmod?: int|null}> $entries
     */
    public static function toXml(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {

            $lines[] = '    <url>';
            $lines[] = '        <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';

            if (isset($entry['lastmod']) && $entry['lastmod'] !== null) {
                $lines[] = '        <lastmod>'.date('Y-m-d', $entry['lastmod']).'</lastmod>';
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
