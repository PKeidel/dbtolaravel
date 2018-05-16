<?php

namespace PKeidel\DBtoLaravel\Providers;

use Illuminate\Support\ServiceProvider;

class DBtoLaravelServiceProvider extends ServiceProvider {
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {

        if(!env('APP_DEBUG', false) && !env('DBTOLARAVEL_ENABLED', false))
            return;

//        $this->publishes([
//            __DIR__.'/path/to/config/dbtolaravel.php' => config_path('dbtolaravel.php'),
//        ]);
//        $value = config('dbtolaravel.option');

        $this->loadRoutesFrom(__DIR__.'/../routes.php');

//        $this->loadTranslationsFrom(__DIR__.'/../translations', 'dbtolaravel');

        // return view('courier::admin')
        $this->loadViewsFrom(__DIR__.'/../views', 'dbtolaravel');

//        if ($this->app->runningInConsole()) {
//            $this->commands([
//                FooCommand::class,
//                BarCommand::class,
//            ]);
//        }

//        $this->publishes([
//            __DIR__.'/path/to/assets' => public_path('vendor/courier'),
//        ], 'public');
//        // php artisan vendor:publish --tag=public --force
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        $this->app->singleton(\PKeidel\DBtoLaravel\DBtoLaravelHelper::class);
    }
}
