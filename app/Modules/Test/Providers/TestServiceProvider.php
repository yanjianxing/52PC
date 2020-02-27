<?php
namespace App\Modules\Test\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
	/**
	 * Register the Test module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Test\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Test module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('test', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('test', base_path('resources/views/vendor/test'));
		View::addNamespace('test', realpath(__DIR__.'/../Resources/Views'));
	}
}
