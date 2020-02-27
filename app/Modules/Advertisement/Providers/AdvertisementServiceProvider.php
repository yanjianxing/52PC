<?php
namespace App\Modules\Advertisement\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class AdvertisementServiceProvider extends ServiceProvider
{
	/**
	 * Register the Advertisement module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Advertisement\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Advertisement module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('advertisement', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('advertisement', base_path('resources/views/vendor/advertisement'));
		View::addNamespace('advertisement', realpath(__DIR__.'/../Resources/Views'));
	}
}
