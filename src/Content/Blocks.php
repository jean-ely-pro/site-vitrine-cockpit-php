<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Decides which blocks a page can render.
 *
 * A block type exists when a partial of that name sits in templates/blocs.
 * Adding a type therefore means adding a partial and an entry in the Cockpit
 * field — nothing else to declare here.
 *
 * Filtering also keeps a stored type from ever reaching an include path: only
 * names that match a real partial get through.
 */
final class Blocks
{
    /** @var list<string>|null */
    private ?array $available = null;

    public function __construct(private readonly string $templateDir)
    {
    }

    /**
     * Keeps only the blocks the site knows how to render.
     *
     * @param mixed $blocks As stored on the page.
     * @return list<array<string, mixed>>
     */
    public function renderable(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            return [];
        }

        $available = $this->available();
        $renderable = [];

        foreach ($blocks as $block) {

            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (is_string($type) && in_array($type, $available, true)) {
                $renderable[] = $block;
            }
        }

        return $renderable;
    }

    /** @return list<string> */
    public function available(): array
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $types = [];

        foreach (glob("{$this->templateDir}/*.html.twig") ?: [] as $template) {
            $types[] = basename($template, '.html.twig');
        }

        return $this->available = $types;
    }
}
