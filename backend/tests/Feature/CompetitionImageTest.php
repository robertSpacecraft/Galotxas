<?php

namespace Tests\Feature;

use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\Season;
use App\Models\User;
use App\Services\CategoryMutationService;
use App\Services\ChampionshipMutationService;
use App\Services\CompetitionImageService;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use App\Services\OfficialResultProtectedDeletionService;
use App\Services\SeasonService;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CompetitionImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('media.disk', 'media_local');
        Storage::fake('media_local');
    }

    public static function entities(): array
    {
        return ['season' => ['seasons'], 'championship' => ['championships'], 'category' => ['categories']];
    }

    #[DataProvider('entities')]
    public function test_admin_forms_support_optional_upload_and_safe_preview(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $this->actingAs(User::factory()->admin()->create());
        $this->get($this->createRoute($entity, 'create'))
            ->assertOk()->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="image"', false)->assertSee('Imagen de portada')
            ->assertSee('8 MB')->assertDontSee('name="remove_image"', false);
        $this->get(route('admin.'.$table.'.edit', $entity))
            ->assertOk()->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(route('admin.'.$table.'.image', $entity), false)
            ->assertSee('Retirar imagen')->assertDontSee($key, false);

        $entity->update(['image_path' => 'https://legacy.invalid/private.png']);
        $this->get(route('admin.'.$table.'.edit', $entity))->assertOk()
            ->assertSee('Retirar imagen')->assertDontSee('https://legacy.invalid', false)
            ->assertDontSee(route('admin.'.$table.'.image', $entity), false);
    }

    #[DataProvider('entities')]
    public function test_admin_image_requires_an_active_administrator_and_can_preview_private_entities(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $entity->forceFill(['is_public' => false])->save();
        $url = route('admin.'.$table.'.image', $entity);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create(['active' => false]))
            ->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Accel-Redirect', '/_private-media/'.$key);
        $entity->update(['image_path' => '../legacy.png']);
        $this->get($url)->assertNotFound();
    }

    #[DataProvider('entities')]
    public function test_create_normalizes_and_persists_only_an_opaque_banner_key(string $table): void
    {
        $parentEntity = $this->entity($table);
        $this->actingAs(User::factory()->admin()->create())
            ->post($this->createRoute($parentEntity), [
                ...$this->payload($parentEntity), 'name' => 'Portada nueva',
                'image' => UploadedFile::fake()->image('cover.png', 2400, 1200),
                'remove_image' => true,
                'image_path' => 'https://untrusted.invalid/image.png',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $created = $parentEntity->newQuery()->where('name', 'Portada nueva')->sole();
        $this->assertTrue(app(CompetitionImageService::class)->isManaged($created->image_path));
        $this->assertStringStartsWith('banners/', $created->image_path);
        $bytes = Storage::disk('media_local')->get($created->image_path);
        $dimensions = getimagesizefromstring($bytes);
        $this->assertSame([1920, 960], [$dimensions[0], $dimensions[1]]);
        $this->assertSame('image/png', $dimensions['mime']);
    }

    #[DataProvider('entities')]
    public function test_save_replace_and_remove_preserve_the_lifecycle(string $table): void
    {
        $entity = $this->entity($table);
        $oldKey = $this->storeImage($entity);
        $url = route('admin.'.$table.'.update', $entity);
        $this->actingAs(User::factory()->admin()->create());
        $this->put($url, $this->payload($entity))->assertSessionHasNoErrors();
        $this->assertSame($oldKey, $entity->fresh()->image_path);
        Storage::disk('media_local')->assertExists($oldKey);
        $this->put($url, [...$this->payload($entity), 'image' => $this->image()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $newKey = $entity->fresh()->image_path;
        $this->assertNotSame($oldKey, $newKey);
        Storage::disk('media_local')->assertMissing($oldKey);
        Storage::disk('media_local')->assertExists($newKey);
        $this->put($url, [...$this->payload($entity), 'remove_image' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($entity->fresh()->image_path);
        Storage::disk('media_local')->assertMissing($newKey);
    }

    #[DataProvider('entities')]
    public function test_invalid_uploads_never_partially_mutate_the_entity(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $this->actingAs(User::factory()->admin()->create());
        $invalid = [
            UploadedFile::fake()->createWithContent('image.jpg', 'not an image'),
            UploadedFile::fake()->image('image.gif'),
            UploadedFile::fake()->image('large.png')->size(8193),
            UploadedFile::fake()->image('wide.png', 6001, 1),
        ];
        foreach ($invalid as $image) {
            $payload = [...$this->payload($entity), 'name' => 'Invalid mutation', 'image' => $image];
            $this->put(route('admin.'.$table.'.update', $entity), $payload)->assertSessionHasErrors('image');
            $this->post($this->createRoute($entity), $payload)->assertSessionHasErrors('image');
            $this->assertSame($key, $entity->fresh()->image_path);
            $this->assertSame($entity->name, $entity->fresh()->name);
            $this->assertDatabaseMissing($table, ['name' => 'Invalid mutation']);
            $this->assertSame([$key], Storage::disk('media_local')->allFiles());
        }
        $this->put(route('admin.'.$table.'.update', $entity), [
            ...$this->payload($entity), 'image' => $this->image(), 'remove_image' => true,
        ])->assertSessionHasErrors(['image' => 'No puedes subir una imagen nueva y retirarla en la misma operación.']);
        $this->assertSame([$key], Storage::disk('media_local')->allFiles());
    }

    #[DataProvider('entities')]
    public function test_upload_storage_failure_returns_sanitized_validation_feedback(string $table): void
    {
        $entity = $this->entity($table);
        $this->mock(MediaStorageService::class)->shouldReceive('store')->once()
            ->andThrow(new MediaStorageException('secret bucket internal-key'));
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.'.$table.'.update', $entity), [
                ...$this->payload($entity), 'name' => 'Must not persist', 'image' => $this->image(),
            ])->assertSessionHasErrors(['image' => 'No se pudo guardar la imagen. Inténtalo de nuevo más tarde.']);
        $this->assertSame($entity->name, $entity->fresh()->name);
        $this->assertNull($entity->fresh()->image_path);
    }

    #[DataProvider('entities')]
    public function test_database_failures_compensate_new_objects_on_create_and_replace(string $table): void
    {
        $entity = $this->entity($table);
        $oldKey = $this->storeImage($entity);
        $attributes = [...$this->payload($entity), 'name' => null];
        $count = $entity->newQuery()->count();
        foreach ([true, false] as $create) {
            try {
                $service = $this->service($entity);
                if ($create) {
                    $entity instanceof Category
                        ? $service->create($entity->championship, $attributes, $this->image())
                        : $service->create($attributes, $this->image());
                } else {
                    $service->update($entity, $attributes, $this->image());
                }
                $this->fail('Expected the database NOT NULL constraint to reject the mutation.');
            } catch (QueryException $exception) {
                $this->assertSame(1048, $exception->errorInfo[1]);
            }
            $this->assertSame($count, $entity->newQuery()->count());
            $this->assertSame($oldKey, $entity->fresh()->image_path);
            $this->assertSame([$oldKey], Storage::disk('media_local')->allFiles());
        }
    }

    #[DataProvider('entities')]
    public function test_stale_models_replace_and_remove_the_current_locked_reference(string $table): void
    {
        $entity = $this->entity($table);
        $original = $this->storeImage($entity);
        $service = $this->service($entity);
        $first = $service->update($entity, $this->payload($entity), $this->image());
        $second = $service->update($entity, $this->payload($entity), $this->image());
        $this->assertNotSame($first->image_path, $second->image_path);
        Storage::disk('media_local')->assertMissing($original);
        Storage::disk('media_local')->assertMissing($first->image_path);
        $this->assertSame([$second->image_path], Storage::disk('media_local')->allFiles());
        $service->update($entity, $this->payload($entity), null, true);
        $this->assertNull($entity->fresh()->image_path);
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    #[DataProvider('entities')]
    public function test_cleanup_failures_after_replace_remove_and_delete_remain_successful(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $this->actingAs(User::factory()->admin()->create());
        $baselineLevel = DB::transactionLevel();
        $this->storageMock()->shouldReceive('delete')->times(3)
            ->andReturnUsing(function (string $oldKey) use ($entity, $baselineLevel): void {
                $this->assertSame($baselineLevel, DB::transactionLevel());
                $this->assertNotSame($oldKey, $entity->fresh()?->image_path);
                throw new MediaStorageException('secret storage detail');
            });
        Log::shouldReceive('warning')->times(3)->with('Competition image cleanup failed.', ['operation' => 'committed_cleanup']);
        $this->put(route('admin.'.$table.'.update', $entity), [...$this->payload($entity), 'image' => $this->image()])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertNotSame($key, $entity->fresh()->image_path);
        $this->put(route('admin.'.$table.'.update', $entity), [...$this->payload($entity), 'remove_image' => true])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertNull($entity->fresh()->image_path);
        $entity->refresh()->update(['image_path' => $key]);
        $this->delete(route('admin.'.$table.'.destroy', $entity))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNull($entity->fresh());
    }

    #[DataProvider('entities')]
    public function test_legacy_references_are_preserved_replaced_or_removed_without_storage_deletion(string $table): void
    {
        $entity = $this->entity($table);
        $legacy = 'https://legacy.invalid/private.png';
        $entity->refresh()->update(['image_path' => $legacy]);
        $this->storageMock()->shouldNotReceive('delete');
        $service = $this->service($entity);
        $service->update($entity, $this->payload($entity));
        $this->assertSame($legacy, $entity->fresh()->image_path);
        $service->update($entity, $this->payload($entity), null, true);
        $this->assertNull($entity->fresh()->image_path);
        $entity->refresh()->update(['image_path' => $legacy]);
        $service->update($entity, $this->payload($entity), $this->image());
        $this->assertTrue(app(CompetitionImageService::class)->isManaged($entity->fresh()->image_path));
        $entity->refresh()->update(['image_path' => $legacy]);
        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.'.$table.'.destroy', $entity))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNull($entity->fresh());
    }

    #[DataProvider('entities')]
    public function test_delete_cleans_managed_images_including_deleted_descendants(string $table): void
    {
        $category = $this->entity('categories');
        $championship = $category->championship;
        $season = $championship->season;
        foreach ([$season, $championship, $category] as $model) {
            $this->storeImage($model);
        }
        $entity = match ($table) {
            'seasons' => $season, 'championships' => $championship, default => $category,
        };
        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.'.$table.'.destroy', $entity))
            ->assertRedirect()->assertSessionHas('success');
        foreach ([$season, $championship, $category] as $model) {
            $this->assertSame($model->fresh() !== null, Storage::disk('media_local')->exists($model->image_path));
        }
        $this->assertNull($entity->fresh());
    }

    #[DataProvider('entities')]
    public function test_official_result_blocks_deletion_without_cleaning_any_images(string $table): void
    {
        $category = $this->entity('categories');
        $championship = $category->championship;
        $season = $championship->season;
        foreach ([$category, $championship, $season] as $model) {
            $this->storeImage($model);
        }
        CategoryOfficialResult::factory()->create(['category_id' => $category->id]);
        $entity = match ($table) {
            'seasons' => $season, 'championships' => $championship, default => $category,
        };
        $this->mock(MediaStorageService::class)->shouldNotReceive('delete');
        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.'.$table.'.destroy', $entity))
            ->assertRedirect()->assertSessionHas('error');
        foreach ([$season, $championship, $category] as $model) {
            $this->assertNotNull($model->fresh());
            Storage::disk('media_local')->assertExists($model->image_path);
        }
    }

    #[DataProvider('entities')]
    public function test_public_serving_requires_a_managed_present_object(string $table): void
    {
        $entity = $this->entity($table);
        $url = route('api.v1.'.$table.'.image', $entity);
        $this->get($url)->assertNotFound();
        foreach (['../legacy.png', 'https://legacy.invalid/image.jpg', 'avatars/'.fake()->uuid().'.png'] as $invalid) {
            $entity->update(['image_path' => $invalid]);
            $this->get($url)->assertNotFound()->assertDontSee($invalid, false);
        }
        $key = $this->storeImage($entity);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Accel-Redirect', '/_private-media/'.$key)
            ->assertHeader('Cache-Control', 'max-age=60, public');
        Storage::disk('media_local')->delete($key);
        $this->get($url)->assertNotFound();
    }

    #[DataProvider('entities')]
    public function test_each_private_ancestor_blocks_public_serving(string $table): void
    {
        $entity = $this->entity($table);
        $this->storeImage($entity);
        $ancestors = match ($table) {
            'seasons' => [$entity],
            'championships' => [$entity, $entity->season],
            default => [$entity, $entity->championship, $entity->championship->season],
        };
        $url = route('api.v1.'.$table.'.image', $entity);
        foreach ($ancestors as $ancestor) {
            $ancestor->forceFill(['is_public' => false])->save();
            $this->get($url)->assertNotFound();
            $ancestor->forceFill(['is_public' => true])->save();
            $this->get($url)->assertOk();
        }
    }

    #[DataProvider('entities')]
    public function test_public_and_admin_storage_failures_are_generic_503(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $this->mock(MediaStorageService::class)->shouldReceive('exists')->twice()->with($key)
            ->andThrow(new MediaStorageException('secret bucket/key host'));
        $this->getJson(route('api.v1.'.$table.'.image', $entity))->assertStatus(503)
            ->assertJsonPath('message', 'No se pudo entregar la imagen. Inténtalo de nuevo más tarde.')
            ->assertDontSee('secret bucket', false)->assertDontSee($key, false);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.'.$table.'.image', $entity))
            ->assertStatus(503)->assertDontSee('secret bucket', false)->assertDontSee($key, false);
    }

    public function test_public_resources_project_root_and_nested_images_without_queries_or_inheritance(): void
    {
        $category = $this->entity('categories');
        $championship = $category->championship;
        $season = $championship->season;
        foreach ([$season, $championship, $category] as $model) {
            $this->storeImage($model);
        }
        $seasonImage = ['url' => route('api.v1.seasons.image', $season)];
        $championshipImage = ['url' => route('api.v1.championships.image', $championship)];
        $categoryImage = ['url' => route('api.v1.categories.image', $category)];
        $this->getJson('/api/v1/seasons')->assertOk()
            ->assertJsonPath('data.0.image', $seasonImage)
            ->assertJsonPath('data.0.championships.0.image', $championshipImage);
        $this->getJson('/api/v1/championships')->assertOk()
            ->assertJsonPath('data.0.image', $championshipImage)
            ->assertJsonPath('data.0.categories.0.image', $categoryImage);
        $this->getJson('/api/v1/championships/'.$championship->id)->assertOk()
            ->assertJsonPath('data.image', $championshipImage)
            ->assertJsonPath('data.categories.0.image', $categoryImage);
        $this->getJson('/api/v1/categories/'.$category->id)->assertOk()->assertJsonPath('data.image', $categoryImage);
        $category->update(['image_path' => null]);
        $this->getJson('/api/v1/categories/'.$category->id)->assertJsonPath('data.image', null);
        $this->getJson('/api/v1/championships/'.$championship->id)->assertJsonPath('data.categories.0.image', null);

        // Projection never checks physical existence and never loads an ancestor for its image.
        $this->mock(MediaStorageService::class)->shouldNotReceive('exists');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($seasonImage, app(CompetitionImageService::class)->publicImage($season));
        $this->assertSame($championshipImage, app(CompetitionImageService::class)->publicImage($championship));
        $this->assertNull(app(CompetitionImageService::class)->publicImage($category));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        foreach ([$season, $championship, $category] as $model) {
            $model->update(['image_path' => 'https://legacy.invalid/image.jpg']);
        }
        foreach (['/api/v1/seasons', '/api/v1/championships', '/api/v1/championships/'.$championship->id, '/api/v1/categories/'.$category->id] as $url) {
            $response = $this->getJson($url)->assertOk();
            $response->assertDontSee('image_path', false)->assertDontSee('banners/', false)
                ->assertDontSee('legacy.invalid', false)->assertDontSee('"key"', false);
            $this->assertNull($response->json(in_array($url, ['/api/v1/seasons', '/api/v1/championships'], true) ? 'data.0.image' : 'data.image'));
        }
    }

    #[DataProvider('entities')]
    public function test_json_admin_updates_ignore_media_inputs_and_do_not_leak_keys(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->putJson('/api/v1/admin/'.$table.'/'.$entity->id, [
            ...$this->payload($entity), 'image_path' => 'https://untrusted.invalid/image.png',
            'remove_image' => true, 'image' => 'untrusted-image-data',
        ])->assertOk()->assertJsonMissingPath('data.image_path')->assertJsonMissingPath('data.image')
            ->assertDontSee($key, false);
        $this->assertSame($key, $entity->fresh()->image_path);
        Storage::disk('media_local')->assertExists($key);
    }

    #[DataProvider('entities')]
    public function test_failed_remove_and_delete_keep_database_reference_and_object(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $this->mock(MediaStorageService::class)->shouldNotReceive('delete');
        $entity::updating(static function (): void {
            throw new RuntimeException('forced save failure');
        });
        $entity::deleting(static function (): void {
            throw new RuntimeException('forced delete failure');
        });

        try {
            foreach (['remove', 'delete'] as $operation) {
                try {
                    if ($operation === 'remove') {
                        $this->service($entity)->update($entity, $this->payload($entity), null, true);
                    } else {
                        $deletions = app(OfficialResultProtectedDeletionService::class);
                        match ($table) {
                            'seasons' => $deletions->deleteSeason($entity),
                            'championships' => $deletions->deleteChampionship($entity),
                            default => $deletions->deleteCategory($entity),
                        };
                    }
                    $this->fail('Expected a failed mutation.');
                } catch (RuntimeException $exception) {
                    $this->assertStringStartsWith('forced ', $exception->getMessage());
                }
                $this->assertSame($key, $entity->fresh()->image_path);
                Storage::disk('media_local')->assertExists($key);
            }
        } finally {
            $entity::flushEventListeners();
        }
    }

    #[DataProvider('entities')]
    public function test_cleanup_waits_for_the_outer_commit_and_is_discarded_on_rollback(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $this->mock(MediaStorageService::class)->shouldNotReceive('delete');
        DB::beginTransaction();
        try {
            $this->service($entity)->update($entity, $this->payload($entity), null, true);
            $this->assertNull($entity->fresh()->image_path);
            Storage::disk('media_local')->assertExists($key);
        } finally {
            DB::rollBack();
        }
        $this->assertSame($key, $entity->fresh()->image_path);
        Storage::disk('media_local')->assertExists($key);
    }

    #[DataProvider('entities')]
    public function test_serving_selects_public_delivery_and_private_admin_temporary_urls(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $delivery = $this->mock(MediaDeliveryService::class);
        $delivery->shouldReceive('deliverPublic')->once()->with($key)->andReturn(response('public-image'));
        $delivery->shouldReceive('deliver')->once()->with($key, true)->andReturn(response('admin-image'));
        $this->get(route('api.v1.'.$table.'.image', $entity))->assertOk()->assertSee('public-image');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.'.$table.'.image', $entity))
            ->assertOk()->assertSee('admin-image');
    }

    public function test_domain_rejections_compensate_uploads_and_preserve_official_state(): void
    {
        $season = Season::factory()->active()->create();
        try {
            app(SeasonService::class)->create([
                'name' => 'Second active', 'status' => 'active', 'is_public' => false,
            ], $this->image());
            $this->fail('The active season invariant must reject this upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame([], Storage::disk('media_local')->allFiles());
        $this->assertSame('active', $season->fresh()->status->value);

        $category = $this->entity('categories');
        $championship = $category->championship;
        $oldKey = $this->storeImage($championship);
        $official = CategoryOfficialResult::factory()->create(['category_id' => $category->id]);
        $snapshot = $official->fresh()->getAttributes();
        try {
            app(ChampionshipMutationService::class)->update($championship, [
                ...$this->payload($championship),
                'type' => $championship->type->value === 'singles' ? 'doubles' : 'singles',
            ], $this->image());
            $this->fail('Official results must block this rule change.');
        } catch (OfficialResultMutationBlockedException) {
            $this->assertSame($oldKey, $championship->fresh()->image_path);
            $this->assertSame($championship->type, $championship->fresh()->type);
        }
        $this->assertSame([$oldKey], Storage::disk('media_local')->allFiles());
        $this->assertSame($snapshot, $official->fresh()->getAttributes());
        // A cover alone can enrich the championship without altering an official result.
        app(ChampionshipMutationService::class)->update($championship, $this->payload($championship), $this->image());
        $this->assertSame($snapshot, $official->fresh()->getAttributes());
        Storage::disk('media_local')->assertMissing($oldKey);
    }

    #[DataProvider('entities')]
    public function test_model_event_vetoes_compensate_uploads_and_never_clean_the_current_image(string $table): void
    {
        $entity = $this->entity($table);
        $key = $this->storeImage($entity);
        $entity::saving(static fn (): bool => false);
        $entity::deleting(static fn (): bool => false);
        try {
            foreach (['create', 'replace', 'delete'] as $operation) {
                try {
                    $service = $this->service($entity);
                    if ($operation === 'create') {
                        $entity instanceof Category
                            ? $service->create($entity->championship, $this->payload($entity), $this->image())
                            : $service->create($this->payload($entity), $this->image());
                    } elseif ($operation === 'replace') {
                        $service->update($entity, $this->payload($entity), $this->image());
                    } else {
                        $deletions = app(OfficialResultProtectedDeletionService::class);
                        match ($table) {
                            'seasons' => $deletions->deleteSeason($entity),
                            'championships' => $deletions->deleteChampionship($entity),
                            default => $deletions->deleteCategory($entity),
                        };
                    }
                    $this->fail('A model event veto must not report success.');
                } catch (RuntimeException $exception) {
                    $this->assertStringStartsWith('No se pudo ', $exception->getMessage());
                }
                $this->assertSame($key, $entity->fresh()->image_path);
                $this->assertSame([$key], Storage::disk('media_local')->allFiles());
                $this->assertSame(1, $entity->newQuery()->count());
            }
        } finally {
            $entity::flushEventListeners();
        }
    }

    private function entity(string $table): Season|Championship|Category
    {
        $season = Season::factory()->publiclyVisible()->create();
        if ($table === 'seasons') {
            return $season;
        }
        $championship = Championship::factory()->publiclyVisible()->create(['season_id' => $season->id]);
        if ($table === 'championships') {
            return $championship;
        }

        return Category::factory()->publiclyVisible()->create(['championship_id' => $championship->id]);
    }

    private function storeImage(Season|Championship|Category $entity): string
    {
        $key = app(MediaObjectKeyGenerator::class)->generate(MediaPurpose::Banner, 'png');
        $image = $this->image();
        Storage::disk('media_local')->put($key, file_get_contents($image->getRealPath()));
        $entity->refresh()->update(['image_path' => $key]);

        return $key;
    }

    private function storageMock(): MediaStorageService
    {
        return $this->instance(MediaStorageService::class, Mockery::mock(MediaStorageService::class, [
            app(FilesystemManager::class), app(MediaObjectKeyGenerator::class),
        ])->makePartial());
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('cover.png', 80, 40);
    }

    private function payload(Season|Championship|Category $entity): array
    {
        return match (true) {
            $entity instanceof Season => [
                'name' => $entity->name, 'status' => $entity->status->value, 'is_public' => true,
            ],
            $entity instanceof Championship => [
                'name' => $entity->name, 'status' => $entity->status, 'is_public' => true,
                'season_id' => $entity->season_id, 'type' => $entity->type->value,
                'registration_status' => 'closed',
            ],
            default => [
                'name' => $entity->name, 'status' => $entity->status, 'is_public' => true,
                'level' => $entity->level, 'gender' => $entity->gender->value,
            ],
        };
    }

    private function createRoute(Season|Championship|Category $entity, string $action = 'store'): string
    {
        return route('admin.'.$entity->getTable().'.'.$action, match (true) {
            $entity instanceof Championship => $entity->season,
            $entity instanceof Category => $entity->championship,
            default => [],
        });
    }

    private function service(Season|Championship|Category $entity): SeasonService|ChampionshipMutationService|CategoryMutationService
    {
        return app(match (true) {
            $entity instanceof Season => SeasonService::class,
            $entity instanceof Championship => ChampionshipMutationService::class,
            default => CategoryMutationService::class,
        });
    }
}
