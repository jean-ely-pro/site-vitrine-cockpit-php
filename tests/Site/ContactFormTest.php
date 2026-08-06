<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Cockpit\ContentSource;
use App\Cockpit\ContentUnavailable;
use App\Contact\ContactForm;
use App\Contact\SpamGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What the form does with what it receives: where it writes, what it refuses,
 * and ce qu'il laisse croire à un robot.
 */
final class ContactFormTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/form-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    /** Records what the form tried to write. */
    private function source(bool $enPanne = false): ContentSource
    {
        return new class($enPanne) implements ContentSource {

            /** @var list<array{string, array<string, mixed>}> */
            public array $ecritures = [];

            public function __construct(private bool $enPanne)
            {
            }

            public function singleton(string $model, bool $populate = false): ?array
            {
                return null;
            }

            public function item(string $model, array $filter, bool $populate = false): ?array
            {
                return null;
            }

            public function items(string $model, array $filter = [], array $sort = [], ?int $limit = null): array
            {
                return [];
            }

            public function create(string $model, array $data): ?array
            {
                if ($this->enPanne) {
                    throw new ContentUnavailable('Cockpit ne répond pas');
                }

                $this->ecritures[] = [$model, $data];

                return $data + ['_id' => 'abc'];
            }
        };
    }

    /** @param array<string, mixed> $extra */
    private function champs(SpamGuard $guard, array $extra = []): array
    {
        return array_merge([
            'nom' => 'Camille Durand',
            'email' => 'camille@exemple.fr',
            'message' => 'Bonjour, je souhaite un devis pour un mariage en juin.',
            'consentement' => '1',
            'retour' => '/nous-ecrire',
            'jeton' => $guard->stamp(time() - 30),
            'site_web' => '',
        ], $extra);
    }

    #[Test]
    public function un_message_valide_est_ecrit_dans_la_seule_collection_prevue(): void
    {
        $guard = new SpamGuard($this->directory, 'secret');
        $source = $this->source();

        $resultat = (new ContactForm($source, $guard))->handle($this->champs($guard), '203.0.113.1');

        $this->assertTrue($resultat->accepted);
        $this->assertCount(1, $source->ecritures);
        $this->assertSame('messages', $source->ecritures[0][0], 'Le formulaire ne doit écrire que dans « messages »');
        $this->assertSame('Camille Durand', $source->ecritures[0][1]['nom']);
        $this->assertTrue($source->ecritures[0][1]['consentement']);
        $this->assertFalse($source->ecritures[0][1]['lu']);
    }

    #[Test]
    public function un_robot_croit_avoir_reussi_mais_rien_nest_ecrit(): void
    {
        $guard = new SpamGuard($this->directory, 'secret');
        $source = $this->source();

        $resultat = (new ContactForm($source, $guard))
            ->handle($this->champs($guard, ['site_web' => 'http://spam.test']), '203.0.113.1');

        $this->assertTrue($resultat->accepted, 'Un robot ne doit rien apprendre en réessayant');
        $this->assertSame([], $source->ecritures, 'Mais rien ne doit être enregistré');
    }

    #[Test]
    public function un_message_incomplet_nest_pas_ecrit(): void
    {
        $guard = new SpamGuard($this->directory, 'secret');
        $source = $this->source();

        $resultat = (new ContactForm($source, $guard))
            ->handle($this->champs($guard, ['consentement' => '']), '203.0.113.1');

        $this->assertFalse($resultat->accepted);
        $this->assertSame([], $source->ecritures);
        $this->assertStringContainsString('vérifier les champs', $resultat->message());
    }

    #[Test]
    public function une_panne_du_contenu_ne_laisse_pas_croire_a_un_envoi(): void
    {
        $guard = new SpamGuard($this->directory, 'secret');

        $resultat = (new ContactForm($this->source(enPanne: true), $guard))
            ->handle($this->champs($guard), '203.0.113.1');

        $this->assertFalse($resultat->accepted);
        $this->assertStringContainsString('n’a pas pu être envoyé', $resultat->message());
    }

    #[Test]
    public function sans_cle_decriture_le_formulaire_nest_pas_propose(): void
    {
        $form = new ContactForm(null, new SpamGuard($this->directory, 'secret'));

        $this->assertFalse($form->isConfigured(), 'Mieux vaut pas de formulaire qu’un formulaire qui perd les messages');
    }

    #[Test]
    public function un_envoi_accepte_compte_pour_la_limite(): void
    {
        $guard = new SpamGuard($this->directory, 'secret');
        $form = new ContactForm($this->source(), $guard);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($form->handle($this->champs($guard), '203.0.113.9')->accepted);
        }

        $resultat = $form->handle($this->champs($guard), '203.0.113.9');

        $this->assertFalse($resultat->accepted);
        $this->assertStringContainsString('Réessayer dans une heure', $resultat->message());
    }
}
