<?php

namespace App\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use Qcloud\Cos\Client;
use Throwable;

/**
 * Tencent Cloud COS Flysystem adapter backed by the official qcloud/cos-sdk-v5.
 */
class CosAdapter implements FilesystemAdapter
{
    protected Client $client;

    protected string $bucket;

    protected string $region;

    protected ?string $customUrl;

    protected PathPrefixer $prefixer;

    public function __construct(array $config)
    {
        $this->bucket = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? '';
        $this->customUrl = $config['custom_url'] ?: null;
        $this->prefixer = new PathPrefixer($config['prefix'] ?? '');

        $this->client = new Client([
            'region' => $this->region,
            'scheme' => 'https',
            'credentials' => [
                'secretId' => $config['secret_id'] ?? '',
                'secretKey' => $config['secret_key'] ?? '',
            ],
        ]);
    }

    protected function key(string $path): string
    {
        return $this->prefixer->prefixPath(ltrim($path, '/'));
    }

    protected function options(Config $config): array
    {
        $options = [];

        $contentType = $config->get('mimetype');
        if ($contentType) {
            $options['ContentType'] = $contentType;
        }

        return $options;
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->key($path));
        } catch (Throwable) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $result = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => rtrim($this->key($path), '/').'/',
                'MaxKeys' => 1,
            ]);

            return !empty($result['Contents']);
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->client->putObject(array_merge($this->options($config), [
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
            'Body' => $contents,
        ]));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->client->upload($this->bucket, $this->key($path), $contents, $this->options($config));
    }

    public function read(string $path): string
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
        ]);

        return (string) $result['Body'];
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
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
        ]);
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = rtrim($this->key($path), '/').'/';
        $marker = '';

        do {
            $result = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => 1000,
                'Marker' => $marker,
            ]);

            $keys = array_column($result['Contents'] ?? [], 'Key');

            if ($keys !== []) {
                $this->client->deleteObjects([
                    'Bucket' => $this->bucket,
                    'Objects' => array_map(static fn ($k) => ['Key' => $k], $keys),
                ]);
            }

            $marker = $result['NextMarker'] ?? '';
        } while ($marker !== '');
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => rtrim($this->key($path), '/').'/',
            'Body' => '',
        ]);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // COS object ACLs are managed through bucket policies; nothing to do.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->head($path);

        return new FileAttributes($path, mimeType: $meta['ContentType'] ?? null);
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->head($path);

        $timestamp = isset($meta['LastModified'])
            ? strtotime((string) $meta['LastModified'])
            : null;

        return new FileAttributes($path, lastModified: $timestamp ?: null);
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->head($path);

        return new FileAttributes($path, fileSize: (int) ($meta['ContentLength'] ?? 0));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = ltrim($this->key($path), '/');
        $marker = '';

        do {
            $result = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => 1000,
                'Marker' => $marker,
            ]);

            foreach ($result['Contents'] ?? [] as $object) {
                $key = $object['Key'];

                if (str_ends_with($key, '/')) {
                    yield new DirectoryAttributes($this->prefixer->stripPrefix(rtrim($key, '/')));
                } else {
                    yield new FileAttributes(
                        $this->prefixer->stripPrefix($key),
                        fileSize: (int) ($object['Size'] ?? 0),
                        lastModified: isset($object['LastModified'])
                            ? strtotime((string) $object['LastModified']) ?: null
                            : null,
                    );
                }
            }

            $marker = $result['NextMarker'] ?? '';
        } while ($marker !== '');
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->client->copyObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($destination),
            'CopySource' => $this->bucket.'.cos.'.$this->region.'.myqcloud.com/'.$this->key($source),
        ]);
    }

    /**
     * Public URL for a stored object.
     */
    public function getUrl(string $path): string
    {
        $key = $this->key($path);

        if ($this->customUrl) {
            return rtrim($this->customUrl, '/').'/'.ltrim($key, '/');
        }

        return 'https://'.$this->bucket.'.cos.'.$this->region.'.myqcloud.com/'.ltrim($key, '/');
    }

    protected function head(string $path): array
    {
        $result = $this->client->headObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
        ]);

        return $result->toArray();
    }
}
