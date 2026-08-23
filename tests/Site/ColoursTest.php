<?php

declare(strict_types=1);

namespace Tests\Site;

use App\View\Colours;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A colour too pale to read must never reach the site, whatever the customer
 * picked. These are the exact values the audit that started this project
 * measured on the earlier prototype.
 */
final class ColoursTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/couleurs-'.bin2hex(random_bytes(6)).'.css';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    #[Test]
    #[DataProvider('couleursRefusees')]
    public function une_couleur_trop_pale_est_ecartee(string $couleur): void
    {
        $css = (new Colours($this->file))->css(['couleurPrincipale' => $couleur]);

        $this->assertStringNotContainsString(
            strtolower($couleur),
            strtolower($css),
            "La couleur {$couleur} n'atteint pas 4,5:1 et ne doit pas être appliquée",
        );
    }

    /** @return array<string, array{string}> */
    public static function couleursRefusees(): array
    {
        return [
            'le rose du prototype, mesuré à 3,53:1' => ['#EC4899'],
            'un gris clair' => ['#9aa0a6'],
            'un jaune sur blanc' => ['#ffd400'],
        ];
    }

    #[Test]
    #[DataProvider('couleursAcceptees')]
    public function une_couleur_lisible_est_appliquee(string $couleur): void
    {
        $css = (new Colours($this->file))->css(['couleurPrincipale' => $couleur]);

        $this->assertStringContainsString(strtolower($couleur), strtolower($css));
    }

    /** @return array<string, array{string}> */
    public static function couleursAcceptees(): array
    {
        return [
            'la couleur par défaut du site' => ['#8b1f5e'],
            'un vert foncé' => ['#1b4d3e'],
            'du noir' => ['#000000'],
        ];
    }

    #[Test]
    public function une_valeur_qui_nest_pas_une_couleur_est_ignoree(): void
    {
        $css = (new Colours($this->file))->css(['couleurPrincipale' => 'rouge vif']);

        $this->assertStringContainsString('#8b1f5e', $css, 'La couleur par défaut doit prendre le relais');
    }

    #[Test]
    public function le_contraste_est_calcule_comme_le_veut_la_norme(): void
    {
        // Valeurs de référence : noir sur blanc vaut 21:1, blanc sur blanc 1:1.
        $this->assertEqualsWithDelta(21.0, Colours::contrast('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, Colours::contrast('#ffffff', '#ffffff'), 0.01);

        // Les trois échecs relevés sur le prototype d'origine.
        $this->assertEqualsWithDelta(3.53, Colours::contrast('#EC4899', '#ffffff'), 0.02);
    }

    #[Test]
    public function la_feuille_est_ecrite_puis_oubliee_a_la_publication(): void
    {
        $colours = new Colours($this->file);

        $colours->ensure(['couleurPrincipale' => '#1b4d3e']);
        $this->assertFileExists($this->file);

        $colours->forget();
        $this->assertFileDoesNotExist($this->file, 'Une publication doit effacer la feuille pour qu’elle soit réécrite');
    }
}
