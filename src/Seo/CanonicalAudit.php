<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * The address a served page claims, checked against the one it answers on.
 *
 * This cannot be seen from the templates: the address is built from SITE_URL,
 * so it is only wrong once the site is installed somewhere. A canonical
 * pointing at another host sends search engines and social networks there,
 * and the site itself disappears from the results it should be in.
 */
final class CanonicalAudit
{
    /**
     * @param string $expected Where the page was actually fetched.
     * @return list<string> What is wrong, in plain words.
     */
    public static function problems(string $html, string $expected): array
    {
        $declared = self::declared($html);

        if (self::notIndexed($html)) {
            return $declared === null
                ? []
                : ["une page qui demande à ne pas être indexée revendique l'adresse {$declared}"];
        }

        if ($declared === null) {
            return ["aucune adresse revendiquée : vérifier SITE_URL dans .env, puis purger le cache"];
        }

        if (preg_match('#^https?://#i', $declared) !== 1) {
            return ["adresse revendiquée relative : {$declared} — les moteurs et les réseaux attendent une adresse complète"];
        }

        if (self::comparable($declared) !== self::comparable($expected)) {
            return ["adresse revendiquée {$declared}, alors que la page répond sur {$expected}"];
        }

        return [];
    }

    private static function declared(string $html): ?string
    {
        if (preg_match('#<link\b[^>]*\brel=["\']?canonical["\'\s>][^>]*>#i', $html, $balise) !== 1) {
            return null;
        }

        if (preg_match('#\bhref=["\']([^"\']*)["\']#i', $balise[0], $href) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : $value;
    }

    private static function notIndexed(string $html): bool
    {
        return preg_match(
            '#<meta\b[^>]*\bname=["\']?robots["\']?[^>]*\bcontent=["\'][^"\']*noindex#i',
            $html,
        ) === 1;
    }

    /**
     * The same address written two ways must compare equal.
     *
     * Host and scheme are case-insensitive; a trailing slash means nothing
     * except at the root, where it is the whole path.
     */
    private static function comparable(string $url): string
    {
        $parts = parse_url(trim($url));

        if (!is_array($parts) || !isset($parts['host'])) {
            return strtolower(trim($url));
        }

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : $path;

        return strtolower($parts['scheme'] ?? '')
            .'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($path === '/' ? '/' : rtrim($path, '/'));
    }
}
