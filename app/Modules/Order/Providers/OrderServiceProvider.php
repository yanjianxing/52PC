<?php
namespace App\Modules\Order\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
	/**
	 * Register the Order module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Order\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Order module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('order', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('order', base_path('resources/views/vendor/order'));
		View::addNamespace('order', realpath(__DIR__.'/../Resources/Views'));
	}
}
