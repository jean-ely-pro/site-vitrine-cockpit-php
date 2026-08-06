<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Decides which blocks a page can render.
 *
 * A block type exists when a partial of that name sits in one of the block
 * folders. Adding a type therefore means adding a partial and an entry in the
 * Cockpit field — nothing else to declare here.
 *
 * Two folders are searched: the ones delivered with the site, and the ones
 * belonging to this particular site. Keeping them apart is what allows an
 * update to be merged without touching a single file of the design.
 *
 * Filtering also keeps a stored type from ever reaching an include path: only
 * names that match a real partial get through.
 */
final class Blocks
{
    /** @var list<string>|null */
    private ?array $available = null;

    /** @var list<string> */
    private readonly array $directories;

    /**
     * @param string ...$directories Where to look for block partials. The site
     *                               keeps its own next to the ones delivered
     *                               with it, so an update never touches them.
     */
    public function __construct(string ...$directories)
    {
        $this->directories = array_values($directories);
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

        foreach ($this->directories as $directory) {
            foreach (glob("{$directory}/*.html.twig") ?: [] as $template) {
                $types[] = basename($template, '.html.twig');
            }
        }

        return $this->available = array_values(array_unique($types));
    }
}
