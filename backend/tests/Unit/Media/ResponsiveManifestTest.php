<?php

namespace Tests\Unit\Media;

use App\Services\Media\ImageFormat;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\VariantPolicyVersion;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class ResponsiveManifestTest extends TestCase
{
    private const MASTER = 'banners/550e8400-e29b-41d4-a716-446655440000.webp';

    private const PREFIX = 'variants/v1/banners/550e8400-e29b-41d4-a716-446655440000';

    public function test_keys_are_deterministic_and_not_accepted_by_existing_delivery_validator(): void
    {
        $keys = app(ResponsiveMediaKeys::class);
        $manifest = $keys->manifest(self::MASTER, VariantPolicyVersion::V1);
        $variant = $keys->variant(self::MASTER, ResponsiveImageProfile::Banner, VariantPolicyVersion::V1, 320, ImageFormat::Webp);
        $this->assertSame(self::PREFIX.'/manifest.json', $manifest);
        $this->assertSame(self::PREFIX.'/w320.webp', $variant);
        $this->assertFalse(app(MediaObjectKeyGenerator::class)->isValid($manifest));
        $this->assertFalse(app(MediaObjectKeyGenerator::class)->isValid($variant));
        $this->assertTrue($keys->isValidVariant($variant, self::MASTER, ResponsiveImageProfile::Banner, VariantPolicyVersion::V1, 320, ImageFormat::Webp));
        $this->assertFalse($keys->isValidVariant($variant, self::MASTER, ResponsiveImageProfile::Avatar, VariantPolicyVersion::V1, 320, ImageFormat::Webp));
        $this->assertFalse($keys->isValidVariant($variant, self::MASTER, ResponsiveImageProfile::Banner, VariantPolicyVersion::V1, 321, ImageFormat::Webp));
        $this->assertFalse($keys->isValidVariant($variant, self::MASTER, ResponsiveImageProfile::Banner, VariantPolicyVersion::V1, 320, ImageFormat::Jpeg));
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_master_cannot_derive_artifacts(string $master): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ResponsiveMediaKeys::class)->manifest($master, VariantPolicyVersion::V1);
    }

    public static function invalidKeys(): iterable
    {
        foreach (['../'.self::MASTER, self::MASTER.'/../x', self::MASTER."\n", '/'.self::MASTER,
            str_replace('banners/', 'other/', self::MASTER), str_replace('.webp', '.json', self::MASTER),
            str_replace('41d4', 'z1d4', self::MASTER), self::PREFIX.'/manifest.json'] as $key) {
            yield [$key];
        }
    }

    public function test_valid_manifest_round_trips_and_zero_variants_are_valid(): void
    {
        $keys = app(ResponsiveMediaKeys::class);
        $data = $this->validData();
        foreach ([$data, [...$data, 'variants' => []]] as $document) {
            $manifest = ResponsiveManifest::fromJson(json_encode($document), self::MASTER, self::PREFIX.'/manifest.json', $keys);
            $this->assertSame($document, json_decode($manifest->toJson(), true));
            $this->assertSame(self::MASTER, $manifest->master->key);
        }
    }

    #[DataProvider('corruptions')]
    public function test_corrupt_manifest_never_produces_trusted_descriptors(string $case): void
    {
        $data = $this->validData();
        $manifestKey = self::PREFIX.'/manifest.json';
        switch ($case) {
            case 'schema': $data['schema_version'] = 2;
                break;
            case 'schema type': $data['schema_version'] = '1';
                break;
            case 'version': $data['policy_version'] = 'v2';
                break;
            case 'policy': $data['preparation_policy'] = 'automatic';
                break;
            case 'profile': $data['profile'] = 'avatar';
                break;
            case 'purpose': $data['purpose'] = 'avatars';
                break;
            case 'unknown': $data['extra'] = 'unsafe';
                break;
            case 'missing': unset($data['master']);
                break;
            case 'master key': $data['master']['key'] = str_replace('440000', '440001', self::MASTER);
                break;
            case 'master mime': $data['master']['mime_type'] = 'image/png';
                break;
            case 'master bounds': $data['master']['width'] = 9999;
                break;
            case 'master size': $data['master']['size'] = 0;
                break;
            case 'manifest path': $manifestKey = str_replace('banners', 'avatars', $manifestKey);
                break;
            case 'manifest uuid': $manifestKey = str_replace('440000', '440001', $manifestKey);
                break;
            case 'manifest version': $manifestKey = str_replace('/v1/', '/v2/', $manifestKey);
                break;
            case 'traversal': $data['variants'][0]['key'] = '../'.self::PREFIX.'/w320.webp';
                break;
            case 'foreign purpose': $data['variants'][0]['key'] = str_replace('banners', 'avatars', $data['variants'][0]['key']);
                break;
            case 'foreign uuid': $data['variants'][0]['key'] = str_replace('440000', '440001', $data['variants'][0]['key']);
                break;
            case 'variant version': $data['variants'][0]['key'] = str_replace('/v1/', '/v2/', $data['variants'][0]['key']);
                break;
            case 'width allowlist': $data['variants'][0]['width'] = 321;
                $data['variants'][0]['key'] = self::PREFIX.'/w321.webp';
                break;
            case 'width key mismatch': $data['variants'][0]['key'] = self::PREFIX.'/w640.webp';
                break;
            case 'width type': $data['variants'][0]['width'] = '320';
                break;
            case 'width float': $data['variants'][0]['width'] = 320.5;
                break;
            case 'duplicate': $data['variants'][] = $data['variants'][0];
                break;
            case 'order': $data['variants'] = array_reverse($data['variants']);
                break;
            case 'master duplicated': $data['master']['width'] = 640;
                break;
            case 'height': $data['variants'][0]['height'] = 0;
                break;
            case 'ratio': $data['variants'][0]['height'] = 300;
                break;
            case 'size': $data['variants'][0]['size'] = -1;
                break;
            case 'mime': $data['variants'][0]['mime_type'] = 'application/json';
                break;
            case 'extension': $data['variants'][0]['key'] = self::PREFIX.'/w320.png';
                break;
            case 'jpeg': $data['variants'][0]['key'] = self::PREFIX.'/w320.jpg';
                $data['variants'][0]['mime_type'] = 'image/jpeg';
                break;
            case 'photo png': $data['variants'][0]['key'] = self::PREFIX.'/w320.png';
                $data['variants'][0]['mime_type'] = 'image/png';
                break;
            case 'descriptor extra': $data['variants'][0]['unsafe'] = true;
                break;
            case 'list object': $data['variants'] = (object) [];
                break;
            case 'descriptor array': $data['variants'][0] = [];
                break;
        }
        $json = match ($case) {
            'json' => '{broken',
            'root' => '[]',
            'oversize' => str_repeat(' ', ResponsiveManifest::MAX_BYTES + 1).json_encode($data),
            default => json_encode($data),
        };
        try {
            ResponsiveManifest::fromJson($json, self::MASTER, $manifestKey, app(ResponsiveMediaKeys::class));
        } catch (Throwable) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Corrupt manifest was accepted: '.$case);
    }

    public static function corruptions(): iterable
    {
        foreach (['schema', 'schema type', 'version', 'policy', 'profile', 'purpose', 'unknown', 'missing',
            'master key', 'master mime', 'master bounds', 'master size', 'manifest path', 'manifest uuid', 'manifest version',
            'traversal', 'foreign purpose', 'foreign uuid', 'variant version', 'width allowlist', 'width key mismatch',
            'width type', 'width float', 'duplicate', 'order', 'master duplicated', 'height', 'ratio', 'size', 'mime',
            'extension', 'jpeg', 'photo png', 'descriptor extra', 'list object', 'descriptor array', 'json', 'root', 'oversize'] as $case) {
            yield $case => [$case];
        }
    }

    private function validData(): array
    {
        return [
            'schema_version' => 1, 'policy_version' => 'v1', 'profile' => 'banner',
            'preparation_policy' => 'photo', 'purpose' => 'banners',
            'master' => ['key' => self::MASTER, 'width' => 800, 'height' => 400, 'mime_type' => 'image/webp', 'size' => 1000],
            'variants' => [
                ['key' => self::PREFIX.'/w320.webp', 'width' => 320, 'height' => 160, 'mime_type' => 'image/webp', 'size' => 100],
                ['key' => self::PREFIX.'/w640.webp', 'width' => 640, 'height' => 320, 'mime_type' => 'image/webp', 'size' => 200],
            ],
        ];
    }
}
