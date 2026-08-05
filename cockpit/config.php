<?php

/**
 * Cockpit configuration for this site.
 *
 * Installed to public/admin/config/config.php by bin/install-cockpit.php.
 * Edit it here — the copy under public/admin is overwritten on every update.
 */

declare(strict_types=1);

// public/admin/config -> public/admin -> public -> project root
$root = str_replace('\\', '/', dirname(__DIR__, 3));

return [

    'app.name' => 'Administration du site',

    // Interface language. Translation files are not shipped with Cockpit;
    // until they are generated, the admin falls back to English.
    'i18n' => 'fr',

    // Internal secret, generated per installation into public/admin/.env.
    // Falling back to a constant would silently weaken every session and
    // signature, so a missing key is a hard failure instead.
    'sec-key' => env('COCKPIT_SEC_KEY', null) ?: throw new RuntimeException(
        'COCKPIT_SEC_KEY est absent de public/admin/.env — relancer php bin/install-cockpit.php',
    ),

    // The database lives outside the web root: it can never be downloaded,
    // even if a rewrite rule is lost.
    'database' => [
        'server' => "mongolite://{$root}/var/data",
        'options' => ['db' => 'app'],
        'driverOptions' => [],
    ],

    'memory' => [
        'server' => "redislite://{$root}/var/data/app.memory.sqlite",
        'options' => [],
    ],

    'search' => [
        'server' => "indexlite://{$root}/var/data",
        'options' => [],
    ],

    'assets' => [

        // Generated variants go next to the uploads, which the web server
        // hands out directly. The default place, storage/tmp, is closed to
        // the outside — see the rules in public/.htaccess.
        'storage' => 'uploads://variantes',

        // The widths the site actually asks for, in WebP: far lighter than
        // the original, and understood everywhere. Height follows the width,
        // so nothing is cropped unless a focal point says otherwise.
        'presets' => [
            'w480' => ['width' => 480, 'mode' => 'fitToWidth', 'quality' => 82, 'mime' => 'webp'],
            'w960' => ['width' => 960, 'mode' => 'fitToWidth', 'quality' => 82, 'mime' => 'webp'],
            'w1440' => ['width' => 1440, 'mode' => 'fitToWidth', 'quality' => 80, 'mime' => 'webp'],
        ],
    ],
];
