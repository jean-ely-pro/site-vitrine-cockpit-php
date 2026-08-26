<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Cockpit\ContentSource;
use App\Contact\ContactForm;
use App\Contact\SpamGuard;
use App\Content\Repository;
use App\Controller\NewsAction;
use App\Media\MediaUrls;
use App\Media\Picture;
use App\View\Colours;
use App\View\SiteExtension;
use App\View\ViewContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Ce qu’une page reçoit avant d’être rendue.
 *
 * Deux valeurs y sont tirées de la même donnée sans vouloir dire la même
 * chose : le slug, qui allume l’entrée de menu, et l’adresse revendiquée. Les
 * confondre ne se voit pas — la page se rend parfaitement — mais fait
 * revendiquer à chaque fiche d’actualité l’adresse de la liste, et aucune
 * n’est alors indexée séparément.
 *
 * Le contexte est monté ici avec ses vraies dépendances : c’est son câblage
 * qui est en cause, pas le calcul isolé qu’il appelle.
 */
final class ContextePageTest extends TestCase
{
    private const ARTICLE = [
        'titre' => 'Pivoines de saison',
        'slug' => 'pivoines-de-saison',
        'date' => '2026-05-12',
    ];

    private string $couleurs = '';

    protected function setUp(): void
    {
        $this->couleurs = sys_get_temp_dir().'/couleurs-'.bin2hex(random_bytes(6)).'.css';
    }

    protected function tearDown(): void
    {
        if (is_file($this->couleurs)) {
            unlink($this->couleurs);
        }
    }

    // ── L’adresse revendiquée ─────────────────────────────────────────────

    #[Test]
    public function une_fiche_dactualite_revendique_sa_propre_adresse(): void
    {
        $html = $this->action()->item(self::ARTICLE['slug'])?->body ?? '';

        $this->assertStringContainsString(
            '<link rel="canonical" href="https://exemple.fr/actualites/pivoines-de-saison">',
            $html,
            'une fiche qui revendique l’adresse de la liste passe pour un doublon',
        );
    }

    #[Test]
    public function une_fiche_dactualite_laisse_le_menu_sur_actualites(): void
    {
        // La contrepartie : l’adresse revendiquée change d’une fiche à
        // l’autre, l’entrée de menu allumée non. C’est pour cela que le slug
        // ne peut pas simplement devenir celui de la fiche.
        $html = $this->action()->item(self::ARTICLE['slug'])?->body ?? '';

        $this->assertStringContainsString('<a href="/actualites" aria-current="page">', $html);
    }

    #[Test]
    public function la_liste_dactualites_revendique_la_sienne(): void
    {
        $html = $this->action()->list()->body;

        $this->assertStringContainsString(
            '<link rel="canonical" href="https://exemple.fr/actualites">',
            $html,
        );
    }

    #[Test]
    public function une_page_ordinaire_revendique_son_slug(): void
    {
        $contexte = $this->contexte()->forPage('services');

        $this->assertSame('https://exemple.fr/services', $contexte['canonique']);
        $this->assertSame('services', $contexte['slug']);
    }

    #[Test]
    public function laccueil_revendique_la_racine_et_non_son_slug(): void
    {
        // L’accueil est stocké sous « accueil » mais répond sur « / » : c’est
        // le même écart, dans l’autre sens.
        $contexte = $this->contexte()->forPage('accueil');

        $this->assertSame('https://exemple.fr/', $contexte['canonique']);
        $this->assertSame('accueil', $contexte['slug']);
    }

    // ── Montage ───────────────────────────────────────────────────────────

    private function action(): NewsAction
    {
        return new NewsAction($this->content(), $this->twig(), $this->contexte());
    }

    private function contexte(): ViewContext
    {
        return new ViewContext(
            $this->content(),
            new ContactForm(null, new SpamGuard(sys_get_temp_dir(), 'secret-de-test')),
            $this->media(),
            new Colours($this->couleurs),
            'https://exemple.fr',
        );
    }

    private function content(): Repository
    {
        return new Repository(new class(self::ARTICLE) implements ContentSource {

            /** @param array<string, mixed> $article */
            public function __construct(private readonly array $article)
            {
            }

            public function singleton(string $model, bool $populate = false): ?array
            {
                return $model === 'settings' ? ['nom' => 'Atelier Bloom'] : null;
            }

            public function item(string $model, array $filter, bool $populate = false): ?array
            {
                return $model === 'articles' && ($filter['slug'] ?? null) === $this->article['slug']
                    ? $this->article
                    : null;
            }

            public function items(string $model, array $filter = [], array $sort = [], ?int $limit = null): array
            {
                return $model === 'articles' ? [$this->article] : [];
            }

            public function create(string $model, array $data): ?array
            {
                return $data;
            }
        });
    }

    private function media(): MediaUrls
    {
        return new MediaUrls('/admin/storage/uploads', 'https://exemple.fr');
    }

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

        $twig->addExtension(new SiteExtension(new Picture($this->media())));
        $twig->addGlobal('accueilSlug', 'accueil');

        return $twig;
    }
}
