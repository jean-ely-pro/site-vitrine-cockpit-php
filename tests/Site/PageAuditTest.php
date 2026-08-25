<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Accessibility\PageAudit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A check that never fails is worth nothing. Each defect is fed in on purpose
 * to make sure it is caught — and a correct page must stay silent.
 */
final class PageAuditTest extends TestCase
{
    private function page(string $corps, string $tete = '', string $lang = 'fr'): string
    {
        return <<<HTML
        <!doctype html>
        <html lang="{$lang}">
        <head>
            <meta charset="utf-8">
            <title>Un titre</title>
            <meta name="description" content="Une description.">
            {$tete}
        </head>
        <body>
            <a class="lien-evitement" href="#contenu">Aller au contenu</a>
            <main id="contenu">{$corps}</main>
        </body>
        </html>
        HTML;
    }

    #[Test]
    public function une_page_correcte_ne_remonte_rien(): void
    {
        $html = $this->page('<h1>Titre</h1><h2>Section</h2><p>Du texte.</p>');

        $this->assertSame([], (new PageAudit())->problems($html));
    }

    #[Test]
    #[DataProvider('ressourcesTierces')]
    public function une_ressource_venue_dailleurs_est_signalee(string $tete, string $corps): void
    {
        $problemes = (new PageAudit())->problems($this->page($corps, tete: $tete));

        $this->assertContains('ressource chargée depuis un autre site : cdn.exemple.net', $problemes);
    }

    /** @return array<string, array{string, string}> */
    public static function ressourcesTierces(): array
    {
        return [
            'image' => ['', '<h1>Titre</h1><img src="https://cdn.exemple.net/photo.jpg" alt="Une photo" width="8" height="8">'],
            'script' => ['<script src="https://cdn.exemple.net/mesure.js"></script>', '<h1>Titre</h1>'],
            'feuille de style' => ['<link rel="stylesheet" href="https://cdn.exemple.net/style.css">', '<h1>Titre</h1>'],
            'police' => ['<link rel="preload" as="font" href="https://cdn.exemple.net/police.woff2">', '<h1>Titre</h1>'],
        ];
    }

    #[Test]
    #[DataProvider('adressesSeulementNommees')]
    public function une_adresse_seulement_nommee_nest_pas_une_ressource(string $tete, string $corps): void
    {
        // Ni l’un ni l’autre ne fait charger quoi que ce soit à la page. Un
        // contrôle qui crie au loup sur le lien Facebook du pied de page finit
        // par être ignoré, et les vraies ressources tierces passent avec.
        $this->assertSame([], (new PageAudit())->problems($this->page($corps, tete: $tete)));
    }

    /** @return array<string, array{string, string}> */
    public static function adressesSeulementNommees(): array
    {
        return [
            'lien vers un réseau social' => ['', '<h1>Titre</h1><p>Suivez-nous sur <a href="https://www.facebook.com/atelier">Facebook</a>.</p>'],
            'adresse revendiquée' => ['<link rel="canonical" href="https://domaine.tld/services">', '<h1>Titre</h1>'],
        ];
    }

    #[Test]
    #[DataProvider('defauts')]
    public function un_defaut_est_signale(string $html, string $extraitAttendu): void
    {
        $problemes = implode(' | ', (new PageAudit())->problems($html));

        $this->assertStringContainsString($extraitAttendu, $problemes);
    }

    /** @return array<string, array{string, string}> */
    public static function defauts(): array
    {
        $audit = new self('defauts');

        return [
            'langue absente' => [
                $audit->page('<h1>Titre</h1>', '', 'en'),
                'la langue de la page n’est pas déclarée en français',
            ],
            'deux titres principaux' => [
                $audit->page('<h1>Un</h1><h1>Deux</h1>'),
                'la page a 2 titres principaux au lieu d’un seul',
            ],
            'aucun titre principal' => [
                $audit->page('<h2>Sans titre principal</h2>'),
                'la page n’a aucun titre principal',
            ],
            'saut de niveau' => [
                $audit->page('<h1>Titre</h1><h2>Section</h2><h4>Trop loin</h4>'),
                'saut de niveau de titre : h2 suivi de h4',
            ],
            'image sans description' => [
                $audit->page('<h1>Titre</h1><img src="/photo.webp" width="10" height="10">'),
                'image sans description : photo.webp',
            ],
            'image sans dimensions' => [
                $audit->page('<h1>Titre</h1><img src="/photo.webp" alt="Une photo">'),
                'image sans dimensions',
            ],
            'champ sans intitulé' => [
                $audit->page('<h1>Titre</h1><form><input type="text" name="nom"></form>'),
                'champ de formulaire sans intitulé : nom',
            ],
            'lien sans adresse' => [
                $audit->page('<h1>Titre</h1><a href="">Cliquer</a>'),
                'un lien sans adresse',
            ],
            'police distante' => [
                $audit->page('<h1>Titre</h1>', '<link rel="stylesheet" href="https://fonts.googleapis.com/css">'),
                'fonts.googleapis.com',
            ],
            'image de tiers' => [
                $audit->page('<h1>Titre</h1><img src="https://images.unsplash.com/x.jpg" alt="x" width="1" height="1">'),
                'images.unsplash.com',
            ],
        ];
    }

    #[Test]
    public function le_lien_devitement_manquant_est_signale(): void
    {
        $html = '<!doctype html><html lang="fr"><head><title>T</title>'
            .'<meta name="description" content="d"></head><body><main><h1>Titre</h1></main></body></html>';

        $this->assertContains(
            'il manque le lien permettant d’aller directement au contenu',
            (new PageAudit())->problems($html),
        );
    }

    #[Test]
    public function une_image_decorative_avec_description_vide_est_acceptee(): void
    {
        $html = $this->page('<h1>Titre</h1><img src="/vignette.webp" alt="" width="10" height="10">');

        $this->assertSame([], (new PageAudit())->problems($html), 'alt="" est la bonne façon de signaler une image décorative');
    }

    #[Test]
    public function un_champ_cache_na_pas_besoin_dintitule(): void
    {
        $html = $this->page('<h1>Titre</h1><form><input type="hidden" name="jeton" value="x"></form>');

        $this->assertSame([], (new PageAudit())->problems($html));
    }
}
