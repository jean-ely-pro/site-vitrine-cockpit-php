<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Contact\SpamGuard;
use App\Contact\Submission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The contact form is the only door open to everyone. What it accepts, and
 * where it sends the visitor back, has to hold.
 */
final class ContactTest extends TestCase
{
    private const SECRET = 'un-secret-de-test';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/contact-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    /** @param array<string, mixed> $extra */
    private function champs(array $extra = []): array
    {
        return array_merge([
            'nom' => 'Camille Durand',
            'email' => 'camille@exemple.fr',
            'message' => 'Bonjour, je souhaite un devis pour un mariage en juin.',
            'consentement' => '1',
            'retour' => '/nous-ecrire',
        ], $extra);
    }

    // ── Validation ────────────────────────────────────────────────────────

    #[Test]
    public function un_message_complet_est_accepte(): void
    {
        $this->assertTrue(Submission::fromInput($this->champs())->isValid());
    }

    #[Test]
    #[DataProvider('champsFautifs')]
    public function un_champ_manquant_est_signale(array $remplacement, string $champAttendu): void
    {
        $submission = Submission::fromInput($this->champs($remplacement));

        $this->assertFalse($submission->isValid());
        $this->assertArrayHasKey($champAttendu, $submission->errors);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function champsFautifs(): array
    {
        return [
            'sans nom' => [['nom' => ''], 'nom'],
            'sans adresse' => [['email' => ''], 'email'],
            'adresse invalide' => [['email' => 'pas-une-adresse'], 'email'],
            'sans message' => [['message' => ''], 'message'],
            'message trop court' => [['message' => 'coucou'], 'message'],
            'sans consentement' => [['consentement' => ''], 'consentement'],
        ];
    }

    #[Test]
    public function le_consentement_nest_jamais_supposé(): void
    {
        $sans = $this->champs();
        unset($sans['consentement']);

        $submission = Submission::fromInput($sans);

        $this->assertFalse($submission->consentement);
        $this->assertFalse($submission->isValid(), 'Un message sans consentement ne doit pas passer');
    }

    #[Test]
    public function les_valeurs_saisies_sont_conservees_pour_la_correction(): void
    {
        $submission = Submission::fromInput($this->champs(['email' => 'invalide']));

        $this->assertSame('Camille Durand', $submission->values()['nom']);
        $this->assertSame('invalide', $submission->values()['email']);
    }

    // ── Adresse de retour ─────────────────────────────────────────────────

    #[Test]
    #[DataProvider('retoursEtrangers')]
    public function le_retour_ne_quitte_jamais_le_site(string $retour): void
    {
        $submission = Submission::fromInput($this->champs(['retour' => $retour]));

        $this->assertSame('/', $submission->origine, "« {$retour} » doit ramener à l'accueil");
    }

    /** @return array<string, array{string}> */
    public static function retoursEtrangers(): array
    {
        return [
            'un autre site' => ['https://ailleurs.test/piege'],
            'un chemin Windows' => ['C:/Program Files/Git/nous-ecrire'],
            'une adresse relative' => ['//ailleurs.test'],
            'des majuscules' => ['/Piege'],
            'vide' => [''],
        ];
    }

    #[Test]
    public function un_retour_du_site_est_conserve(): void
    {
        foreach (['/nous-ecrire', '/', '/actualites/une-nouvelle'] as $retour) {
            $this->assertSame($retour, Submission::fromInput($this->champs(['retour' => $retour]))->origine);
        }
    }

    // ── Anti-spam ─────────────────────────────────────────────────────────

    private function guard(): SpamGuard
    {
        return new SpamGuard($this->directory, self::SECRET);
    }

    #[Test]
    public function un_envoi_normal_passe(): void
    {
        $guard = $this->guard();
        $jeton = $guard->stamp(time() - 30);

        $this->assertNull($guard->refusal(['jeton' => $jeton, 'site_web' => ''], '203.0.113.1'));
    }

    #[Test]
    public function le_champ_invisible_rempli_est_ecarte(): void
    {
        $guard = $this->guard();
        $jeton = $guard->stamp(time() - 30);

        $this->assertSame(
            'spam:pot',
            $guard->refusal(['jeton' => $jeton, 'site_web' => 'http://spam.test'], '203.0.113.1'),
        );
    }

    #[Test]
    public function un_envoi_instantane_est_ecarte(): void
    {
        $guard = $this->guard();

        $this->assertSame(
            'spam:vitesse',
            $guard->refusal(['jeton' => $guard->stamp(), 'site_web' => ''], '203.0.113.1'),
        );
    }

    #[Test]
    #[DataProvider('jetonsInvalides')]
    public function un_jeton_qui_ne_vient_pas_du_site_est_ecarte(mixed $jeton): void
    {
        $this->assertSame(
            'spam:jeton',
            $this->guard()->refusal(['jeton' => $jeton, 'site_web' => ''], '203.0.113.1'),
        );
    }

    /** @return array<string, array{mixed}> */
    public static function jetonsInvalides(): array
    {
        return [
            'absent' => [null],
            'vide' => [''],
            'sans signature' => ['1700000000'],
            'signature fausse' => ['1700000000.aaaaaaaa'],
            'forgé' => [(string) (time() - 60).'.'.hash_hmac('sha256', (string) (time() - 60), 'mauvais-secret')],
        ];
    }

    #[Test]
    public function le_debit_est_limite_puis_se_rouvre(): void
    {
        $guard = $this->guard();
        $ip = '203.0.113.7';
        $maintenant = time();

        for ($i = 0; $i < 5; $i++) {
            $this->assertNull(
                $guard->refusal(['jeton' => $guard->stamp($maintenant - 30), 'site_web' => ''], $ip, $maintenant),
                'Les cinq premiers envois doivent passer',
            );
            $guard->record($ip, $maintenant);
        }

        $this->assertSame(
            'debit',
            $guard->refusal(['jeton' => $guard->stamp($maintenant - 30), 'site_web' => ''], $ip, $maintenant),
            'Le sixième envoi dans l’heure doit être refusé',
        );

        // Une heure plus tard, la même personne peut écrire à nouveau.
        $plusTard = $maintenant + 3601;

        $this->assertNull(
            $guard->refusal(['jeton' => $guard->stamp($plusTard - 30), 'site_web' => ''], $ip, $plusTard),
        );
    }

    #[Test]
    public function la_limite_ne_vaut_que_pour_lexpediteur(): void
    {
        $guard = $this->guard();
        $maintenant = time();

        for ($i = 0; $i < 5; $i++) {
            $guard->record('203.0.113.7', $maintenant);
        }

        $this->assertNull(
            $guard->refusal(['jeton' => $guard->stamp($maintenant - 30), 'site_web' => ''], '203.0.113.8', $maintenant),
            'Un autre visiteur ne doit pas être bloqué',
        );
    }

    #[Test]
    public function ladresse_du_visiteur_nest_pas_conservee(): void
    {
        $guard = $this->guard();
        $guard->record('203.0.113.7');

        foreach (glob($this->directory.'/*') ?: [] as $fichier) {
            $this->assertStringNotContainsString('203.0.113.7', basename($fichier));
            $this->assertStringNotContainsString('203.0.113.7', (string) file_get_contents($fichier));
        }
    }
}
