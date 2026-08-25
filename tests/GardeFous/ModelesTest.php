<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Les modèles de contenu de Cockpit.
 *
 * Deux erreurs de configuration ne produisent ici ni exception, ni
 * avertissement : un type de champ que Cockpit ne connaît pas, et un gabarit
 * d’affichage écrit en Twig. L’administration dégrade silencieusement son
 * affichage, et c’est le client qui découvre l’écran incompréhensible.
 */
final class ModelesTest extends TestCase
{
    /** @return array<string, array<string, mixed>> chemin du champ => sa déclaration */
    private function champs(): array
    {
        $trouves = [];

        $parcourir = function (array $champs, string $chemin) use (&$parcourir, &$trouves): void {
            foreach ($champs as $champ) {
                if (!is_array($champ) || !is_string($champ['name'] ?? null)) {
                    continue;
                }

                $ici = $chemin.'/'.$champ['name'];
                $trouves[$ici] = $champ;

                if (is_array($champ['opts']['fields'] ?? null)) {
                    $parcourir($champ['opts']['fields'], $ici);
                }
            }
        };

        foreach (glob(dirname(__DIR__, 2).'/cockpit/models/*.model.php') ?: [] as $fichier) {
            $modele = include $fichier;

            if (is_array($modele['fields'] ?? null)) {
                $parcourir($modele['fields'], basename($fichier, '.model.php'));
            }
        }

        return $trouves;
    }

    /** @return array<string, string> chemin du champ => gabarit d’affichage */
    private function gabarits(): array
    {
        $trouves = [];

        foreach ($this->champs() as $chemin => $champ) {
            if (isset($champ['opts']['display']) && is_string($champ['opts']['display'])) {
                $trouves[$chemin] = $champ['opts']['display'];
            }
        }

        return $trouves;
    }

    /**
     * Les composants de champ que Cockpit enregistre côté navigateur.
     *
     * Ils se lisent dans l’installation, donc pas avant que
     * bin/install-cockpit.php ait tourné.
     *
     * @return list<string>|null
     */
    private function composantsEnregistres(): ?array
    {
        $racine = dirname(__DIR__, 2).'/public/admin';

        if (!is_dir($racine)) {
            return null;
        }

        $noms = [];
        $motifs = ['/modules/*/assets/*.js', '/modules/*/assets/js/*.js', '/addons/*/assets/js/*.js'];

        foreach ($motifs as $motif) {
            foreach (glob($racine.$motif) ?: [] as $fichier) {
                preg_match_all(
                    '/VueView\.component\(\s*[\'"]([a-zA-Z-]+)[\'"]/',
                    (string) file_get_contents($fichier),
                    $m,
                );

                foreach ($m[1] as $nom) {
                    // Le moteur de rendu résout les deux formes : avec et sans
                    // le préfixe « field- ».
                    $noms[$nom] = true;

                    if (str_starts_with($nom, 'field-')) {
                        $noms[substr($nom, 6)] = true;
                    }
                }
            }
        }

        return array_keys($noms);
    }

    /** @return list<string> Les types de section proposés au client. */
    private function typesProposes(): array
    {
        $champ = $this->champs()['pages/blocs/type'] ?? null;
        $valeurs = [];

        foreach ((is_array($champ) ? $champ['opts']['options'] ?? [] : []) as $option) {
            $valeur = is_array($option) ? ($option['value'] ?? '') : $option;

            if (is_string($valeur) && $valeur !== '') {
                $valeurs[] = $valeur;
            }
        }

        return $valeurs;
    }

    /** @return list<string> Les types de section qui ont un gabarit. */
    private function typesRendus(): array
    {
        $racine = dirname(__DIR__, 2);
        $trouves = [];

        // Les deux dossiers que le site consulte au rendu, dans le même ordre
        // que App\Content\Blocks.
        foreach (['/templates/blocs', '/templates-client/blocs'] as $dossier) {
            foreach (glob($racine.$dossier.'/*.html.twig') ?: [] as $gabarit) {
                $trouves[basename($gabarit, '.html.twig')] = true;
            }
        }

        return array_keys($trouves);
    }

    #[Test]
    public function le_parcours_des_modeles_trouve_des_gabarits(): void
    {
        // Sans ce contrôle, un parcours cassé rendrait les deux suivants verts
        // en n’examinant plus rien.
        $this->assertNotSame([], $this->gabarits(), 'aucun gabarit d’affichage trouvé dans cockpit/models/');
    }

    #[Test]
    public function les_types_de_section_sont_trouves(): void
    {
        // Un parcours cassé rendrait les trois contrôles suivants verts en
        // ne comparant plus que des listes vides.
        $this->assertNotSame([], $this->typesProposes(), 'aucun type de section trouvé dans pages.model.php');
        $this->assertNotSame([], $this->typesRendus(), 'aucun gabarit de section trouvé dans templates/blocs/');
    }

    #[Test]
    public function chaque_type_propose_au_client_a_son_gabarit(): void
    {
        foreach ($this->typesProposes() as $type) {
            $this->assertContains(
                $type,
                $this->typesRendus(),
                "« {$type} » est proposé dans l’administration mais aucun gabarit ne le rend : le client ajoute une section qui n’apparaît pas sur sa page",
            );
        }
    }

    #[Test]
    public function chaque_gabarit_est_proposable_au_client(): void
    {
        foreach ($this->typesRendus() as $type) {
            $this->assertContains(
                $type,
                $this->typesProposes(),
                "le gabarit « {$type} » existe mais n’est proposé nulle part dans l’administration : personne ne peut s’en servir",
            );
        }
    }

    #[Test]
    public function les_conditions_ne_citent_que_des_types_existants(): void
    {
        // Une condition qui nomme un type disparu ou mal orthographié cache
        // ses champs pour toujours, sans rien dire.
        $proposes = $this->typesProposes();

        foreach ($this->champs() as $chemin => $champ) {

            $condition = $champ['condition'] ?? null;

            if (!is_string($condition) || !str_contains($condition, 'data.type')) {
                continue;
            }

            preg_match_all("/'([^']+)'/", $condition, $cites);

            foreach ($cites[1] as $type) {
                $this->assertContains(
                    $type,
                    $proposes,
                    "{$chemin} n’apparaît que pour le type « {$type} », qui n’est proposé nulle part",
                );
            }
        }
    }

    #[Test]
    public function chaque_type_de_champ_correspond_a_un_composant(): void
    {
        $composants = $this->composantsEnregistres();

        if ($composants === null) {
            $this->markTestSkipped('Cockpit n’est pas installé : lancer php bin/install-cockpit.php');
        }

        // Si la lecture des composants cassait, la boucle ci-dessous
        // n’examinerait plus rien et le test resterait vert.
        $this->assertContains('wysiwyg', $composants, 'la lecture des composants de Cockpit ne renvoie plus rien d’attendu');

        foreach ($this->champs() as $chemin => $champ) {
            $type = $champ['type'] ?? null;

            if (!is_string($type)) {
                continue;
            }

            $this->assertContains(
                $type,
                $composants,
                "{$chemin} : le type « {$type} » n’est enregistré par aucun composant — l’administration retombe sur un éditeur d’objet brut",
            );
        }
    }

    #[Test]
    public function les_gabarits_daffichage_sont_du_javascript(): void
    {
        foreach ($this->gabarits() as $champ => $gabarit) {
            $this->assertStringNotContainsString(
                '{{',
                $gabarit,
                "{$champ} : « {$gabarit} » est du Twig, l’administration attend du JavaScript — écrire \${…}",
            );

            $this->assertStringContainsString(
                '${',
                $gabarit,
                "{$champ} : « {$gabarit} » ne contient rien à interpoler, il s’affichera tel quel",
            );
        }
    }

    #[Test]
    public function chaque_gabarit_daffichage_prevoit_un_repli(): void
    {
        foreach ($this->gabarits() as $champ => $gabarit) {
            $this->assertStringContainsString(
                '||',
                $gabarit,
                "{$champ} : sans repli, un champ vide affiche « undefined » dans la liste",
            );
        }
    }

    #[Test]
    public function aucun_gabarit_daffichage_nutilise_de_guillemets_doubles(): void
    {
        // Le libellé d’un contentItemLink est injecté dans un attribut HTML
        // délimité par des guillemets doubles : un seul le referme trop tôt.
        foreach ($this->gabarits() as $champ => $gabarit) {
            $this->assertStringNotContainsString(
                '"',
                $gabarit,
                "{$champ} : « {$gabarit} » casserait l’attribut HTML, écrire le repli entre guillemets simples",
            );
        }
    }
}
