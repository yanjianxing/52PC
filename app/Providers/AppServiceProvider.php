<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Extensions\ExtendBlade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ExtendBlade::register();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
