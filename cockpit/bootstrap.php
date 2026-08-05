<?php

/**
 * Empties the page cache whenever content is saved in the admin.
 *
 * Cockpit loads this file at start-up. Installed to
 * public/admin/config/bootstrap.php by bin/install-cockpit.php — edit it here,
 * the copy is overwritten on every update.
 *
 * The whole cache goes, not just the page that was edited: the site identity,
 * the menu and page titles appear on every page, so a partial purge would
 * leave stale copies behind. On a site of a few pages this costs nothing —
 * each page is rendered again on its next visit.
 */

declare(strict_types=1);

require_once dirname(APP_DIR, 2).'/src/Cache/PageCache.php';

/** @var Lime\App $app */
$cache = new App\Cache\PageCache(dirname(APP_DIR).'/cache');

$emptyCache = static function () use ($cache, $app): void {

    $removed = $cache->clear();

    if ($removed > 0) {
        $app->trigger('site.cache.purged', [$removed]);
    }
};

// Saving an item, and removing one, both change what visitors should see.
$app->on('content.item.save', $emptyCache);
$app->on('content.remove', $emptyCache);
