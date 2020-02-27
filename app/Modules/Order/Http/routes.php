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

Route::group(['prefix' => 'order','middleware' => 'auth'], function() {

	Route::get('pay/alipay/return','CallBackController@alipayReturn')->name('alipayReturn');//支付宝支付同步回调地址

});
Route::get('order/searchOrder','CallBackController@searchOrder')->name('searchOrder');//查询订单状态
Route::get('order/searcharticleOrder','CallBackController@searcharticleOrder')->name('searcharticleOrder');//查询资讯支付订单状态

//支付回调
Route::post('order/pay/alipay/notify', 'CallBackController@alipayNotify')->name('alipayNotifyCreate');
Route::post('order/pay/wechat/notify', 'CallBackController@wechatNotify')->name('wechatNotifyCreate');
