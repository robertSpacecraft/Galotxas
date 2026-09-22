<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PublicIdentityAuthorizationState;
use App\Models\Player;
use App\Models\ProfileDeclaration;
use App\Models\PublicIdentityAuthorization;
use App\Models\User;
use App\Services\ProfileDeclarationService;
use App\Services\SelfServicePlayerProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ProfileSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nickname_and_license_are_normalized_and_nullable_fields_can_be_cleared(): void
    {
        [$user, $player] = $this->actingPlayer('1990-01-01', [
            'nickname' => null,
            'license_number' => 'LIC-OLD',
        ]);

        $this->patchJson('/api/v1/me/player-profile', [
            'nickname' => "  A\u{0300}lies\t  Esportiu  ",
            'license_number' => "\u{00A0} LIC-42 \u{00A0}",
            'dominant_hand' => '',
        ])->assertOk()
            ->assertJsonPath('data.nickname', 'Àlies Esportiu')
            ->assertJsonPath('data.license_number', 'LIC-42')
            ->assertJsonPath('data.dominant_hand', null);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'nickname' => 'Àlies Esportiu',
            'license_number' => 'LIC-42',
            'dominant_hand' => null,
        ]);

        $this->patchJson('/api/v1/me/player-profile', ['license_number' => ''])
            ->assertOk()
            ->assertJsonPath('data.license_number', null);

        $this->assertNull($player->fresh()->license_number);
        $this->assertSame($user->id, $player->user_id);
    }

    public function test_self_service_rejects_database_collation_and_internal_whitespace_collisions(): void
    {
        Player::factory()->create(['nickname' => 'Álias Deportivo']);
        [, $player] = $this->actingPlayer('1990-01-01', ['nickname' => null]);

        $this->patchJson('/api/v1/me/player-profile', ['nickname' => 'alias deportivo   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nickname');

        Player::factory()->create(['nickname' => 'La Ràpida']);
        $this->patchJson('/api/v1/me/player-profile', ['nickname' => '  La   Ràpida  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nickname');

        $this->assertNull($player->fresh()->nickname);
    }

    public function test_profile_creation_and_update_reject_normalized_nickname_and_license_collisions(): void
    {
        Player::factory()->create([
            'nickname' => 'Alias Reservado',
            'license_number' => 'LIC-RESERVADA',
        ]);
        $newUser = User::factory()->create();
        app(ProfileDeclarationService::class)->recordGeneral($newUser);
        Sanctum::actingAs($newUser);

        $this->postJson('/api/v1/me/player-profile', [
            'level' => 5,
            'nickname' => ' alias   reservado ',
            'license_number' => ' LIC-RESERVADA ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['nickname', 'license_number']);

        [, $player] = $this->actingPlayer('1990-01-01', [
            'nickname' => 'Original',
            'license_number' => 'LIC-ORIGINAL',
        ]);
        $this->patchJson('/api/v1/me/player-profile', [
            'license_number' => ' LIC-RESERVADA ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('license_number');

        $this->assertSame('LIC-ORIGINAL', $player->fresh()->license_number);
    }

    public function test_database_accepts_multiple_null_nicknames_and_rejects_direct_duplicates(): void
    {
        Player::factory()->count(3)->create(['nickname' => null]);
        Player::factory()->create(['nickname' => 'Únic']);

        $this->assertDatabaseCount('players', 4);

        $this->expectException(QueryException::class);
        Player::factory()->create(['nickname' => 'unic']);
    }

    public function test_expected_nickname_constraint_race_is_translated_but_unrelated_failures_are_not(): void
    {
        Player::factory()->create(['nickname' => 'Alias reservado']);
        [$user] = $this->actingPlayer('1990-01-01', ['nickname' => null]);

        try {
            app(SelfServicePlayerProfileService::class)->update($user, [
                'nickname' => 'alias reservado',
            ]);
            $this->fail('La carrera de unicidad debía convertirse en error de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nickname', $exception->errors());
        }
    }

    public function test_expected_license_constraint_race_is_translated_to_field_validation(): void
    {
        Player::factory()->create(['license_number' => 'LIC-RESERVADA']);
        [$user] = $this->actingPlayer('1990-01-01', ['license_number' => null]);

        try {
            app(SelfServicePlayerProfileService::class)->update($user, [
                'license_number' => 'LIC-RESERVADA',
            ]);
            $this->fail('La carrera de unicidad debía convertirse en error de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('license_number', $exception->errors());
        }
    }

    public function test_slug_is_assigned_on_creation_and_stays_immutable_after_self_edit(): void
    {
        $user = User::factory()->create();
        app(ProfileDeclarationService::class)->recordGeneral($user);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/me/player-profile', [
            'nickname' => 'Alias Inicial',
            'level' => 5,
        ])->assertCreated();

        $slug = $created->json('data.slug');
        $this->assertNotSame('', $slug);

        $this->patchJson('/api/v1/me/player-profile', ['nickname' => 'Alias Nuevo'])
            ->assertOk()
            ->assertJsonPath('data.slug', $slug);
    }

    public function test_closed_patch_rejects_forbidden_and_unknown_fields_without_mutation(): void
    {
        [, $player] = $this->actingPlayer('1990-01-01', ['level' => 4]);

        foreach (['user_id', 'email', 'name', 'lastname', 'role', 'dni', 'gender', 'level', 'active', 'slug', 'authorization_id', 'future_field'] as $field) {
            $this->patchJson('/api/v1/me/player-profile', [$field => 'manipulated'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('payload');
        }

        $this->assertSame(4, $player->fresh()->level);
    }

    public function test_profile_text_fields_reject_non_text_json_values_instead_of_normalizing_them_to_null(): void
    {
        [, $player] = $this->actingPlayer('1990-01-01', [
            'nickname' => 'Original',
            'license_number' => 'LIC-ORIGINAL',
            'dominant_hand' => 'right',
            'notes' => 'Nota original',
        ]);

        $this->patchJson('/api/v1/me/player-profile', [
            'nickname' => ['Alias'],
            'license_number' => ['LIC-2'],
            'dominant_hand' => ['left'],
            'notes' => ['Nota'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nickname',
                'license_number',
                'dominant_hand',
                'notes',
            ]);

        $player->refresh();
        $this->assertSame('Original', $player->nickname);
        $this->assertSame('LIC-ORIGINAL', $player->license_number);
        $this->assertSame('right', $player->dominant_hand);
        $this->assertSame('Nota original', $player->notes);
    }

    public function test_unrelated_database_failures_are_not_translated_as_uniqueness_validation(): void
    {
        [$user] = $this->actingPlayer('1990-01-01');

        $this->expectException(QueryException::class);

        app(SelfServicePlayerProfileService::class)->update($user, [
            'notes' => str_repeat('x', 70_000),
        ]);
    }

    public function test_null_to_adult_and_null_to_minor_require_confirmation_and_record_dob_evidence(): void
    {
        [, $adult] = $this->actingPlayer(null);
        $adultDate = CarbonImmutable::today()->subYears(25)->toDateString();

        $this->patchJson('/api/v1/me/player-profile', ['birth_date' => $adultDate])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date_confirmed', 'profile_notice_id']);

        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->dobConfirmationPayload(),
            'birth_date' => $adultDate,
        ])->assertOk()
            ->assertJsonPath('data.public_identity.status', 'adult_alias');

        [, $minor] = $this->actingPlayer(null);
        $minorDate = CarbonImmutable::today()->subYears(12)->toDateString();
        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->dobConfirmationPayload(),
            'birth_date' => $minorDate,
        ])->assertOk()
            ->assertJsonPath('data.public_identity.display_name', 'Participante')
            ->assertJsonPath('data.public_identity.status', 'minor_no_effective_authorization');

        $this->assertDatabaseCount('profile_declarations', 4);
        $this->assertSame(2, ProfileDeclaration::query()
            ->where('declaration_kind', ProfileDeclarationService::BIRTH_DATE)
            ->count());
        $this->assertSame($adultDate, $adult->fresh()->birth_date?->format('Y-m-d'));
        $this->assertSame($minorDate, $minor->fresh()->birth_date?->format('Y-m-d'));
    }

    public function test_adult_to_adult_is_allowed_without_dni_but_adult_to_minor_is_rejected(): void
    {
        [, $player] = $this->actingPlayer('1990-01-01', ['dni' => null]);

        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->dobConfirmationPayload(),
            'birth_date' => '1991-02-03',
        ])->assertOk();

        $this->assertNull($player->fresh()->dni);

        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->dobConfirmationPayload(),
            'birth_date' => CarbonImmutable::today()->subYears(12)->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');

        $this->assertSame('1991-02-03', $player->fresh()->birth_date?->format('Y-m-d'));
    }

    public function test_known_minor_cannot_change_date_become_adult_or_clear_then_bypass(): void
    {
        [, $player] = $this->actingPlayer(CarbonImmutable::today()->subYears(12)->toDateString());

        foreach ([
            CarbonImmutable::today()->subYears(13)->toDateString(),
            '1990-01-01',
            null,
        ] as $birthDate) {
            $this->patchJson('/api/v1/me/player-profile', [
                ...$this->dobConfirmationPayload(),
                'birth_date' => $birthDate,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('birth_date');
        }

        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->dobConfirmationPayload(),
            'birth_date' => '1990-01-01',
        ])->assertUnprocessable();

        $this->assertSame(
            CarbonImmutable::today()->subYears(12)->toDateString(),
            $player->fresh()->birth_date?->format('Y-m-d')
        );
    }

    public function test_known_adult_cannot_clear_birth_date_and_unchanged_date_needs_no_confirmation(): void
    {
        [, $player] = $this->actingPlayer('1990-01-01');

        $this->patchJson('/api/v1/me/player-profile', ['birth_date' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');

        $before = ProfileDeclaration::query()
            ->where('declaration_kind', ProfileDeclarationService::BIRTH_DATE)
            ->count();

        $this->patchJson('/api/v1/me/player-profile', [
            'birth_date' => '1990-01-01',
            'dominant_hand' => 'left',
        ])->assertOk();

        $this->assertSame($before, ProfileDeclaration::query()
            ->where('declaration_kind', ProfileDeclarationService::BIRTH_DATE)
            ->count());
        $this->assertSame('left', $player->fresh()->dominant_hand);
    }

    public function test_pending_or_approved_authorization_blocks_dob_and_minor_nickname_but_not_other_fields(): void
    {
        foreach ([PublicIdentityAuthorizationState::PENDING, PublicIdentityAuthorizationState::APPROVED] as $state) {
            [, $player] = $this->actingPlayer(
                CarbonImmutable::today()->subYears(12)->toDateString(),
                ['nickname' => 'Alias '.$state->value]
            );
            $factory = PublicIdentityAuthorization::factory()->for($player);
            ($state === PublicIdentityAuthorizationState::APPROVED ? $factory->approved() : $factory)
                ->create(['state' => $state]);

            $this->patchJson('/api/v1/me/player-profile', ['nickname' => 'Cambio '.$state->value])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('nickname');

            $this->patchJson('/api/v1/me/player-profile', [
                ...$this->dobConfirmationPayload(),
                'birth_date' => CarbonImmutable::today()->subYears(13)->toDateString(),
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('birth_date');

            $this->patchJson('/api/v1/me/player-profile', [
                'dominant_hand' => 'both',
                'license_number' => 'LIC-'.$state->value,
            ])->assertOk();

            $this->assertSame('both', $player->fresh()->dominant_hand);
        }
    }

    public function test_linked_authorization_also_blocks_adult_dob_change_without_mutating_evidence(): void
    {
        [, $player] = $this->actingPlayer('1990-01-01');
        $authorization = PublicIdentityAuthorization::factory()->for($player)->create();

        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->dobConfirmationPayload(),
            'birth_date' => '1991-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');

        $this->assertSame(PublicIdentityAuthorizationState::PENDING, $authorization->fresh()->state);
        $this->assertSame('1990-01-01', $player->fresh()->birth_date?->format('Y-m-d'));
    }

    public function test_registration_requires_current_general_declaration_and_persists_account_evidence(): void
    {
        $base = [
            'name' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson('/api/v1/auth/register', $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_declaration_accepted', 'profile_notice_id', 'profile_notice_version']);

        $this->postJson('/api/v1/auth/register', [
            ...$base,
            ...$this->generalDeclarationPayload(),
            'profile_declaration_accepted' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('profile_declaration_accepted');

        $this->postJson('/api/v1/auth/register', [
            ...$base,
            ...$this->generalDeclarationPayload(),
            'profile_notice_version' => '9.9.9',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('profile_notice_version');

        $this->postJson('/api/v1/auth/register', [
            ...$base,
            ...$this->generalDeclarationPayload(),
        ])->assertCreated()
            ->assertJsonPath('data.user.profile_declaration_required', false);

        $user = User::query()->where('email', 'ada@example.test')->sole();
        $evidence = ProfileDeclaration::query()->sole();
        $this->assertSame($user->id, $evidence->actor_user_id);
        $this->assertNull($evidence->subject_player_id);
        $this->assertSame(ProfileDeclarationService::GENERAL, $evidence->declaration_kind);
        $this->assertSame('NOTICE-ACCOUNT-PROFILE', $evidence->notice_id);
        $this->assertSame('1.0.0', $evidence->notice_version);
        $this->assertNotNull($evidence->declared_at);
        $this->assertSame([
            'id',
            'actor_user_id',
            'subject_player_id',
            'declaration_kind',
            'notice_id',
            'notice_version',
            'declared_at',
        ], Schema::getColumnListing('profile_declarations'));
    }

    public function test_profile_creation_requires_dob_confirmation_only_when_dob_is_supplied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/player-profile', [
            ...$this->generalDeclarationPayload(),
            'level' => 5,
            'birth_date' => '1990-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date_confirmed');

        $this->postJson('/api/v1/me/player-profile', [
            ...$this->generalDeclarationPayload(),
            'birth_date_confirmed' => true,
            'level' => 5,
            'birth_date' => '1990-01-01',
        ])->assertCreated();

        $this->assertSame(2, ProfileDeclaration::query()->count());
    }

    public function test_first_relevant_write_requires_general_declaration_and_recognized_evidence_is_reused(): void
    {
        [, $player] = $this->actingPlayer('1990-01-01', [], false);

        $this->patchJson('/api/v1/me/player-profile', ['dominant_hand' => 'left'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_declaration_accepted');

        $this->patchJson('/api/v1/me/player-profile', [
            ...$this->generalDeclarationPayload(),
            'dominant_hand' => 'left',
        ])->assertOk();

        $this->patchJson('/api/v1/me/player-profile', ['license_number' => 'LIC-REUSE'])
            ->assertOk();

        $this->assertSame(1, ProfileDeclaration::query()
            ->where('declaration_kind', ProfileDeclarationService::GENERAL)
            ->count());
        $this->assertSame('LIC-REUSE', $player->fresh()->license_number);
    }

    public function test_private_diagnostic_exposes_all_coarse_statuses_without_authorization_details(): void
    {
        config(['public_identity.authorization_enabled' => true]);

        $cases = [];
        $cases[] = $this->diagnosticFor(null, ['nickname' => 'Alias'], 'birth_date_unknown');
        $cases[] = $this->diagnosticFor('1990-01-01', ['nickname' => 'Alias adult'], 'adult_alias');
        $cases[] = $this->diagnosticFor('1990-01-01', ['nickname' => null], 'adult_name_initial');

        [$user, $adultWithoutIdentity] = $this->actingPlayer('1990-01-01', ['nickname' => null]);
        $user->update(['lastname' => '']);
        $cases[] = $this->getJson('/api/v1/me/player-profile')
            ->assertOk()
            ->assertJsonPath('data.public_identity.status', 'adult_no_publishable_identity');

        $minorDate = CarbonImmutable::today()->subYears(12)->toDateString();
        $cases[] = $this->diagnosticFor($minorDate, ['nickname' => 'Menor'], 'minor_no_effective_authorization');

        [, $authorizedMinor] = $this->actingPlayer($minorDate, ['nickname' => 'Alias menor']);
        PublicIdentityAuthorization::factory()->for($authorizedMinor)->approved()->create();
        $response = $this->getJson('/api/v1/me/player-profile')
            ->assertOk()
            ->assertJsonPath('data.public_identity.status', 'minor_effective_authorization')
            ->assertJsonPath('data.public_identity.display_name', 'Alias menor');

        $serialized = $response->getContent();
        foreach (['guardian_email', 'guardian_name', 'authorization_id', 'notice_version', 'approved_at'] as $privateField) {
            $this->assertStringNotContainsString($privateField, $serialized);
        }

        $this->assertNotEmpty($cases);
        $this->assertSame($adultWithoutIdentity->id, $user->player->id);
    }

    public function test_declaration_migration_does_not_fabricate_historical_rows(): void
    {
        $this->assertDatabaseCount('profile_declarations', 0);
    }

    public function test_declaration_migration_is_strictly_forward_only(): void
    {
        $migration = require database_path('migrations/2026_09_21_000001_create_profile_declarations_table.php');

        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    /** @return array{User, Player} */
    private function actingPlayer(
        ?string $birthDate,
        array $attributes = [],
        bool $withGeneralDeclaration = true,
    ): array {
        $user = User::factory()->create();
        $player = Player::factory()->for($user)->create([
            'birth_date' => $birthDate,
            'nickname' => $attributes['nickname'] ?? 'Alias '.strtolower(fake()->unique()->bothify('????????')),
            ...$attributes,
        ]);

        if ($withGeneralDeclaration) {
            app(ProfileDeclarationService::class)->recordGeneral($user, $player);
        }

        Sanctum::actingAs($user);

        return [$user, $player];
    }

    private function diagnosticFor(?string $birthDate, array $attributes, string $status): mixed
    {
        $this->actingPlayer($birthDate, $attributes);

        return $this->getJson('/api/v1/me/player-profile')
            ->assertOk()
            ->assertJsonPath('data.public_identity.status', $status);
    }

    /** @return array<string, mixed> */
    private function generalDeclarationPayload(): array
    {
        return [
            'profile_declaration_accepted' => true,
            'profile_notice_id' => 'NOTICE-ACCOUNT-PROFILE',
            'profile_notice_version' => '1.0.0',
        ];
    }

    /** @return array<string, mixed> */
    private function dobConfirmationPayload(): array
    {
        return [
            'birth_date_confirmed' => true,
            'profile_notice_id' => 'NOTICE-ACCOUNT-PROFILE',
            'profile_notice_version' => '1.0.0',
        ];
    }
}
