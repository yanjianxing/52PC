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

Route::group(['prefix' => 'im'], function() {

});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['prefix' => 'im'], function () {
	//
    Route::get('message/{uid}', 'IndexController@getMessage');
    Route::post('addAttention', 'IndexController@addAttention');


    //Route::post('addMessageNumber', 'IndexController@addMessageNumber');
    Route::post('imUserList', 'IndexController@imUserList');

    Route::get('imBlade', 'IndexController@imBlade');

    Route::post('getImUserInfo', 'IndexController@getImUserInfo');
});
