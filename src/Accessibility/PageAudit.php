<?php

declare(strict_types=1);

namespace App\Accessibility;

use DOMDocument;
use DOMXPath;

/**
 * Reads a page as it is served and lists what would stand in a visitor's way.
 *
 * Works on the delivered HTML rather than on the templates: what matters is
 * what arrives, whichever path it took to get there — cache included.
 *
 * No dependency on purpose. A check that needs a toolchain installed is a
 * check nobody runs on the customer's hosting.
 */
final class PageAudit
{
    /** Hosts a local page may legitimately point at during development. */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1'];

    /** @return list<string> */
    public function problems(string $html): array
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        return array_merge(
            $this->head($xpath),
            $this->structure($xpath),
            $this->links($xpath),
            $this->images($xpath),
            $this->forms($xpath),
            $this->thirdParties($xpath),
        );
    }

    /** @return list<string> */
    private function head(DOMXPath $xpath): array
    {
        $problems = [];

        if ($xpath->query('//html/@lang')->item(0)?->nodeValue !== 'fr') {
            $problems[] = 'la langue de la page n’est pas déclarée en français';
        }

        if (trim($xpath->query('//title')->item(0)?->textContent ?? '') === '') {
            $problems[] = 'la page n’a pas de titre';
        }

        $description = $xpath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue;

        if ($description === null || trim($description) === '') {
            $problems[] = 'la page n’a pas de méta-description';
        }

        return $problems;
    }

    /** @return list<string> */
    private function structure(DOMXPath $xpath): array
    {
        $problems = [];

        if ($xpath->query('//main')->length !== 1) {
            $problems[] = 'la page devrait avoir exactement une zone de contenu principale';
        }

        $h1 = $xpath->query('//h1')->length;

        if ($h1 === 0) {
            $problems[] = 'la page n’a aucun titre principal';
        } elseif ($h1 > 1) {
            $problems[] = "la page a {$h1} titres principaux au lieu d’un seul";
        }

        // A jump — h2 then h4 — leaves a hole in the outline a screen reader
        // reads out as a missing section.
        $previous = 0;

        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {

            $level = (int) substr($heading->nodeName, 1);

            if ($previous !== 0 && $level > $previous + 1) {
                $text = trim(mb_substr($heading->textContent, 0, 40));
                $problems[] = "saut de niveau de titre : h{$previous} suivi de h{$level} (« {$text} »)";
            }

            $previous = $level;
        }

        return $problems;
    }

    /** @return list<string> */
    private function links(DOMXPath $xpath): array
    {
        $problems = [];

        if ($xpath->query('//a[@class="lien-evitement"]')->length === 0) {
            $problems[] = 'il manque le lien permettant d’aller directement au contenu';
        }

        foreach ($xpath->query('//a[not(@href) or normalize-space(@href)=""]') as $empty) {
            $problems[] = 'un lien sans adresse : « '.trim(mb_substr($empty->textContent, 0, 30)).' »';
        }

        foreach ($xpath->query('//a[normalize-space(.)="" and not(.//img) and not(@aria-label)]') as $mute) {
            $problems[] = 'un lien sans intitulé, vers '.$mute->getAttribute('href');
        }

        return $problems;
    }

    /** @return list<string> */
    private function images(DOMXPath $xpath): array
    {
        $problems = [];

        foreach ($xpath->query('//img') as $image) {

            $src = $image->getAttribute('src');
            $short = basename(parse_url($src, PHP_URL_PATH) ?: $src);

            if (!$image->hasAttribute('alt')) {
                $problems[] = "image sans description : {$short}";
            }

            if (!$image->hasAttribute('width') || !$image->hasAttribute('height')) {
                $problems[] = "image sans dimensions, la page se décalera : {$short}";
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function forms(DOMXPath $xpath): array
    {
        $problems = [];

        foreach ($xpath->query('//input[not(@type) or @type!="hidden"]|//textarea|//select') as $field) {

            $id = $field->getAttribute('id');
            $labelled = $id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0;

            if (!$labelled && $field->getAttribute('aria-label') === '') {
                $name = $field->getAttribute('name') ?: $field->nodeName;
                $problems[] = "champ de formulaire sans intitulé : {$name}";
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function thirdParties(DOMXPath $xpath): array
    {
        $problems = [];

        foreach ($xpath->query('//*[@src or @href]') as $node) {

            // The canonical link loads nothing: it names an address. What it
            // says is checked by App\Seo\CanonicalAudit.
            if ($node->nodeName === 'link' && strtolower($node->getAttribute('rel')) === 'canonical') {
                continue;
            }

            $url = $node->getAttribute('src') ?: $node->getAttribute('href');

            if (preg_match('#^https?://#', $url) !== 1) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST) ?: '';

            if ($host !== '' && !in_array($host, self::LOCAL_HOSTS, true)) {
                $problems[] = "ressource chargée depuis un autre site : {$host}";
            }
        }

        return array_values(array_unique($problems));
    }
}
