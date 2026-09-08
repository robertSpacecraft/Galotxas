<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\NewsArticle;
use App\Models\Player;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\CategoryMutationService;
use App\Services\ChampionshipMutationService;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\ResponsiveMediaStorage;
use App\Services\NewsArticleService;
use App\Services\OfficialResultProtectedDeletionService;
use App\Services\ProfilePhotoService;
use App\Services\SeasonService;
use App\Services\SponsorService;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithResponsiveMedia;
use Tests\TestCase;

class ResponsiveUploadLifecycleTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithResponsiveMedia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->assertSame(DatabaseTransactionsManager::class, get_class(app('db.transactions')));
        config()->set('media.disk', 'media_local');
        Storage::fake('media_local');
    }

    protected function tearDown(): void
    {
        try {
            if (DB::transactionLevel() > 0) {
                DB::rollBack(0);
            }
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public static function domains(): iterable
    {
        foreach (['avatar', 'news', 'sponsor', 'season', 'championship', 'category'] as $domain) {
            yield $domain => [$domain];
        }
    }

    public static function competitions(): iterable
    {
        foreach (['season', 'championship', 'category'] as $domain) {
            yield $domain => [$domain];
        }
    }

    #[DataProvider('domains')]
    public function test_new_upload_uses_explicit_policy_master_dimensions_and_private_complete_set(string $domain): void
    {
        $image = $this->image();
        $entity = $this->create($domain, $image);
        $key = $this->key($entity);
        $profile = $this->profile($domain);
        $manifest = $this->assertResponsiveSet($key, $profile);
        $this->assertTrue(app(MediaObjectKeyGenerator::class)->isValidForPurpose($key, $profile->purpose()));
        $this->assertNotEmpty($manifest->variants);
        $policy = $domain === 'sponsor' ? ImagePreparationPolicy::Graphic : ImagePreparationPolicy::Photo;
        $this->assertSame($policy, $manifest->policy);
        $candidate = ImageManager::gd(strip: true)->read(file_get_contents($image->getRealPath()));
        $limits = config('media.profiles.'.$profile->value);
        $candidate->scaleDown(width: $limits['output_max_width'], height: $limits['output_max_height']);
        if ($domain === 'sponsor') {
            $png = (string) $candidate->toPng(interlaced: false, indexed: false);
            $webp = (string) $candidate->toWebp(quality: 100, strip: true);
            $expected = strlen($webp) < strlen($png) ? $webp : $png;
        } else {
            $expected = (string) $candidate->toWebp(quality: 82, strip: true);
            $this->assertSame('image/webp', $manifest->master->mimeType);
        }
        $this->assertSame($expected, Storage::disk('media_local')->get($key));
        if ($entity instanceof NewsArticle || $entity instanceof Sponsor) {
            $prefix = $entity instanceof NewsArticle ? 'image' : 'logo';
            $this->assertSame([$manifest->master->width, $manifest->master->height], [$entity->{$prefix.'_width'}, $entity->{$prefix.'_height'}]);
        }
        $before = Storage::disk('media_local')->allFiles();
        if ($domain !== 'avatar') {
            $this->update($entity, null);
            $this->assertSame($key, $this->key($entity->fresh()));
            $this->assertSame($before, Storage::disk('media_local')->allFiles());
        }
    }

    #[DataProvider('domains')]
    public function test_create_inside_ambient_transaction_keeps_or_compensates_complete_set(string $domain): void
    {
        foreach ([false, true] as $commit) {
            DB::beginTransaction();
            $entity = $this->create($domain, $this->image());
            $objects = $this->objects($this->key($entity));
            $this->assertObjects($objects, true);
            $commit ? DB::commit() : DB::rollBack();
            $this->assertSame($commit, $entity->fresh() !== null);
            $this->assertObjects($objects, $commit);
            if (! $commit) {
                $this->assertSame([], Storage::disk('media_local')->allFiles());
            }
        }
    }

    #[DataProvider('domains')]
    public function test_new_sets_keep_master_delivery_and_existing_resource_shapes(string $domain): void
    {
        $entity = $this->create($domain, $this->image());
        $key = $this->key($entity);
        $manifest = $this->assertResponsiveSet($key);
        if ($entity instanceof NewsArticle) {
            $entity->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        } elseif ($entity instanceof Sponsor) {
            $entity->update(['is_active' => true]);
        } elseif ($entity instanceof Category) {
            $entity->championship->season->forceFill(['is_public' => true])->save();
        }
        $url = match ($domain) {
            'avatar' => route('api.v1.me.profile-photo.image'),
            'news' => route('api.v1.news.image', $entity->slug),
            'sponsor' => route('api.v1.sponsors.logo', $entity),
            default => route('api.v1.'.$entity->getTable().'.image', $entity),
        };
        if ($entity instanceof User) {
            Sanctum::actingAs($entity);
        }
        $this->get($url)->assertOk()->assertHeader('X-Accel-Redirect', '/_private-media/'.$key)
            ->assertHeader('Content-Type', $manifest->master->mimeType);
        [$endpoint, $path, $shape] = match ($domain) {
            'avatar' => ['/api/v1/me', 'data.user.profile_photo', ['url' => $url]],
            'news' => ['/api/v1/news/'.$entity->slug, 'data.image', [
                'url' => $url, 'width' => $entity->image_width, 'height' => $entity->image_height,
                'alt' => $entity->image_alt, 'credit' => $entity->image_credit,
            ]],
            'sponsor' => ['/api/v1/sponsors', 'data.0.logo', ['url' => $url, 'width' => $entity->logo_width, 'height' => $entity->logo_height]],
            'season' => ['/api/v1/seasons', 'data.0.image', ['url' => $url]],
            default => ['/api/v1/'.$entity->getTable().'/'.$entity->id, 'data.image', ['url' => $url]],
        };
        $this->getJson($endpoint)->assertOk()->assertJsonPath($path, $shape)
            ->assertDontSee($key, false)->assertDontSee('variants', false)->assertDontSee('srcset', false);
        if (! $entity instanceof User) {
            $adminUrl = match ($domain) {
                'news' => route('admin.news-articles.image', $entity), 'sponsor' => route('admin.sponsors.logo', $entity),
                default => route('admin.'.$entity->getTable().'.image', $entity),
            };
            $this->actingAs(User::factory()->admin()->create())->get($adminUrl)->assertOk()
                ->assertHeader('X-Accel-Redirect', '/_private-media/'.$key);
        }
        foreach (['deliver', 'deliverPrivate', 'deliverPublic'] as $method) {
            foreach ([$manifest->variants[0]->key, app(ResponsiveMediaKeys::class)->manifest($key, $manifest->version)] as $privateKey) {
                try {
                    app(MediaDeliveryService::class)->$method($privateKey);
                    $this->fail('Derivative delivery must remain closed.');
                } catch (MediaStorageException $exception) {
                    $this->assertSame('La clave multimedia no es válida.', $exception->getMessage());
                }
            }
        }
    }

    #[DataProvider('domains')]
    public function test_stale_replacements_observe_current_reference_and_ambient_commit_or_rollback(string $domain): void
    {
        $stale = $this->create($domain, $this->image());
        $original = $this->objects($this->key($stale));
        $first = $this->update($stale, $this->image());
        $firstObjects = $this->objects($this->key($first));
        $this->assertObjects($original, false);
        foreach ([false, true] as $commit) {
            DB::beginTransaction();
            DB::beginTransaction();
            $second = $this->update($stale, $this->image());
            $secondObjects = $this->objects($this->key($second));
            $this->assertSame($this->key($second), $this->key($stale->fresh()));
            $this->assertObjects($firstObjects, true);
            DB::commit();
            $this->assertObjects($firstObjects, true);
            $commit ? DB::commit() : DB::rollBack();
            $this->assertObjects($firstObjects, ! $commit);
            $this->assertObjects($secondObjects, $commit);
            $this->assertSame($this->key($commit ? $second : $first), $this->key($stale->fresh()));
            $this->assertOnlyResponsiveSet($this->key($stale->fresh()));
        }
    }

    #[DataProvider('domains')]
    public function test_stale_remove_and_delete_wait_for_commit_and_rollback_keeps_media(string $domain): void
    {
        $stale = $this->create($domain, $this->image());
        $current = $this->update($stale, $this->image());
        if ($domain !== 'sponsor') {
            $objects = $this->objects($this->key($current));
            foreach ([false, true] as $commit) {
                DB::beginTransaction();
                $this->remove($stale);
                $this->assertNull($this->key($stale->fresh()));
                $this->assertObjects($objects, true);
                $commit ? DB::commit() : DB::rollBack();
                $this->assertObjects($objects, ! $commit);
                $this->assertSame($commit ? null : $this->key($current), $this->key($stale->fresh()));
            }
            $current = $this->update($stale, $this->image());
        }
        $objects = $this->objects($this->key($current));
        foreach ([false, true] as $commit) {
            DB::beginTransaction();
            $this->deleteEntity($stale);
            $this->assertNull($stale->newQuery()->find($stale->id));
            $this->assertObjects($objects, true);
            $commit ? DB::commit() : DB::rollBack();
            $this->assertObjects($objects, ! $commit);
            $this->assertSame($commit, $stale->newQuery()->find($stale->id) === null);
        }
        if ($stale instanceof NewsArticle) {
            $this->assertSoftDeleted($stale);
            $this->assertSame($this->key($current), NewsArticle::withTrashed()->findOrFail($stale->id)->image_key);
        }
    }

    #[DataProvider('domains')]
    public function test_real_database_rejection_preserves_old_set_and_compensates_new_upload(string $domain): void
    {
        $entity = $this->create($domain, $this->image());
        $old = $this->key($entity);
        $files = Storage::disk('media_local')->allFiles();
        $entity::saving(function (Model $row) {
            $row->{$row instanceof NewsArticle ? 'title' : 'name'} = null;
        });
        try {
            try {
                $this->update($entity, $this->image());
                $this->fail('Expected MariaDB NOT NULL rejection.');
            } catch (QueryException $exception) {
                $this->assertSame(1048, $exception->errorInfo[1]);
            }
            $this->assertSame($old, $this->key($entity->fresh()));
            $this->assertSame($files, Storage::disk('media_local')->allFiles());
            if ($domain !== 'avatar') {
                try {
                    $this->create($domain, $this->image());
                    $this->fail('Expected MariaDB create rejection.');
                } catch (QueryException $exception) {
                    $this->assertSame(1048, $exception->errorInfo[1]);
                }
                $this->assertSame($files, Storage::disk('media_local')->allFiles());
            }
        } finally {
            $entity::flushEventListeners();
        }
    }

    #[DataProvider('domains')]
    public function test_model_event_veto_cannot_publish_or_delete_media(string $domain): void
    {
        $entity = $this->create($domain, $this->image());
        $key = $this->key($entity);
        $objects = Storage::disk('media_local')->allFiles();
        $entity::saving(static fn () => false);
        $entity::deleting(static fn () => false);
        try {
            foreach (['replace', 'delete'] as $operation) {
                try {
                    $operation === 'replace' ? $this->update($entity, $this->image()) : $this->deleteEntity($entity);
                    $this->fail('A model veto must fail the media mutation.');
                } catch (RuntimeException $exception) {
                    $this->assertStringStartsWith('No se pudo ', $exception->getMessage());
                }
                $this->assertSame($key, $this->key($entity->fresh()));
                $this->assertSame($objects, Storage::disk('media_local')->allFiles());
            }
        } finally {
            $entity::flushEventListeners();
        }
    }

    #[DataProvider('domains')]
    public function test_legacy_masters_and_invalid_references_remain_safe(string $domain): void
    {
        $entity = $this->create($domain, $this->image());
        $this->deleteEntity($entity);
        foreach (['jpg', 'png', 'webp', 'invalid'] as $format) {
            $entity = $this->create($domain, $this->image());
            app(ResponsiveMediaStorage::class)->deleteSet($this->key($entity), $this->profile($domain));
            $key = $format === 'invalid' ? 'cms/00000000-0000-4000-8000-000000000001.png'
                : app(MediaObjectKeyGenerator::class)->generate($this->profile($domain)->purpose(), $format);
            Storage::disk('media_local')->put($key, 'legacy or unrelated');
            $entity->forceFill([$this->column($entity) => $key])->save();
            $this->update($entity, $this->image());
            $this->assertSame($format === 'invalid', Storage::disk('media_local')->exists($key));
            $this->deleteEntity($entity); // stale delete uses the replacement key.
        }
        foreach (['../private.png', 'https://legacy.invalid/private.png', 'malformed'] as $invalid) {
            $entity = $this->create($domain, $this->image());
            app(ResponsiveMediaStorage::class)->deleteSet($this->key($entity), $this->profile($domain));
            $entity->forceFill([$this->column($entity) => $invalid])->save();
            $real = Storage::disk('media_local');
            $before = $real->allFiles();
            // No adapter operation is permitted for these untrusted DB references.
            Storage::set('media_local', Mockery::mock(FilesystemAdapter::class));
            if ($domain !== 'sponsor') {
                $this->remove($entity);
                $this->assertNull($this->key($entity->fresh()));
                $entity->forceFill([$this->column($entity) => $invalid])->save();
            }
            $this->deleteEntity($entity);
            Storage::set('media_local', $real);
            $this->assertSame($before, Storage::disk('media_local')->allFiles());
        }
        foreach (['remove', 'delete'] as $operation) {
            if ($domain === 'sponsor' && $operation === 'remove') {
                continue;
            }
            $entity = $this->create($domain, $this->image());
            app(ResponsiveMediaStorage::class)->deleteSet($this->key($entity), $this->profile($domain));
            $legacy = app(MediaObjectKeyGenerator::class)->generate($this->profile($domain)->purpose(), 'jpg');
            Storage::disk('media_local')->put($legacy, 'legacy');
            $entity->forceFill([$this->column($entity) => $legacy])->save();
            $operation === 'remove' ? $this->remove($entity) : $this->deleteEntity($entity);
            Storage::disk('media_local')->assertMissing($legacy);
        }
    }

    #[DataProvider('domains')]
    public function test_cleanup_failure_after_real_commit_keeps_new_reference(string $domain): void
    {
        $entity = $this->create($domain, $this->image());
        $old = $this->key($entity);
        $oldObjects = $this->objects($old);
        $this->failMediaDeletion([$old], 1, function () {
            $this->assertSame(0, DB::transactionLevel());
        });
        DB::beginTransaction();
        $updated = $this->update($entity, $this->image());
        $this->assertObjects($oldObjects, true);
        DB::commit();
        $this->assertSame($this->key($updated), $this->key($entity->fresh()));
        $this->assertObjects(array_slice($oldObjects, 1), false);
        $this->assertResponsiveSet($this->key($updated));
    }

    public function test_player_only_deletion_preserves_entire_private_avatar_set(): void
    {
        $user = $this->create('avatar', $this->image());
        $objects = $this->objects($this->key($user));
        $player = Player::factory()->for($user)->create();
        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.players.destroy', $player))->assertRedirect();
        $this->assertNotNull($user->fresh());
        $this->assertObjects($objects, true);
    }

    #[DataProvider('competitions')]
    public function test_cascade_sets_survive_rollback_and_clean_only_deleted_descendants_on_commit(string $domain): void
    {
        $category = $this->create('category', $this->image());
        $championship = $this->update($category->championship, $this->image());
        $season = $this->update($championship->season, $this->image());
        $entity = $$domain;
        $objects = [];
        foreach ([$season, $championship, $category] as $row) {
            $objects[$row->getTable()] = $this->objects($this->key($row));
        }
        foreach ([false, true] as $commit) {
            DB::beginTransaction();
            $this->deleteEntity($entity);
            foreach ($objects as $set) {
                $this->assertObjects($set, true);
            }
            $commit ? DB::commit() : DB::rollBack();
            foreach ([$season, $championship, $category] as $row) {
                $this->assertObjects($objects[$row->getTable()], $row->fresh() !== null);
            }
        }
    }

    public function test_cascade_deduplicates_exact_keys_and_official_results_block_all_cleanup(): void
    {
        $category = $this->create('category', $this->image());
        $championship = $category->championship;
        $season = $championship->season;
        $key = $this->key($category);
        $championship->update(['image_path' => $key]);
        $season->update(['image_path' => $key]);
        $real = Storage::disk('media_local');
        $deleted = [];
        $disk = Mockery::mock($real)->makePartial();
        $disk->shouldReceive('delete')->andReturnUsing(function ($key) use (&$deleted, $real) {
            $deleted[] = $key;

            return $real->delete($key);
        });
        Storage::set('media_local', $disk);
        CategoryOfficialResult::factory()->create(['category_id' => $category->id]);
        foreach ([$category, $championship, $season] as $row) {
            $this->actingAs(User::factory()->admin()->create())->delete(route('admin.'.$row->getTable().'.destroy', $row))
                ->assertRedirect()->assertSessionHas('error');
            $this->assertNotNull($row->fresh());
            $this->assertSame([], $deleted);
        }
        CategoryOfficialResult::query()->where('category_id', $category->id)->delete();
        $this->deleteEntity($season);
        $this->assertCount(10, $deleted); // manifest, 4 widths x 2 formats, master.
        $this->assertCount(10, array_unique($deleted));
        $this->assertSame([], $real->allFiles());
    }

    #[DataProvider('competitionRetries')]
    public function test_competition_real_retries_never_delete_the_final_committed_set(string $domain, bool $failAll): void
    {
        $entity = $this->create($domain, $this->image());
        $old = $this->key($entity);
        $oldObjects = $this->objects($old);
        $blocker = User::factory()->create();
        $config = DB::connection()->getConfig();
        $other = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password']);
        $other->beginTransaction();
        $other->query('SELECT id FROM users WHERE id = '.(int) $blocker->id.' FOR UPDATE');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $attemptKeys = [];
        $entity::updated(function (Model $row) use (&$attemptKeys, $other, $blocker, $oldObjects, $failAll) {
            $attemptKeys[] = $this->key($row);
            $this->assertObjects($oldObjects, true);
            $this->assertResponsiveSet($this->key($row));
            try {
                DB::table('users')->where('id', $blocker->id)->update(['name' => 'retry']);
            } catch (QueryException $exception) {
                $this->assertSame(1205, $exception->errorInfo[1]);
                if (! $failAll) {
                    $other->rollBack();
                }
                throw $exception;
            }
        });
        $real = Storage::disk('media_local');
        $disk = Mockery::mock($real)->makePartial();
        $puts = [];
        $deletes = [];
        $disk->shouldReceive('put')->andReturnUsing(function ($key, $bytes, $options) use (&$puts, $real) {
            $puts[] = $key;

            return $real->put($key, $bytes, $options);
        });
        $disk->shouldReceive('delete')->andReturnUsing(function ($key) use (&$deletes, $real) {
            $deletes[] = $key;

            return $real->delete($key);
        });
        Storage::set('media_local', $disk);
        try {
            $updated = $this->update($entity, $this->image());
            $this->assertFalse($failAll);
        } catch (QueryException $exception) {
            $this->assertTrue($failAll);
            $this->assertSame(1205, $exception->errorInfo[1]);
        } finally {
            $entity::flushEventListeners();
            if ($other->inTransaction()) {
                $other->rollBack();
            }
            DB::statement('SET SESSION innodb_lock_wait_timeout = DEFAULT');
        }
        $this->assertCount($failAll ? 3 : 2, $attemptKeys);
        $this->assertCount(1, array_unique($attemptKeys));
        $this->assertCount(6, $puts);
        $this->assertCount(6, array_unique($puts));
        $this->assertCount(10, $deletes);
        $this->assertCount(10, array_unique($deletes));
        $this->assertSame($failAll ? $attemptKeys[0] : $old, end($deletes));
        $this->assertSame($failAll ? $old : $attemptKeys[0], $this->key($entity->fresh()));
        $this->assertOnlyResponsiveSet($this->key($entity->fresh()));
    }

    public static function competitionRetries(): iterable
    {
        foreach (['season', 'championship', 'category'] as $domain) {
            yield $domain.' succeeds' => [$domain, false];
            yield $domain.' fails' => [$domain, true];
        }
    }

    private function create(string $domain, UploadedFile $image): Model
    {
        return match ($domain) {
            'avatar' => app(ProfilePhotoService::class)->store(User::factory()->create(), $image),
            'news' => app(NewsArticleService::class)->create($this->newsAttributes(), $image, User::factory()->admin()->create()),
            'sponsor' => app(SponsorService::class)->create(['name' => 'Sponsor'], $image),
            'season' => app(SeasonService::class)->create(['name' => 'Season', 'status' => 'planned', 'is_public' => true], $image),
            'championship' => app(ChampionshipMutationService::class)->create([
                'name' => 'Championship '.Str::uuid(), 'status' => 'draft', 'is_public' => true,
                'season_id' => Season::factory()->publiclyVisible()->create()->id, 'type' => 'singles', 'registration_status' => 'closed',
            ], $image),
            default => app(CategoryMutationService::class)->create(Championship::factory()->publiclyVisible()->create(), [
                'name' => 'Category', 'status' => 'draft', 'is_public' => true, 'gender' => 'mixed',
            ], $image),
        };
    }

    private function update(Model $entity, ?UploadedFile $image, bool $remove = false): Model
    {
        return match (true) {
            $entity instanceof User => $remove ? app(ProfilePhotoService::class)->remove($entity) : app(ProfilePhotoService::class)->store($entity, $image),
            $entity instanceof NewsArticle => app(NewsArticleService::class)->update($entity, [
                ...$this->newsAttributes(), 'slug' => $entity->slug, 'remove_image' => $remove,
            ], $image, User::factory()->admin()->create()),
            $entity instanceof Sponsor => app(SponsorService::class)->update($entity, ['name' => $entity->name], $image),
            $entity instanceof Season => app(SeasonService::class)->update($entity, [
                'name' => $entity->name, 'status' => $entity->status->value, 'is_public' => true,
            ], $image, $remove),
            $entity instanceof Championship => app(ChampionshipMutationService::class)->update($entity, [
                'name' => $entity->name, 'status' => $entity->status, 'is_public' => true,
                'season_id' => $entity->season_id, 'type' => $entity->type->value, 'registration_status' => 'closed',
            ], $image, $remove),
            default => app(CategoryMutationService::class)->update($entity, [
                'name' => $entity->name, 'status' => $entity->status, 'is_public' => true, 'gender' => $entity->gender->value,
            ], $image, $remove),
        };
    }

    private function remove(Model $entity): void
    {
        $this->update($entity, null, true);
    }

    private function deleteEntity(Model $entity): void
    {
        match (true) {
            $entity instanceof User => app(ProfilePhotoService::class)->deleteUser($entity),
            $entity instanceof NewsArticle => app(NewsArticleService::class)->delete($entity),
            $entity instanceof Sponsor => app(SponsorService::class)->delete($entity),
            $entity instanceof Season => app(OfficialResultProtectedDeletionService::class)->deleteSeason($entity),
            $entity instanceof Championship => app(OfficialResultProtectedDeletionService::class)->deleteChampionship($entity),
            default => app(OfficialResultProtectedDeletionService::class)->deleteCategory($entity),
        };
    }

    private function column(Model $entity): string
    {
        return match (true) {
            $entity instanceof User => 'profile_photo_path', $entity instanceof NewsArticle => 'image_key',
            $entity instanceof Sponsor => 'logo_key', default => 'image_path',
        };
    }

    private function key(Model $entity): ?string
    {
        return $entity->{$this->column($entity)};
    }

    private function profile(string $domain): ResponsiveImageProfile
    {
        return match ($domain) {
            'avatar' => ResponsiveImageProfile::Avatar, 'news' => ResponsiveImageProfile::NewsCover,
            'sponsor' => ResponsiveImageProfile::SponsorLogo, default => ResponsiveImageProfile::Banner,
        };
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('source.png', 1600, 800);
    }

    private function newsAttributes(): array
    {
        return [
            'title' => 'News', 'slug' => 'news-'.Str::uuid(), 'excerpt' => 'Excerpt', 'body' => 'Body',
            'status' => 'draft', 'image_alt' => 'Fixture', 'image_source' => 'Test', 'image_rights_confirmed' => true,
        ];
    }

    private function objects(string $key): array
    {
        $manifest = app(ResponsiveMediaStorage::class)->readManifest($key);
        $this->assertNotNull($manifest);

        return [$key, app(ResponsiveMediaKeys::class)->manifest($key, $manifest->version), ...array_column($manifest->variants, 'key')];
    }

    private function assertObjects(array $objects, bool $exists): void
    {
        foreach ($objects as $key) {
            $this->assertSame($exists, Storage::disk('media_local')->exists($key), 'Unexpected responsive object availability.');
        }
    }
}
