<?php

namespace Tests\Feature;

use App\Enums\PublicIdentityAuthorizationEventType;
use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Mail\GuardianPublicIdentityConfirmation;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\SchoolProgram;
use App\Models\User;
use App\Services\PublicPlayerIdentityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class PublicIdentityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-06 10:00:00');
        config([
            'public_identity.authorization_enabled' => true,
            'public_identity.notification_enabled' => true,
            'public_identity.confirmation_ttl_hours' => 48,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_school_request_is_optional_separate_versioned_and_persists_before_mail(): void
    {
        Mail::fake();
        $program = SchoolProgram::factory()->publiclyVisible()->enrollmentsOpen()->create();

        $this->postJson('/api/v1/school/enrollments', $this->minorPayload('alias'))
            ->assertCreated()
            ->assertExactJson([
                'message' => 'La solicitud de inscripción se ha recibido correctamente.',
                'data' => null,
            ]);

        $authorization = PublicIdentityAuthorization::query()->sole();
        $enrollment = $authorization->schoolEnrollment;

        $this->assertSame($program->id, $enrollment->school_program_id);
        $this->assertSame('1.1.0', $enrollment->privacy_notice_version);
        $this->assertNotNull($enrollment->privacy_acknowledged_at);
        $this->assertNull($authorization->player_id);
        $this->assertSame(PublicIdentityAuthorizationMode::ALIAS, $authorization->mode);
        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->state);
        $this->assertSame('guardian@example.test', $authorization->guardian_email);
        $this->assertSame('NOTICE-PUBLIC-IDENTITY-MINORS', $authorization->notice_id);
        $this->assertSame('1.0.0', $authorization->notice_version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $authorization->confirmation_token_hash);
        $this->assertDatabaseHas('public_identity_authorization_events', [
            'public_identity_authorization_id' => $authorization->id,
            'type' => PublicIdentityAuthorizationEventType::REQUESTED->value,
        ]);

        Mail::assertSent(GuardianPublicIdentityConfirmation::class, function ($mail) use ($authorization): bool {
            $token = $this->tokenFromUrl($mail->confirmationUrl);
            $this->assertSame(hash('sha256', $token), $authorization->confirmation_token_hash);
            $rendered = $mail->render();
            $this->assertSame(
                rtrim((string) config('app.frontend_url'), '/').'/legal/privacidad',
                $mail->privacyUrl
            );
            $this->assertStringContainsString('href="'.$mail->privacyUrl.'"', $rendered);
            $this->assertStringNotContainsString('Menor Privado', $rendered);
            $this->assertStringNotContainsString('guardian@example.test', $rendered);

            return true;
        });
    }

    public function test_guardian_token_is_private_single_use_and_confirmation_does_not_publish(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('name_initial');
        $token = $this->sentToken();

        $lookup = $this->postJson('/api/v1/public-identity/confirmation/lookup', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.mode', 'name_initial')
            ->assertJsonPath('data.scope', PublicIdentityAuthorization::SCOPE)
            ->assertJsonMissingPath('data.guardian_email')
            ->assertJsonMissingPath('data.participant_name')
            ->assertJsonMissingPath('data.player_id')
            ->assertJsonMissingPath('data.birth_date');
        $this->assertStringNotContainsString('guardian@example.test', $lookup->getContent());

        $this->postJson('/api/v1/public-identity/confirmation/confirm', ['token' => $token])
            ->assertOk()
            ->assertExactJson([
                'message' => 'La decisión se ha registrado correctamente.',
                'data' => ['received' => true],
            ]);

        $authorization->refresh();
        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->state);
        $this->assertNotNull($authorization->guardian_confirmed_at);
        $this->assertNull($authorization->confirmation_token_hash);

        $this->postJson('/api/v1/public-identity/confirmation/confirm', ['token' => $token])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'El enlace no es válido o ya no está disponible.',
                'data' => null,
            ]);
    }

    public function test_under_fourteen_requires_link_confirmation_and_review_then_revokes_immediately(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('alias', '2014-08-07');
        $token = $this->sentToken();
        $this->postJson('/api/v1/public-identity/confirmation/confirm', ['token' => $token])->assertOk();

        $user = User::factory()->create(['name' => 'Menor', 'lastname' => 'Privado']);
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'birth_date' => '2014-08-07',
            'nickname' => '  Alias   Jove ',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.public-identity-authorizations.link-player', $authorization), [
                'player_id' => $player->id,
                'link_confirmed' => '1',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.public-identity-authorizations.approve', $authorization))
            ->assertRedirect();

        $authorization->refresh();
        $this->assertSame(PublicIdentityAuthorizationState::APPROVED, $authorization->state);
        $this->assertSame(1, $authorization->approval_slot);
        $this->assertSame(
            'Alias Jove',
            app(PublicPlayerIdentityService::class)->displayName(
                $player->fresh()->load(['user', 'publicIdentityAuthorizations'])
            )
        );

        $this->actingAs($admin)
            ->post(route('admin.public-identity-authorizations.revoke', $authorization), [
                'private_reason' => 'Retirada solicitada por el representante.',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Participante',
            app(PublicPlayerIdentityService::class)->displayName(
                $player->fresh()->load(['user', 'publicIdentityAuthorizations'])
            )
        );
        $this->assertDatabaseHas('public_identity_authorizations', [
            'id' => $authorization->id,
            'state' => PublicIdentityAuthorizationState::REVOKED->value,
            'approval_slot' => null,
        ]);
    }

    public function test_fourteen_to_seventeen_requires_recorded_assent_and_never_changes_mode(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('name_initial', '2012-08-06');
        $this->postJson('/api/v1/public-identity/confirmation/confirm', [
            'token' => $this->sentToken(),
        ])->assertOk();
        $player = Player::factory()->create(['birth_date' => '2012-08-06']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['player_id' => $player->id, 'link_confirmed' => '1']
        )->assertRedirect();
        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.approve', $authorization),
            ['mode' => 'alias']
        )->assertSessionHasErrors('minor_assent');
        $this->assertSame(
            PublicIdentityAuthorizationMode::NAME_INITIAL,
            $authorization->fresh()->mode
        );

        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.record-assent', $authorization),
            ['assent_confirmed' => '1']
        )->assertRedirect();
        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.approve', $authorization)
        )->assertRedirect();

        $authorization->refresh();
        $this->assertSame(PublicIdentityAuthorizationState::APPROVED, $authorization->state);
        $this->assertNotNull($authorization->minor_assent_recorded_at);
        $this->assertSame($admin->id, $authorization->minor_assent_recorded_by);
    }

    public function test_anonymous_choice_is_explicit_denial_without_mail_and_does_not_block_enrollment(): void
    {
        Mail::fake();
        $this->requestAuthorization('anonymous');
        $authorization = PublicIdentityAuthorization::query()->sole();

        $this->assertSame(PublicIdentityAuthorizationMode::ANONYMOUS, $authorization->mode);
        $this->assertSame(PublicIdentityAuthorizationState::DENIED, $authorization->state);
        $this->assertNull($authorization->confirmation_token_hash);
        $this->assertNotNull($authorization->denied_at);
        $this->assertDatabaseCount('school_enrollments', 1);
        Mail::assertNothingSent();
    }

    public function test_expired_or_denied_token_has_the_same_generic_response(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('alias');
        $token = $this->sentToken();
        $authorization->update(['confirmation_token_expires_at' => CarbonImmutable::now()->subSecond()]);

        $this->postJson('/api/v1/public-identity/confirmation/lookup', ['token' => $token])
            ->assertNotFound();
        $this->assertSame(PublicIdentityAuthorizationState::EXPIRED, $authorization->fresh()->state);

        Mail::fake();
        $this->requestAuthorization('alias', '2014-08-07', 'guardian2@example.test');
        $deniedToken = $this->sentToken();
        $this->postJson('/api/v1/public-identity/confirmation/deny', ['token' => $deniedToken])
            ->assertOk();
        $this->postJson('/api/v1/public-identity/confirmation/lookup', ['token' => $deniedToken])
            ->assertNotFound();
    }

    public function test_feature_disabled_is_fail_closed_and_school_does_not_accept_forged_authorization(): void
    {
        config(['public_identity.authorization_enabled' => false]);
        SchoolProgram::factory()->publiclyVisible()->enrollmentsOpen()->create();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.public_identity_authorization.enabled', false)
            ->assertJsonMissingPath('data.public_identity_authorization.notice_version');

        $this->postJson('/api/v1/school/enrollments', $this->minorPayload('alias'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('public_identity_authorization');
        $this->postJson('/api/v1/public-identity/confirmation/lookup', [
            'token' => str_repeat('a', 64),
        ])->assertNotFound();
        $this->assertDatabaseCount('school_enrollments', 0);
        $this->assertDatabaseCount('public_identity_authorizations', 0);
    }

    public function test_admin_routes_require_an_active_administrator(): void
    {
        $authorization = PublicIdentityAuthorization::factory()->create();
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);

        $this->get(route('admin.public-identity-authorizations.index'))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($user)
            ->get(route('admin.public-identity-authorizations.show', $authorization))
            ->assertForbidden();
        $this->actingAs($inactiveAdmin)
            ->get(route('admin.public-identity-authorizations.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_link_candidates_require_explicit_selection_and_never_infer_identity(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('alias', '2014-08-07');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.public-identity-authorizations.show', $authorization))
            ->assertOk()
            ->assertSee('No hay jugadores compatibles')
            ->assertSee('debe permanecer pendiente');

        $firstUser = User::factory()->create([
            'name' => 'Primera',
            'lastname' => 'Coincidencia',
        ]);
        $first = Player::factory()->create([
            'user_id' => $firstUser->id,
            'birth_date' => '2014-08-07',
            'nickname' => 'Alias uno',
        ]);
        $differentUser = User::factory()->create([
            'name' => 'Fecha',
            'lastname' => 'Diferente',
        ]);
        Player::factory()->create([
            'user_id' => $differentUser->id,
            'birth_date' => '2014-08-08',
        ]);

        $oneCandidate = $this->actingAs($admin)
            ->get(route('admin.public-identity-authorizations.show', $authorization))
            ->assertOk()
            ->assertSee('Primera Coincidencia')
            ->assertSee('Alias uno')
            ->assertDontSee('Fecha Diferente');
        $this->assertStringNotContainsString(
            'value="'.$first->id.'" selected',
            $oneCandidate->getContent()
        );

        $secondUser = User::factory()->create([
            'name' => 'Segunda',
            'lastname' => 'Coincidencia',
        ]);
        $second = Player::factory()->create([
            'user_id' => $secondUser->id,
            'birth_date' => '2014-08-07',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.public-identity-authorizations.show', $authorization))
            ->assertOk()
            ->assertSee('Primera Coincidencia')
            ->assertSee('Segunda Coincidencia');
        $this->assertNull($authorization->fresh()->player_id);
        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->fresh()->state);

        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['player_id' => $first->id]
        )->assertSessionHasErrors('link_confirmed');
        $this->assertNull($authorization->fresh()->player_id);

        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['_token' => 'csrf-test', 'player_id' => $first->id, 'link_confirmed' => '1']
        )->assertRedirect();

        $linked = $authorization->events()
            ->where('type', PublicIdentityAuthorizationEventType::PLAYER_LINKED->value)
            ->sole();
        $this->assertSame($admin->id, $linked->actor_user_id);
        $this->assertSame(CarbonImmutable::now()->toDateTimeString(), $linked->occurred_at->toDateTimeString());
        $this->assertSame(['player_id' => $first->id], $linked->metadata);

        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['player_id' => $second->id, 'link_confirmed' => '1']
        )->assertRedirect();
        $changed = $authorization->events()
            ->where('type', PublicIdentityAuthorizationEventType::PLAYER_LINK_CHANGED->value)
            ->sole();
        $this->assertSame($admin->id, $changed->actor_user_id);
        $this->assertSame([
            'previous_player_id' => $first->id,
            'player_id' => $second->id,
        ], $changed->metadata);
        $this->assertSame($second->id, $authorization->fresh()->player_id);

        $this->postJson('/api/v1/public-identity/confirmation/confirm', [
            'token' => $this->sentToken(),
        ])->assertOk();
        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['player_id' => $first->id, 'link_confirmed' => '1']
        )->assertSessionHasErrors('player_id');
        $this->assertSame($second->id, $authorization->fresh()->player_id);
        $this->assertSame(1, $authorization->events()
            ->where('type', PublicIdentityAuthorizationEventType::PLAYER_LINK_CHANGED->value)
            ->count());
    }

    public function test_database_rejects_invalid_domain_values_and_duplicate_effective_authorization(): void
    {
        $authorization = PublicIdentityAuthorization::factory()->create();

        foreach ([
            'scope' => 'photographs',
            'mode' => 'full_name',
            'state' => 'published',
        ] as $column => $value) {
            try {
                PublicIdentityAuthorization::query()
                    ->whereKey($authorization->id)
                    ->update([$column => $value]);
                $this->fail("La base de datos aceptó {$column} inválido.");
            } catch (QueryException) {
                $this->assertDatabaseHas('public_identity_authorizations', [
                    'id' => $authorization->id,
                    $column => $authorization->getRawOriginal($column),
                ]);
            }
        }

        $player = Player::factory()->create(['birth_date' => '2014-08-07']);
        PublicIdentityAuthorization::factory()->approved()->create(['player_id' => $player->id]);

        $this->expectException(QueryException::class);
        PublicIdentityAuthorization::factory()->approved()->create(['player_id' => $player->id]);
    }

    public function test_unreliable_player_link_is_rejected_and_cannot_be_approved(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('alias', '2014-08-07');
        $player = Player::factory()->create(['birth_date' => '2013-08-07']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.public-identity-authorizations.link-player', $authorization),
            ['player_id' => $player->id, 'link_confirmed' => '1']
        )->assertSessionHasErrors('player_id');
        $this->assertNull($authorization->fresh()->player_id);

        $this->actingAs($admin)
            ->post(route('admin.public-identity-authorizations.approve', $authorization))
            ->assertSessionHasErrors('player_id');
    }

    public function test_mail_failure_keeps_pending_request_and_records_only_sanitized_event(): void
    {
        config(['mail.default' => 'public-identity-test-failure']);
        SchoolProgram::factory()->publiclyVisible()->enrollmentsOpen()->create();

        $this->postJson('/api/v1/school/enrollments', $this->minorPayload('alias'))
            ->assertCreated();

        $authorization = PublicIdentityAuthorization::query()->sole();
        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->state);
        $this->assertDatabaseHas('public_identity_authorization_events', [
            'public_identity_authorization_id' => $authorization->id,
            'type' => PublicIdentityAuthorizationEventType::NOTIFICATION_FAILED->value,
        ]);
        $event = $authorization->events()->where(
            'type',
            PublicIdentityAuthorizationEventType::NOTIFICATION_FAILED->value
        )->sole();
        $this->assertSame(['error_type' => InvalidArgumentException::class], $event->metadata);
        $this->assertStringNotContainsString('guardian@example.test', json_encode($event->metadata));
    }

    public function test_resend_invalidates_previous_token_and_is_rate_limited(): void
    {
        Mail::fake();
        $authorization = $this->requestAuthorization('alias');
        $firstToken = $this->sentToken();
        $admin = User::factory()->admin()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($admin)
                ->post(route('admin.public-identity-authorizations.resend', $authorization))
                ->assertRedirect();
        }

        $this->postJson('/api/v1/public-identity/confirmation/lookup', ['token' => $firstToken])
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('admin.public-identity-authorizations.resend', $authorization))
            ->assertTooManyRequests();
        $this->assertDatabaseCount('public_identity_authorizations', 1);
        $this->assertSame(5, $authorization->events()
            ->where('type', PublicIdentityAuthorizationEventType::RESENT->value)
            ->count());
    }

    public function test_public_token_lookup_is_rate_limited_without_enumerating_subjects(): void
    {
        $token = str_repeat('9', 64);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/v1/public-identity/confirmation/lookup', ['token' => $token])
                ->assertNotFound()
                ->assertJsonMissingPath('errors.token');
        }

        $this->postJson('/api/v1/public-identity/confirmation/lookup', ['token' => $token])
            ->assertTooManyRequests()
            ->assertExactJson([
                'message' => 'Demasiados intentos. Inténtalo de nuevo más tarde.',
                'data' => null,
            ]);
    }

    private function requestAuthorization(
        string $mode,
        string $birthDate = '2014-08-07',
        string $email = 'guardian@example.test'
    ): PublicIdentityAuthorization {
        $program = SchoolProgram::query()->where('is_public', true)->first();
        if ($program === null) {
            SchoolProgram::factory()->publiclyVisible()->enrollmentsOpen()->create();
        } else {
            $program->update(['enrollments_open' => true]);
        }

        $this->postJson('/api/v1/school/enrollments', $this->minorPayload(
            $mode,
            $birthDate,
            $email
        ))->assertCreated();

        return PublicIdentityAuthorization::query()->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function minorPayload(
        string $mode,
        string $birthDate = '2014-08-07',
        string $email = 'GUARDIAN@EXAMPLE.TEST'
    ): array {
        return [
            'participant_name' => 'Menor Privado',
            'participant_birth_date' => $birthDate,
            'contact_phone' => '600 000 000',
            'contact_email' => $email,
            'guardian_name' => 'Representante Privado',
            'guardian_relationship' => 'Madre',
            'privacy_acknowledged' => true,
            'privacy_notice_version' => '1.1.0',
            'public_identity_authorization' => [
                'mode' => $mode,
                'notice_version' => '1.0.0',
                ...($mode === 'anonymous' ? [] : ['guardian_authority_declared' => true]),
            ],
        ];
    }

    private function sentToken(): string
    {
        $mail = Mail::sent(GuardianPublicIdentityConfirmation::class)->last();
        $this->assertNotNull($mail);

        return $this->tokenFromUrl($mail->confirmationUrl);
    }

    private function tokenFromUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_FRAGMENT), $fragment);

        return (string) ($fragment['token'] ?? '');
    }
}
