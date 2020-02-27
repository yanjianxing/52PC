<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class CommonHelperProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        require app_path('Extensions/CommonHelper.php');
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
