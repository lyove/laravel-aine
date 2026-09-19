<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use League\Flysystem\Filesystem;
use App\Aine\ContentSerializer;
use App\Filesystem\CosAdapter;
use App\Filesystem\OssAdapter;
use App\Filesystem\QiniuAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        JsonResource::withoutWrapping();

        // Object-storage drivers used when the global Media setting
        // "Image processing library" is set to a cloud provider. Each is
        // wrapped in the Laravel FilesystemAdapter so url()/temporaryUrl()
        // work through the adapter's getUrl()/config. S3 is built into
        // Laravel and does not need registration here.
        Storage::extend('oss', function ($app, array $config) {
            $adapter = new OssAdapter($config);

            return new LaravelFilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        Storage::extend('cos', function ($app, array $config) {
            $adapter = new CosAdapter($config);

            return new LaravelFilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        Storage::extend('qiniu', function ($app, array $config) {
            $adapter = new QiniuAdapter($config);

            return new LaravelFilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        $this->app->terminating(function () {
            ContentSerializer::reset();
            \App\Http\Resources\ContentResource::reset();
        });
    }
}
