<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use App\Services\Media\NormalizedImage;
use App\Services\ProfilePhotoService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProfilePhotoLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    public function test_database_failure_compensates_the_new_avatar_object(): void
    {
        $user = User::factory()->create();
        User::updating(function (): void {
            throw new RuntimeException('forced profile photo database failure');
        });

        try {
            app(ProfilePhotoService::class)->store(
                $user,
                UploadedFile::fake()->image('avatar.png', 40, 40)
            );
            $this->fail('The forced database failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced profile photo database failure', $exception->getMessage());
        } finally {
            User::flushEventListeners();
        }

        $this->assertNull($user->refresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_replace_cleanup_failure_is_logged_without_reverting_the_new_reference(): void
    {
        $oldKey = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $newKey = 'avatars/00000000-0000-4000-8000-000000000002.png';
        $user = $this->userWithPhoto($oldKey);
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('store')
            ->once()
            ->with(MediaPurpose::Avatar, Mockery::type(NormalizedImage::class))
            ->andReturn($newKey);
        $storage->shouldReceive('delete')
            ->once()
            ->with($oldKey)
            ->andThrow(new MediaStorageException('secret cleanup detail'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Profile photo cleanup failed.', ['operation' => 'replace_old_object']);

        $this->serviceWith($storage)->store(
            $user,
            UploadedFile::fake()->image('replacement.png', 40, 40)
        );

        $this->assertSame($newKey, $user->refresh()->profile_photo_path);
    }

    public function test_remove_cleanup_failure_is_logged_without_restoring_the_reference(): void
    {
        $oldKey = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user = $this->userWithPhoto($oldKey);
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('delete')
            ->once()
            ->with($oldKey)
            ->andThrow(new MediaStorageException('secret cleanup detail'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Profile photo cleanup failed.', ['operation' => 'remove_object']);

        $this->serviceWith($storage)->remove($user);

        $this->assertNull($user->refresh()->profile_photo_path);
    }

    public function test_stale_replace_requests_use_the_locked_database_reference(): void
    {
        $oldKey = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user = $this->userWithPhoto($oldKey);
        Storage::disk('media_local')->put($oldKey, 'old-avatar');
        $firstStaleRequest = User::query()->findOrFail($user->id);
        $secondStaleRequest = User::query()->findOrFail($user->id);
        $service = app(ProfilePhotoService::class);

        $first = $service->store(
            $firstStaleRequest,
            UploadedFile::fake()->image('first.png', 40, 40)
        );
        $firstKey = $first->profile_photo_path;
        $second = $service->store(
            $secondStaleRequest,
            UploadedFile::fake()->image('second.webp', 40, 40)
        );
        $secondKey = $second->profile_photo_path;

        $this->assertNotSame($firstKey, $secondKey);
        $this->assertSame($secondKey, $user->refresh()->profile_photo_path);
        Storage::disk('media_local')->assertMissing($oldKey);
        Storage::disk('media_local')->assertMissing($firstKey);
        Storage::disk('media_local')->assertExists($secondKey);
        $this->assertSame([$secondKey], Storage::disk('media_local')->allFiles());
    }

    public function test_stale_replace_then_delete_uses_the_latest_committed_reference(): void
    {
        $oldKey = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user = $this->userWithPhoto($oldKey);
        Storage::disk('media_local')->put($oldKey, 'old-avatar');
        $replaceRequest = User::query()->findOrFail($user->id);
        $deleteRequest = User::query()->findOrFail($user->id);
        $service = app(ProfilePhotoService::class);

        $replacement = $service->store(
            $replaceRequest,
            UploadedFile::fake()->image('replacement.png', 40, 40)
        );
        $replacementKey = $replacement->profile_photo_path;
        $service->remove($deleteRequest);

        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('media_local')->assertMissing($oldKey);
        Storage::disk('media_local')->assertMissing($replacementKey);
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_invalid_legacy_reference_is_cleared_without_touching_storage(): void
    {
        $user = $this->userWithPhoto('../legacy-private.jpg');
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldNotReceive('delete');

        $this->serviceWith($storage)->remove($user);

        $this->assertNull($user->refresh()->profile_photo_path);
    }

    public function test_admin_user_deletion_cleans_avatar_after_deleting_the_row(): void
    {
        $admin = User::factory()->admin()->create();
        $key = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user = $this->userWithPhoto($key);
        Storage::disk('media_local')->put($key, 'avatar');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Storage::disk('media_local')->assertMissing($key);
    }

    public function test_user_deletion_cleanup_failure_is_logged_without_restoring_the_row(): void
    {
        $key = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user = $this->userWithPhoto($key);
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('delete')
            ->once()
            ->with($key)
            ->andThrow(new MediaStorageException('secret cleanup detail'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Profile photo cleanup failed.', ['operation' => 'delete_user_object']);

        $this->serviceWith($storage)->deleteUser($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deleting_only_player_preserves_user_and_avatar(): void
    {
        $admin = User::factory()->admin()->create();
        $key = 'avatars/00000000-0000-4000-8000-000000000001.jpg';
        $user = $this->userWithPhoto($key);
        $player = Player::factory()->for($user)->create();
        Storage::disk('media_local')->put($key, 'avatar');

        $this->actingAs($admin)
            ->delete(route('admin.players.destroy', $player))
            ->assertRedirect(route('admin.players.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_photo_path' => $key,
        ]);
        Storage::disk('media_local')->assertExists($key);
    }

    private function userWithPhoto(string $key): User
    {
        $user = User::factory()->create();
        $user->forceFill(['profile_photo_path' => $key])->save();

        return $user;
    }

    private function serviceWith(MediaStorageService $storage): ProfilePhotoService
    {
        return new ProfilePhotoService(
            app(ImageNormalizer::class),
            $storage,
            app(MediaObjectKeyGenerator::class),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks'));

        parent::tearDown();
    }
}
