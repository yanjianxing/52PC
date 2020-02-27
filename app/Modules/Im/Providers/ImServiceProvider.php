<?php
namespace App\Modules\Im\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ImServiceProvider extends ServiceProvider
{
	/**
	 * Register the Im module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Im\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Im module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('im', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('im', base_path('resources/views/vendor/im'));
		View::addNamespace('im', realpath(__DIR__.'/../Resources/Views'));
	}
}
