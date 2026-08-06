<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use PasswordPolicy\Policy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The rule that protects the administration. Cockpit has none of its own, so
 * everything here is what stands between an account and a guess.
 */
final class PasswordPolicyTest extends TestCase
{
    #[Test]
    #[DataProvider('motsDePasseRefuses')]
    public function un_mot_de_passe_faible_est_refuse(string $motDePasse): void
    {
        $this->assertNotNull(
            Policy::refusal($motDePasse, ['admin', 'admin@exemple.fr']),
            "« {$motDePasse} » doit être refusé",
        );
    }

    /** @return array<string, array{string}> */
    public static function motsDePasseRefuses(): array
    {
        return [
            'trop court' => ['Chevalier7'],
            'vide' => [''],
            'très courant' => ['motdepasse123'],
            'courant en anglais' => ['Password123456'],
            'suite au clavier' => ['azertyuiop12'],
            'caractères répétés' => ['aaaaaaaaaaaaaaaa'],
            'deux caractères seulement' => ['ababababababab'],
            'contient le nom du compte' => ['admin-Nantes22'],
            'contient l’adresse' => ['admin@exemple.fr-2026'],
            'douze caractères, deux sortes' => ['chevaliersab'],
        ];
    }

    #[Test]
    #[DataProvider('motsDePasseAcceptes')]
    public function un_mot_de_passe_solide_est_accepte(string $motDePasse): void
    {
        $this->assertNull(
            Policy::refusal($motDePasse, ['admin', 'admin@exemple.fr']),
            "« {$motDePasse} » devrait passer",
        );
    }

    /** @return array<string, array{string}> */
    public static function motsDePasseAcceptes(): array
    {
        return [
            'douze caractères, trois sortes' => ['Chevalier7xz'],
            'phrase longue et simple' => ['les fleurs poussent au printemps'],
            'phrase de seize caractères' => ['cheval bateau ru'],
            'mêlé et long' => ['Tr0ubadour!Nantes'],
        ];
    }

    #[Test]
    public function la_longueur_dispense_de_la_variete(): void
    {
        // Seize caractères d'une seule sorte : la longueur suffit.
        $this->assertNull(Policy::refusal('cheval bateau ru'));

        // Le même, raccourci sous le seuil : la variété redevient exigée.
        $this->assertNotNull(Policy::refusal('cheval bateu'));
    }

    #[Test]
    public function le_motif_du_refus_est_dit_clairement(): void
    {
        $raison = Policy::refusal('court');

        $this->assertIsString($raison);
        $this->assertStringContainsString('12 caractères', $raison);
    }
}
