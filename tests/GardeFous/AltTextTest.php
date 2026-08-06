<?php

declare(strict_types=1);

namespace Tests\GardeFous;

use Media\AltText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An image without a description is unreadable to anyone using a screen
 * reader, and shows nothing at all when it fails to load.
 */
final class AltTextTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function champsAvecImage(): array
    {
        return [
            ['name' => 'image', 'type' => 'asset', 'label' => 'Image'],
            ['name' => 'alt', 'type' => 'text', 'label' => 'Description de l’image'],
        ];
    }

    #[Test]
    public function une_image_sans_description_est_refusee(): void
    {
        $manquant = AltText::missingIn($this->champsAvecImage(), [
            'image' => ['path' => '/photo.webp'],
            'alt' => '',
        ]);

        $this->assertSame('Image', $manquant);
    }

    #[Test]
    public function une_description_faite_despaces_ne_compte_pas(): void
    {
        $manquant = AltText::missingIn($this->champsAvecImage(), [
            'image' => ['path' => '/photo.webp'],
            'alt' => '   ',
        ]);

        $this->assertNotNull($manquant);
    }

    #[Test]
    public function une_image_decrite_passe(): void
    {
        $manquant = AltText::missingIn($this->champsAvecImage(), [
            'image' => ['path' => '/photo.webp'],
            'alt' => 'Un bouquet de fleurs de saison',
        ]);

        $this->assertNull($manquant);
    }

    #[Test]
    public function sans_image_aucune_description_nest_exigee(): void
    {
        $manquant = AltText::missingIn($this->champsAvecImage(), ['image' => null, 'alt' => '']);

        $this->assertNull($manquant, 'Exiger une description sans image bloquerait le client pour rien');
    }

    #[Test]
    public function une_image_dans_une_section_est_verifiee_aussi(): void
    {
        $champs = [
            ['name' => 'blocs', 'type' => 'set', 'multiple' => true, 'opts' => [
                'fields' => $this->champsAvecImage(),
            ]],
        ];

        $manquant = AltText::missingIn($champs, ['blocs' => [
            ['image' => ['path' => '/a.webp'], 'alt' => 'Décrite'],
            ['image' => ['path' => '/b.webp'], 'alt' => ''],
        ]]);

        $this->assertSame('Image', $manquant, 'La deuxième section n’a pas de description');
    }

    #[Test]
    public function un_champ_image_sans_champ_de_description_nest_pas_contraint(): void
    {
        // Le logo, par exemple : aucune description ne l'accompagne dans le modèle.
        $champs = [['name' => 'logo', 'type' => 'asset', 'label' => 'Logo']];

        $this->assertNull(AltText::missingIn($champs, ['logo' => ['path' => '/logo.webp']]));
    }
}
