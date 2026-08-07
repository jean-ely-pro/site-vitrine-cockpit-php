<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Http\Route;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Les adresses que le site se réserve. Une page qui en prendrait une ne serait
 * jamais servie, sans que rien à l'écran ne l'explique.
 */
final class RoutageTest extends TestCase
{
    #[Test]
    public function les_adresses_reservees_sont_declarees_une_seule_fois(): void
    {
        $this->assertSame(
            array_values(array_unique(Route::RESERVED)),
            Route::RESERVED,
            'Une adresse déclarée deux fois signale deux endroits à tenir à jour',
        );
    }

    #[Test]
    public function les_adresses_du_site_figurent_parmi_les_reservees(): void
    {
        $this->assertContains(Route::CONTACT, Route::RESERVED);
        $this->assertContains(Route::NEWS, Route::RESERVED);

        foreach (array_keys(Route::LEGAL) as $legal) {
            $this->assertContains($legal, Route::RESERVED, "{$legal} est servie par le site");
        }
    }

    #[Test]
    public function chaque_page_legale_a_son_gabarit(): void
    {
        foreach (Route::LEGAL as $adresse => $gabarit) {
            $this->assertFileExists(
                dirname(__DIR__, 2)."/templates/{$gabarit}",
                "{$adresse} n’a pas de gabarit",
            );
        }
    }

    #[Test]
    public function les_adresses_commencent_par_une_barre_oblique(): void
    {
        foreach (Route::RESERVED as $adresse) {
            $this->assertStringStartsWith('/', $adresse);
        }
    }

    #[Test]
    public function le_garde_fou_de_ladministration_couvre_les_memes_adresses(): void
    {
        $addon = file_get_contents(dirname(__DIR__, 2).'/cockpit/addons/EditorGuards/bootstrap.php');

        preg_match("/RESERVED_PAGE_SLUGS = \[([^\]]*)\]/", (string) $addon, $trouve);
        preg_match_all("/'([^']+)'/", $trouve[1] ?? '', $slugs);

        // Les pages n'ont pas de barre oblique, les adresses du site en ont une.
        $attendus = array_map(
            static fn (string $adresse): string => trim($adresse, '/'),
            [Route::CONTACT, Route::NEWS, ...array_keys(Route::LEGAL)],
        );

        foreach ($attendus as $attendu) {
            $this->assertContains(
                $attendu,
                $slugs[1] ?? [],
                "L’administration doit refuser une page nommée « {$attendu} »",
            );
        }
    }
}
