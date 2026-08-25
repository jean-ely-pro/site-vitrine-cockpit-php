<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Media\MediaUrls;
use App\Media\Picture;
use App\Seo\SocialMeta;
use App\View\SiteExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Ce qu’un moteur de recherche et un réseau social lisent de la page.
 *
 * Deux choses se vérifient ici et nulle part ailleurs : l’adresse que la page
 * revendique, et l’aperçu affiché quand un lien est partagé. Aucune des deux
 * n’est visible en regardant le site.
 */
final class MetaSocialesTest extends TestCase
{
    private function twig(): Environment
    {
        $racine = dirname(__DIR__, 2);

        $twig = new Environment(
            new FilesystemLoader(array_values(array_filter([
                "{$racine}/templates-client",
                "{$racine}/templates",
            ], 'is_dir'))),
            ['strict_variables' => false, 'autoescape' => 'html'],
        );

        $twig->addExtension(new SiteExtension(new Picture(
            new MediaUrls('/admin/storage/uploads', 'https://exemple.fr'),
        )));
        $twig->addGlobal('accueilSlug', 'accueil');

        return $twig;
    }

    /** @param array<string, mixed> $extra */
    private function rendre(string $gabarit, array $extra = []): string
    {
        return $this->twig()->render($gabarit, array_merge([
            'site' => ['nom' => 'Atelier Bloom', 'description' => 'Fleuriste à Nantes'],
            'menu' => [],
            'afficherActualites' => false,
            'jsonld' => '',
            'canonique' => 'https://exemple.fr/services',
            'slug' => 'services',
            'page' => ['titre' => 'Nos services', 'blocs' => []],
        ], $extra));
    }

    // ── L’adresse revendiquée ─────────────────────────────────────────────

    #[Test]
    #[DataProvider('adresses')]
    public function ladresse_revendiquee_est_absolue(string $siteUrl, string $chemin, ?string $attendue): void
    {
        $this->assertSame($attendue, SocialMeta::canonical($siteUrl, $chemin));
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function adresses(): array
    {
        return [
            'page' => ['https://exemple.fr', '/services', 'https://exemple.fr/services'],
            'accueil' => ['https://exemple.fr', '/', 'https://exemple.fr/'],
            'barre finale du site' => ['https://exemple.fr/', '/services', 'https://exemple.fr/services'],
            'barre finale du chemin' => ['https://exemple.fr', '/services/', 'https://exemple.fr/services'],
            'chemin sans barre' => ['https://exemple.fr', 'services', 'https://exemple.fr/services'],
            'sous-dossier' => ['https://exemple.fr/site', '/services', 'https://exemple.fr/site/services'],
            // Une adresse fausse envoie moteurs et réseaux ailleurs : mieux
            // vaut n'en revendiquer aucune.
            'adresse du site vide' => ['', '/services', null],
            'adresse du site sans protocole' => ['exemple.fr', '/services', null],
            'chemin de fichier' => ['/var/www', '/services', null],
        ];
    }

    #[Test]
    public function une_page_porte_son_adresse_et_la_repete_pour_les_reseaux(): void
    {
        $html = $this->rendre('page.html.twig');

        $this->assertStringContainsString('<link rel="canonical" href="https://exemple.fr/services">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://exemple.fr/services">', $html);
    }

    #[Test]
    public function une_page_introuvable_ne_revendique_aucune_adresse(): void
    {
        $this->assertStringNotContainsString('rel="canonical"', $this->rendre('404.html.twig'));
    }

    #[Test]
    public function une_liste_dactualites_vide_ne_revendique_aucune_adresse(): void
    {
        $html = $this->rendre('actualites.html.twig', ['articles' => []]);

        $this->assertStringContainsString('name="robots" content="noindex"', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
    }

    #[Test]
    public function une_liste_dactualites_remplie_garde_son_adresse(): void
    {
        $html = $this->rendre('actualites.html.twig', [
            'articles' => [['titre' => 'Ouverture le dimanche', 'slug' => 'ouverture-le-dimanche']],
            'canonique' => 'https://exemple.fr/actualites',
        ]);

        $this->assertStringNotContainsString('content="noindex"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://exemple.fr/actualites">', $html);
    }

    // ── L’aperçu ──────────────────────────────────────────────────────────

    #[Test]
    public function lapercu_reprend_le_titre_de_la_page_sans_le_reecrire(): void
    {
        $html = $this->rendre('page.html.twig', [
            'page' => ['titre' => 'Nos services', 'seoTitre' => 'Fleurs et abonnements', 'blocs' => []],
        ]);

        preg_match('#<title>(.*?)</title>#', $html, $titre);
        preg_match('#<meta property="og:title" content="(.*?)">#', $html, $partage);

        $this->assertNotEmpty($titre[1] ?? '');
        $this->assertSame($titre[1], $partage[1] ?? null, 'le titre partagé doit être celui de la page, pas une seconde version');
    }

    #[Test]
    public function lapercu_reprend_la_description_de_la_page(): void
    {
        $html = $this->rendre('page.html.twig', [
            'page' => ['titre' => 'Nos services', 'seoDescription' => 'Bouquets et compositions.', 'blocs' => []],
        ]);

        $this->assertStringContainsString('<meta name="description" content="Bouquets et compositions.">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Bouquets et compositions.">', $html);
    }

    #[Test]
    public function limage_dapercu_est_une_adresse_complete(): void
    {
        // Les réseaux vont chercher l'image depuis leurs propres serveurs : un
        // chemin relatif au site ne leur dit rien.
        $html = $this->rendre('page.html.twig', [
            'page' => ['titre' => 'Nos services', 'blocs' => [
                ['type' => 'hero', 'image' => ['path' => '/bandeau.jpg'], 'alt' => 'Un bouquet blanc'],
            ]],
        ]);

        $this->assertStringContainsString(
            '<meta property="og:image" content="https://exemple.fr/admin/storage/uploads/bandeau.jpg">',
            $html,
        );
        $this->assertStringContainsString('<meta property="og:image:alt" content="Un bouquet blanc">', $html);
    }

    #[Test]
    public function limage_de_partage_du_site_lemporte_sur_celle_de_la_page(): void
    {
        $html = $this->rendre('page.html.twig', [
            'site' => ['nom' => 'Atelier Bloom', 'imagePartage' => ['path' => '/partage.jpg']],
            'page' => ['titre' => 'Nos services', 'blocs' => [
                ['type' => 'hero', 'image' => ['path' => '/bandeau.jpg'], 'alt' => 'Un bouquet blanc'],
            ]],
        ]);

        // L'image du bandeau est présente dans le corps de la page : c'est la
        // balise d'aperçu qu'il faut lire, pas le HTML entier.
        preg_match('#<meta property="og:image" content="(.*?)">#', $html, $apercu);

        $this->assertSame('https://exemple.fr/admin/storage/uploads/partage.jpg', $apercu[1] ?? null);
    }

    #[Test]
    public function le_logo_sert_de_dernier_recours(): void
    {
        $html = $this->rendre('page.html.twig', [
            'site' => ['nom' => 'Atelier Bloom', 'logo' => ['path' => '/logo.png']],
        ]);

        $this->assertStringContainsString(
            '<meta property="og:image" content="https://exemple.fr/admin/storage/uploads/logo.png">',
            $html,
        );
    }

    #[Test]
    public function sans_aucune_image_lapercu_reste_valide(): void
    {
        $html = $this->rendre('page.html.twig');

        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
    }

    #[Test]
    public function une_actualite_est_annoncee_comme_telle(): void
    {
        $html = $this->rendre('actualite.html.twig', [
            'article' => [
                'titre' => 'Ouverture le dimanche',
                'resume' => 'À partir du mois prochain.',
                'date' => '2026-09-06',
                'image' => ['path' => '/dimanche.jpg'],
                'alt' => 'La devanture ouverte',
            ],
            'canonique' => 'https://exemple.fr/actualites/ouverture-le-dimanche',
        ]);

        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="article:published_time" content="2026-09-06">', $html);
    }

    #[Test]
    public function une_page_ordinaire_nest_pas_annoncee_comme_une_actualite(): void
    {
        $html = $this->rendre('page.html.twig');

        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringNotContainsString('article:published_time', $html);
    }
}
