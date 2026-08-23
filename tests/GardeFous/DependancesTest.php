<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Le sens des dépendances entre dossiers de src/.
 *
 * Il est décrit dans docs/architecture.md. Sans contrôle, la description s'en
 * écarte au premier ajout, et l'architecture n'existe plus que sur le papier.
 */
final class DependancesTest extends TestCase
{
    /** @return array<string, list<string>> */
    private function dependances(): array
    {
        $trouvees = [];

        foreach (glob(dirname(__DIR__, 2).'/src/*', GLOB_ONLYDIR) ?: [] as $dossier) {
            $nom = basename($dossier);
            $deps = [];

            foreach (glob("{$dossier}/*.php") ?: [] as $fichier) {
                preg_match_all('/^use App\\\\([A-Za-z]+)\\\\/m', (string) file_get_contents($fichier), $m);

                foreach ($m[1] as $dep) {
                    if ($dep !== $nom) {
                        $deps[$dep] = true;
                    }
                }
            }

            ksort($deps);
            $trouvees[$nom] = array_keys($deps);
        }

        return $trouvees;
    }

    #[Test]
    public function rien_ne_depend_des_controleurs(): void
    {
        foreach ($this->dependances() as $dossier => $deps) {
            $this->assertNotContains(
                'Controller',
                $deps,
                "{$dossier} dépend de Controller : une adresse ne doit jamais être une brique du site",
            );
        }
    }

    #[Test]
    public function les_dossiers_sans_dependance_le_restent(): void
    {
        // Ce sont les briques réutilisables : elles se testent seules, sans
        // rien monter autour. Leur donner une dépendance leur ferait perdre ça.
        $isoles = ['Accessibility', 'Cache', 'Cockpit', 'Http', 'Media', 'Seo'];

        $dependances = $this->dependances();

        foreach ($isoles as $dossier) {
            $this->assertSame(
                [],
                $dependances[$dossier] ?? [],
                "{$dossier} ne dépendait d’aucun autre dossier",
            );
        }
    }

    #[Test]
    public function laccess_au_contenu_passe_par_cockpit_et_par_lui_seul(): void
    {
        foreach ($this->dependances() as $dossier => $deps) {
            if ($dossier === 'Cockpit' || !in_array('Cockpit', $deps, true)) {
                continue;
            }

            $this->assertContains(
                $dossier,
                ['Content', 'Contact'],
                "{$dossier} parle directement à l’API : passer par Content ou Contact",
            );
        }
    }

    #[Test]
    public function aucune_dependance_circulaire(): void
    {
        $dependances = $this->dependances();

        foreach ($dependances as $dossier => $deps) {
            foreach ($deps as $dep) {
                $this->assertNotContains(
                    $dossier,
                    $dependances[$dep] ?? [],
                    "{$dossier} et {$dep} dépendent l’un de l’autre : ni l’un ni l’autre ne se lit seul",
                );
            }
        }
    }

    #[Test]
    public function le_document_darchitecture_cite_tous_les_dossiers(): void
    {
        $document = (string) file_get_contents(dirname(__DIR__, 2).'/docs/architecture.md');

        foreach (array_keys($this->dependances()) as $dossier) {
            $this->assertStringContainsString(
                "`{$dossier}/`",
                $document,
                "docs/architecture.md ne dit pas à quoi sert {$dossier}",
            );
        }
    }
}
