<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The bridge between the public site and the admin. Its one promise to visitors:
 * nothing — no button, and no address of the admin in any public file.
 */
final class PasserelleTest extends TestCase
{
    private const RACINE = __DIR__.'/../..';

    private function fichier(string $chemin): string
    {
        return (string) file_get_contents(self::RACINE.'/'.$chemin);
    }

    #[Test]
    public function chaque_page_charge_la_passerelle(): void
    {
        $this->assertStringContainsString('src="/assets/js/passerelle.js" defer', $this->fichier('templates/base.html.twig'));
    }

    #[Test]
    public function aucun_fichier_public_ne_donne_l_adresse_de_l_administration(): void
    {
        foreach (['public/assets/js/passerelle.js', 'public/assets/css/passerelle.css'] as $chemin) {
            $this->assertDoesNotMatchRegularExpression('#/admin\b#', $this->fichier($chemin), "{$chemin} révélerait l’adresse de l’administration");
        }
    }

    #[Test]
    public function sans_cookie_le_site_n_affiche_rien(): void
    {
        $script = $this->fichier('public/assets/js/passerelle.js');

        $this->assertMatchesRegularExpression('/if \(!brut\) \{\s*return;/', $script, 'sans le cookie de l’administration, le script doit s’arrêter tout de suite');
    }

    #[Test]
    public function le_cookie_est_retire_a_la_deconnexion(): void
    {
        $amorce = $this->fichier('cockpit/addons/Passerelle/bootstrap.php');

        $this->assertStringContainsString("'expires' => time() - 3600", $amorce, 'sans session, le cookie doit être effacé');
        $this->assertStringContainsString("'samesite' => 'Lax'", $amorce);
    }

    #[Test]
    public function la_pastille_ne_s_affiche_que_si_l_addon_confirme_la_connexion(): void
    {
        $script = $this->fichier('public/assets/js/passerelle.js');

        // L'addon répond lui-même : désactivé ou retiré, il ne répond plus, et la pastille disparaît.
        $this->assertStringContainsString("'passerelle/etat'", $script);
        $this->assertStringContainsString('etat.connecte === true', $script, 'la pastille ne doit s’afficher que sur un oui');
        $this->assertStringContainsString('.catch(oublier)', $script, 'sans réponse, la pastille ne doit pas s’afficher');
    }

    #[Test]
    public function l_etat_ne_repond_qu_a_l_adresse_du_site_et_ne_prolonge_pas_la_session(): void
    {
        $amorce = $this->fichier('cockpit/addons/Passerelle/bootstrap.php');

        $this->assertStringContainsString("'/passerelle/etat'", $amorce);
        $this->assertMatchesRegularExpression("/HTTP_ORIGIN'\\] \\?\\? ''\\) === \\\$origine/", $amorce, 'seule l’adresse du site (SITE_URL) peut poser la question depuis une autre origine');
        $this->assertStringContainsString("'Access-Control-Allow-Credentials'", $amorce);
        $this->assertMatchesRegularExpression('/return false;\s*\}, 1001\);/', $amorce, 'la question ne doit pas prolonger la session');
    }
}
