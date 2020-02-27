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

Route::group(['prefix' => 'activity','middleware' => 'auth'], function() {
	Route::get('/checkdownloadlogin','ActivityController@checkdownloadlogin')->name('checkdownloadlogin');//白皮书下载检测登录返回
	Route::post('/downloadearthwhitepaper','ActivityController@downloadearthwhitepaper')->name('downloadearthwhitepaper');//白皮书下载


    Route::get('/checkdownloadlogin101','ActivityController@checkdownloadlogin101')->name('checkdownloadlogin101');//成功案例下载第一版测试登录返回
    Route::get('/checkdownloadlogin102','ActivityController@checkdownloadlogin102')->name('checkdownloadlogin102');//成功案例下载第二版测试登录返回

    Route::post('/lottery','ActivityController@lottery')->name('lottery');//白皮书下载
	Route::post('/Siliconlottery','ActivityController@Siliconlottery')->name('Siliconlottery');//白皮书下载
	Route::post('/NIlottery','ActivityController@NIlottery')->name('NIlottery');//白皮书下载
});

Route::group(['prefix' => 'activity'], function() {
	Route::get('/wyzlkb','ActivityController@wyzlkb')->name('wyzlkb');//维样
	Route::post('/postimg','ActivityController@postimg')->name('postimg');//维样上传图片
	Route::get('/casedownload','ActivityController@casedownload')->name('casedownload');//成功案例下载第一版
	Route::get('/casedownload2','ActivityController@casedownload2')->name('casedownload2');//成功案例下载第二版
	Route::get('/casedownload3','ActivityController@casedownload3')->name('casedownload3');//.成功案例下载第三版
	Route::post('/postdownload','ActivityController@postdownload')->name('postdownload');//成功案例下载第一版保存用户信息
	Route::post('/postdownload2','ActivityController@postdownload2')->name('postdownload2');//成功案例下载第二版保存用户信息
	Route::post('/postdownload3','ActivityController@postdownload3')->name('postdownload3');//.成功案例下载第三版保存用户信息
	Route::get('/packagelist','ActivityController@package_list')->name('package_list');//成功案例下载
	Route::get('/whitepaper','ActivityController@whitepaper')->name('whitepaper');//白皮书下载
	Route::post('/downloadwhitepaper','ActivityController@downloadwhitepaper')->name('downloadwhitepaper');//白皮书下载

	Route::get('/earthwhitepaper','ActivityController@earthwhitepaper')->name('earthwhitepaper');//世键白皮书下载
	Route::post('/checkdownload','ActivityController@checkdownload')->name('checkdownload');//白皮书下载

	Route::get('/nationwhitepaper','ActivityController@nationwhitepaper')->name('nationwhitepaper');//Nation白皮书

    Route::get('/ADICarwhitepaper','ActivityController@ADICarwhitepaper')->name('ADICarwhitepaper');//.ADI混合动力/电动汽车白皮书
    Route::get('/ADIBatterywhitepaper','ActivityController@ADIBatterywhitepaper')->name('ADIBatterywhitepaper');//.ADI锂离子电池白皮书
    Route::get('/ADICircuitwhitepaper','ActivityController@ADICircuitwhitepaper')->name('ADICircuitwhitepaper');//.ADI参考电路白皮书
    Route::get('/nationalLaboratory','ActivityController@nationalLaboratory')->name('nationalLaboratory');//.国体智慧开放实验室
    Route::post('/postnationalLaboratory','ActivityController@postnationalLaboratory')->name('postnationalLaboratory');//.国体智慧开放实验室保存用户信息
    Route::get('/millionSupportProgram','ActivityController@millionSupportProgram')->name('millionSupportProgram');//.百万扶持计划页面展示
    Route::get('/millionProgramgCollect','ActivityController@millionProgramgCollect')->name('millionProgramgCollect');//.百万扶持计划页面收集用户信息
    Route::post('/postmillionProgramgCollect','ActivityController@postmillionProgramgCollect')->name('postmillionProgramgCollect');//.百万扶持计划页面保存用户信息


    Route::get('/specialzone1','ActivityController@specialzone1')->name('specialzone1');//特色专区落地页面
	Route::get('/specialzone2','ActivityController@specialzone2')->name('specialzone2');//特色专区落地页面
	Route::get('/specialzone3','ActivityController@specialzone3')->name('specialzone3');//特色专区落地页面
	Route::get('/facsxc','ActivityController@facsxc')->name('facsxc');//特色专区落地页面
	Route::get('/seminar','ActivityController@seminar')->name('seminar');//特色专区落地页面
	Route::get('/facs','ActivityController@facs')->name('facs');//方案超市局域网

	Route::get('/sendVip','ActivityController@sendvip')->name('sendvip');//送会员活动
	Route::get('/facsList','ActivityController@facsList')->name('facsList');//.方案榜单

	Route::post('/postxinpian','ActivityController@postxinpian')->name('postxinpian');//芯片信息保存
});
