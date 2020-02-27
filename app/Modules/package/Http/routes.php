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

Route::group(['prefix' => 'shop'], function() {

	Route::get('/ajaxUpdateShop','IndexController@ajaxUpdateShop')->name('ajaxUpdateShop');//更新店铺的开关状态
	Route::post('/ajaxUpdatePic','IndexController@ajaxUpdatePic')->name('ajaxUpdatePic');//获取上传店铺背景图片信息
	Route::get('/ajaxDelPic','IndexController@ajaxDelPic')->name('ajaxDeletePic');//删除店铺背景图片
	Route::get('/ajaxUpdateBack','IndexController@ajaxUpdateBack')->name('ajaxUpdateBack');//修改店铺背景图片信息
	Route::get('/ajaxServiceComments','IndexController@ajaxServiceComments')->name('ajaxServiceComments');//ajax获取评论

	Route::get('/about/{id}','IndexController@shopabout')->name('shopabout');//店铺介绍
	Route::get('/successStory/{id}','IndexController@successstory')->name('successstory');//商城成功案例

	Route::get('/work/{id}','IndexController@shopall')->name('shopall');//商城所有商品
	Route::get('/rated/{id}','IndexController@rated')->name('rated');//商城交易评价
    
    //Route::get('/fananshop','IndexController@fananshop')->name('fananshop');//方案超市

	Route::get('/serviceAll/{id}','IndexController@serviceAll')->name('serviceAll');//服务商所有商品

    Route::get('getSecondCate/{cateId}', 'IndexController@getSecondCate');//获取二级分类

	Route::get('/buyGoods/{id}','GoodsController@buyGoods')->name('buyGoods');//购买商品页
	Route::post('addGoodsComment', 'GoodsController@addGoodsComment');//提交对商品的评价
	Route::post('ajaxGetGoodsComment', 'GoodsController@ajaxGetGoodsComment');//ajax获取商品评论

	Route::get('/buyservice/{id}','IndexController@buyService')->name('buyService');//购买服务

	Route::get('/successDetail/{id}','IndexController@successDetail')->name('successDetail');//店铺成功案例详情页
	Route::get('/ajaxAdd', 'IndexController@ajaxAdd')->name('ajaxCreateShop');
	Route::post('/contactMe', 'IndexController@contactMe')->name('messageCreate');
	Route::get('/navList','IndexController@navList')->name('navList');//获取最新的导航信息
	Route::get('/{id}','IndexController@shopOutside')->name('shopOutside');//店铺对外页面
});
Route::group(['prefix' => 'shop', 'middleware' => 'auth'], function () {
    Route::get('/manage/{id}','IndexController@shop')->name('shop');//店铺对内页面
	Route::get('/orders/{id}','GoodsController@orders')->name('orders');//商品订单
	Route::post('/postOrder','GoodsController@postOrder')->name('postOrder');//生成商品订单

	Route::get('/pay/{id}','GoodsController@pay')->name('pay');//订单支付视图
	Route::post('/postPayOrder','GoodsController@postPayOrder')->name('postPayOrder');//订单支付

	Route::get('/confirm/{id}','GoodsController@confirm')->name('confirm');//商品确认源文件视图
	Route::post('/postConfirm','GoodsController@postConfirm')->name('postConfirm');//确认源文件
	Route::post('/postRightsInfo','GoodsController@postRightsInfo')->name('postRightsInfo');//购买商品维权
	Route::get('/download/{id}','GoodsController@download')->name('download');//下载商品附件



});
