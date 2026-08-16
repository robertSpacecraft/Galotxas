<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaStorageService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ProfilePhotoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    public function test_profile_photo_routes_require_an_active_authenticated_user(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/me/profile-photo', [
                'photo' => UploadedFile::fake()->image('photo.jpg', 40, 40),
            ])
            ->assertUnauthorized();
        $this->deleteJson('/api/v1/me/profile-photo')->assertUnauthorized();
        $this->getJson('/api/v1/me/profile-photo/image')->assertUnauthorized();

        $inactive = User::factory()->create(['active' => false]);
        Sanctum::actingAs($inactive);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/me/profile-photo', [
                'photo' => UploadedFile::fake()->image('photo.jpg', 40, 40),
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'El usuario está inactivo.');
        $this->deleteJson('/api/v1/me/profile-photo')->assertForbidden();
        $this->getJson('/api/v1/me/profile-photo/image')->assertForbidden();
    }

    public function test_user_without_player_can_upload_and_read_their_private_photo(): void
    {
        $user = $this->authenticate();

        $response = $this->upload(UploadedFile::fake()->image('avatar.png', 200, 100))
            ->assertOk()
            ->assertJsonPath('message', 'Foto de perfil actualizada correctamente.')
            ->assertJsonPath(
                'data.profile_photo.url',
                route('api.v1.me.profile-photo.image')
            );

        $key = $user->refresh()->profile_photo_path;

        $this->assertIsString($key);
        $this->assertMatchesRegularExpression(
            '/\Aavatars\/[0-9a-f-]{36}\.png\z/',
            $key
        );
        Storage::disk('media_local')->assertExists($key);
        $this->assertPrivateKeyIsAbsent($response, $key);

        $me = $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.profile_photo.url', route('api.v1.me.profile-photo.image'));
        $this->assertPrivateKeyIsAbsent($me, $key);

        $this->get('/api/v1/me/profile-photo/image')
            ->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_private-media/'.$key)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Vary', 'Authorization');
    }

    public function test_upload_replaces_old_object_and_delete_is_idempotent(): void
    {
        $user = $this->authenticate();

        $this->upload(UploadedFile::fake()->image('first.jpg', 80, 40))->assertOk();
        $firstKey = $user->refresh()->profile_photo_path;
        Storage::disk('media_local')->assertExists($firstKey);

        $this->upload(UploadedFile::fake()->image('second.webp', 40, 80))->assertOk();
        $secondKey = $user->refresh()->profile_photo_path;

        $this->assertNotSame($firstKey, $secondKey);
        Storage::disk('media_local')->assertMissing($firstKey);
        Storage::disk('media_local')->assertExists($secondKey);

        $this->deleteJson('/api/v1/me/profile-photo')
            ->assertOk()
            ->assertExactJson([
                'message' => 'Foto de perfil eliminada correctamente.',
                'data' => ['profile_photo' => null],
            ]);

        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('media_local')->assertMissing($secondKey);
        $this->getJson('/api/v1/me')->assertJsonPath('data.user.profile_photo', null);
        $this->getJson('/api/v1/me/profile-photo/image')->assertNotFound();

        $this->deleteJson('/api/v1/me/profile-photo')
            ->assertOk()
            ->assertJsonPath('data.profile_photo', null);
    }

    public function test_invalid_legacy_or_wrong_purpose_keys_fail_closed(): void
    {
        $user = $this->authenticate();

        foreach ([
            '../private/avatar.jpg',
            'legacy/photo.jpg',
            'sponsors/00000000-0000-4000-8000-000000000001.jpg',
            'avatars/not-a-uuid.jpg',
            'avatars/00000000-0000-4000-8000-000000000001.svg',
        ] as $invalidKey) {
            $user->forceFill(['profile_photo_path' => $invalidKey])->save();

            $this->getJson('/api/v1/me')
                ->assertOk()
                ->assertJsonPath('data.user.profile_photo', null);
            $this->getJson('/api/v1/me/profile-photo/image')->assertNotFound();
        }
    }

    public function test_valid_reference_with_missing_object_returns_not_found(): void
    {
        $user = $this->authenticate();
        $user->forceFill([
            'profile_photo_path' => 'avatars/00000000-0000-4000-8000-000000000001.jpg',
        ])->save();

        $this->getJson('/api/v1/me/profile-photo/image')->assertNotFound();
    }

    public function test_storage_failures_are_sanitized_for_upload_and_delivery(): void
    {
        $user = $this->authenticate();
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('store')
            ->once()
            ->andThrow(new MediaStorageException('secret upload detail'));
        $this->app->instance(MediaStorageService::class, $storage);

        $upload = $this->upload(UploadedFile::fake()->image('avatar.jpg', 40, 40))
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'La foto de perfil no está disponible temporalmente.',
                'data' => null,
            ]);
        $this->assertStringNotContainsString('secret', $upload->getContent());
        $this->assertNull($user->refresh()->profile_photo_path);

        $key = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user->forceFill(['profile_photo_path' => $key])->save();
        $deliveryStorage = Mockery::mock(MediaStorageService::class);
        $deliveryStorage->shouldReceive('exists')
            ->once()
            ->with($key)
            ->andThrow(new MediaStorageException('secret read detail'));
        $this->app->instance(MediaStorageService::class, $deliveryStorage);

        $delivery = $this->getJson('/api/v1/me/profile-photo/image')
            ->assertServiceUnavailable();
        $this->assertStringNotContainsString('secret', $delivery->getContent());
        $this->assertStringNotContainsString($key, $delivery->getContent());
    }

    public function test_s3_delivery_uses_the_private_ttl_and_keeps_the_stable_route_private(): void
    {
        $user = $this->authenticate();
        $key = 'avatars/00000000-0000-4000-8000-000000000001.webp';
        $user->forceFill(['profile_photo_path' => $key])->save();
        config()->set('media.disk', 'media_s3');
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('exists')->once()->with($key)->andReturnTrue();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with($key, true)
            ->andReturn('https://objects.example.test/private-avatar');
        $this->app->instance(MediaStorageService::class, $storage);

        $this->get('/api/v1/me/profile-photo/image')
            ->assertRedirect('https://objects.example.test/private-avatar')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Vary', 'Authorization');
    }

    public function test_upload_validation_rejects_missing_unsupported_forged_and_oversized_files(): void
    {
        $this->authenticate();

        $this->upload(null)->assertUnprocessable()->assertJsonValidationErrors('photo');

        foreach ([
            UploadedFile::fake()->createWithContent(
                'vector.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"></svg>'
            ),
            UploadedFile::fake()->create('animation.gif', 10, 'image/gif'),
            UploadedFile::fake()->create('image.avif', 10, 'image/avif'),
            UploadedFile::fake()->createWithContent('forged.jpg', 'not an image'),
            UploadedFile::fake()->create('oversized.png', 3073, 'image/png'),
        ] as $file) {
            $this->authenticate();
            $this->upload($file)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('photo');
        }

        $this->authenticate();
        $this->upload(UploadedFile::fake()->image('too-wide.png', 4097, 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_profile_photo_mutations_are_limited_but_image_get_is_not(): void
    {
        $user = $this->authenticate();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->upload(UploadedFile::fake()->image("avatar-{$attempt}.jpg", 20, 20))
                ->assertOk();
        }

        $this->upload(UploadedFile::fake()->image('limited.jpg', 20, 20))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Demasiados intentos. Inténtalo de nuevo más tarde.');

        $key = $user->refresh()->profile_photo_path;

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->get('/api/v1/me/profile-photo/image')->assertOk();
        }

        Storage::disk('media_local')->assertExists($key);
    }

    private function authenticate(): User
    {
        $user = User::factory()->create(['active' => true]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function upload(?UploadedFile $photo)
    {
        return $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/me/profile-photo', array_filter([
                'photo' => $photo,
            ]));
    }

    private function assertPrivateKeyIsAbsent($response, string $key): void
    {
        $this->assertStringNotContainsString('profile_photo_path', $response->getContent());
        $this->assertStringNotContainsString($key, $response->getContent());
        $this->assertStringNotContainsString('avatars/', $response->getContent());
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks'));

        parent::tearDown();
    }
}
