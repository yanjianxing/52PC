<?php
namespace App\Modules\Pay\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class PayServiceProvider extends ServiceProvider
{
	/**
	 * Register the Pay module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Pay\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Pay module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('pay', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('pay', base_path('resources/views/vendor/pay'));
		View::addNamespace('pay', realpath(__DIR__.'/../Resources/Views'));
	}
}
