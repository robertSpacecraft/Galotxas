<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Models\User;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaStorageService;
use App\Services\SponsorService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminSponsorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_local');
        config()->set('media.disk', 'media_local');
    }

    public function test_sponsor_admin_requires_an_active_administrator(): void
    {
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);

        $this->get(route('admin.sponsors.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($user)->get(route('admin.sponsors.index'))->assertForbidden();
        $this->actingAs($inactiveAdmin)
            ->get(route('admin.sponsors.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.sponsors.index'))
            ->assertOk()
            ->assertSee('Colaboradores');
    }

    public function test_admin_can_create_update_replace_and_delete_a_sponsor_safely(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload([
                'name' => '  Empresa colaboradora  ',
                'logo' => UploadedFile::fake()->image('first.png', 200, 100),
                'website_url' => 'https://example.com/support',
                'sort_order' => '15',
                'is_active' => '1',
            ]))
            ->assertRedirect(route('admin.sponsors.index'))
            ->assertSessionHasNoErrors();

        $sponsor = Sponsor::query()->sole();
        $firstKey = $sponsor->logo_key;
        $this->assertDatabaseHas('sponsors', [
            'id' => $sponsor->id,
            'name' => 'Empresa colaboradora',
            'website_url' => 'https://example.com/support',
            'sort_order' => 15,
            'is_active' => true,
            'logo_width' => 200,
            'logo_height' => 100,
        ]);
        Storage::disk('media_local')->assertExists($firstKey);

        $this->actingAs($admin)
            ->put(route('admin.sponsors.update', $sponsor), $this->payload([
                'name' => 'Empresa actualizada',
                'website_url' => '',
                'sort_order' => '4',
                'is_active' => '0',
            ]))
            ->assertRedirect(route('admin.sponsors.index'))
            ->assertSessionHasNoErrors();

        $sponsor->refresh();
        $this->assertSame($firstKey, $sponsor->logo_key);
        $this->assertNull($sponsor->website_url);
        $this->assertFalse($sponsor->is_active);
        Storage::disk('media_local')->assertExists($firstKey);

        $this->actingAs($admin)
            ->put(route('admin.sponsors.update', $sponsor), $this->payload([
                'name' => 'Empresa actualizada',
                'logo' => UploadedFile::fake()->image('replacement.webp', 120, 240),
                'sort_order' => '4',
                'is_active' => '1',
            ]))
            ->assertRedirect(route('admin.sponsors.index'))
            ->assertSessionHasNoErrors();

        $sponsor->refresh();
        $replacementKey = $sponsor->logo_key;
        $this->assertNotSame($firstKey, $replacementKey);
        $this->assertSame([120, 240], [$sponsor->logo_width, $sponsor->logo_height]);
        Storage::disk('media_local')->assertMissing($firstKey);
        Storage::disk('media_local')->assertExists($replacementKey);

        $this->actingAs($admin)
            ->delete(route('admin.sponsors.destroy', $sponsor))
            ->assertRedirect(route('admin.sponsors.index'));

        $this->assertDatabaseMissing('sponsors', ['id' => $sponsor->id]);
        Storage::disk('media_local')->assertMissing($replacementKey);
    }

    public function test_create_requires_a_logo_and_update_preserves_it_when_omitted(): void
    {
        $admin = User::factory()->admin()->create();
        $sponsor = Sponsor::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload())
            ->assertSessionHasErrors('logo');

        $this->actingAs($admin)
            ->put(route('admin.sponsors.update', $sponsor), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame($sponsor->logo_key, $sponsor->refresh()->logo_key);
    }

    public function test_admin_validation_rejects_unsafe_urls_invalid_windows_and_bad_images(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            'http://example.com',
            'javascript:alert(1)',
            'data:text/plain,test',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
            'mailto:club@example.com',
            '//example.com',
            'https://user:secret@example.com',
            "https://example.com/path\nheader",
        ] as $unsafeUrl) {
            $this->actingAs($admin)
                ->post(route('admin.sponsors.store'), $this->payload([
                    'logo' => UploadedFile::fake()->image('logo.png', 20, 10),
                    'website_url' => $unsafeUrl,
                ]))
                ->assertSessionHasErrors('website_url');
        }

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload([
                'logo' => UploadedFile::fake()->image('logo.png', 20, 10),
                'starts_at' => '2026-08-17T12:00',
                'ends_at' => '2026-08-17T11:59',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload([
                'logo' => UploadedFile::fake()->createWithContent('logo.png', 'not an image'),
            ]))
            ->assertSessionHasErrors('logo');

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload([
                'logo' => UploadedFile::fake()->image('too-wide.png', 6001, 1),
            ]))
            ->assertSessionHasErrors('logo');

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload([
                'logo' => UploadedFile::fake()->create('too-large.png', 8193, 'image/png'),
            ]))
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseCount('sponsors', 0);
    }

    public function test_an_end_without_a_start_is_a_valid_open_window(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.sponsors.store'), $this->payload([
                'logo' => UploadedFile::fake()->image('logo.png', 20, 10),
                'ends_at' => '2026-08-17T12:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Sponsor::query()->sole()->ends_at);
    }

    public function test_database_failure_compensates_the_new_object(): void
    {
        Sponsor::creating(function (): void {
            throw new RuntimeException('forced database failure');
        });

        try {
            app(SponsorService::class)->create(
                $this->serviceAttributes(),
                UploadedFile::fake()->image('logo.png', 20, 10)
            );
            $this->fail('The forced database failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced database failure', $exception->getMessage());
        } finally {
            Sponsor::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('media_local')->allFiles());
        $this->assertDatabaseCount('sponsors', 0);
    }

    public function test_replace_database_failure_removes_new_object_and_preserves_old_reference(): void
    {
        $sponsor = Sponsor::factory()->create();
        Storage::disk('media_local')->put($sponsor->logo_key, 'old-logo');
        Sponsor::updating(function (): void {
            throw new RuntimeException('forced replace failure');
        });

        try {
            app(SponsorService::class)->update(
                $sponsor,
                $this->serviceAttributes(),
                UploadedFile::fake()->image('replacement.png', 20, 10)
            );
            $this->fail('The forced replace failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced replace failure', $exception->getMessage());
        } finally {
            Sponsor::flushEventListeners();
        }

        $this->assertSame($sponsor->logo_key, $sponsor->refresh()->logo_key);
        $this->assertSame([$sponsor->logo_key], Storage::disk('media_local')->allFiles());
    }

    public function test_cleanup_failure_after_database_delete_is_logged_without_restoring_the_row(): void
    {
        $sponsor = Sponsor::factory()->create();
        $storage = Mockery::mock(MediaStorageService::class);
        $storage->shouldReceive('delete')
            ->once()
            ->with($sponsor->logo_key)
            ->andThrow(new MediaStorageException('secret backend detail'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Sponsor media cleanup failed.', ['operation' => 'delete_object']);
        $service = new SponsorService(app(ImageNormalizer::class), $storage);

        $service->delete($sponsor);

        $this->assertDatabaseMissing('sponsors', ['id' => $sponsor->id]);
    }

    public function test_admin_can_preview_inactive_logo_and_direct_internal_path_is_not_a_route(): void
    {
        $admin = User::factory()->admin()->create();
        $sponsor = Sponsor::factory()->inactive()->create();
        Storage::disk('media_local')->put($sponsor->logo_key, 'logo-bytes');

        $this->actingAs($admin)
            ->get(route('admin.sponsors.logo', $sponsor))
            ->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_private-media/'.$sponsor->logo_key)
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->get('/_private-media/'.$sponsor->logo_key)->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Empresa colaboradora',
            'website_url' => '',
            'sort_order' => '0',
            'is_active' => '0',
            'starts_at' => '',
            'ends_at' => '',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceAttributes(): array
    {
        return [
            'name' => 'Empresa colaboradora',
            'website_url' => null,
            'sort_order' => 0,
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks'));

        parent::tearDown();
    }
}
