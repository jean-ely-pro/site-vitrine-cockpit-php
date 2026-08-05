<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Where the media picked in the admin are served from.
 *
 * In production that is a path under the site itself; in development the admin
 * runs on its own port, so the base address is absolute. Structured data needs
 * absolute addresses either way.
 */
final class MediaUrls
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $siteUrl,
    ) {
    }

    /** @param mixed $asset The asset object stored on the content item. */
    public function url(mixed $asset): ?string
    {
        $path = is_array($asset) ? ($asset['path'] ?? null) : null;

        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    public function absoluteUrl(mixed $asset): ?string
    {
        $url = $this->url($asset);

        if ($url === null || !str_starts_with($url, '/')) {
            return $url;
        }

        return rtrim($this->siteUrl, '/').$url;
    }
}
