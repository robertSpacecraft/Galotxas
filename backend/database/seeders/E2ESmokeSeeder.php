<?php

namespace Database\Seeders;

use App\Enums\CategoryGender;
use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipType;
use App\Enums\CmsBlockType;
use App\Enums\CmsPageStatus;
use App\Enums\GameMatchStatus;
use App\Enums\PlayerGender;
use App\Enums\PublicIdentityAuthorizationEventType;
use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Enums\SchoolDayOfWeek;
use App\Enums\SchoolEnrollmentStatus;
use App\Enums\SeasonStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\CmsPage;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\PublicIdentityAuthorizationEvent;
use App\Models\Round;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Models\SchoolSchedule;
use App\Models\Season;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class E2ESmokeSeeder extends Seeder
{
    public const PASSWORD = 'E2E-password-123!';

    public const UNDER_14_IDENTITY_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public const TEEN_IDENTITY_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public const EXPIRED_IDENTITY_TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public const DENIED_IDENTITY_TOKEN = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

    public function run(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! app()->environment('e2e') || $database !== 'galotxas_e2e') {
            throw new RuntimeException(
                'E2ESmokeSeeder solo puede ejecutarse con APP_ENV=e2e sobre la base galotxas_e2e.'
            );
        }

        DB::transaction(function (): void {
            User::query()->updateOrCreate(
                ['email' => 'admin.e2e@example.test'],
                [
                    'name' => 'Admin',
                    'lastname' => 'E2E',
                    'password' => Hash::make(self::PASSWORD),
                    'role' => 'admin',
                    'active' => true,
                ]
            );

            $homeUser = $this->createPlayerUser(
                'player1.e2e@example.test',
                'Jugador',
                'Uno E2E',
                'Pilotari E2E 1',
                'pilotari-e2e-1',
                'E2E00001A',
                'E2E-LIC-001',
                'right'
            );
            $awayUser = $this->createPlayerUser(
                'player2.e2e@example.test',
                'Jugador',
                'Dos E2E',
                'Pilotari E2E 2',
                'pilotari-e2e-2',
                'E2E00002B',
                'E2E-LIC-002',
                'left'
            );

            $season = Season::query()->updateOrCreate(
                ['name' => 'Temporada E2E 2026'],
                [
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'status' => SeasonStatus::ACTIVE->value,
                ]
            );
            $season->is_public = true;
            $season->save();

            $championship = Championship::query()->updateOrCreate(
                ['slug' => 'campeonato-individual-e2e'],
                [
                    'season_id' => $season->id,
                    'name' => 'Campeonato Individual E2E',
                    'description' => 'Escenario aislado para las pruebas de humo E2E.',
                    'type' => ChampionshipType::SINGLES->value,
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-08-31',
                    'status' => 'active',
                    'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
                    'registration_starts_at' => null,
                    'registration_ends_at' => null,
                ]
            );
            $championship->is_public = true;
            $championship->save();

            $category = Category::query()->updateOrCreate(
                ['slug' => 'individual-e2e'],
                [
                    'championship_id' => $championship->id,
                    'name' => 'Individual E2E',
                    'level' => 5,
                    'gender' => CategoryGender::MALE->value,
                    'description' => 'Categoría determinista para Playwright.',
                    'status' => 'active',
                ]
            );
            $category->is_public = true;
            $category->save();

            $homePlayer = $homeUser->player;
            $awayPlayer = $awayUser->player;

            foreach ([$homePlayer, $awayPlayer] as $player) {
                CategoryRegistration::query()->updateOrCreate(
                    ['category_id' => $category->id, 'player_id' => $player->id],
                    ['status' => 'approved']
                );
            }

            $homeEntry = CategoryEntry::query()->updateOrCreate(
                ['category_id' => $category->id, 'player_id' => $homePlayer->id],
                ['entry_type' => 'player', 'team_id' => null, 'status' => 'approved']
            );
            $awayEntry = CategoryEntry::query()->updateOrCreate(
                ['category_id' => $category->id, 'player_id' => $awayPlayer->id],
                ['entry_type' => 'player', 'team_id' => null, 'status' => 'approved']
            );

            $venue = Venue::query()->updateOrCreate(
                ['name' => 'Pista E2E'],
                [
                    'location' => 'Entorno aislado Playwright',
                    'description' => 'No pertenece a los datos de desarrollo.',
                ]
            );

            $confirmationRound = $this->createRound($category, 'Jornada E2E Confirmación', 1);
            $reviewRound = $this->createRound($category, 'Jornada E2E Discrepancia', 2);

            $this->createMatch(
                $confirmationRound,
                $venue,
                $homeEntry,
                $awayEntry,
                '2026-08-01 18:00:00'
            );
            $this->createMatch(
                $reviewRound,
                $venue,
                $homeEntry,
                $awayEntry,
                '2026-08-02 18:00:00'
            );

            $page = CmsPage::query()->updateOrCreate(
                ['slug' => 'e2e-publicada'],
                [
                    'title' => 'Contenido E2E publicado',
                    'status' => CmsPageStatus::PUBLISHED->value,
                    'published_at' => '2026-01-01 10:00:00',
                    'seo_title' => 'Contenido E2E publicado',
                    'seo_description' => 'Página pública determinista del escenario Playwright.',
                ]
            );

            $page->blocks()->updateOrCreate(
                ['type' => CmsBlockType::HEADING->value, 'sort_order' => 10],
                ['data' => ['text' => 'Escenario público E2E', 'level' => 2]]
            );
            $page->blocks()->updateOrCreate(
                ['type' => CmsBlockType::TEXT->value, 'sort_order' => 20],
                ['data' => ['text' => 'Este contenido procede exclusivamente de la base temporal E2E.']]
            );

            $this->createClubCmsPage(
                'nosotros',
                'Quiénes somos E2E',
                'Identidad institucional ficticia E2E'
            );
            $this->createClubCmsPage(
                'contacto',
                'Contacto E2E',
                'Canales de contacto ficticios E2E'
            );
            $this->createClubCmsPage(
                'federarse',
                'Federarse E2E',
                'Información federativa ficticia E2E'
            );
            $this->createClubCmsPage(
                'documentos',
                'Documentos E2E',
                'Documentación institucional ficticia E2E'
            );

            $schoolLocation = SchoolLocation::query()->updateOrCreate(
                ['name' => 'Pista Escuela E2E'],
                [
                    'locality' => 'Monóvar',
                    'address' => 'Calle Escuela, 1',
                    'is_active' => true,
                    'sort_order' => 10,
                    'admin_notes' => 'Ubicación exclusiva del escenario E2E.',
                ]
            );

            $schoolProgram = SchoolProgram::query()->updateOrCreate(
                ['name' => 'Escuela de Galotxas E2E'],
                [
                    'public_description' => 'Programa operativo ficticio para validar la experiencia E2E.',
                    'enrollment_information' => 'Completa la solicitud y el equipo de Escuela revisará los datos antes de activarla.',
                    'is_public' => true,
                    'enrollments_open' => true,
                    'default_school_location_id' => $schoolLocation->id,
                    'contact_phone' => '600 111 222',
                    'contact_email' => 'escuela.e2e@example.test',
                    'sort_order' => 10,
                ]
            );

            $schoolMinorLevel = SchoolLevel::query()->updateOrCreate(
                [
                    'school_program_id' => $schoolProgram->id,
                    'name' => 'Iniciación E2E',
                ],
                [
                    'minimum_age' => 8,
                    'maximum_age' => 17,
                    'is_active' => true,
                    'is_public' => true,
                    'sort_order' => 10,
                ]
            );

            $schoolAdultLevel = SchoolLevel::query()->updateOrCreate(
                [
                    'school_program_id' => $schoolProgram->id,
                    'name' => 'Adultos E2E',
                ],
                [
                    'minimum_age' => 18,
                    'maximum_age' => null,
                    'is_active' => true,
                    'is_public' => true,
                    'sort_order' => 20,
                ]
            );

            SchoolSchedule::query()->updateOrCreate(
                [
                    'school_level_id' => $schoolMinorLevel->id,
                    'school_location_id' => $schoolLocation->id,
                    'day_of_week' => SchoolDayOfWeek::TUESDAY->value,
                    'starts_at' => '17:00:00',
                ],
                [
                    'ends_at' => '18:30:00',
                    'is_active' => true,
                    'sort_order' => 10,
                ]
            );

            SchoolSchedule::query()->updateOrCreate(
                [
                    'school_level_id' => $schoolAdultLevel->id,
                    'school_location_id' => $schoolLocation->id,
                    'day_of_week' => SchoolDayOfWeek::THURSDAY->value,
                    'starts_at' => '19:00:00',
                ],
                [
                    'ends_at' => '20:30:00',
                    'is_active' => true,
                    'sort_order' => 10,
                ]
            );

            $this->seedPublicIdentityScenario($schoolProgram, $venue);
        });
    }

    private function seedPublicIdentityScenario(
        SchoolProgram $schoolProgram,
        Venue $venue
    ): void {
        $underFourteenUser = $this->createPlayerUser(
            'minor-under14.e2e@example.test',
            'Lia',
            'Ñíguez E2E',
            'Alias Menor E2E',
            'alias-menor-e2e',
            'E2E00003C',
            'E2E-LIC-003',
            'right',
            '2014-08-07'
        );
        $teenUser = $this->createPlayerUser(
            'minor-teen.e2e@example.test',
            'Noa',
            'Écija E2E',
            '',
            'nombre-inicial-menor-e2e',
            'E2E00004D',
            'E2E-LIC-004',
            'left',
            '2010-08-07'
        );
        $adultUser = $this->createPlayerUser(
            'identity-adult.e2e@example.test',
            'Adulto',
            'Identidad E2E',
            'Rival Identidad E2E',
            'rival-identidad-e2e',
            'E2E00005E',
            'E2E-LIC-005',
            'right'
        );

        $season = Season::query()->create([
            'name' => 'Temporada Identidad E2E 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => SeasonStatus::FINISHED->value,
        ]);
        $season->forceFill(['is_public' => true])->save();
        $championship = Championship::query()->create([
            'season_id' => $season->id,
            'name' => 'Campeonato Identidad Menores E2E',
            'slug' => 'identidad-menores-e2e',
            'description' => 'Fixture aislada de identidad pública verificable.',
            'type' => ChampionshipType::SINGLES->value,
            'start_date' => '2025-07-01',
            'end_date' => '2025-08-31',
            'status' => 'finished',
            'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
        ]);
        $championship->forceFill(['is_public' => true])->save();
        $category = Category::query()->create([
            'championship_id' => $championship->id,
            'name' => 'Identidad Menores E2E',
            'slug' => 'identidad-menores-e2e',
            'level' => 5,
            'gender' => CategoryGender::MIXED->value,
            'description' => 'Categoría aislada para verificar proyecciones de menores.',
            'status' => 'active',
        ]);
        $category->forceFill(['is_public' => true])->save();

        $entries = collect([$underFourteenUser->player, $teenUser->player, $adultUser->player])
            ->mapWithKeys(function (Player $player) use ($category): array {
                CategoryRegistration::query()->create([
                    'category_id' => $category->id,
                    'player_id' => $player->id,
                    'status' => 'approved',
                ]);

                $entry = CategoryEntry::query()->create([
                    'category_id' => $category->id,
                    'player_id' => $player->id,
                    'entry_type' => 'player',
                    'team_id' => null,
                    'status' => 'approved',
                ]);

                return [$player->slug => $entry];
            });

        $underFourteenRound = $this->createRound($category, 'Identidad menor de 14 E2E', 1);
        $teenRound = $this->createRound($category, 'Identidad 14 a 17 E2E', 2);
        $this->createMatch(
            $underFourteenRound,
            $venue,
            $entries['alias-menor-e2e'],
            $entries['rival-identidad-e2e'],
            '2025-08-01 18:00:00'
        );
        $this->createMatch(
            $teenRound,
            $venue,
            $entries['nombre-inicial-menor-e2e'],
            $entries['rival-identidad-e2e'],
            '2025-08-02 18:00:00'
        );

        $underFourteenEnrollment = $this->createIdentityEnrollment(
            $schoolProgram,
            'Menor Alias E2E',
            '2014-08-07',
            'guardian-under14.e2e@example.test'
        );
        $teenEnrollment = $this->createIdentityEnrollment(
            $schoolProgram,
            'Menor Inicial E2E',
            '2010-08-07',
            'guardian-teen.e2e@example.test'
        );
        $expiredEnrollment = $this->createIdentityEnrollment(
            $schoolProgram,
            'Menor Expirado E2E',
            '2014-08-07',
            'guardian-expired.e2e@example.test'
        );
        $deniedEnrollment = $this->createIdentityEnrollment(
            $schoolProgram,
            'Menor Denegación E2E',
            '2014-08-07',
            'guardian-denied.e2e@example.test'
        );

        $this->createPendingIdentityAuthorization(
            $underFourteenEnrollment,
            $underFourteenUser->player,
            PublicIdentityAuthorizationMode::ALIAS,
            self::UNDER_14_IDENTITY_TOKEN
        );
        $this->createPendingIdentityAuthorization(
            $teenEnrollment,
            $teenUser->player,
            PublicIdentityAuthorizationMode::NAME_INITIAL,
            self::TEEN_IDENTITY_TOKEN
        );
        $this->createPendingIdentityAuthorization(
            $expiredEnrollment,
            null,
            PublicIdentityAuthorizationMode::ALIAS,
            self::EXPIRED_IDENTITY_TOKEN,
            CarbonImmutable::now()->subDay()
        );
        $this->createPendingIdentityAuthorization(
            $deniedEnrollment,
            null,
            PublicIdentityAuthorizationMode::ALIAS,
            self::DENIED_IDENTITY_TOKEN
        );

    }

    private function createIdentityEnrollment(
        SchoolProgram $program,
        string $participantName,
        string $birthDate,
        string $guardianEmail
    ): SchoolEnrollment {
        $enrollment = new SchoolEnrollment;
        $enrollment->forceFill([
            'school_program_id' => $program->id,
            'school_level_id' => null,
            'user_id' => null,
            'participant_name' => $participantName,
            'participant_birth_date' => $birthDate,
            'contact_phone' => '600 000 000',
            'contact_email' => $guardianEmail,
            'guardian_name' => 'Representante E2E',
            'guardian_relationship' => 'Tutor legal',
            'status' => SchoolEnrollmentStatus::PENDING->value,
            'requested_at' => CarbonImmutable::now(),
            'privacy_notice_id' => 'NOTICE-SCHOOL-ENROLLMENT',
            'privacy_notice_version' => '1.0.0',
            'privacy_acknowledged_at' => CarbonImmutable::now(),
        ]);
        $enrollment->save();

        return $enrollment->refresh();
    }

    private function createPendingIdentityAuthorization(
        SchoolEnrollment $enrollment,
        ?Player $player,
        PublicIdentityAuthorizationMode $mode,
        string $plainToken,
        ?CarbonImmutable $tokenExpiresAt = null
    ): PublicIdentityAuthorization {
        $authorization = PublicIdentityAuthorization::query()->create([
            'school_enrollment_id' => $enrollment->id,
            'player_id' => $player?->id,
            'scope' => PublicIdentityAuthorization::SCOPE,
            'mode' => $mode->value,
            'state' => PublicIdentityAuthorizationState::PENDING->value,
            'guardian_email' => $enrollment->contact_email,
            'guardian_name' => $enrollment->guardian_name,
            'guardian_relationship' => $enrollment->guardian_relationship,
            'guardian_authority_declared_at' => CarbonImmutable::now(),
            'notice_id' => 'NOTICE-PUBLIC-IDENTITY-MINORS',
            'notice_version' => '1.0.0',
            'requested_at' => CarbonImmutable::now(),
            'confirmation_token_hash' => hash('sha256', $plainToken),
            'confirmation_token_expires_at' => $tokenExpiresAt ?? CarbonImmutable::now()->addDays(2),
        ]);

        PublicIdentityAuthorizationEvent::query()->create([
            'public_identity_authorization_id' => $authorization->id,
            'type' => PublicIdentityAuthorizationEventType::REQUESTED->value,
            'occurred_at' => CarbonImmutable::now(),
            'metadata' => ['fixture' => 'e2e'],
        ]);

        return $authorization;
    }

    private function createPlayerUser(
        string $email,
        string $name,
        string $lastname,
        string $nickname,
        string $slug,
        string $dni,
        string $licenseNumber,
        string $dominantHand,
        string $birthDate = '1990-01-01'
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'lastname' => $lastname,
                'password' => Hash::make(self::PASSWORD),
                'role' => 'user',
                'active' => true,
            ]
        );

        Player::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nickname' => $nickname,
                'slug' => $slug,
                'dni' => $dni,
                'birth_date' => $birthDate,
                'gender' => PlayerGender::MALE->value,
                'level' => 5,
                'license_number' => $licenseNumber,
                'dominant_hand' => $dominantHand,
                'notes' => 'Perfil exclusivo del escenario E2E.',
                'active' => true,
            ]
        );

        return $user->load('player');
    }

    private function createClubCmsPage(string $slug, string $title, string $heading): void
    {
        $page = CmsPage::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => '2026-01-01 10:00:00',
                'seo_title' => $title,
                'seo_description' => "Escenario técnico aislado para {$slug}.",
            ]
        );

        $page->blocks()->updateOrCreate(
            ['type' => CmsBlockType::HEADING->value, 'sort_order' => 10],
            ['data' => ['text' => $heading, 'level' => 2]]
        );
        $page->blocks()->updateOrCreate(
            ['type' => CmsBlockType::TEXT->value, 'sort_order' => 20],
            ['data' => [
                'text' => "Contenido CMS de prueba exclusivo de la fachada {$slug}.",
            ]]
        );
    }

    private function createRound(Category $category, string $name, int $order): Round
    {
        return Round::query()->updateOrCreate(
            ['category_id' => $category->id, 'name' => $name],
            [
                'order' => $order,
                'type' => 'league',
                'phase' => 'league',
                'stage' => 'matchday',
            ]
        );
    }

    private function createMatch(
        Round $round,
        Venue $venue,
        CategoryEntry $homeEntry,
        CategoryEntry $awayEntry,
        string $scheduledDate
    ): void {
        GameMatch::query()->updateOrCreate(
            ['round_id' => $round->id],
            [
                'venue_id' => $venue->id,
                'home_entry_id' => $homeEntry->id,
                'away_entry_id' => $awayEntry->id,
                'scheduled_date' => $scheduledDate,
                'status' => GameMatchStatus::SCHEDULED->value,
                'home_score' => null,
                'away_score' => null,
                'winner_entry_id' => null,
                'submitted_by' => null,
                'validated_by' => null,
            ]
        );
    }
}
