<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Content\Blocks;
use App\Media\MediaUrls;
use App\Media\Picture;
use App\View\SiteExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What ends up in the page: which sections are rendered at all, and what an
 * image weighs by the time it reaches the visitor.
 */
final class RenduTest extends TestCase
{
    // ── Sections ──────────────────────────────────────────────────────────

    private function blocks(): Blocks
    {
        return new Blocks(dirname(__DIR__, 2).'/templates/blocs');
    }

    #[Test]
    public function les_types_disponibles_sont_ceux_qui_ont_un_gabarit(): void
    {
        $disponibles = $this->blocks()->available();

        foreach (['hero', 'texte-image', 'contact', 'formulaire', 'temoignages'] as $type) {
            $this->assertContains($type, $disponibles);
        }
    }

    #[Test]
    #[DataProvider('typesRefuses')]
    public function un_type_sans_gabarit_nest_pas_rendu(mixed $type): void
    {
        $this->assertSame([], $this->blocks()->renderable([['type' => $type]]));
    }

    /** @return array<string, array{mixed}> */
    public static function typesRefuses(): array
    {
        return [
            'inconnu' => ['inexistant'],
            'remontée de dossier' => ['../partials/pied'],
            'chemin absolu' => ['/etc/passwd'],
            'vide' => [''],
            'absent' => [null],
            'pas une chaîne' => [['tableau']],
        ];
    }

    #[Test]
    public function les_sections_connues_sont_conservees_dans_lordre(): void
    {
        $blocs = [
            ['type' => 'hero', 'titre' => 'Un'],
            ['type' => 'inexistant'],
            ['type' => 'contact', 'titre' => 'Deux'],
        ];

        $rendus = $this->blocks()->renderable($blocs);

        $this->assertCount(2, $rendus);
        $this->assertSame('hero', $rendus[0]['type']);
        $this->assertSame('contact', $rendus[1]['type']);
    }

    // ── Images ────────────────────────────────────────────────────────────

    private function picture(): Picture
    {
        return new Picture(new MediaUrls('/medias', 'https://exemple.fr'));
    }

    /** @return array<string, mixed> */
    private function imageAvecCopies(): array
    {
        return [
            'path' => '/2026/08/photo.webp',
            'width' => 1600,
            'height' => 900,
            'variantes' => [
                'w480' => ['path' => 'variantes/a.webp', 'width' => 480],
                'w1440' => ['path' => 'variantes/c.webp', 'width' => 1440],
                'w960' => ['path' => 'variantes/b.webp', 'width' => 960],
            ],
        ];
    }

    #[Test]
    public function les_copies_sont_proposees_de_la_plus_petite_a_la_plus_grande(): void
    {
        $image = $this->picture()->from($this->imageAvecCopies());

        $this->assertSame(
            '/medias/variantes/a.webp 480w, '
            .'/medias/variantes/b.webp 960w, '
            .'/medias/variantes/c.webp 1440w',
            $image['srcset'],
        );
    }

    #[Test]
    public function le_repli_nest_jamais_loriginal_en_pleine_taille(): void
    {
        $image = $this->picture()->from($this->imageAvecCopies());

        $this->assertSame('/medias/variantes/b.webp', $image['src']);
        $this->assertStringNotContainsString('photo.webp', $image['src']);
    }

    #[Test]
    public function les_dimensions_sont_toujours_donnees(): void
    {
        $image = $this->picture()->from($this->imageAvecCopies());

        $this->assertSame(1600, $image['width']);
        $this->assertSame(900, $image['height']);
    }

    #[Test]
    public function une_image_sans_copies_reste_affichable(): void
    {
        $image = $this->picture()->from(['path' => '/2026/08/photo.webp', 'width' => 800, 'height' => 600]);

        $this->assertSame('/medias/2026/08/photo.webp', $image['src']);
        $this->assertSame('', $image['srcset']);
    }

    #[Test]
    public function sans_image_rien_nest_rendu(): void
    {
        $this->assertNull($this->picture()->from(null));
        $this->assertNull($this->picture()->from([]));
        $this->assertNull($this->picture()->from(['path' => '']));
    }

    // ── Téléphone ─────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('numeros')]
    public function un_numero_devient_composable(string $saisi, string $attendu): void
    {
        $extension = new SiteExtension($this->picture());

        $this->assertSame($attendu, $extension->telHref($saisi));
    }

    /** @return array<string, array{string, string}> */
    public static function numeros(): array
    {
        return [
            'français avec espaces' => ['02 40 00 00 00', '+33240000000'],
            'français collé' => ['0240000000', '+33240000000'],
            'avec points' => ['02.40.00.00.00', '+33240000000'],
            'déjà international' => ['+33240000000', '+33240000000'],
            'étranger' => ['+32 2 000 00 00', '+3220000000'],
        ];
    }
}
