<?php
namespace App\Modules\Bre\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class BreServiceProvider extends ServiceProvider
{
	/**
	 * Register the Bre module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Bre\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Bre module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('bre', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('bre', base_path('resources/views/vendor/bre'));
		View::addNamespace('bre', realpath(__DIR__.'/../Resources/Views'));
	}
}
