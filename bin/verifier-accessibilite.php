<?php

declare(strict_types=1);

/**
 * Checks the HTML the site actually serves.
 *
 * Not the templates, not the intentions: the pages as a visitor — or a search
 * engine — receives them, cache included. Every address in the site map is
 * fetched and read, plus the legal pages, which are reachable from everywhere.
 *
 * Usage: php bin/verifier-accessibilite.php [adresse-du-site]
 */

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

$root = dirname(__DIR__);

require_once "{$root}/src/Accessibility/PageAudit.php";
require_once "{$root}/src/Seo/CanonicalAudit.php";
require_once "{$root}/src/View/Colours.php";

use App\Accessibility\PageAudit;
use App\Seo\CanonicalAudit;
use App\View\Colours;

$base = rtrim($argv[1] ?? 'http://localhost:8080', '/');

/**
 * @param array<string, string> $headers
 * @return array{corps: string, code: int}
 */
function fetch(string $url, array $headers = []): array
{
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_map(
            static fn (string $name, string $value): string => "{$name}: {$value}",
            array_keys($headers),
            $headers,
        ),
    ]);

    $body = (string) curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return ['corps' => $body, 'code' => $code];
}

/** @return list<string> */
function addresses(string $base): array
{
    $sitemap = fetch("{$base}/sitemap.xml");
    $found = [];

    if ($sitemap['code'] === 200) {
        preg_match_all('#<loc>([^<]+)</loc>#', $sitemap['corps'], $matches);
        $found = $matches[1] ?? [];
    }

    if ($found === []) {
        $found = ["{$base}/"];
    }

    // Reachable from every page, so held to the same standard.
    foreach (['/mentions-legales', '/confidentialite'] as $legal) {
        $found[] = $base.$legal;
    }

    return array_values(array_unique($found));
}

/** Reads the values the site itself uses, from its own configuration. */
function env(string $root, string $name): string
{
    static $values = null;

    if ($values === null) {

        $values = [];

        foreach (@file("{$root}/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value);
            }
        }
    }

    return $values[$name] ?? '';
}

/** @return list<string> */
function stylesheetProblems(string $root): array
{
    $css = @file_get_contents("{$root}/public/assets/css/site.css");

    if ($css === false) {
        return ['la feuille de style du site est introuvable'];
    }

    // Text made transparent is the usual way to fail a contrast requirement
    // without noticing: the colour is right, the rendering is not.
    return preg_match('/^\s*opacity\s*:/m', $css) === 1
        ? ['la feuille de style applique une transparence, ce qui dégrade les contrastes']
        : [];
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function colourProblems(array $settings): array
{
    $problems = [];

    $colours = [
        'couleur principale' => $settings['couleurPrincipale'] ?? null,
        'couleur du texte' => $settings['couleurTexte'] ?? null,
    ];

    foreach ($colours as $label => $colour) {

        if (!is_string($colour) || preg_match('/^#[0-9a-f]{6}$/i', trim($colour)) !== 1) {
            continue;
        }

        $ratio = Colours::contrast(trim($colour), '#ffffff');

        if ($ratio < Colours::MINIMUM) {
            $problems[] = sprintf(
                '%s (%s) : contraste de %s:1 sur blanc, il en faut 4,5:1 — le site garde sa couleur par défaut',
                $label,
                $colour,
                number_format($ratio, 1, ',', ' '),
            );
        }
    }

    return $problems;
}

// ── Exécution ─────────────────────────────────────────────────────────────

echo "\nVérification de l'accessibilité et du référencement — {$base}\n\n";

$audit = new PageAudit();
$pages = addresses($base);
$total = 0;

foreach ($pages as $address) {

    $page = fetch($address);
    $path = parse_url($address, PHP_URL_PATH) ?: '/';

    if ($page['code'] !== 200) {
        printf("  %-40s injoignable (HTTP %d)\n", $path, $page['code']);
        $total++;
        continue;
    }

    $problems = array_merge(
        $audit->problems($page['corps']),
        // Comparée à l'adresse d'où la page vient d'être lue, et non à celle
        // que le site croit avoir : c'est ce désaccord même qu'on cherche.
        CanonicalAudit::problems($page['corps'], $base.$path),
    );
    $total += count($problems);

    printf("  %-40s %s\n", $path, $problems === [] ? 'conforme' : count($problems).' à corriger');

    foreach ($problems as $problem) {
        echo "      · {$problem}\n";
    }
}

// Checks that belong to the site as a whole rather than to one page.
$global = stylesheetProblems($root);

$apiUrl = env($root, 'COCKPIT_API_URL');
$apiKey = env($root, 'COCKPIT_API_KEY');

if ($apiUrl !== '' && $apiKey !== '') {

    $settings = json_decode(
        fetch(rtrim($apiUrl, '/').'/content/item/settings', ['api-key' => $apiKey])['corps'],
        true,
    );

    if (is_array($settings)) {
        $global = array_merge($global, colourProblems($settings));
    }
}

if ($global !== []) {

    echo "\n  Sur l'ensemble du site :\n";

    foreach ($global as $problem) {
        echo "      · {$problem}\n";
    }

    $total += count($global);
}

echo "\n";

if ($total === 0) {
    echo '  Aucun problème relevé sur '.count($pages)." pages.\n\n";
    exit(0);
}

echo "  {$total} point(s) à corriger.\n\n";
exit(1);
