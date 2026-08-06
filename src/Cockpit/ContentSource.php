<?php

declare(strict_types=1);

namespace App\Cockpit;

/**
 * Where the site reads its content, and where the form files a message.
 *
 * Stated as an interface so the rules built on top of it — never serving a
 * draft, never letting the contact form reach anything but its own collection
 * — can be verified without a Cockpit installation behind them.
 */
interface ContentSource
{
    /** @return array<string, mixed>|null */
    public function singleton(string $model, bool $populate = false): ?array;

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>|null
     */
    public function item(string $model, array $filter, bool $populate = false): ?array;

    /**
     * @param array<string, mixed> $filter
     * @param array<string, int> $sort
     * @return list<array<string, mixed>>
     */
    public function items(string $model, array $filter = [], array $sort = [], ?int $limit = null): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create(string $model, array $data): ?array;
}
