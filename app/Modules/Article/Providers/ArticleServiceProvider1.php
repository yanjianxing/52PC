<?php
namespace App\Modules\Article\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ArticleServiceProvider extends ServiceProvider
{
	/**
	 * Register the Article module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Article\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Article module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('article', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('article', base_path('resources/views/vendor/article'));
		View::addNamespace('article', realpath(__DIR__.'/../Resources/Views'));
	}
}
