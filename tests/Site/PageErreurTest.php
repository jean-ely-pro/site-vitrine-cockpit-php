<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Media\MediaUrls;
use App\Media\Picture;
use App\Twig\SiteExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * La page servie quand une adresse n'existe pas.
 *
 * Elle passe par le même gabarit que les autres, donc tout ce qui est ajouté
 * aux pages l'atteint sans qu'on y pense — y compris ce qui n'a rien à y faire.
 */
final class PageErreurTest extends TestCase
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

        $media = new MediaUrls('/admin/storage/uploads', 'https://exemple.fr');
        $twig->addExtension(new SiteExtension(new Picture($media)));
        $twig->addGlobal('accueilSlug', 'accueil');

        return $twig;
    }

    /** @param array<string, mixed> $extra */
    private function rendre(array $extra = []): string
    {
        return $this->twig()->render('404.html.twig', array_merge([
            'site' => ['nom' => 'Atelier Bloom', 'email' => 'contact@exemple.fr'],
            'menu' => [['libelle' => 'Accueil', 'slug' => 'accueil']],
            'afficherActualites' => true,
            'jsonld' => '',
        ], $extra));
    }

    #[Test]
    public function la_page_derreur_ne_porte_pas_de_donnees_structurees(): void
    {
        $this->assertStringNotContainsString(
            'ld+json',
            $this->rendre(),
            'Décrire l’établissement sur une page qui n’existe pas n’apprend rien à un moteur',
        );
    }

    #[Test]
    public function la_page_derreur_demande_a_ne_pas_etre_indexee(): void
    {
        $this->assertStringContainsString('name="robots" content="noindex"', $this->rendre());
    }

    #[Test]
    public function la_page_derreur_garde_un_menu_complet(): void
    {
        $html = $this->rendre();

        $this->assertStringContainsString('href="/"', $html);
        $this->assertStringContainsString(
            'href="/actualites"',
            $html,
            'Le menu doit être le même partout : une page d’erreur amputée déroute le visiteur',
        );
    }

    #[Test]
    public function la_page_derreur_propose_un_retour(): void
    {
        $this->assertMatchesRegularExpression('#<a href="/">[^<]+</a>#', $this->rendre());
    }

    #[Test]
    public function la_page_derreur_tient_sans_contenu_du_tout(): void
    {
        // Ce que voit le visiteur si le service de contenu ne répond pas.
        $html = $this->twig()->render('404.html.twig', ['site' => [], 'menu' => []]);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringNotContainsString('ld+json', $html);
    }
}
