<?php

namespace App\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use OSS\OssClient;
use Throwable;

/**
 * Aliyun OSS Flysystem adapter backed by the official aliyuncs/oss-sdk-php.
 *
 * Used when the global Media setting "Image processing library" is set to OSS.
 * Objects are stored under the configured prefix with public-read ACL so the
 * OSS_* URL can serve them directly.
 */
class OssAdapter implements FilesystemAdapter
{
    protected OssClient $client;

    protected string $bucket;

    protected string $endpoint;

    protected ?string $customUrl;

    protected PathPrefixer $prefixer;

    public function __construct(array $config)
    {
        $this->client = new OssClient(
            $config['access_key_id'] ?? '',
            $config['access_key_secret'] ?? '',
            $config['endpoint'] ?? '',
            (bool) ($config['use_ssl'] ?? false),
        );

        $this->bucket = $config['bucket'] ?? '';
        $this->endpoint = $config['endpoint'] ?? '';
        $this->customUrl = $config['url'] ?? ($config['custom_url'] ?? null);
        $this->prefixer = new PathPrefixer($config['prefix'] ?? '');
    }

    protected function key(string $path): string
    {
        return $this->prefixer->prefixPath(ltrim($path, '/'));
    }

    protected function options(Config $config): array
    {
        $headers = [];

        if ($config->get('visibility') === 'public') {
            $headers[OssClient::OSS_HEADERS] = ['x-oss-object-acl' => 'public-read'];
        }

        $contentType = $config->get('mimetype');
        if ($contentType) {
            $headers[OssClient::OSS_CONTENT_TYPE] = $contentType;
        }

        return $headers;
    }

    /**
     * Public URL for a stored object (custom URL wins, otherwise the
     * bucket endpoint is used).
     */
    public function getUrl(string $path): string
    {
        $key = $this->key($path);

        if (!empty($this->customUrl)) {
            return rtrim($this->customUrl, '/').'/'.ltrim($key, '/');
        }

        $endpoint = rtrim($this->endpoint, '/');

        return $endpoint.'/'.$this->bucket.'/'.ltrim($key, '/');
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
            $result = $this->client->listObjects($this->bucket, [
                'prefix' => rtrim($this->key($path), '/').'/',
                'max-keys' => 1,
            ]);

            return count($result->getObjectList() ?? []) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->client->putObject($this->bucket, $this->key($path), $contents, $this->options($config));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $meta = stream_get_meta_data($contents);

        $this->client->uploadFile($this->bucket, $this->key($path), $meta['uri'], $this->options($config));
    }

    public function read(string $path): string
    {
        return $this->client->getObject($this->bucket, $this->key($path));
    }

    public function readStream(string $path)
    {
        $stream = tmpfile();
        fwrite($stream, $this->client->getObject($this->bucket, $this->key($path)));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->client->deleteObject($this->bucket, $this->key($path));
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = rtrim($this->key($path), '/').'/';
        $marker = null;

        do {
            $options = ['prefix' => $prefix, 'max-keys' => 1000];
            if ($marker !== null) {
                $options['marker'] = $marker;
            }

            $result = $this->client->listObjects($this->bucket, $options);

            $keys = array_map(
                static fn ($object) => $object->getKey(),
                $result->getObjectList() ?? []
            );

            if ($keys !== []) {
                $this->client->deleteObjects($this->bucket, $keys);
            }

            $marker = $result->getNextMarker();
        } while ($marker !== null && $marker !== '');
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->client->putObject($this->bucket, rtrim($this->key($path), '/').'/', '');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->client->putObjectAcl(
            $this->bucket,
            $this->key($path),
            $visibility === 'public' ? 'public-read' : 'private'
        );
    }

    public function visibility(string $path): FileAttributes
    {
        $acl = $this->client->getObjectAcl($this->bucket, $this->key($path));

        return new FileAttributes($path, visibility: $acl === 'public-read' ? 'public' : 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->client->getObjectMeta($this->bucket, $this->key($path));

        return new FileAttributes($path, mimeType: $meta['content-type'] ?? null);
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->client->getObjectMeta($this->bucket, $this->key($path));

        $timestamp = isset($meta['last-modified'])
            ? strtotime((string) $meta['last-modified'])
            : null;

        return new FileAttributes($path, lastModified: $timestamp ?: null);
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->client->getObjectMeta($this->bucket, $this->key($path));

        return new FileAttributes($path, fileSize: (int) ($meta['content-length'] ?? 0));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = ltrim($this->key($path), '/');
        $marker = null;

        do {
            $options = ['prefix' => $prefix, 'max-keys' => 1000];
            if ($marker !== null) {
                $options['marker'] = $marker;
            }

            $result = $this->client->listObjects($this->bucket, $options);

            foreach ($result->getObjectList() ?? [] as $object) {
                $key = $object->getKey();

                if (str_ends_with($key, '/')) {
                    yield new DirectoryAttributes($this->prefixer->stripPrefix(rtrim($key, '/')));
                } else {
                    yield new FileAttributes(
                        $this->prefixer->stripPrefix($key),
                        fileSize: $object->getSize(),
                        lastModified: $object->getLastModified(),
                    );
                }
            }

            $marker = $result->getNextMarker();
        } while ($marker !== null && $marker !== '');
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->client->copyObject(
            $this->bucket,
            $this->key($source),
            $this->bucket,
            $this->key($destination),
            $this->options($config),
        );
    }
}
