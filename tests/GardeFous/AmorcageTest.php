<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Les fichiers chargés par Cockpit au démarrage.
 *
 * Ils ne passent par aucun autochargement : ils désignent des classes du site
 * par leur nom et par leur chemin, à la main. Un renommage dans src/ les casse
 * en silence, et l'administration ne démarre plus — ce qui ne se voit qu'en
 * l'ouvrant.
 */
final class AmorcageTest extends TestCase
{
    /** @return list<array{string}> */
    public static function fichiersDamorcage(): array
    {
        $racine = dirname(__DIR__, 2);

        $fichiers = array_merge(
            [$racine.'/cockpit/bootstrap.php'],
            glob($racine.'/cockpit/addons/*/bootstrap.php') ?: [],
            glob($racine.'/cockpit/addons/*/admin.php') ?: [],
        );

        return array_map(static fn (string $f): array => [$f], array_values(array_filter($fichiers, 'is_file')));
    }

    #[Test]
    #[DataProvider('fichiersDamorcage')]
    public function les_classes_du_site_citees_existent(string $fichier): void
    {
        $source = (string) file_get_contents($fichier);
        $racine = dirname(__DIR__, 2);

        preg_match_all('/App\\\\([A-Za-z]+\\\\)*[A-Z][A-Za-z]*/', $source, $trouvees);

        // Cockpit utilise lui aussi le préfixe App\ : ses propres classes sont
        // citées dans ces fichiers et ne vivent pas dans src/.
        $classes = array_filter(
            array_unique($trouvees[0] ?? []),
            static fn (string $c): bool => !str_starts_with($c, 'App\Exception\\'),
        );

        if ($classes === []) {
            $this->assertTrue(true, 'Ce fichier ne cite aucune classe du site');

            return;
        }

        foreach ($classes as $classe) {

            $chemin = $racine.'/src/'.str_replace(['App\\', '\\'], ['', '/'], $classe).'.php';

            $this->assertFileExists(
                $chemin,
                basename(dirname($fichier)).'/'.basename($fichier)." cite {$classe}, qui n’existe pas",
            );
        }
    }

    #[Test]
    #[DataProvider('fichiersDamorcage')]
    public function les_chemins_de_fichiers_cites_existent(string $fichier): void
    {
        $source = (string) file_get_contents($fichier);
        $racine = dirname(__DIR__, 2);

        preg_match_all('#["\']?\{?\$root\}?/(src/[A-Za-z/]+\.php)#', $source, $trouves);
        preg_match_all("#/\.\./(src/[A-Za-z/]+\.php)#", $source, $relatifs);

        $chemins = array_unique(array_merge($trouves[1] ?? [], $relatifs[1] ?? []));

        foreach ($chemins as $chemin) {
            $this->assertFileExists(
                "{$racine}/{$chemin}",
                basename($fichier)." charge {$chemin}, qui n’existe pas",
            );
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function le_fichier_de_purge_designe_bien_les_deux_classes_attendues(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/cockpit/bootstrap.php');

        $this->assertStringContainsString('PageCache', $source, 'Sans elle, le cache n’est jamais vidé');
        $this->assertStringContainsString('Colours', $source, 'Sans elle, un changement de couleur reste invisible');
    }
}
