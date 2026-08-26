<?php

declare(strict_types=1);

namespace App\View;

use App\Contact\ContactForm;
use App\Content\Repository;
use App\Media\MediaUrls;
use App\Seo\LocalBusiness;
use App\Seo\SocialMeta;
use App\View\Colours;

/**
 * What every page needs, whatever it shows: identity, menu, structured data.
 *
 * Gathered in one place so an action only has to describe what makes it
 * different from the others.
 */
final class ViewContext
{
    public function __construct(
        private readonly Repository $content,
        private readonly ContactForm $contactForm,
        private readonly MediaUrls $media,
        private readonly Colours $colours,
        private readonly string $siteUrl,
        private readonly string $homePageSlug = 'accueil',
    ) {
    }

    /**
     * @param array<string, mixed> $extra What this page adds to the common set.
     * @param string|null $path Where this page is served, when that is not the
     *                          slug itself: a news item is reached under the
     *                          news slug, but claims an address of its own.
     * @return array<string, mixed>
     */
    public function forPage(string $slug, array $extra = [], ?string $path = null): array
    {
        $settings = $this->content->settings();

        // Written on the first render after a purge, exactly like a page.
        $this->colours->ensure($settings);

        // array_merge, not «+»: what an action passes must win over the
        // defaults, and «+» keeps the left-hand value.
        return array_merge([
            'site' => $settings,
            'menu' => $this->content->menu(),
            'afficherActualites' => $this->content->hasArticles(),
            // The slug drives the menu's active entry; the path drives the
            // address the page claims. They part company on a news item.
            'slug' => $slug,
            'canonique' => SocialMeta::canonical($this->siteUrl, $path ?? $this->path($slug)),
            'jsonld' => $this->jsonLd($settings),
            'contactActif' => $this->contactForm->isConfigured(),
            'jetonContact' => $this->contactForm->stamp(),
            'formulaire' => [],
        ], $extra);
    }

    /**
     * Where a page is served, given the slug it is stored under.
     *
     * The home page answers at the root, not at its slug — the address the
     * page claims must be the one visitors reach.
     */
    private function path(string $slug): string
    {
        $slug = trim($slug, '/');

        return $slug === '' || $slug === $this->homePageSlug ? '/' : '/'.$slug;
    }

    /**
     * The LocalBusiness description, ready to be dropped into the page.
     *
     * Empty until the site has at least a name: structured data describing a
     * business with no name says nothing and is better left out.
     *
     * @param array<string, mixed> $settings
     */
    private function jsonLd(array $settings): string
    {
        if (trim((string) ($settings['nom'] ?? '')) === '') {
            return '';
        }

        $data = LocalBusiness::fromSettings(
            $settings,
            $this->siteUrl,
            $this->media->absoluteUrl($settings['logo'] ?? null),
        );

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
