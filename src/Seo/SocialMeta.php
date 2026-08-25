<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * The address a page claims as its own.
 *
 * Search engines use it to pick one address among several that show the same
 * thing, and social networks resolve their preview against it. A wrong one is
 * worse than none: it points them somewhere else. So it is only produced when
 * the site address is known and looks like an address.
 */
final class SocialMeta
{
    /**
     * The absolute address of a page, or null when it cannot be trusted.
     *
     * @param string $path Path of the page, `/` for the home page.
     */
    public static function canonical(string $siteUrl, string $path): ?string
    {
        $site = rtrim(trim($siteUrl), '/');

        if ($site === '' || preg_match('#^https?://[^\s/]+#i', $site) !== 1) {
            return null;
        }

        $path = '/'.ltrim(trim($path), '/');

        // No trailing slash except at the root: the site serves /services, so
        // claiming /services/ would name an address it does not answer on.
        return $site.($path === '/' ? '/' : rtrim($path, '/'));
    }
}
