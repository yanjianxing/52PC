<?php

/*
|--------------------------------------------------------------------------
| Module Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for the module.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::group(['prefix' => 'substation', 'middleware' => ['substation']], function() {
	Route::get('/tasks/{id}','TaskController@getTasks')->name('substation_tasks');//分站需求路由
	Route::get('/service/{id}','ServiceController@getService')->name('substation_service');//分站服务商路由
	Route::get('/{id}','TaskController@index')->name('substation/index');//分站首页路由
});
