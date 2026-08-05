<?php

/**
 * Guards on what the text editor may produce.
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

/** @var Lime\App $this */

$this->on('content.item.save.before', function (string $modelName, array &$item) {

    $model = $this->module('content')->model($modelName);

    if ($model) {
        $item = Headings::inFields($model['fields'] ?? [], $item);
    }
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer') {
        $assets[] = [
            'src' => 'editorguards:assets/heading-levels.js',
            'type' => 'module',
            'position' => 'footer',
        ];
    }
});
