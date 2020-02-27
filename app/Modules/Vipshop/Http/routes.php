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

Route::group(['prefix' => 'vipshop'], function() {
	Route::get('/','VipshopController@Index')->name('shopIndex');//vip店铺首页路由


	Route::get('index','VipshopController@Index')->name('vipIndex');//vip首页路由
	Route::get('page','VipshopController@Page')->name('vipPage');//vip访谈路由
	Route::get('details/{id}','VipshopController@Details')->name('vipDeails');//vip访谈详情路由
	Route::get('payvip','VipshopController@getPayvip')->name('payvip');//套餐购买
    Route::post('feedback','VipshopController@feedback')->name('addFeedback');//创建vip反馈
    Route::get('vipinfo','VipshopController@vipinfo')->name('vipinfo');//特权介绍

    Route::get('/serviceshop','VipshopController@serviceshop')->name('serviceshop');//快包项目
    
    // vip特权全部路由

    Route::get('/vipDetail','VipshopController@vipDetail')->name('vipDetail');//vip详情介绍

    Route::get('/vipServer','VipshopController@vipServer')->name('vipServer');//vip服务

    Route::get('/vipCartServer','VipshopController@vipCartServer')->name('vipCartServer');//直通车服务

    Route::get('/vipTopServer','VipshopController@vipTopServer')->name('vipTopServer');//项目置顶服务

    Route::get('/vipUrgentServer','VipshopController@vipUrgentServer')->name('vipUrgentServer');//项目加急服务

    Route::get('/vipSicServer','VipshopController@vipSicServer')->name('vipSicServer');//私密项目对接服务

    Route::get('/developmentHistory','VipshopController@developmentHistory')->name('developmentHistory');//发展历程

    Route::get('/linkWe','VipshopController@linkWe')->name('linkWe');//联系我们

    Route::get('/cooperation','VipshopController@cooperation')->name('cooperation');//商务合作

    Route::get('/bannerShowPic','VipshopController@bannerShowPic')->name('bannerShowPic');//网站广告

    Route::get('/listsort','VipshopController@listsort')->name('listsort');//列表排名
});

Route::group(['prefix' => 'vipshop', 'middleware' => 'auth'], function (){
    Route::post('payvip','VipshopController@postPayvip');//套餐购买
    Route::get('vipPayorder','VipshopController@vipPayorder')->name('vipPayorder');//套餐支付
    Route::post('vipPayorder', 'VipshopController@postVipPayorder');
    Route::post('thirdPayorder', 'VipshopController@thirdPayorder');
    Route::get('vipsucceed','VipshopController@vipSucceed')->name('vipSucceed');//套餐支付成功
    Route::get('vipfailure','VipshopController@vipFailure')->name('vipFailure');//套餐支付失败

});
