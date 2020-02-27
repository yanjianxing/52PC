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

Route::group(['prefix' => 'article'], function() {

	//资讯中心
	Route::get('/','InformationController@index')->name('informationList');
	//新闻内容
	Route::get('/{id}','InformationController@newsDetail')->name('newsDetail')->where('id', '[0-9]+');

	//页脚配置 关于我们
	Route::get('/aboutUs/{catID}','FooterArticleController@aboutUs')->name('aboutUsDetail');
	//页脚配置 帮助中心
	Route::get('/helpCenter/{catID}/{upID}','FooterArticleController@helpCenter')->name('helpCenterDetail');

});