<?php

namespace Tests\Feature;

use App\Enums\PublicIdentityAuthorizationEventType;
use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Http\Middleware\IsAdmin;
use App\Mail\GuardianPublicIdentityConfirmation;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\User;
use App\Services\PublicIdentityAuthorizationService;
use App\Services\PublicPlayerIdentityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class DirectPublicIdentityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-22 10:00:00');
        config([
            'public_identity.authorization_enabled' => true,
            'public_identity.notification_enabled' => true,
            'public_identity.confirmation_ttl_hours' => 48,
        ]);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_direct_service_creates_pending_alias_with_player_notice_and_hashed_token(): void
    {
        $player = $this->minorPlayer();

        $result = $this->service()->createForPlayer(
            $player,
            $this->admin,
            $this->attributes('alias')
        );
        $authorization = $result['authorization'];

        $this->assertSame($player->id, $authorization->player_id);
        $this->assertNull($authorization->school_enrollment_id);
        $this->assertSame(PublicIdentityAuthorizationMode::ALIAS, $authorization->mode);
        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->state);
        $this->assertSame(PublicIdentityAuthorization::SCOPE, $authorization->scope);
        $this->assertSame('NOTICE-PUBLIC-IDENTITY-MINORS', $authorization->notice_id);
        $this->assertSame('1.0.0', $authorization->notice_version);
        $this->assertSame('guardian@example.test', $authorization->guardian_email);
        $this->assertSame('Representante Legal', $authorization->guardian_name);
        $this->assertNotNull($authorization->guardian_authority_declared_at);
        $this->assertNotNull($result['token']);
        $this->assertSame(hash('sha256', $result['token']), $authorization->confirmation_token_hash);
        $this->assertNotSame($result['token'], $authorization->confirmation_token_hash);
        $this->assertSame(
            '2026-09-24 10:00:00',
            $authorization->confirmation_token_expires_at->toDateTimeString()
        );
        $this->assertDatabaseHas('public_identity_authorization_events', [
            'public_identity_authorization_id' => $authorization->id,
            'type' => PublicIdentityAuthorizationEventType::REQUESTED->value,
            'actor_user_id' => $this->admin->id,
        ]);
        $this->assertArrayNotHasKey('confirmation_token_hash', $authorization->toArray());
    }

    public function test_direct_service_supports_name_initial(): void
    {
        $nameResult = $this->service()->createForPlayer(
            $this->minorPlayer(['nickname' => null]),
            $this->admin,
            $this->attributes('name_initial')
        );

        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $nameResult['authorization']->state);
        $this->assertNotNull($nameResult['token']);
        $this->assertSame(PublicIdentityAuthorizationMode::NAME_INITIAL, $nameResult['authorization']->mode);
    }

    public function test_direct_service_and_admin_request_reject_anonymous_without_persisting(): void
    {
        Mail::fake();
        $player = $this->minorPlayer();

        $this->assertServiceRejected(
            $player,
            $this->attributes('anonymous', authorityDeclared: false),
            'mode'
        );
        $this->actingAs($this->admin)->post(
            route('admin.players.public-identity-authorizations.store', $player),
            $this->attributes('anonymous', authorityDeclared: false)
        )->assertSessionHasErrors('mode');

        $this->assertDatabaseCount('public_identity_authorizations', 0);
        Mail::assertNothingSent();
    }

    public function test_direct_service_rejects_age_mode_notice_and_guardian_evidence_before_persisting(): void
    {
        $adult = $this->minorPlayer(['birth_date' => '2000-01-01']);
        $unknownBirthDate = $this->minorPlayer(['birth_date' => null]);
        $validMinor = $this->minorPlayer();

        $this->assertServiceRejected($adult, $this->attributes('alias'), 'player');
        $this->assertServiceRejected($unknownBirthDate, $this->attributes('alias'), 'player');
        $this->assertServiceRejected($validMinor, $this->attributes('full_name'), 'mode');
        $this->assertServiceRejected(
            $validMinor,
            [...$this->attributes('alias'), 'notice_id' => 'NOTICE-UNKNOWN'],
            'notice_version'
        );
        $this->assertServiceRejected(
            $validMinor,
            [...$this->attributes('alias'), 'notice_version' => '0.9.0'],
            'notice_version'
        );
        $this->assertServiceRejected(
            $validMinor,
            [...$this->attributes('alias'), 'guardian_authority_declared' => false],
            'guardian_authority_declared'
        );
        $this->assertServiceRejected(
            $validMinor,
            [...$this->attributes('alias'), 'guardian_name' => '   '],
            'guardian_name'
        );

        $this->assertDatabaseCount('public_identity_authorizations', 0);
    }

    public function test_direct_service_rejects_modes_that_cannot_be_projected(): void
    {
        $withoutAlias = $this->minorPlayer(['nickname' => '   ']);
        $withoutLastname = $this->minorPlayer(
            userAttributes: ['lastname' => '   ']
        );

        $this->assertServiceRejected($withoutAlias, $this->attributes('alias'), 'mode');
        $this->assertServiceRejected(
            $withoutLastname,
            $this->attributes('name_initial'),
            'mode'
        );
        $this->assertDatabaseCount('public_identity_authorizations', 0);
    }

    public function test_pending_and_approved_block_but_historical_states_allow_a_new_request(): void
    {
        foreach ([
            PublicIdentityAuthorizationState::PENDING,
            PublicIdentityAuthorizationState::APPROVED,
        ] as $state) {
            $player = $this->minorPlayer();
            $factory = PublicIdentityAuthorization::factory()->for($player);
            ($state === PublicIdentityAuthorizationState::APPROVED ? $factory->approved() : $factory)
                ->create([
                    'school_enrollment_id' => null,
                    'expires_at' => $state === PublicIdentityAuthorizationState::APPROVED
                        ? CarbonImmutable::now()->subDay()
                        : null,
                ]);

            $this->assertServiceRejected($player, $this->attributes('alias'), 'player');
        }

        foreach ([
            PublicIdentityAuthorizationState::DENIED,
            PublicIdentityAuthorizationState::REVOKED,
            PublicIdentityAuthorizationState::EXPIRED,
        ] as $state) {
            $player = $this->minorPlayer();
            $factory = PublicIdentityAuthorization::factory()->for($player);
            $historical = match ($state) {
                PublicIdentityAuthorizationState::DENIED => $factory->denied(),
                PublicIdentityAuthorizationState::REVOKED => $factory->revoked(),
                PublicIdentityAuthorizationState::EXPIRED => $factory->state([
                    'state' => PublicIdentityAuthorizationState::EXPIRED,
                ]),
                default => $factory,
            };
            $historical->create(['school_enrollment_id' => null]);

            $created = $this->service()->createForPlayer(
                $player,
                $this->admin,
                $this->attributes('alias')
            );
            $this->assertSame(PublicIdentityAuthorizationState::PENDING, $created['authorization']->state);
        }

        $this->assertDatabaseCount('public_identity_authorizations', 8);
    }

    public function test_admin_direct_request_records_actor_and_sends_subject_aware_mail_after_creation(): void
    {
        Mail::fake();
        $player = $this->minorPlayer();

        $response = $this->actingAs($this->admin)->post(
            route('admin.players.public-identity-authorizations.store', $player),
            $this->attributes('alias')
        );

        $authorization = PublicIdentityAuthorization::query()->sole();
        $response
            ->assertRedirect(route('admin.public-identity-authorizations.show', $authorization))
            ->assertSessionHas('success');
        $this->assertNull($authorization->school_enrollment_id);
        $this->assertDatabaseHas('public_identity_authorization_events', [
            'public_identity_authorization_id' => $authorization->id,
            'type' => PublicIdentityAuthorizationEventType::REQUESTED->value,
            'actor_user_id' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)
            ->get(route('admin.public-identity-authorizations.index', ['age_group' => 'under_14']))
            ->assertOk()
            ->assertSee(route('admin.public-identity-authorizations.show', $authorization));
        Mail::assertSent(GuardianPublicIdentityConfirmation::class, function ($mail): bool {
            $rendered = $mail->render();
            $copy = preg_replace('/\s+/', ' ', strip_tags($rendered));
            $this->assertSame('Nombre Menor Apellido Privado', $mail->minorReference);
            $this->assertStringContainsString(
                'Persona menor a la que se refiere la solicitud: Nombre Menor Apellido Privado',
                $copy
            );
            $this->assertStringContainsString(
                'el club debe completar su revisión antes de publicarla',
                $copy
            );
            $this->assertStringNotContainsString('vincular después', $copy);
            $this->assertStringNotContainsString('jugador correcto', $copy);
            $this->assertStringNotContainsString('Alias Menor', $copy);
            $this->assertStringNotContainsString('23/09/2014', $copy);
            $this->assertStringNotContainsString('DNI', $copy);

            return $mail->hasTo('guardian@example.test');
        });
    }

    public function test_notification_flag_blocks_every_direct_request_without_mail(): void
    {
        Mail::fake();
        config(['public_identity.notification_enabled' => false]);

        foreach (['alias', 'name_initial'] as $mode) {
            $player = $this->minorPlayer();
            $this->actingAs($this->admin)->post(
                route('admin.players.public-identity-authorizations.store', $player),
                $this->attributes($mode)
            )->assertSessionHasErrors('notification');
        }

        $this->assertDatabaseCount('public_identity_authorizations', 0);
        Mail::assertNothingSent();
    }

    public function test_authorization_flag_blocks_every_direct_request(): void
    {
        config(['public_identity.authorization_enabled' => false]);
        $player = $this->minorPlayer();

        $this->actingAs($this->admin)->post(
            route('admin.players.public-identity-authorizations.store', $player),
            $this->attributes('alias')
        )->assertSessionHasErrors('public_identity_authorization');

        $this->assertDatabaseCount('public_identity_authorizations', 0);
    }

    public function test_mail_failure_keeps_direct_request_pending_and_reports_recovery_path(): void
    {
        config(['mail.default' => 'public-identity-direct-test-failure']);
        $player = $this->minorPlayer();

        $response = $this->actingAs($this->admin)->post(
            route('admin.players.public-identity-authorizations.store', $player),
            $this->attributes('alias')
        );

        $authorization = PublicIdentityAuthorization::query()->sole();
        $response
            ->assertRedirect(route('admin.public-identity-authorizations.show', $authorization))
            ->assertSessionHas(
                'error',
                'La solicitud quedó creada y sigue pendiente, pero el correo no pudo enviarse. Puedes reenviarlo desde este detalle.'
            );
        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->state);
        $event = $authorization->events()
            ->where('type', PublicIdentityAuthorizationEventType::NOTIFICATION_FAILED->value)
            ->sole();
        $this->assertSame(['error_type' => InvalidArgumentException::class], $event->metadata);
    }

    public function test_player_admin_requires_admin_and_only_offers_valid_minor_flow(): void
    {
        $minor = $this->minorPlayer();
        $adult = $this->minorPlayer(['birth_date' => '2000-01-01']);
        $unknownBirthDate = $this->minorPlayer(['birth_date' => null]);
        $user = User::factory()->create();

        $this->get(route('admin.players.show', $minor))->assertRedirect(route('admin.login'));
        $this->actingAs($user)->get(route('admin.players.show', $minor))->assertForbidden();
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $minor))
            ->assertOk()
            ->assertSee('Identidad pública del menor')
            ->assertSee('Proyección pública actual')
            ->assertSee('Participante')
            ->assertSee('Solicitar autorización de identidad pública')
            ->assertSee('Confirmo que el representante indicado ha declarado ante el Club')
            ->assertDontSee('Declaro ejercer la patria potestad')
            ->assertSee('value="alias"', false)
            ->assertSee('value="name_initial"', false)
            ->assertDontSee('value="anonymous"', false);
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $adult))
            ->assertOk()
            ->assertDontSee('Identidad pública del menor')
            ->assertDontSee('Solicitar autorización de identidad pública');
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $unknownBirthDate))
            ->assertOk()
            ->assertDontSee('Identidad pública del menor')
            ->assertDontSee('Solicitar autorización de identidad pública');

        $route = app('router')->getRoutes()
            ->getByName('admin.players.public-identity-authorizations.store');
        $this->assertNotNull($route);
        $this->assertContains('web', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains(IsAdmin::class, $route->middleware());
    }

    public function test_player_admin_hides_unavailable_modes_and_duplicate_form(): void
    {
        $player = $this->minorPlayer();

        config(['public_identity.notification_enabled' => false]);
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $player))
            ->assertOk()
            ->assertSee('No puede iniciarse una solicitud directa sin el correo')
            ->assertDontSee('value="anonymous"', false)
            ->assertDontSee('value="alias"', false)
            ->assertDontSee('value="name_initial"', false)
            ->assertDontSee('Solicitar autorización de identidad pública');

        config(['public_identity.notification_enabled' => true]);
        $authorization = PublicIdentityAuthorization::factory()->for($player)->create([
            'school_enrollment_id' => null,
        ]);
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $player))
            ->assertOk()
            ->assertSee('Ya existe una autorización pendiente')
            ->assertSee(route('admin.public-identity-authorizations.show', $authorization))
            ->assertDontSee('Solicitar autorización de identidad pública');

        $approvedPlayer = $this->minorPlayer();
        PublicIdentityAuthorization::factory()->for($approvedPlayer)->approved()->create([
            'school_enrollment_id' => null,
        ]);
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $approvedPlayer))
            ->assertOk()
            ->assertSee('Ya existe una autorización aprobada')
            ->assertDontSee('Solicitar autorización de identidad pública');

        config(['public_identity.authorization_enabled' => false]);
        $otherPlayer = $this->minorPlayer();
        $this->actingAs($this->admin)
            ->get(route('admin.players.show', $otherPlayer))
            ->assertOk()
            ->assertSee('Las solicitudes de identidad pública están desactivadas')
            ->assertDontSee('Solicitar autorización de identidad pública');
    }

    public function test_direct_admin_payload_is_closed_and_never_accepts_player_id(): void
    {
        Mail::fake();
        $routePlayer = $this->minorPlayer();
        $forgedPlayer = $this->minorPlayer();

        $this->actingAs($this->admin)->post(
            route('admin.players.public-identity-authorizations.store', $routePlayer),
            [...$this->attributes('alias'), 'player_id' => $forgedPlayer->id]
        )->assertSessionHasErrors('payload');
        $this->actingAs($this->admin)->post(
            route('admin.players.public-identity-authorizations.store', $routePlayer),
            [...$this->attributes('alias'), 'guardian_name' => ['forged']]
        )->assertSessionHasErrors('guardian_name');

        $this->assertDatabaseCount('public_identity_authorizations', 0);
        Mail::assertNothingSent();
    }

    public function test_direct_detail_identifies_fixed_subject_and_rejects_every_relink_attempt(): void
    {
        $player = $this->minorPlayer();
        $otherPlayer = $this->minorPlayer();
        $authorization = $this->service()->createForPlayer(
            $player,
            $this->admin,
            $this->attributes('alias')
        )['authorization'];

        $this->actingAs($this->admin)
            ->get(route('admin.public-identity-authorizations.show', $authorization))
            ->assertOk()
            ->assertSee('Solicitud directa desde la ficha del jugador')
            ->assertSee('El expediente nació vinculado a este jugador')
            ->assertDontSee('No hay jugadores compatibles')
            ->assertDontSee('Jugador compatible')
            ->assertDontSee('Vincular de forma explícita');

        $this->actingAs($this->admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['player_id' => $otherPlayer->id, 'link_confirmed' => '1']
        )->assertSessionHasErrors('player_id');

        $this->assertSame($player->id, $authorization->fresh()->player_id);
        $this->assertDatabaseMissing('public_identity_authorization_events', [
            'public_identity_authorization_id' => $authorization->id,
            'type' => PublicIdentityAuthorizationEventType::PLAYER_LINK_CHANGED->value,
        ]);
    }

    public function test_public_confirmation_and_denial_remain_private_for_direct_origin(): void
    {
        $confirmedResult = $this->service()->createForPlayer(
            $this->minorPlayer(),
            $this->admin,
            $this->attributes('alias')
        );

        $lookup = $this->postJson('/api/v1/public-identity/confirmation/lookup', [
            'token' => $confirmedResult['token'],
        ])->assertOk()
            ->assertJsonPath('data.mode', 'alias')
            ->assertJsonMissingPath('data.player_id')
            ->assertJsonMissingPath('data.school_enrollment_id')
            ->assertJsonMissingPath('data.guardian_email')
            ->assertJsonMissingPath('data.origin');
        $this->assertStringNotContainsString('guardian@example.test', $lookup->getContent());

        $this->postJson('/api/v1/public-identity/confirmation/confirm', [
            'token' => $confirmedResult['token'],
        ])->assertOk();
        $this->postJson('/api/v1/public-identity/confirmation/lookup', [
            'token' => $confirmedResult['token'],
        ])->assertNotFound();

        $deniedPlayer = $this->minorPlayer();
        $deniedResult = $this->service()->createForPlayer(
            $deniedPlayer,
            $this->admin,
            $this->attributes('alias')
        );
        $this->postJson('/api/v1/public-identity/confirmation/deny', [
            'token' => $deniedResult['token'],
        ])->assertOk();
        $this->assertSame(
            PublicIdentityAuthorizationState::DENIED,
            $deniedResult['authorization']->fresh()->state
        );
        $this->assertSame('Participante', $this->displayName($deniedPlayer));

        $expiredResult = $this->service()->createForPlayer(
            $this->minorPlayer(),
            $this->admin,
            $this->attributes('alias')
        );
        $expiredResult['authorization']->update([
            'confirmation_token_expires_at' => CarbonImmutable::now()->subSecond(),
        ]);
        $this->postJson('/api/v1/public-identity/confirmation/lookup', [
            'token' => $expiredResult['token'],
        ])->assertNotFound();
        $this->assertSame(
            PublicIdentityAuthorizationState::EXPIRED,
            $expiredResult['authorization']->fresh()->state
        );
    }

    public function test_direct_under_fourteen_lifecycle_projects_and_revokes_immediately(): void
    {
        $player = $this->minorPlayer(['nickname' => 'Alias Menor']);
        $result = $this->service()->createForPlayer(
            $player,
            $this->admin,
            $this->attributes('alias')
        );

        $this->assertTrue($this->service()->confirm($result['token']));
        $this->service()->approve($result['authorization'], $this->admin);
        $this->assertSame('Alias Menor', $this->displayName($player));

        config(['public_identity.authorization_enabled' => false]);
        $this->assertSame('Participante', $this->displayName($player));

        config(['public_identity.authorization_enabled' => true]);
        $this->service()->revoke($result['authorization'], $this->admin);
        $this->assertSame('Participante', $this->displayName($player));
    }

    public function test_direct_fourteen_to_seventeen_requires_assent_before_name_initial_projection(): void
    {
        $player = $this->minorPlayer(
            ['birth_date' => '2010-09-22', 'nickname' => null],
            ['name' => 'María del Mar', 'lastname' => 'López García']
        );
        $result = $this->service()->createForPlayer(
            $player,
            $this->admin,
            $this->attributes('name_initial')
        );
        $this->assertTrue($this->service()->confirm($result['token']));

        try {
            $this->service()->approve($result['authorization'], $this->admin);
            $this->fail('La aprobación aceptó una autorización 14–17 sin conformidad.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('minor_assent', $exception->errors());
        }
        $this->assertSame('Participante', $this->displayName($player));

        $this->service()->recordMinorAssent($result['authorization'], $this->admin);
        $this->service()->approve($result['authorization'], $this->admin);
        $this->assertSame('María del Mar L.', $this->displayName($player));
    }

    private function service(): PublicIdentityAuthorizationService
    {
        return app(PublicIdentityAuthorizationService::class);
    }

    /** @param array<string, mixed> $playerAttributes */
    private function minorPlayer(
        array $playerAttributes = [],
        array $userAttributes = []
    ): Player {
        $user = User::factory()->create([
            'name' => 'Nombre Menor',
            'lastname' => 'Apellido Privado',
            ...$userAttributes,
        ]);

        return Player::factory()->create([
            'user_id' => $user->id,
            'birth_date' => '2014-09-23',
            'nickname' => 'Alias Menor '.$user->id,
            ...$playerAttributes,
        ])->load('user');
    }

    /** @return array<string, mixed> */
    private function attributes(string $mode, bool $authorityDeclared = true): array
    {
        return [
            'guardian_name' => '  Representante   Legal ',
            'guardian_relationship' => ' Madre ',
            'guardian_email' => 'GUARDIAN@EXAMPLE.TEST',
            'mode' => $mode,
            'guardian_authority_declared' => $authorityDeclared,
            'notice_id' => 'NOTICE-PUBLIC-IDENTITY-MINORS',
            'notice_version' => '1.0.0',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function assertServiceRejected(Player $player, array $attributes, string $field): void
    {
        $count = PublicIdentityAuthorization::query()->count();

        try {
            $this->service()->createForPlayer($player, $this->admin, $attributes);
            $this->fail("La creación directa no rechazó el campo {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertSame($count, PublicIdentityAuthorization::query()->count());
    }

    private function displayName(Player $player): string
    {
        return app(PublicPlayerIdentityService::class)->displayName(
            $player->fresh()->load(['user', 'publicIdentityAuthorizations'])
        );
    }
}
