<?php

declare(strict_types=1);

namespace App\Content;

use App\Cockpit\Client;

/**
 * Reads what the public site is allowed to show.
 *
 * Everything unpublished is filtered out here, once, so no template can leak
 * a draft: Cockpit marks published items with `_state` = 1.
 */
final class Repository
{
    private const PUBLISHED = 1;

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    /** @var list<array{libelle: string, slug: string}>|null */
    private ?array $menu = null;

    private ?bool $hasArticles = null;

    /** @var array<string, mixed>|null */
    private ?array $legal = null;

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Site identity, colours, contact details and opening hours.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings ??= $this->client->singleton('settings') ?? [];
    }

    /**
     * A published page, or null when the slug is unknown or still a draft.
     *
     * @return array<string, mixed>|null
     */
    public function page(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        // Populated: a section may point at another page — the contact form
        // links to the privacy policy — and a bare reference has no address.
        return $this->client->item('pages', [
            'slug' => $slug,
            '_state' => self::PUBLISHED,
        ], populate: true);
    }

    /**
     * Every published page.
     *
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        return $this->client->items(
            'pages',
            ['_state' => self::PUBLISHED],
            ['titre' => 1],
        );
    }

    /**
     * Published news items, most recent first.
     *
     * @return list<array<string, mixed>>
     */
    public function articles(?int $limit = null): array
    {
        return $this->client->items(
            'articles',
            ['_state' => self::PUBLISHED],
            ['date' => -1],
            $limit,
        );
    }

    /**
     * Legal notices: publisher and host, everything else comes from settings.
     *
     * @return array<string, mixed>
     */
    public function legal(): array
    {
        return $this->legal ??= $this->client->singleton('legal') ?? [];
    }

    /**
     * Whether anything at all has been published in the news.
     *
     * Decides if the menu carries a « Actualités » entry: an empty section is
     * worse than no section.
     */
    public function hasArticles(): bool
    {
        return $this->hasArticles ??= $this->articles(1) !== [];
    }

    /**
     * A published news item, or null when the slug is unknown or still a draft.
     *
     * @return array<string, mixed>|null
     */
    public function article(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        return $this->client->item('articles', [
            'slug' => $slug,
            '_state' => self::PUBLISHED,
        ]);
    }

    /**
     * Menu entries, in order, reduced to what a link needs.
     *
     * Entries whose page was deleted or unpublished are dropped: a menu never
     * points at a page the visitor cannot reach.
     *
     * @return list<array{libelle: string, slug: string}>
     */
    public function menu(): array
    {
        if ($this->menu !== null) {
            return $this->menu;
        }

        $singleton = $this->client->singleton('menu', populate: true) ?? [];
        $published = array_column($this->pages(), null, 'slug');
        $entries = [];

        foreach ($singleton['entrees'] ?? [] as $entry) {

            $page = $entry['page'] ?? null;
            $slug = is_array($page) ? ($page['slug'] ?? null) : null;

            if (!is_string($slug) || !isset($published[$slug])) {
                continue;
            }

            $label = trim((string) ($entry['libelle'] ?? ''));

            $entries[] = [
                'libelle' => $label !== '' ? $label : (string) ($page['titre'] ?? $slug),
                'slug' => $slug,
            ];
        }

        return $this->menu = $entries;
    }
}
