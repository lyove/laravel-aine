<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Validation\Rules\Password;
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

        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

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
