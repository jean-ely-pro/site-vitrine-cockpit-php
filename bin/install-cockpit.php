<?php

declare(strict_types=1);

/**
 * Installs (or updates) Cockpit into public/admin from the official release
 * archive. The version and its checksum are pinned below: bump both together
 * to update, then run this script again on every hosting.
 *
 * Usage: php bin/install-cockpit.php [--force]
 */

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

const COCKPIT_VERSION = '2.14.0';
const COCKPIT_ARCHIVE_URL = 'https://github.com/Cockpit-HQ/Cockpit/releases/download/2.14.0/cockpit-core.zip';
const COCKPIT_ARCHIVE_SHA256 = 'aff1113ff03852b58622b64160ea614f305ebae01a736195be69b4b9cd2dae98';

$root = dirname(__DIR__);
$target = "{$root}/public/admin";
$tmpDir = "{$root}/var/tmp/install";
$archive = "{$tmpDir}/cockpit-".COCKPIT_VERSION.'.zip';
$force = in_array('--force', $argv, true);

// Core directories are replaced wholesale on update; everything else under
// public/admin (storage, .env) is left untouched.
$coreEntries = ['modules', 'lib', 'addons', 'install'];

function step(string $message): void
{
    echo "  {$message}\n";
}

function fail(string $message): never
{
    fwrite(STDERR, "\n  Échec : {$message}\n\n");
    exit(1);
}

function removeRecursive(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        removeRecursive("{$path}/{$entry}");
    }

    @rmdir($path);
}

function copyRecursive(string $source, string $destination): void
{
    if (is_file($source)) {
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0o755, true);
        }
        if (!copy($source, $destination)) {
            fail("copie impossible : {$destination}\n  Un antivirus bloque-t-il l'écriture dans public/admin ?");
        }
        return;
    }

    if (!is_dir($destination) && !mkdir($destination, 0o755, true) && !is_dir($destination)) {
        fail("création impossible : {$destination}");
    }

    foreach (scandir($source) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        copyRecursive("{$source}/{$entry}", "{$destination}/{$entry}");
    }
}

echo "\nInstallation de Cockpit ".COCKPIT_VERSION."\n\n";

// 1. Requirements ---------------------------------------------------------

$missing = [];

foreach (['pdo_sqlite', 'gd', 'curl', 'fileinfo', 'zip'] as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = $extension;
    }
}

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    $missing[] = 'PHP >= 8.3 (version courante : '.PHP_VERSION.')';
}

if ($missing) {
    fail('prérequis manquants : '.implode(', ', $missing)."\n  Voir docs/cockpit-prerequis.md.");
}

step('Prérequis PHP vérifiés.');

if (is_dir($target) && !$force) {
    $installed = null;

    if (is_file("{$target}/bootstrap.php")) {
        preg_match("/APP_VERSION\s*=\s*'([^']+)'/", (string) file_get_contents("{$target}/bootstrap.php"), $m);
        $installed = $m[1] ?? null;
    }

    if ($installed === COCKPIT_VERSION && is_file("{$target}/index.php")) {
        step("Cockpit {$installed} est déjà installé. Utiliser --force pour réinstaller.");
        echo "\n";
        exit(0);
    }

    step('Version installée : '.($installed ?? 'inconnue').' — mise à jour vers '.COCKPIT_VERSION.'.');
}

// 2. Download and verify --------------------------------------------------

if (!is_dir($tmpDir) && !mkdir($tmpDir, 0o755, true) && !is_dir($tmpDir)) {
    fail("création impossible du dossier temporaire : {$tmpDir}");
}

if (!is_file($archive) || hash_file('sha256', $archive) !== COCKPIT_ARCHIVE_SHA256) {
    step("Téléchargement de l'archive officielle…");

    $handle = fopen($archive, 'wb');

    if ($handle === false) {
        fail("écriture impossible : {$archive}");
    }

    $curl = curl_init(COCKPIT_ARCHIVE_URL);
    curl_setopt_array($curl, [
        CURLOPT_FILE => $handle,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_TIMEOUT => 300,
    ]);

    $ok = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($handle);

    if ($ok === false) {
        @unlink($archive);
        fail("téléchargement impossible : {$error}");
    }
}

$checksum = hash_file('sha256', $archive);

if ($checksum !== COCKPIT_ARCHIVE_SHA256) {
    @unlink($archive);
    fail("empreinte de l'archive inattendue.\n  Attendue : ".COCKPIT_ARCHIVE_SHA256."\n  Obtenue  : {$checksum}");
}

step('Empreinte SHA-256 vérifiée.');

// 3. Extract --------------------------------------------------------------

$extractDir = "{$tmpDir}/extract";
removeRecursive($extractDir);

$zip = new ZipArchive();

if ($zip->open($archive) !== true) {
    fail("archive illisible : {$archive}");
}

$zip->extractTo($extractDir);
$zip->close();

$source = "{$extractDir}/cockpit-core";

if (!is_dir($source)) {
    fail("contenu d'archive inattendu : {$source} est absent.");
}

step('Archive extraite.');

// 4. Install --------------------------------------------------------------

foreach ($coreEntries as $entry) {
    removeRecursive("{$target}/{$entry}");
}

if (!is_dir($target) && !mkdir($target, 0o755, true) && !is_dir($target)) {
    fail("création impossible : {$target}");
}

foreach (scandir($source) ?: [] as $entry) {
    if (in_array($entry, ['.', '..', 'storage', 'config'], true)) {
        continue;
    }

    copyRecursive("{$source}/{$entry}", "{$target}/{$entry}");
}

step('Fichiers de Cockpit installés dans public/admin.');

// 5. Project configuration and content model ------------------------------

copyRecursive("{$root}/cockpit/config.php", "{$target}/config/config.php");
step('Configuration du projet en place.');

foreach (glob("{$root}/cockpit/models/*.model.php") ?: [] as $model) {
    copyRecursive($model, "{$target}/storage/content/".basename($model));
}

step('Modèle de contenu en place.');

// Runtime folders. Cockpit resolves #cache, #tmp and #uploads by looking them
// up on disk: a missing one breaks the admin, so create them all up front.
// The database itself lives in /var, outside the web root.
foreach ([
    "{$root}/var/data",
    "{$target}/storage/cache",
    "{$target}/storage/tmp/thumbs",
    "{$target}/storage/uploads",
] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
        fail("création impossible : {$dir}");
    }
}

// Cockpit's shipped .htaccess protects its code but says nothing about
// storage. Everything there is internal — except uploads, which the public
// site serves.
foreach (['storage', 'storage/content', 'storage/cache', 'storage/tmp'] as $dir) {
    file_put_contents("{$target}/{$dir}/.htaccess", "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
}

file_put_contents("{$target}/storage/uploads/.htaccess", "Require all granted\n<IfModule !mod_authz_core.c>\n    Allow from all\n</IfModule>\n");

step('Dossiers internes de Cockpit fermés à la consultation.');

// Cockpit's own secret, read from public/admin/.env by config/config.php.
$adminEnv = "{$target}/.env";

if (!is_file($adminEnv)) {
    file_put_contents($adminEnv, 'COCKPIT_SEC_KEY='.bin2hex(random_bytes(24))."\n");
    step('Clé interne de Cockpit générée.');
}

// 6. Sanity check ---------------------------------------------------------

$expected = ['index.php', 'bootstrap.php', 'config/config.php', 'modules/App/bootstrap.php'];
$absent = array_values(array_filter($expected, static fn (string $f): bool => !is_file("{$target}/{$f}")));

foreach (['storage/cache', 'storage/tmp/thumbs', 'storage/uploads', 'storage/content'] as $dir) {
    if (!is_dir("{$target}/{$dir}")) {
        $absent[] = "{$dir}/";
    }
}

if ($absent) {
    fail("fichiers absents après installation : ".implode(', ', $absent)
        ."\n  Un antivirus a probablement supprimé une partie de l'installation.");
}

echo "\n  Cockpit ".COCKPIT_VERSION." est installé.\n";
echo "  Étape suivante : php bin/cockpit-init.php\n\n";
