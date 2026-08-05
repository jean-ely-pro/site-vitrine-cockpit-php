<?php

declare(strict_types=1);

namespace Media;

use Lime\App;

/**
 * Builds the lighter copies of an image the site serves.
 *
 * Done once, when the image is sent, rather than when a page is rendered: a
 * page is rendered every time the cache is emptied, an image is sent once.
 * The addresses are stored on the asset itself, so the site needs no call of
 * its own to know them.
 */
final class Variants
{
    /** Beyond this, an image is heavy enough to be worth mentioning. */
    public const HEAVY_BYTES = 600 * 1024;

    /** Beyond this width, an image carries far more detail than a page shows. */
    public const WIDE_PIXELS = 2600;

    /** Prefix Cockpit puts on paths inside the uploads storage. */
    private const UPLOADS = 'uploads://';

    /**
     * Generates every preset for one asset and records their addresses.
     *
     * @param array<string, mixed> $asset
     * @return array<string, mixed> The asset, with its variants.
     */
    public static function build(App $app, array $asset, bool $rebuild = false): array
    {
        if (($asset['type'] ?? null) !== 'image' || ($asset['mime'] ?? '') === 'image/svg+xml') {
            return $asset;
        }

        $variants = [];

        foreach ($app->module('assets')->presets() as $name => $preset) {

            // Enlarging an image adds weight without adding detail.
            if (isset($preset['width'], $asset['width']) && $preset['width'] > (int) $asset['width']) {
                continue;
            }

            // Asked for as a path, not as an address: the address Cockpit
            // builds depends on how it was reached, a path never does. The
            // site turns it into an address the same way it does for the
            // original image.
            $path = $app->helper('asset')->image([
                'src' => $asset['_id'],
                'mode' => $preset['mode'] ?? 'fitToWidth',
                'width' => $preset['width'] ?? null,
                'height' => $preset['height'] ?? null,
                'quality' => $preset['quality'] ?? 82,
                'mime' => $preset['mime'] ?? 'webp',
                // Honour the focal point set in the admin, when there is one.
                'fp' => $asset['fp'] ?? null,
                'rebuild' => $rebuild,
            ], true);

            if (is_string($path) && str_starts_with($path, self::UPLOADS)) {
                $variants[$name] = [
                    'path' => substr($path, strlen(self::UPLOADS)),
                    'width' => (int) ($preset['width'] ?? 0),
                ];
            }
        }

        $asset['variantes'] = $variants;

        return $asset;
    }
}
