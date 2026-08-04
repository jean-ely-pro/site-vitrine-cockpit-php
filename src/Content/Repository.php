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

        return $this->client->item('pages', [
            'slug' => $slug,
            '_state' => self::PUBLISHED,
        ]);
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
}
