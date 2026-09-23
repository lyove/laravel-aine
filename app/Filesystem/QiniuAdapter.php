<?php

namespace App\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use RuntimeException;
use Throwable;

/**
 * Qiniu Cloud Kodo Flysystem adapter backed by the official qiniu/php-sdk.
 */
class QiniuAdapter implements FilesystemAdapter
{
    protected Auth $auth;

    protected string $bucket;

    protected ?string $domain;

    protected PathPrefixer $prefixer;

    public function __construct(array $config)
    {
        $this->auth = new Auth($config['access_key'] ?? '', $config['secret_key'] ?? '');
        $this->bucket = $config['bucket'] ?? '';
        $this->domain = $config['custom_url'] ?: null;
        $this->prefixer = new PathPrefixer($config['prefix'] ?? '');
    }

    protected function key(string $path): string
    {
        return $this->prefixer->prefixPath(ltrim($path, '/'));
    }

    protected function manager(): BucketManager
    {
        return new BucketManager($this->auth);
    }

    protected function uploader(): UploadManager
    {
        return new UploadManager();
    }

    protected function assertOk(?Throwable $error): void
    {
        if ($error !== null) {
            throw new RuntimeException('Qiniu request failed: '.$error->getMessage());
        }
    }

    public function fileExists(string $path): bool
    {
        try {
            [, $error] = $this->manager()->stat($this->bucket, $this->key($path));

            return $error === null;
        } catch (Throwable) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            [, $items, $error] = $this->manager()->listPrefix(
                $this->bucket,
                rtrim($this->key($path), '/').'/',
                null,
                1
            );

            return $error === null && !empty($items);
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $token = $this->auth->uploadToken($this->bucket);
        [, $error] = $this->uploader()->put($token, $this->key($path), $contents, null, $config->get('mimetype'));

        $this->assertOk($error);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'qiniu_');
        file_put_contents($tmp, stream_get_contents($contents));

        try {
            $token = $this->auth->uploadToken($this->bucket);
            [, $error] = $this->uploader()->putFile($token, $this->key($path), $tmp, null, $config->get('mimetype'));
            $this->assertOk($error);
        } finally {
            @unlink($tmp);
        }
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($this->getUrl($path));

        if ($contents === false) {
            throw new RuntimeException('Qiniu read failed for: '.$path);
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $stream = tmpfile();
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        [, $error] = $this->manager()->delete($this->bucket, $this->key($path));

        if ($error !== null && !str_contains($error->message(), 'no such file')) {
            $this->assertOk($error);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = rtrim($this->key($path), '/').'/';

        do {
            [, $items, $error] = $this->manager()->listPrefix($this->bucket, $prefix);

            if ($error !== null) {
                $this->assertOk($error);

                break;
            }

            foreach ($items ?? [] as $item) {
                $this->manager()->delete($this->bucket, $item['key']);
            }
        } while (!empty($items) && count($items) >= 1000);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->write(rtrim($path, '/').'/', '', $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Qiniu bucket access is controlled at the bucket level; nothing to do.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $info = $this->stat($path);

        return new FileAttributes($path, mimeType: $info['mimeType'] ?? null);
    }

    public function lastModified(string $path): FileAttributes
    {
        $info = $this->stat($path);

        $timestamp = isset($info['putTime'])
            ? (int) round((int) $info['putTime'] / 10000000)
            : null;

        return new FileAttributes($path, lastModified: $timestamp);
    }

    public function fileSize(string $path): FileAttributes
    {
        $info = $this->stat($path);

        return new FileAttributes($path, fileSize: (int) ($info['fsize'] ?? 0));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = ltrim($this->key($path), '/');
        $marker = '';

        do {
            [$marker, $items, $error] = $this->manager()->listPrefix($this->bucket, $prefix, $marker ?: null, 1000);

            if ($error !== null) {
                $this->assertOk($error);

                break;
            }

            foreach ($items ?? [] as $item) {
                $key = $item['key'];

                if (str_ends_with($key, '/')) {
                    yield new DirectoryAttributes($this->prefixer->stripPrefix(rtrim($key, '/')));
                } else {
                    yield new FileAttributes(
                        $this->prefixer->stripPrefix($key),
                        fileSize: (int) ($item['fsize'] ?? 0),
                        lastModified: isset($item['putTime'])
                            ? (int) round((int) $item['putTime'] / 10000000)
                            : null,
                    );
                }
            }
        } while (!empty($items) && count($items) >= 1000);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        [, $error] = $this->manager()->copy($this->bucket, $this->key($source), $this->bucket, $this->key($destination));

        $this->assertOk($error);
    }

    /**
     * Public URL for a stored object (requires the bound domain).
     */
    public function getUrl(string $path): string
    {
        $key = $this->key($path);

        if (!$this->domain) {
            throw new RuntimeException('Qiniu access domain is not configured.');
        }

        return rtrim($this->domain, '/').'/'.ltrim($key, '/');
    }

    protected function stat(string $path): array
    {
        [$info, $error] = $this->manager()->stat($this->bucket, $this->key($path));

        $this->assertOk($error);

        return $info;
    }
}
