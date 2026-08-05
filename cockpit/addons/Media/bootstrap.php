<?php

/**
 * Media handling: lighter copies, compulsory descriptions, weight warnings.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

declare(strict_types=1);

require_once __DIR__.'/Variants.php';
require_once __DIR__.'/AltText.php';

use Media\AltText;
use Media\Variants;

/** @var Lime\App $this */

// Lighter copies are built when the image arrives, not when a page is
// rendered: a page is rendered again every time the cache is emptied.
$this->on('assets.uploaded', function ($assets) {

    foreach ($assets as $asset) {

        $withVariants = Variants::build($this, $asset);

        if (($withVariants['variantes'] ?? []) !== []) {
            $this->dataStorage->save('assets', $withVariants);
        }
    }
});

// A focal point moved after the fact changes how the copies are framed. The
// name of a generated file follows its options, so a changed focal point
// simply produces a new one — nothing needs rebuilding by force.
$this->on('assets.asset.update', function (&$asset) {
    $asset = Variants::build($this, $asset);
});

// An image without a description is unusable for part of the visitors.
$this->on('content.item.save.before', function (string $modelName, array &$item) {

    $model = $this->module('content')->model($modelName);

    if (!$model) {
        return;
    }

    $missing = AltText::missingIn($model['fields'] ?? [], $item);

    if ($missing !== null) {
        throw new \App\Exception\AppNotification(
            'Renseigner « '.$missing.' » : la description de l’image est lue à voix haute par '
            .'les lecteurs d’écran et s’affiche si l’image ne charge pas.'
        );
    }
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer') {
        $assets[] = [
            'src' => 'media:assets/poids-images.js',
            'type' => 'module',
            'position' => 'footer',
        ];
    }
});
