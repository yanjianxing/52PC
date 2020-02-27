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

Route::group(['prefix' => 'page'], function() {
	Route::get('/yingjian.html','PageController@yingjian')->name('yingjian');
	Route::get('/yingjian2.html','PageController@yingjian2')->name('yingjian2');
	Route::get('/ruanjian.html','PageController@ruanjian')->name('ruanjian');
	Route::get('/app.html','PageController@app')->name('app');
	Route::get('/fwsrz.html','PageController@fwsrz')->name('fwsrz');
	Route::get('/gongkong.html','PageController@gongkong')->name('gongkong');
	Route::get('/employerty.html','PageController@employer_tongyong')->name('employer_tongyong');//雇主通用着陆页
    Route::get('/employer_create.html','PageController@employer_create')->name('employer_create');//.发包落地页
    Route::get('/orienteering_create.html','PageController@orienteering_create')->name('orienteering_create');//.定向发包落地页
	Route::post('/getPageCode','PageController@getPageCode')->name('getPageCode');
	Route::post('/createHardware','PageController@createHardware')->name('createHardware');
});
