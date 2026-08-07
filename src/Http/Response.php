<?php

declare(strict_types=1);

namespace App\Http;

/**
 * A rendered response, kept separate from its emission so the page cache can
 * store the body without going through the web server.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    /**
     * Headers for a response that will also be stored on disk.
     *
     * Browsers revalidate on every visit, so emptying the cache takes effect
     * at once instead of waiting for a copy held by the visitor to expire.
     * `X-Page-Cache` says who answered: PHP, or the web server from disk.
     *
     * @return array<string, string>
     */
    public static function cacheable(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'X-Page-Cache' => 'miss',
        ];
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
