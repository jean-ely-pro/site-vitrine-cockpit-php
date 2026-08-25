<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Turns an asset into what a template needs to show it well.
 *
 * The lighter copies are built when the image is sent and their addresses are
 * carried by the asset itself, so rendering a page asks nothing of anyone.
 * When an image has no copies — sent before this existed — the original is
 * served on its own, and the page still works.
 */
final class Picture
{
    public function __construct(private readonly MediaUrls $urls)
    {
    }

    /**
     * @param mixed $asset The asset stored on the content item.
     * @return array{src: string, srcset: string, width: int|null, height: int|null}|null
     */
    /** Width a browser without `srcset` support should end up loading. */
    private const FALLBACK_WIDTH = 960;

    /**
     * The address of the original, written in full.
     *
     * Social networks and messaging apps fetch preview images from their own
     * servers: a path relative to the site means nothing to them.
     */
    public function absolute(mixed $asset): ?string
    {
        return $this->urls->absoluteUrl($asset);
    }

    public function from(mixed $asset): ?array
    {
        $original = $this->urls->url($asset);

        if ($original === null) {
            return null;
        }

        return [
            // The plain `src` is what a browser ignoring `srcset` will take,
            // so it points at a copy rather than at the full-size original.
            'src' => $this->fallback($asset) ?? $original,
            'srcset' => $this->srcset($asset),
            'width' => isset($asset['width']) ? (int) $asset['width'] : null,
            'height' => isset($asset['height']) ? (int) $asset['height'] : null,
        ];
    }

    /** The copy closest to a sensible display width. */
    private function fallback(mixed $asset): ?string
    {
        $variants = is_array($asset) ? ($asset['variantes'] ?? null) : null;

        if (!is_array($variants) || $variants === []) {
            return null;
        }

        $best = null;
        $bestGap = PHP_INT_MAX;

        foreach ($variants as $variant) {

            $width = is_array($variant) ? (int) ($variant['width'] ?? 0) : 0;
            $gap = abs($width - self::FALLBACK_WIDTH);

            if ($width > 0 && $gap < $bestGap) {
                $best = $variant;
                $bestGap = $gap;
            }
        }

        return $best === null ? null : $this->urls->url($best);
    }

    /**
     * The lighter copies, described by their width so the browser can pick.
     *
     * @param mixed $asset
     */
    private function srcset(mixed $asset): string
    {
        $variants = is_array($asset) ? ($asset['variantes'] ?? null) : null;

        if (!is_array($variants)) {
            return '';
        }

        $entries = [];

        foreach ($variants as $variant) {

            if (!is_array($variant)) {
                continue;
            }

            $url = $this->urls->url($variant);
            $width = (int) ($variant['width'] ?? 0);

            if ($url !== null && $width > 0) {
                $entries[$width] = "{$url} {$width}w";
            }
        }

        ksort($entries);

        return implode(', ', $entries);
    }
}
