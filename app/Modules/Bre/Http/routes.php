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

Route::group(['prefix' => 'bre'], function() {
    Route::get('/', 'IndexController@index')->name('indexList');

    //服务商
    Route::get('/service', 'IndexController@getService')->name('serviceList');
    Route::post('/feedbackInfo', 'IndexController@creatInfo')->name('feedbackCreate');//添加投诉建议信息
    Route::get('/serviceCaseList/{uid}', 'ServiceController@serviceCaseList')->name('serviceCaseList');
    Route::get('/serviceEvaluateDetail/{uid}', 'ServiceController@serviceEvaluateDetail')->name('serviceEvaluateDetail');
    Route::get('/serviceCaseDetail/{id}/{uid}', 'ServiceController@serviceCaseDetail')->name('serviceCaseDetail');
    Route::get('/ajaxAdd', 'ServiceController@ajaxAdd')->name('ajaxCreateAttention');
    Route::get('/ajaxDel', 'ServiceController@ajaxDel')->name('ajaxDeleteAttention');
    Route::post('/contactMe', 'ServiceController@contactMe')->name('messageCreate');

    //前台协议详情页
    Route::get('/agree/{code_name}', 'AgreementController@index')->name('agreementDetail');

    //商城
    Route::get('/shop', 'IndexController@shop')->name('shopList');
    //商城立即发布链接切换
    Route::get('/changeUrl', 'IndexController@changeUrl')->name('changeUrl');

    //ajax获取商城下一页
    Route::post('/ajaxGoodsList', 'IndexController@ajaxGoodsList')->name('ajaxGoodsList');

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

Route::group(['prefix' => 'bre', 'middleware' => ['ruleengine']], function () {
	//

	//Route::get('/{id}', 'IndexController@breDetail');

    //首页
    //Route::get('/homePage', 'HomeController@index')->name('homePageList');
});
