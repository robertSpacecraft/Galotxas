<?php

namespace Tests\Feature;

use App\Enums\OfficialIdentityProjection;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\CategoryEntry;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\Team;
use App\Models\User;
use App\Services\OfficialResultIdentitySnapshotService;
use App\Services\PublicPlayerIdentityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialResultIdentitySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['public_identity.authorization_enabled' => true]);
    }

    public function test_adult_projection_prefers_alias_then_name_and_initial(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-04 12:00:00');
        $withAlias = $this->player('  Clara ', '  García López ', '  La   Roja  ', '1990-01-01');
        $withoutAlias = $this->player('  Núria del Mar ', '  Pérez Soler ', '   ', '1990-01-01');
        $service = app(PublicPlayerIdentityService::class);

        $alias = $service->resolve($withAlias, $asOf);
        $initial = $service->resolve($withoutAlias, $asOf);

        $this->assertSame(OfficialIdentityProjection::ALIAS, $alias->projection);
        $this->assertSame('La Roja', $alias->displayName);
        $this->assertSame(OfficialIdentityProjection::NAME_INITIAL, $initial->projection);
        $this->assertSame('Núria del Mar P.', $initial->displayName);
    }

    public function test_minor_effective_modes_and_fail_closed_states_are_explicit(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-04 12:00:00');
        $service = app(PublicPlayerIdentityService::class);

        $aliasMinor = $this->player('Menor', 'Alias', '  Pilota   Veloç ', '2014-01-01');
        $this->authorization($aliasMinor, 'alias');
        $resolvedAlias = $service->resolve($aliasMinor->fresh(['user', 'publicIdentityAuthorizations']), $asOf);
        $this->assertSame(OfficialIdentityProjection::ALIAS, $resolvedAlias->projection);
        $this->assertSame('Pilota Veloç', $resolvedAlias->displayName);

        $initialMinor = $this->player('Marina', 'Sanchis Vidal', 'Alias privado', '2014-01-01');
        $this->authorization($initialMinor, 'name_initial');
        $resolvedInitial = $service->resolve($initialMinor->fresh(['user', 'publicIdentityAuthorizations']), $asOf);
        $this->assertSame(OfficialIdentityProjection::NAME_INITIAL, $resolvedInitial->projection);
        $this->assertSame('Marina S.', $resolvedInitial->displayName);

        $anonymousMinor = $this->player('Anónima', 'Siempre', 'No publicar', '2014-01-01');
        $this->assertAnonymous($service->resolve($anonymousMinor, $asOf));

        $aliasWithoutNickname = $this->player('Menor', 'Sin Alias', '   ', '2014-01-01');
        $this->authorization($aliasWithoutNickname, 'alias');
        $this->assertAnonymous($service->resolve(
            $aliasWithoutNickname->fresh(['user', 'publicIdentityAuthorizations']),
            $asOf,
        ));

        foreach (['revoked', 'expired'] as $state) {
            $minor = $this->player('Estado', $state, 'Alias sensible', '2014-01-01');
            $authorization = $this->authorization($minor, 'alias');
            $authorization->forceFill([
                'state' => $state,
                'approval_slot' => null,
                'revoked_at' => $state === 'revoked' ? now() : null,
                'expires_at' => $state === 'expired' ? now()->subDay() : null,
            ])->save();
            $this->assertAnonymous($service->resolve(
                $minor->fresh(['user', 'publicIdentityAuthorizations']),
                $asOf,
            ));
        }
    }

    public function test_identity_uses_the_exact_as_of_adulthood_boundary(): void
    {
        $player = $this->player('Justo', 'Límite', 'Alias de adulto', '2008-09-04');
        $service = app(PublicPlayerIdentityService::class);

        $before = $service->resolve($player, CarbonImmutable::parse('2026-09-03 23:59:59'));
        $atBoundary = $service->resolve($player, CarbonImmutable::parse('2026-09-04 00:00:00'));

        $this->assertSame(OfficialIdentityProjection::ANONYMOUS, $before->projection);
        $this->assertSame(OfficialIdentityProjection::ALIAS, $atBoundary->projection);
        $this->assertSame('Alias de adulto', $atBoundary->displayName);
    }

    public function test_entry_snapshots_keep_minimal_internal_and_public_identities(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-04');
        $player = $this->player('  Alba  ', '  Martínez Costa  ', null, '1990-01-01');
        $entry = CategoryEntry::factory()->playerEntry()->create([
            'player_id' => $player->id,
            'status' => 'approved',
        ])->load('player.user', 'player.publicIdentityAuthorizations');
        $snapshot = app(OfficialResultIdentitySnapshotService::class)->snapshot($entry, $asOf);

        $this->assertSame(OfficialIdentityProjection::NAME_INITIAL, $snapshot->projection);
        $this->assertSame('Alba Martínez Costa', $snapshot->displayName);
        $this->assertSame('Alba M.', $snapshot->publicDisplayName);

        $team = Team::factory()->create(['name' => '  Equip   Blau  ']);
        $teamEntry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $team->category_id,
            'team_id' => $team->id,
            'status' => 'approved',
        ])->load('team');
        $teamSnapshot = app(OfficialResultIdentitySnapshotService::class)->snapshot($teamEntry, $asOf);
        $this->assertSame(OfficialIdentityProjection::TEAM_NAME, $teamSnapshot->projection);
        $this->assertSame('Equip Blau', $teamSnapshot->displayName);
        $this->assertSame('Equip Blau', $teamSnapshot->publicDisplayName);

        $serialized = json_encode([
            'player' => get_object_vars($snapshot),
            'team' => get_object_vars($teamSnapshot),
        ], JSON_THROW_ON_ERROR);
        foreach (['dni', 'email', 'birth_date', 'license', 'guardian', 'private_reason', 'token', 'photo'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $serialized);
        }
    }

    public function test_snapshot_rejects_values_over_255_characters_without_truncating(): void
    {
        $player = $this->player(
            str_repeat('N', 150),
            str_repeat('A', 150),
            null,
            '1990-01-01',
        );
        $entry = CategoryEntry::factory()->playerEntry()->create([
            'player_id' => $player->id,
            'status' => 'approved',
        ])->load('player.user', 'player.publicIdentityAuthorizations');

        $this->expectException(OfficialResultSourceIntegrityException::class);
        app(OfficialResultIdentitySnapshotService::class)->snapshot(
            $entry,
            CarbonImmutable::parse('2026-09-04'),
        );
    }

    private function player(
        string $name,
        string $lastname,
        ?string $nickname,
        string $birthDate,
    ): Player {
        $user = User::factory()->create(compact('name', 'lastname'));

        return Player::factory()->create([
            'user_id' => $user->id,
            'nickname' => $nickname,
            'birth_date' => $birthDate,
            'active' => true,
        ])->load('user', 'publicIdentityAuthorizations');
    }

    private function authorization(Player $player, string $mode): PublicIdentityAuthorization
    {
        return PublicIdentityAuthorization::factory()->approved()->create([
            'player_id' => $player->id,
            'mode' => $mode,
        ]);
    }

    private function assertAnonymous($identity): void
    {
        $this->assertSame(OfficialIdentityProjection::ANONYMOUS, $identity->projection);
        $this->assertSame('Participante', $identity->displayName);
    }
}
