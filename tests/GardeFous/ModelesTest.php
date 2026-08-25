<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Les gabarits d’affichage des modèles Cockpit.
 *
 * L’administration évalue « opts.display » comme un gabarit JavaScript, pas
 * comme du Twig. Une chaîne écrite en {{ }} ne produit ni erreur ni
 * avertissement : elle s’affiche telle quelle dans les listes du client.
 */
final class ModelesTest extends TestCase
{
    /** @return array<string, string> chemin du champ => gabarit d’affichage */
    private function gabarits(): array
    {
        $trouves = [];

        $parcourir = function (array $champs, string $chemin) use (&$parcourir, &$trouves): void {
            foreach ($champs as $champ) {
                if (!is_array($champ) || !is_string($champ['name'] ?? null)) {
                    continue;
                }

                $ici = $chemin.'/'.$champ['name'];

                if (isset($champ['opts']['display']) && is_string($champ['opts']['display'])) {
                    $trouves[$ici] = $champ['opts']['display'];
                }

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

    #[Test]
    public function le_parcours_des_modeles_trouve_des_gabarits(): void
    {
        // Sans ce contrôle, un parcours cassé rendrait les deux suivants verts
        // en n’examinant plus rien.
        $this->assertNotSame([], $this->gabarits(), 'aucun gabarit d’affichage trouvé dans cockpit/models/');
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
