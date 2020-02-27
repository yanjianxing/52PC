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

Route::group(['prefix' => 'test'], function() {
	Route::get('/', function() {
		dd('This is the Test module index page.');
	});
	
	Route::any('/testCallback','TestCallbackController@testCallback');//test oauth2.0 callbackURL
});
