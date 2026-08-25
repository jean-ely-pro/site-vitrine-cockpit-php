<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use EditorGuards\Headings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Hiding buttons in the editor protects nothing: text pasted from a word
 * processor brings its own outline. The correction happens when content is
 * saved, whatever route it came in by.
 */
final class HeadingsTest extends TestCase
{
    #[Test]
    #[DataProvider('niveaux')]
    public function les_niveaux_sont_ramenes_dans_la_plage(string $donne, string $attendu): void
    {
        $this->assertSame($attendu, Headings::normalise($donne));
    }

    /** @return array<string, array{string, string}> */
    public static function niveaux(): array
    {
        return [
            'un titre de niveau un devient deux' => ['<h1>Collé</h1>', '<h2>Collé</h2>'],
            'quatre devient trois' => ['<h4>Trop loin</h4>', '<h3>Trop loin</h3>'],
            'six devient trois' => ['<h6>Bien trop loin</h6>', '<h3>Bien trop loin</h3>'],
            'deux et trois ne bougent pas' => ['<h2>A</h2><h3>B</h3>', '<h2>A</h2><h3>B</h3>'],
            'les attributs sont conservés' => ['<h1 class="x">T</h1>', '<h2 class="x">T</h2>'],
            'majuscules acceptées' => ['<H1>T</H1>', '<h2>T</h2>'],
            'du texte sans titre est inchangé' => ['<p>Rien à corriger</p>', '<p>Rien à corriger</p>'],
            'texte vide' => ['', ''],
        ];
    }

    #[Test]
    public function le_reste_du_contenu_nest_pas_touche(): void
    {
        $donne = '<h1>Titre</h1><p>Un <strong>mot</strong> et un <a href="/x">lien</a>.</p>';
        $attendu = '<h2>Titre</h2><p>Un <strong>mot</strong> et un <a href="/x">lien</a>.</p>';

        $this->assertSame($attendu, Headings::normalise($donne));
    }

    #[Test]
    public function les_sections_imbriquees_sont_traitees(): void
    {
        $champs = [
            ['name' => 'blocs', 'type' => 'set', 'multiple' => true, 'opts' => [
                'fields' => [['name' => 'texte', 'type' => 'wysiwyg']],
            ]],
        ];

        $donnees = ['blocs' => [
            ['texte' => '<h1>Dans une section</h1>'],
            ['texte' => '<h4>Dans une autre</h4>'],
        ]];

        $corrige = Headings::inFields($champs, $donnees);

        $this->assertSame('<h2>Dans une section</h2>', $corrige['blocs'][0]['texte']);
        $this->assertSame('<h3>Dans une autre</h3>', $corrige['blocs'][1]['texte']);
    }

    #[Test]
    public function un_champ_absent_ne_provoque_rien(): void
    {
        $champs = [['name' => 'texte', 'type' => 'wysiwyg']];

        $this->assertSame(['autre' => 'valeur'], Headings::inFields($champs, ['autre' => 'valeur']));
    }
}
