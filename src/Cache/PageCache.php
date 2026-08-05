<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Stores rendered pages as plain files the web server can serve on its own.
 *
 * On shared hosting this is what keeps the site fast: once a page has been
 * visited, Apache answers the next visitors straight from disk and PHP is
 * never started. The cache is emptied whenever content is saved in the admin,
 * so what is served is never out of step with what was published.
 */
final class PageCache
{
    /** Slugs are the only free part of a cached name; keep them strict. */
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Addresses that are not pages but are still worth serving from disk. */
    private const FILES = ['/sitemap.xml' => 'sitemap.xml', '/robots.txt' => 'robots.txt'];

    public function __construct(
        private readonly string $directory,
        private readonly bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Name of the file a route is stored under, or null when it must not be
     * cached at all.
     */
    public function fileFor(string $route): ?string
    {
        if (isset(self::FILES[$route])) {
            return self::FILES[$route];
        }

        $slug = trim($route, '/');

        if ($slug === '') {
            return 'index.html';
        }

        return preg_match(self::SLUG, $slug) === 1 ? "{$slug}.html" : null;
    }

    /**
     * Stores a rendered response.
     *
     * Only plain successful GET requests are stored, so an error page or a
     * search result never takes the place of a real page.
     *
     * @param array<string, mixed> $server
     */
    public function store(string $route, string $body, int $status, array $server): void
    {
        if (!$this->enabled || $status !== 200) {
            return;
        }

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'GET' || ($server['QUERY_STRING'] ?? '') !== '') {
            return;
        }

        $file = $this->fileFor($route);

        if ($file === null) {
            return;
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o755, true) && !is_dir($this->directory)) {
            return;
        }

        // Written aside then moved into place, so a visitor can never be
        // served a half-written page.
        $temporary = "{$this->directory}/.{$file}.".bin2hex(random_bytes(6));

        if (file_put_contents($temporary, $body) === false) {
            return;
        }

        if (!rename($temporary, "{$this->directory}/{$file}")) {
            @unlink($temporary);
        }
    }

    /**
     * Empties the cache.
     *
     * @return int Number of files removed.
     */
    public function clear(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $removed = 0;

        foreach (glob("{$this->directory}/*") ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }

        // Half-written files left behind by an interrupted store.
        foreach (glob("{$this->directory}/.*") ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
