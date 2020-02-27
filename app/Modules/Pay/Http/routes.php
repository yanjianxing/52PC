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


Route::post('/pay/wechatpay/notify', 'WechatpayController@notify')->name('wechatpayCreate');
Route::post('/pay/alipay/notify', 'AlipayController@notify')->name('alipaypayCreate');

Route::group(['prefix' => 'pay', 'middleware' => 'auth'], function() {
	Route::get('/', function() {
		dd('This is the Pay module index page.');
	});
    //支付宝支付路由
    Route::get('/alipay', 'AlipayController@getAlipay')->name('alipayPage');
    Route::get('/alipay/return', 'AlipayController@result')->name('alipayReturnPage');


    //银联支付路由
//    Route::get('/unionpay', 'UnionpayController@getUnionpay');
//    Route::get('/unionpay/return', 'UnionpayController@result');
//    Route::post('/unionpay/notify', 'UnionpayController@notify');

    //微信支付路由
    Route::get('/wechatpay', 'WechatpayController@getWechatpay')->name('wechatpay');


});