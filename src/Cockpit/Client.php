<?php

declare(strict_types=1);

namespace App\Cockpit;

/**
 * Minimal read client for Cockpit's content API.
 *
 * The API key it carries is a read-only one: it cannot write, whatever
 * happens to the front end.
 */
final class Client
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 5,
    ) {
    }

    /**
     * Fetches a singleton.
     *
     * @return array<string, mixed>|null
     */
    public function singleton(string $model): ?array
    {
        $item = $this->get("/content/item/{$model}");

        return is_array($item) && $item !== [] ? $item : null;
    }

    /**
     * Fetches one item of a collection.
     *
     * @param array<string, mixed> $filter
     * @return array<string, mixed>|null
     */
    public function item(string $model, array $filter): ?array
    {
        $item = $this->get("/content/item/{$model}", [
            'filter' => json_encode($filter, JSON_THROW_ON_ERROR),
        ]);

        return is_array($item) && $item !== [] ? $item : null;
    }

    /**
     * Fetches several items of a collection.
     *
     * @param array<string, mixed> $filter
     * @param array<string, int> $sort
     * @return list<array<string, mixed>>
     */
    public function items(string $model, array $filter = [], array $sort = [], ?int $limit = null): array
    {
        $params = ['filter' => json_encode($filter, JSON_THROW_ON_ERROR)];

        if ($sort !== []) {
            $params['sort'] = json_encode($sort, JSON_THROW_ON_ERROR);
        }

        if ($limit !== null) {
            $params['limit'] = (string) $limit;
        }

        $items = $this->get("/content/items/{$model}", $params);

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @param array<string, string> $params
     * @return array<mixed>|null
     */
    private function get(string $endpoint, array $params = []): ?array
    {
        $url = rtrim($this->baseUrl, '/').$endpoint;

        if ($params !== []) {
            $url .= '?'.http_build_query($params);
        }

        $curl = curl_init($url);

        if ($curl === false) {
            throw new ContentUnavailable("Requête impossible vers {$url}.");
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["api-key: {$this->apiKey}"],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new ContentUnavailable("Contenu injoignable : {$error}");
        }

        if ($status === 401 || $status === 403 || $status === 412) {
            throw new ContentUnavailable('Clé de lecture refusée par Cockpit.');
        }

        // Cockpit answers 404 when nothing matches the filter. That is an
        // absent page, not a broken service: the caller turns it into a 404.
        if ($status === 404) {
            return null;
        }

        if ($status >= 400) {
            throw new ContentUnavailable("Cockpit a répondu {$status} pour {$endpoint}.");
        }

        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
