<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Content\Blocks;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * What allows an update to be merged without touching the design: a site keeps
 * its own templates and blocks beside the delivered ones, never inside them.
 *
 * If this breaks, every site built on this base starts producing conflicts on
 * its stylesheet and its layout at the next update.
 */
final class SeparationTest extends TestCase
{
    private string $socle;
    private string $client;

    protected function setUp(): void
    {
        $racine = sys_get_temp_dir().'/separation-'.bin2hex(random_bytes(6));

        $this->socle = "{$racine}/templates";
        $this->client = "{$racine}/templates-client";

        mkdir("{$this->socle}/blocs", 0o755, true);
        mkdir("{$this->client}/blocs", 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (['blocs', ''] as $sous) {
            foreach ([$this->socle, $this->client] as $dossier) {
                foreach (glob(rtrim("{$dossier}/{$sous}", '/').'/*') ?: [] as $f) {
                    if (is_file($f)) {
                        unlink($f);
                    }
                }
            }
        }

        foreach (["{$this->socle}/blocs", "{$this->client}/blocs", $this->socle, $this->client, dirname($this->socle)] as $d) {
            @rmdir($d);
        }
    }

    #[Test]
    public function un_bloc_propre_au_site_est_reconnu(): void
    {
        file_put_contents("{$this->socle}/blocs/hero.html.twig", 'socle');
        file_put_contents("{$this->client}/blocs/galerie.html.twig", 'client');

        $types = (new Blocks("{$this->socle}/blocs", "{$this->client}/blocs"))->available();

        $this->assertContains('hero', $types);
        $this->assertContains('galerie', $types, 'Un bloc déposé côté site doit exister sans autre déclaration');
    }

    #[Test]
    public function un_bloc_propre_au_site_est_rendu(): void
    {
        file_put_contents("{$this->client}/blocs/galerie.html.twig", 'client');

        $blocks = new Blocks("{$this->socle}/blocs", "{$this->client}/blocs");

        $this->assertSame(
            [['type' => 'galerie']],
            $blocks->renderable([['type' => 'galerie'], ['type' => 'inexistant']]),
        );
    }

    #[Test]
    public function un_type_present_des_deux_cotes_nest_compte_quune_fois(): void
    {
        file_put_contents("{$this->socle}/blocs/hero.html.twig", 'socle');
        file_put_contents("{$this->client}/blocs/hero.html.twig", 'client');

        $types = (new Blocks("{$this->socle}/blocs", "{$this->client}/blocs"))->available();

        $this->assertSame(['hero'], $types);
    }

    #[Test]
    public function un_gabarit_du_site_lemporte_sur_celui_livre(): void
    {
        file_put_contents("{$this->socle}/pied.html.twig", 'pied livré');
        file_put_contents("{$this->client}/pied.html.twig", 'pied du site');

        // Même ordre que dans public/index.php : le dossier du site en premier.
        $twig = new Environment(new FilesystemLoader([$this->client, $this->socle]));

        $this->assertSame('pied du site', $twig->render('pied.html.twig'));
    }

    #[Test]
    public function un_gabarit_livre_reste_utilise_sil_nest_pas_remplace(): void
    {
        file_put_contents("{$this->socle}/pied.html.twig", 'pied livré');

        $twig = new Environment(new FilesystemLoader([$this->client, $this->socle]));

        $this->assertSame('pied livré', $twig->render('pied.html.twig'));
    }

    #[Test]
    public function un_dossier_client_absent_ne_gene_pas(): void
    {
        file_put_contents("{$this->socle}/blocs/hero.html.twig", 'socle');

        $blocks = new Blocks("{$this->socle}/blocs", "{$this->socle}/../inexistant/blocs");

        $this->assertSame(['hero'], $blocks->available());
    }

    #[Test]
    public function le_site_reel_declare_bien_ses_deux_dossiers(): void
    {
        $entree = file_get_contents(dirname(__DIR__, 2).'/public/index.php');

        $this->assertStringContainsString('templates-client', $entree);
        $this->assertMatchesRegularExpression(
            '/templates-client.*templates["\']/s',
            $entree,
            'Le dossier du site doit précéder celui livré, sinon il ne l’emporte pas',
        );
    }
}
