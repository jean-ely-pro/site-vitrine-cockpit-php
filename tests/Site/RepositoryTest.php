<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Cockpit\ContentSource;
use App\Content\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The single rule that keeps unfinished work off the site: only what carries
 * the published state is ever asked for. If it were to slip, a draft would go
 * online without anyone doing anything.
 */
final class RepositoryTest extends TestCase
{
    /** Records what was asked of the content service, and answers on cue. */
    private function source(array $reponses = []): ContentSource
    {
        return new class($reponses) implements ContentSource {

            /** @var list<array{string, array<string, mixed>}> */
            public array $demandes = [];

            public function __construct(private array $reponses)
            {
            }

            public function singleton(string $model, bool $populate = false): ?array
            {
                $this->demandes[] = [$model, []];

                return $this->reponses[$model] ?? null;
            }

            public function item(string $model, array $filter, bool $populate = false): ?array
            {
                $this->demandes[] = [$model, $filter];

                return $this->reponses[$model] ?? null;
            }

            public function items(string $model, array $filter = [], array $sort = [], ?int $limit = null): array
            {
                $this->demandes[] = [$model, $filter];

                return $this->reponses[$model] ?? [];
            }

            public function create(string $model, array $data): ?array
            {
                return $data;
            }
        };
    }

    #[Test]
    public function une_page_nest_demandee_que_publiee(): void
    {
        $source = $this->source();
        (new Repository($source))->page('services');

        [$modele, $filtre] = $source->demandes[0];

        $this->assertSame('pages', $modele);
        $this->assertSame('services', $filtre['slug']);
        $this->assertSame(1, $filtre['_state'], 'Sans cet état, un brouillon serait servi');
    }

    #[Test]
    public function une_actualite_nest_demandee_que_publiee(): void
    {
        $source = $this->source();
        (new Repository($source))->article('portes-ouvertes');

        [$modele, $filtre] = $source->demandes[0];

        $this->assertSame('articles', $modele);
        $this->assertSame(1, $filtre['_state']);
    }

    #[Test]
    public function les_listes_ne_contiennent_que_du_publie(): void
    {
        $source = $this->source();
        $repository = new Repository($source);

        $repository->pages();
        $repository->articles();

        foreach ($source->demandes as [$modele, $filtre]) {
            $this->assertSame(1, $filtre['_state'], "La liste « {$modele} » doit écarter les brouillons");
        }
    }

    #[Test]
    public function un_slug_vide_ne_declenche_aucune_demande(): void
    {
        $source = $this->source();
        $repository = new Repository($source);

        $this->assertNull($repository->page(''));
        $this->assertNull($repository->article(''));
        $this->assertSame([], $source->demandes);
    }

    #[Test]
    public function le_menu_ecarte_les_entrees_vers_une_page_retiree(): void
    {
        $source = $this->source([
            'menu' => ['entrees' => [
                ['libelle' => 'Accueil', 'page' => ['slug' => 'accueil', 'titre' => 'Accueil']],
                ['libelle' => 'Fantôme', 'page' => ['slug' => 'supprimee', 'titre' => 'Supprimée']],
            ]],
            'pages' => [['slug' => 'accueil', 'titre' => 'Accueil']],
        ]);

        $menu = (new Repository($source))->menu();

        $this->assertCount(1, $menu, 'Une entrée vers une page dépubliée doit disparaître');
        $this->assertSame('accueil', $menu[0]['slug']);
    }

    #[Test]
    public function une_entree_sans_libelle_reprend_le_titre_de_la_page(): void
    {
        $source = $this->source([
            'menu' => ['entrees' => [
                ['libelle' => '', 'page' => ['slug' => 'services', 'titre' => 'Nos services']],
            ]],
            'pages' => [['slug' => 'services', 'titre' => 'Nos services']],
        ]);

        $this->assertSame('Nos services', (new Repository($source))->menu()[0]['libelle']);
    }

    #[Test]
    public function le_contenu_nest_demande_quune_fois_par_page(): void
    {
        $source = $this->source(['settings' => ['nom' => 'Atelier Bloom']]);
        $repository = new Repository($source);

        $repository->settings();
        $repository->settings();
        $repository->settings();

        $this->assertCount(1, $source->demandes, 'Chaque appel supplémentaire coûterait une requête à Cockpit');
    }
}
