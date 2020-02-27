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
Route::group(['prefix' => 'news'], function() {
    Route::get('/','IndexController@casenews')->name('casenews');//方案讯
    Route::get('/{id}','IndexController@newsdetailpage')->name('newsdetailpage')->where('id', '[0-9]+');//方案讯详情

    Route::get('/special','IndexController@specialList')->name('specialList');//专题列表
    Route::get('/special/{id}','IndexController@specialDetail')->name('specialDetail')->where('id', '[0-9]+');//专题详情

});

Route::group(['prefix' => 'employ'], function() {
    Route::get('/create/{id}','EmployController@employCreate')->where('id', '[0-9]+');//雇佣TA
    Route::post('/update','EmployController@employUpdate');//提交雇佣数据
});

Route::group(['prefix' => 'employ','middleware' => 'auth'], function() {

	// Route::get('/create/{id}','EmployController@employCreate')->where('id', '[0-9]+');//雇佣TA
	// Route::post('/update','EmployController@employUpdate');//提交雇佣数据
	Route::get('/success/{id}','EmployController@success')->name('success')->where('id', '[0-9]+');//雇佣发布成功等待页面
	Route::get('/workIn/{id}','EmployController@workin')->name('workin')->where('id', '[0-9]+');//雇佣详情


    Route::post('/comment','IndexController@comment')->name('comment');//方案讯评论
    Route::get('/addnewspage','IndexController@addnewspage')->name('addnewspage');//方案讯新增
    Route::post('/postsavenews','IndexController@postsavenews')->name('postsavenews');//方案讯新增
    Route::get('/articlesuccess/{id}','IndexController@articlesuccess')->name('articlesuccess');//成功支付资讯

    Route::get('/trusteemoney/{id}','IndexController@trusteemoney')->name('trusteemoney');//任务详情 托管赏金
    Route::post('/paytrusteemoney','IndexController@paytrusteemoney')->name('paytrusteemoney');//任务详情 托管赏金

    Route::get('/shares','IndexController@shares')->name('shares'); //分享自动发送相关奖励


});
