<?php
namespace App\Modules\Task\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class TaskServiceProvider extends ServiceProvider
{
	/**
	 * Register the Task module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Task\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Task module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('task', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('task', base_path('resources/views/vendor/task'));
		View::addNamespace('task', realpath(__DIR__.'/../Resources/Views'));
	}
}
