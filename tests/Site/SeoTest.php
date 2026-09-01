<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Seo\LocalBusiness;
use App\Seo\OpeningHours;
use App\Seo\Sitemap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Structured data that contradicts the page is worse than none: a shop shown
 * as open when it is closed sends people to a closed door.
 */
final class SeoTest extends TestCase
{
    // ── Horaires ──────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('horairesLisibles')]
    public function un_horaire_clair_est_converti(string $texte, array $attendu): void
    {
        $plages = array_map(
            static fn (array $s): string => "{$s['opens']}-{$s['closes']}",
            OpeningHours::toSchema(['lundi' => $texte]),
        );

        $this->assertSame($attendu, $plages);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function horairesLisibles(): array
    {
        return [
            'heures en h' => ['9h – 18h', ['09:00-18:00']],
            'tiret simple' => ['9h-18h', ['09:00-18:00']],
            'deux-points' => ['09:00 - 18:00', ['09:00-18:00']],
            'avec minutes' => ['9h30 – 18h30', ['09:30-18:30']],
            'coupure de midi' => ['9h30 – 12h, 14h – 18h30', ['09:30-12:00', '14:00-18:30']],
            'avec « à »' => ['8h à 20h', ['08:00-20:00']],
        ];
    }

    #[Test]
    #[DataProvider('horairesAmbigus')]
    public function un_horaire_ambigu_est_laisse_de_cote(string $texte): void
    {
        $this->assertSame(
            [],
            OpeningHours::toSchema(['lundi' => $texte]),
            "« {$texte} » ne se lit pas avec certitude : mieux vaut ne rien publier",
        );
    }

    /** @return array<string, array{string}> */
    public static function horairesAmbigus(): array
    {
        return [
            'fermé' => ['Fermé'],
            'vide' => [''],
            'sur rendez-vous' => ['sur rendez-vous'],
            'jour et nuit' => ['24h/24'],
            'plage qui n’avance pas' => ['18h – 9h'],
            'heure impossible' => ['9h – 42h'],
        ];
    }

    #[Test]
    public function chaque_jour_ouvre_sa_propre_plage(): void
    {
        $specifications = OpeningHours::toSchema([
            'lundi' => '',
            'mardi' => '9h – 18h',
            'samedi' => '9h – 12h',
        ]);

        $this->assertCount(2, $specifications);
        $this->assertStringEndsWith('Tuesday', $specifications[0]['dayOfWeek']);
        $this->assertStringEndsWith('Saturday', $specifications[1]['dayOfWeek']);
    }

    // ── Établissement ─────────────────────────────────────────────────────

    #[Test]
    public function letablissement_est_decrit_depuis_lidentite(): void
    {
        $data = LocalBusiness::fromSettings([
            'nom' => 'Atelier Bloom',
            'telephone' => '02 40 00 00 00',
            'email' => 'contact@atelier-bloom.fr',
            'adresse' => "12 rue des Lilas\n44000 Nantes",
            'siret' => '000 000 000 00000',
        ], 'https://atelier-bloom.fr');

        $this->assertSame('LocalBusiness', $data['@type']);
        $this->assertSame('Atelier Bloom', $data['name']);
        $this->assertSame('12 rue des Lilas', $data['address']['streetAddress']);
        $this->assertSame('44000', $data['address']['postalCode']);
        $this->assertSame('Nantes', $data['address']['addressLocality']);
        $this->assertSame('SIRET', $data['identifier']['propertyID']);
    }

    #[Test]
    public function un_champ_vide_nest_pas_publie(): void
    {
        $data = LocalBusiness::fromSettings(['nom' => 'Atelier Bloom'], 'https://atelier-bloom.fr');

        foreach (['telephone', 'email', 'address', 'identifier', 'openingHoursSpecification'] as $absent) {
            $this->assertArrayNotHasKey($absent, $data, "« {$absent} » vide ne doit pas figurer");
        }
    }

    #[Test]
    public function une_adresse_qui_ne_se_decoupe_pas_reste_entiere(): void
    {
        $data = LocalBusiness::fromSettings([
            'nom' => 'Atelier Bloom',
            'adresse' => 'Lieu-dit les Sources',
        ], 'https://atelier-bloom.fr');

        $this->assertSame('Lieu-dit les Sources', $data['address']['streetAddress']);
        $this->assertArrayNotHasKey('postalCode', $data['address']);
    }

    #[Test]
    public function seules_les_adresses_de_reseaux_valides_sont_reprises(): void
    {
        $data = LocalBusiness::fromSettings([
            'nom' => 'Atelier Bloom',
            'reseaux' => [
                ['nom' => 'Instagram', 'url' => 'https://instagram.com/atelier'],
                ['nom' => 'Facebook', 'url' => 'pas une adresse'],
                ['nom' => 'X', 'url' => ''],
            ],
        ], 'https://atelier-bloom.fr');

        $this->assertSame(['https://instagram.com/atelier'], $data['sameAs']);
    }

    // ── Plan du site ──────────────────────────────────────────────────────

    #[Test]
    public function le_plan_du_site_liste_les_adresses(): void
    {
        $xml = Sitemap::toXml([
            ['loc' => 'https://exemple.fr/', 'lastmod' => 1754179200],
            ['loc' => 'https://exemple.fr/services'],
        ]);

        $this->assertStringContainsString('<loc>https://exemple.fr/</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2025-08-03</lastmod>', $xml);
        $this->assertStringContainsString('<loc>https://exemple.fr/services</loc>', $xml);
    }

    #[Test]
    public function le_fichier_dindexation_ne_nomme_pas_ladministration(): void
    {
        $robots = Sitemap::robotsTxt('https://exemple.fr');

        // Ce fichier est public : y écrire l'adresse du panneau la donnerait à
        // quiconque le demande. L'indexation est refusée par un en-tête, dans
        // public/.htaccess.
        $this->assertStringNotContainsString('admin', $robots);
        $this->assertStringContainsString('Sitemap: https://exemple.fr/sitemap.xml', $robots);
    }
}
