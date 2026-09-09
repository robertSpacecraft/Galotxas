<?php

namespace Tests\Unit\Media;

use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\ResponsiveMediaResolver;
use App\Services\Media\ResponsiveMediaStorage;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class PreservedResponsiveManifestTest extends TestCase
{
    use ResponsiveImageFixtures;

    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    #[DataProvider('formats')]
    public function test_preserved_master_formats_round_trip(string $extension, string $mime, string $policy): void
    {
        $data = $this->data($extension, $mime, $policy);
        $manifest = $this->parse($data);
        $this->assertSame($data, json_decode($manifest->toJson(), true));
        $this->assertSame(2, $manifest->schemaVersion);
        $this->assertSame($mime, $manifest->master->mimeType);
    }

    public static function formats(): iterable
    {
        foreach (['photo', 'graphic'] as $policy) {
            foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $ext => $mime) {
                yield [$ext, $mime, $policy];
            }
        }
    }

    public function test_upload_factory_and_schema_one_shape_are_unchanged(): void
    {
        $set = app(ResponsiveImagePreparer::class)->prepare($this->uploadBytes($this->fixtureBytes(400, 200)),
            ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo);
        $key = 'banners/'.self::UUID.'.webp';
        $manifest = ResponsiveManifest::fromPrepared($key, $set, app(ResponsiveMediaKeys::class));
        $data = json_decode($manifest->toJson(), true);
        $this->assertSame(1, $data['schema_version']);
        $this->assertSame(['schema_version', 'policy_version', 'profile', 'preparation_policy', 'purpose', 'master', 'variants'], array_keys($data));
        // Schema 1 historically allows an incomplete list; schema 2 must not relax/change it.
        $data['variants'] = [];
        $this->assertSame($data, json_decode($this->parse($data)->toJson(), true));
    }

    #[DataProvider('invalidCases')]
    public function test_schema_two_rejects_invalid_contract(string $case): void
    {
        $data = $this->data();
        switch ($case) {
            case 'mode_missing': unset($data['master_mode']);
                break;
            case 'mode_wrong': $data['master_mode'] = 'normalized';
                break;
            case 'mode_type': $data['master_mode'] = true;
                break;
            case 'extra': $data['run_id'] = 'private';
                break;
            case 'schema_type': $data['schema_version'] = '2';
                break;
            case 'schema_unknown': $data['schema_version'] = 3;
                break;
            case 'schema_one_mode': $data['schema_version'] = 1;
                break;
            case 'schema_one_jpeg': $data['schema_version'] = 1;
                unset($data['master_mode']);
                break;
            case 'schema_one_photo_png': $data = $this->data('png', 'image/png');
                $data['schema_version'] = 1;
                unset($data['master_mode']);
                break;
            case 'incomplete': array_pop($data['variants']);
                break;
            case 'empty': $data['variants'] = [];
                break;
            case 'duplicate': $data['variants'][1] = $data['variants'][0];
                break;
            case 'order': $data['variants'] = array_reverse($data['variants']);
                break;
            case 'ratio': $data['variants'][0]['height'] = 200;
                break;
            case 'mime': $data['master']['mime_type'] = 'image/webp';
                break;
            case 'bounds': $data['master']['width'] = 1921;
                break;
            case 'zero': $data['master']['height'] = 0;
                break;
            case 'variant_extra': $data['variants'][0]['extra'] = 1;
                break;
            case 'master_extra': $data['master']['extra'] = 1;
                break;
            case 'purpose': $data['purpose'] = 'avatars';
                break;
            case 'profile': $data['profile'] = 'avatar';
                break;
            case 'policy': $data['preparation_policy'] = 'auto';
                break;
            case 'version': $data['policy_version'] = 'v2';
                break;
            case 'foreign_key': $data['variants'][0]['key'] = str_replace(self::UUID, '660e8400-e29b-41d4-a716-446655440000', $data['variants'][0]['key']);
                break;
            case 'variant_jpeg': $data['variants'][0]['key'] = str_replace('.webp', '.jpg', $data['variants'][0]['key']);
                $data['variants'][0]['mime_type'] = 'image/jpeg';
                break;
            case 'photo_png': $data['variants'][0]['key'] = str_replace('.webp', '.png', $data['variants'][0]['key']);
                $data['variants'][0]['mime_type'] = 'image/png';
                break;
            case 'equal_master': $data['master']['width'] = 640;
                $data['master']['height'] = 320;
                break;
        }
        $this->expectException(Throwable::class);
        $this->parse($data);
    }

    public static function invalidCases(): iterable
    {
        foreach (['mode_missing', 'mode_wrong', 'mode_type', 'extra', 'schema_type', 'schema_unknown', 'schema_one_mode',
            'schema_one_jpeg', 'schema_one_photo_png', 'incomplete', 'empty', 'duplicate', 'order', 'ratio', 'mime',
            'bounds', 'zero', 'variant_extra', 'master_extra', 'purpose', 'profile', 'policy', 'version', 'foreign_key',
            'variant_jpeg', 'photo_png', 'equal_master'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_graphic_can_mix_formats_and_tiny_master_needs_no_variants(): void
    {
        $data = $this->data('jpg', 'image/jpeg', 'graphic');
        $data['variants'][0]['key'] = str_replace('.webp', '.png', $data['variants'][0]['key']);
        $data['variants'][0]['mime_type'] = 'image/png';
        $this->assertCount(2, $this->parse($data)->variants);
        $data['master']['width'] = 320;
        $data['master']['height'] = 160;
        $data['variants'] = [];
        $this->assertSame([], $this->parse($data)->variants);
    }

    public function test_existing_public_resolver_consumes_preserved_manifest_without_contract_changes(): void
    {
        $manifest = $this->parse($this->data());
        $storage = Mockery::mock(ResponsiveMediaStorage::class);
        $storage->shouldReceive('readManifest')->once()->with($manifest->master->key)->andReturn($manifest);
        $cache = Mockery::mock(CacheFactory::class);
        $cache->shouldReceive('store')->once()->andThrow(new \RuntimeException('No presentation cache in this test.'));
        $resolver = new ResponsiveMediaResolver($storage, new MediaObjectKeyGenerator, $cache);
        $url = 'https://example.test/api/v1/seasons/1/image';
        $image = $resolver->image($manifest->master->key, ResponsiveImageProfile::Banner, $url, fn (int $width) => $url.'/'.$width);
        $this->assertSame(['url', 'width', 'height', 'variants'], array_keys($image));
        $this->assertSame($url, $image['url']);
        $this->assertSame(800, $image['width']);
        $this->assertSame([320, 640], array_column($image['variants'], 'width'));
        $this->assertSame(['image/webp', 'image/webp'], array_column($image['variants'], 'mime_type'));
    }

    private function data(string $ext = 'jpg', string $mime = 'image/jpeg', string $policy = 'photo'): array
    {
        return [
            'schema_version' => 2, 'policy_version' => 'v1', 'profile' => 'banner', 'preparation_policy' => $policy,
            'purpose' => 'banners', 'master_mode' => 'preserved',
            'master' => ['key' => 'banners/'.self::UUID.'.'.$ext, 'width' => 800, 'height' => 400, 'mime_type' => $mime, 'size' => 500],
            'variants' => array_map(fn (int $width) => ['key' => 'variants/v1/banners/'.self::UUID.'/w'.$width.'.webp',
                'width' => $width, 'height' => intdiv($width, 2), 'mime_type' => 'image/webp', 'size' => 100], [320, 640]),
        ];
    }

    private function parse(array $data): ResponsiveManifest
    {
        return ResponsiveManifest::fromJson(json_encode($data), $data['master']['key'],
            'variants/v1/banners/'.self::UUID.'/manifest.json', app(ResponsiveMediaKeys::class));
    }
}
