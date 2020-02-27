<?php
namespace App\Modules\Manage\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ManageServiceProvider extends ServiceProvider
{
	/**
	 * Register the Manage module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Manage\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Manage module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('manage', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('manage', base_path('resources/views/vendor/manage'));
		View::addNamespace('manage', realpath(__DIR__.'/../Resources/Views'));
	}
}
