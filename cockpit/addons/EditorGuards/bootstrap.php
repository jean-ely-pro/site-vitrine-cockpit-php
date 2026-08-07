<?php

/**
 * Guards applied to what is typed in the admin.
 *
 * The page title is the only level-one heading of a page. The editor offers
 * levels one to six all the same, and text pasted from a word processor
 * carries whatever it likes — so the rule is enforced when content is saved,
 * whatever route it came in by. Hiding the buttons is only a courtesy.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

declare(strict_types=1);

require_once __DIR__.'/Headings.php';

use EditorGuards\Headings;

/**
 * Addresses the site serves itself. A page taking one of them would simply
 * never be shown, which nothing on screen would explain.
 *
 * Kept in step with App\Http\Route on the site side — two separate
 * applications, so the value is stated in both.
 */
const RESERVED_PAGE_SLUGS = ['actualites', 'contact', 'mentions-legales', 'confidentialite'];

/** @var Lime\App $this */

$this->on('content.item.save.before', function (string $modelName, array &$item) {

    if ($modelName === 'pages' && in_array($item['slug'] ?? '', RESERVED_PAGE_SLUGS, true)) {
        throw new \App\Exception\AppNotification(
            'L’adresse « '.$item['slug'].' » est déjà utilisée par le site lui-même. En choisir une autre.'
        );
    }

    $model = $this->module('content')->model($modelName);

    if ($model) {
        $item = Headings::inFields($model['fields'] ?? [], $item);
    }
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer') {

        foreach (['heading-levels.js', 'contraste-couleurs.js'] as $script) {
            $assets[] = [
                'src' => "editorguards:assets/{$script}",
                'type' => 'module',
                'position' => 'footer',
            ];
        }
    }
});
