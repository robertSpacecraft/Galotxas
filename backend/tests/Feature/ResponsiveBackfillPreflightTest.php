<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\ResponsiveBackfillPreflight;
use App\Services\Media\ExistingMasterPreparer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InspectsMemoryMedia;
use Tests\TestCase;
use Tests\Unit\Media\ResponsiveImageFixtures;

class ResponsiveBackfillPreflightTest extends TestCase
{
    use InspectsMemoryMedia;
    use RefreshDatabase;
    use ResponsiveImageFixtures;

    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    private function registry(): ManagedMediaReferenceRegistry
    {
        return app(ManagedMediaReferenceRegistry::class);
    }

    private function createReference(ManagedMediaDomain $domain): ManagedMediaReference
    {
        $attributes = [$domain->column() => $domain->profile()->purpose()->value.'/'.self::UUID.'.webp'];
        if ($prefix = $domain->metadataPrefix()) {
            $attributes[$prefix.'_width'] = 400;
            $attributes[$prefix.'_height'] = 200;
        }
        $model = $domain->model();
        $row = $model::factory()->create($attributes);

        return $this->registry()->find($domain, $row->id);
    }

    private function preflight(array $objects, ?callable $configure = null): ResponsiveBackfillPreflight
    {
        return new ResponsiveBackfillPreflight($this->registry(), $this->memoryInspector($objects, $configure), app(ExistingMasterPreparer::class));
    }

    private function publishedObjects(ManagedMediaReference $reference): array
    {
        $bytes = $this->fixtureBytes(400, 200, 'webp');
        $set = app(ExistingMasterPreparer::class)->prepare($reference->masterKey, $bytes, $reference->domain->profile(), $reference->domain->policy());
        $objects = [$reference->masterKey => $bytes];
        foreach ($set->manifest->variants as $index => $descriptor) {
            $objects[$descriptor->key] = $set->variants[$index]->bytes;
        }
        $objects['variants/v1/'.$this->registry()->identity($reference).'/manifest.json'] = $set->manifest->toJson();

        return $objects;
    }

    #[DataProvider('domains')]
    public function test_six_domains_are_live_owners_and_preflight_never_writes(ManagedMediaDomain $domain): void
    {
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $reference = $this->createReference($domain);
        $objects = $this->publishedObjects($reference);
        DB::listen(function ($query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query->sql);
        });
        $result = $this->preflight($objects)->inspect($reference);
        $this->assertSame(PreflightClassification::ResponsiveOk, $result->classification);
        $legacy = [$reference->masterKey => $objects[$reference->masterKey]];
        $result = $this->preflight($legacy)->inspect($reference);
        $this->assertSame(PreflightClassification::LegacyBackfillable, $result->classification);
        $this->assertSame($reference->masterKey, $result->prepared->master->key);
        $this->assertSame(2, $result->prepared->manifest->schemaVersion);
    }

    public static function domains(): iterable
    {
        foreach (ManagedMediaDomain::cases() as $domain) {
            yield $domain->value => [$domain];
        }
    }

    #[DataProvider('states')]
    public function test_deterministic_states(string $case, PreflightClassification $expected): void
    {
        $reference = $this->createReference(ManagedMediaDomain::News);
        $objects = $this->publishedObjects($reference);
        $manifestKey = 'variants/v1/'.$this->registry()->identity($reference).'/manifest.json';
        $variantKey = dirname($manifestKey).'/w320.webp';
        $configure = null;
        switch ($case) {
            case 'missing': unset($objects[$reference->masterKey]);
                break;
            case 'invalid_image': $objects[$reference->masterKey] = 'invalid';
                break;
            case 'corrupt_manifest': $objects[$manifestKey] = '{';
                break;
            case 'missing_variant': unset($objects[$variantKey]);
                break;
            case 'variant_header': $objects[$variantKey] = $this->fixtureBytes(20, 10, 'webp');
                break;
            case 'master_descriptor': $data = json_decode($objects[$manifestKey], true);
                $data['master']['size']++;
                $objects[$manifestKey] = json_encode($data);
                break;
            case 'schema1_incomplete': $data = json_decode($objects[$manifestKey], true);
                $data['schema_version'] = 1;
                unset($data['master_mode']);
                $data['variants'] = [];
                $objects[$manifestKey] = json_encode($data);
                break;
            case 'partial': unset($objects[$manifestKey]);
                break;
            case 'alternate': $objects[dirname($manifestKey).'/w320.png'] = 'alternate';
                break;
            case 'large_residue': $objects[dirname($manifestKey).'/w1280.webp'] = 'residue';
                break;
            case 'only_large_residue': $objects = [$reference->masterKey => $objects[$reference->masterKey], dirname($manifestKey).'/w1280.png' => 'residue'];
                break;
            case 'metadata': NewsArticle::whereKey($reference->id)->update(['image_width' => 99]);
                $reference = $this->registry()->find($reference->domain, $reference->id);
                break;
            case 'zero_metadata': NewsArticle::whereKey($reference->id)->update(['image_height' => 0]);
                $reference = $this->registry()->find($reference->domain, $reference->id);
                break;
            case 'null_metadata': NewsArticle::whereKey($reference->id)->update(['image_width' => null, 'image_height' => null]);
                $reference = $this->registry()->find($reference->domain, $reference->id);
                break;
            case 'transport': $configure = fn ($disk) => $disk->shouldReceive('fileExists')->with($reference->masterKey)->andThrow(new RuntimeException('private'));
                break;
            case 'manifest_transport': $configure = fn ($disk) => $disk->shouldReceive('readStream')->with($manifestKey)->andThrow(new RuntimeException('private'));
                break;
            case 'variant_transport': $configure = fn ($disk) => $disk->shouldReceive('readStream')->with($variantKey)->andThrow(new RuntimeException('private'));
                break;
            case 'invalid_ref': NewsArticle::whereKey($reference->id)->update(['image_key' => '../private']);
                $reference = $this->registry()->find($reference->domain, $reference->id);
                break;
        }
        $result = $this->preflight($objects, $configure)->inspect($reference);
        $this->assertSame($expected, $result->classification);
        if ($case === 'corrupt_manifest') {
            $this->assertContains(InspectionReason::ExistingVariantCollision, $result->reasons);
        }
    }

    public static function states(): iterable
    {
        yield ['missing', PreflightClassification::MasterMissing];
        yield ['invalid_image', PreflightClassification::MasterUnprocessable];
        yield ['corrupt_manifest', PreflightClassification::ManifestInvalid];
        yield ['missing_variant', PreflightClassification::ResponsiveIncomplete];
        yield ['variant_header', PreflightClassification::ResponsiveIncomplete];
        yield ['master_descriptor', PreflightClassification::ResponsiveIncomplete];
        yield ['schema1_incomplete', PreflightClassification::ResponsiveIncomplete];
        yield ['partial', PreflightClassification::PartialCollision];
        yield ['alternate', PreflightClassification::ResponsiveIncomplete];
        yield ['large_residue', PreflightClassification::ResponsiveIncomplete];
        yield ['only_large_residue', PreflightClassification::PartialCollision];
        yield ['metadata', PreflightClassification::MetadataMismatch];
        yield ['zero_metadata', PreflightClassification::MetadataMismatch];
        yield ['null_metadata', PreflightClassification::ResponsiveOk];
        yield ['transport', PreflightClassification::InspectionFailed];
        yield ['manifest_transport', PreflightClassification::InspectionFailed];
        yield ['variant_transport', PreflightClassification::InspectionFailed];
        yield ['invalid_ref', PreflightClassification::InvalidReference];
    }

    public function test_deleted_news_are_enumerated_but_excluded_even_with_invalid_key(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::News);
        $row = NewsArticle::findOrFail($reference->id);
        $row->update(['image_key' => '../old']);
        $row->delete();
        $references = $this->registry()->batch(ManagedMediaDomain::News);
        $this->assertCount(1, $references);
        $this->assertSame(PreflightClassification::ExcludedDeleted, $this->preflight([])->inspect($references[0])->classification);
        $this->assertSame([], $this->registry()->liveOwners($references));
    }

    public function test_nulls_and_inactive_owners_are_not_visibility_filtered(): void
    {
        $row = User::factory()->create(['active' => false]);
        $reference = $this->registry()->find(ManagedMediaDomain::Avatar, $row->id);
        $this->assertSame(PreflightClassification::ExcludedNull, $this->preflight([])->inspect($reference)->classification);
        $row->forceFill(['profile_photo_path' => 'avatars/'.self::UUID.'.webp'])->save();
        $reference = $this->registry()->find(ManagedMediaDomain::Avatar, $row->id);
        $this->assertSame(PreflightClassification::ResponsiveOk, $this->preflight($this->publishedObjects($reference))->inspect($reference)->classification);
    }

    #[DataProvider('sharedReferences')]
    public function test_duplicates_and_aliases_outside_selected_domain_take_precedence(string $extension): void
    {
        $reference = $this->createReference(ManagedMediaDomain::Category);
        Season::factory()->create(['image_path' => 'banners/'.self::UUID.'.'.$extension]);
        // Selection only contains category; registry still searches all owner domains.
        $result = $this->preflight([])->inspectBatch([$reference])[0];
        $this->assertSame(PreflightClassification::ReferenceConflict, $result->classification);
        $this->assertSame([InspectionReason::SharedIdentity], $result->reasons);
    }

    public static function sharedReferences(): iterable
    {
        yield ['webp'];
        yield ['jpg'];
        yield ['png'];
    }

    public function test_case_insensitive_database_matches_are_not_valid_owners_and_queries_are_batched(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::Category);
        Season::factory()->create(['image_path' => strtoupper($reference->masterKey)]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $owners = $this->registry()->liveOwners([$reference, $reference, $reference]);
        $this->assertCount(1, $owners[$this->registry()->identity($reference)]);
        $this->assertCount(6, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_batch_cursor_and_fresh_reference_read(): void
    {
        $rows = User::factory()->count(4)->create();
        $batch = $this->registry()->batch(ManagedMediaDomain::Avatar, $rows[0]->id, 2, $rows[3]->id);
        $this->assertSame([$rows[1]->id, $rows[2]->id], array_column($batch, 'id'));
        $rows[1]->forceFill(['profile_photo_path' => 'avatars/'.self::UUID.'.png'])->save();
        $this->assertNull($batch[0]->masterKey);
        $this->assertSame($rows[1]->profile_photo_path, $this->registry()->find(ManagedMediaDomain::Avatar, $rows[1]->id)->masterKey);
    }

    public function test_removed_owner_snapshot_cannot_be_backfillable(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::Category);
        Category::whereKey($reference->id)->update(['image_path' => null]);
        $this->assertSame(PreflightClassification::InvalidReference, $this->preflight([])->inspect($reference)->classification);
    }

    public function test_same_table_duplicate_avatars_are_conflicts(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::Avatar);
        User::factory()->create(['profile_photo_path' => $reference->masterKey]);
        $this->assertSame(PreflightClassification::ReferenceConflict, $this->preflight([])->inspect($reference)->classification);
    }

    public function test_soft_deleted_alias_does_not_claim_live_news_identity(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::News);
        $deleted = NewsArticle::factory()->create(['image_key' => str_replace('.webp', '.jpg', $reference->masterKey)]);
        $deleted->delete();
        $owners = $this->registry()->liveOwners([$reference]);
        $this->assertCount(1, $owners[$this->registry()->identity($reference)]);
        $this->assertSame(PreflightClassification::ResponsiveOk, $this->preflight($this->publishedObjects($reference))->inspect($reference)->classification);
    }

    public function test_inactive_sponsor_owns_media_but_metadata_mismatch_is_reported(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::Sponsor);
        Sponsor::whereKey($reference->id)->update(['is_active' => false, 'logo_width' => 401]);
        $reference = $this->registry()->find($reference->domain, $reference->id);
        $this->assertSame(PreflightClassification::MetadataMismatch, $this->preflight($this->publishedObjects($reference))->inspect($reference)->classification);
        $this->assertSame(401, $this->registry()->find($reference->domain, $reference->id)->width);
    }

    public function test_corrupt_manifest_precedes_collisions_metadata_and_secondary_inspection_failure(): void
    {
        $reference = $this->createReference(ManagedMediaDomain::News);
        NewsArticle::whereKey($reference->id)->update(['image_width' => 123]);
        $reference = $this->registry()->find($reference->domain, $reference->id);
        $objects = $this->publishedObjects($reference);
        $prefix = 'variants/v1/'.$this->registry()->identity($reference);
        $objects[$prefix.'/manifest.json'] = '{';
        $result = $this->preflight($objects,
            fn ($disk) => $disk->shouldReceive('fileExists')->with($prefix.'/w1280.png')->andThrow(new RuntimeException('private')))->inspect($reference);
        $this->assertSame(PreflightClassification::ManifestInvalid, $result->classification);
        $this->assertContains(InspectionReason::ExistingVariantCollision, $result->reasons);
        $this->assertContains(InspectionReason::TransportError, $result->reasons);
    }
}
