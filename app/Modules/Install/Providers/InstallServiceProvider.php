<?php

namespace App\Modules\Install\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class InstallServiceProvider extends ServiceProvider
{
	/**
     * Bootstrap the application events.
     *
     * @return void
     */
	public function boot()
	{
		// You may register any additional middleware provided with your
		// module with the following addMiddleware() method. You may
		// pass in either an array or a string.
		// $this->addMiddleware('');
	}

	/**
	 * Register the Install module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Install\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Install module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('install', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('install', base_path('resources/views/vendor/install'));
		View::addNamespace('install', realpath(__DIR__.'/../Resources/Views'));
	}

}
