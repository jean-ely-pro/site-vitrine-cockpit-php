<?php

declare(strict_types=1);

/**
 * Builds the lighter copies for images already in the library.
 *
 * New images get theirs when they are sent. This is for the ones that were
 * already there, and for the day the list of widths changes.
 *
 * Content items keep a copy of the asset they point at, so they are saved
 * again afterwards — otherwise the pages would go on serving the original.
 *
 * Usage: php bin/generer-variantes.php [--refaire]
 */

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

$root = dirname(__DIR__);
$adminDir = "{$root}/public/admin";

if (!is_file("{$adminDir}/bootstrap.php")) {
    fwrite(STDERR, "\n  Cockpit n'est pas installé.\n\n");
    exit(1);
}

require "{$adminDir}/bootstrap.php";
require_once "{$adminDir}/addons/Media/Variants.php";

/** @var Lime\App $app */
$app = Cockpit::instance($adminDir);

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n  Échec : {$e->getMessage()}\n  {$e->getFile()}:{$e->getLine()}\n\n");
    exit(1);
});

$rebuild = in_array('--refaire', $argv, true);

echo "\nGénération des copies allégées\n\n";

$assets = $app->dataStorage->find('assets', ['filter' => ['type' => 'image']])->toArray();
$done = 0;

foreach ($assets as $asset) {

    $withVariants = Media\Variants::build($app, $asset, $rebuild);
    $count = count($withVariants['variantes'] ?? []);

    if ($count === 0) {
        echo "  {$asset['title']} — aucune copie (image plus petite que les largeurs demandées)\n";
        continue;
    }

    $app->dataStorage->save('assets', $withVariants);
    echo "  {$asset['title']} — {$count} copies\n";
    $done++;
}

// Items hold their own copy of the asset; saving them again refreshes it.
$content = $app->module('content');
$refreshed = 0;

foreach ($content->models() as $name => $model) {

    if (($model['type'] ?? 'collection') === 'singleton') {

        $item = $content->item($name);

        if (is_array($item) && isset($item['_id'])) {
            $content->saveItem($name, $item);
            $refreshed++;
        }

        continue;
    }

    foreach ($content->items($name) as $item) {
        $content->saveItem($name, $item);
        $refreshed++;
    }
}

echo "\n  {$done} images traitées, {$refreshed} contenus rafraîchis.\n";
echo "  Le cache des pages a été vidé au passage.\n\n";
