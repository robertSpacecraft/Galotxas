<?php

namespace App\Services\Media\Backfill\Safety;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemS3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

/** No exists()+put fallback, master writes, retries, temp namespace or deletion. */
class ExclusiveObjectCreator
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    public function create(TargetObject $target, string $bytes): CreateReceipt
    {
        $target->validateBytes($bytes);
        $diskName = config('media.disk');
        try {
            if (! in_array($diskName, ['media_local', 'media_s3'], true)) {
                return new CreateReceipt(CreateState::Failed);
            }
            $disk = $this->filesystems->disk($diskName);
            $config = $disk->getConfig();
            $configured = config('filesystems.disks.'.$diskName, []);
            // Refuse a cached/replaced disk whose key mapping or identity differs from config.
            foreach (['driver', 'root', 'prefix', 'bucket', 'endpoint', 'region', 'use_path_style_endpoint', 'visibility'] as $field) {
                if (($config[$field] ?? null) !== ($configured[$field] ?? null)) {
                    return new CreateReceipt(CreateState::Failed);
                }
            }
            if (($config['visibility'] ?? null) !== 'private' || ! empty($config['prefix'])
                || ! empty($config['read-only'])) {
                return new CreateReceipt(CreateState::Failed);
            }
            if ($diskName === 'media_local' && ($config['driver'] ?? null) === 'local'
                && get_class($disk->getAdapter()) === LocalFilesystemAdapter::class) {
                return $this->local($disk, $target, $bytes);
            }
            if ($diskName === 'media_s3' && ($config['driver'] ?? null) === 's3'
                && $disk instanceof AwsS3V3Adapter && get_class($disk->getAdapter()) === FlysystemS3Adapter::class
                && empty($config['root']) && $disk->path($target->key) === $target->key
                && $disk->getClient() instanceof S3Client && ! empty($config['bucket'])) {
                return $this->s3($disk, $target, $bytes);
            }
        } catch (Throwable) {
            // Only adapter/config setup executes here; effect methods classify their own errors.
        }

        return new CreateReceipt(CreateState::Failed);
    }

    private function local(FilesystemAdapter $disk, TargetObject $target, string $bytes): CreateReceipt
    {
        $stream = null;
        $owned = false;
        try {
            $root = realpath($disk->getConfig()['root']);
            if ($root === false || ! is_dir($root)) {
                return new CreateReceipt(CreateState::Failed);
            }
            $parent = $root;
            foreach (explode('/', dirname($target->key)) as $component) {
                $parent .= '/'.$component;
                if (is_link($parent) || (! is_dir($parent) && ! @mkdir($parent, 0700) && ! is_dir($parent))
                    || realpath($parent) !== $parent) {
                    return new CreateReceipt(CreateState::Failed);
                }
            }
            $path = $parent.'/'.basename($target->key);
            // O_CREAT|O_EXCL: an existing file or symlink cannot be truncated or followed.
            $mask = umask(0077);
            try {
                $stream = @fopen($path, 'x+b');
            } finally {
                umask($mask);
            }
            if ($stream === false) {
                clearstatcache(true, $path);

                return new CreateReceipt(@lstat($path) !== false ? CreateState::Rejected : CreateState::Failed);
            }
            $owned = true;
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = $this->writeChunk($stream, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    return new CreateReceipt(CreateState::Unknown);
                }
                $offset += $written;
            }
            if (! fflush($stream) || ! fsync($stream)) {
                return new CreateReceipt(CreateState::Unknown);
            }
            if (! fclose($stream)) {
                $stream = null;

                return new CreateReceipt(CreateState::Unknown);
            }
            $stream = null;

            return new CreateReceipt(CreateState::Created);
        } catch (Throwable) {
            return new CreateReceipt($owned ? CreateState::Unknown : CreateState::Failed);
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }
        }
    }

    /** Narrow fault-injection seam for partial-write tests. */
    protected function writeChunk($stream, string $bytes): int|false
    {
        return fwrite($stream, $bytes);
    }

    private function s3(AwsS3V3Adapter $disk, TargetObject $target, string $bytes): CreateReceipt
    {
        $client = $disk->getClient();
        // Check installed SDK model before dispatch; provider behavior is a staging acceptance gate.
        if (! $client->getApi()->getOperation('PutObject')->getInput()->hasMember('IfNoneMatch')) {
            return new CreateReceipt(CreateState::Failed);
        }
        try {
            $result = $client->execute($client->getCommand('PutObject', [
                'Bucket' => $disk->getConfig()['bucket'], 'Key' => $target->key,
                'Body' => $bytes, 'ContentType' => $target->mimeType, 'IfNoneMatch' => '*',
                '@retries' => 0,
                // No ACL: private bucket defaults, compatible with bucket-owner-enforced storage.
            ]));

            $status = $result['@metadata']['statusCode'] ?? null;
            if ($status === 412) {
                return new CreateReceipt(CreateState::Rejected);
            }
            if (! is_int($status) || $status < 200 || $status >= 300) {
                return new CreateReceipt(CreateState::Unknown);
            }

            return new CreateReceipt(CreateState::Created, $result['ETag'] ?? null, $result['VersionId'] ?? null);
        } catch (AwsException $error) {
            if ($error->getStatusCode() === 412 || $error->getAwsErrorCode() === 'PreconditionFailed') {
                return new CreateReceipt(CreateState::Rejected);
            }

            return new CreateReceipt(CreateState::Unknown);
        } catch (Throwable) {
            return new CreateReceipt(CreateState::Unknown);
        }
    }
}
