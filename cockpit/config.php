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
    'sec-key' => env('COCKPIT_SEC_KEY', null) ?? 'cle-non-definie-voir-public-admin-env',

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
];
