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

Route::group(['prefix' => 'shop' , 'middleware' => 'auth'], function() {
	//by quanke 20181119
	Route::get('pubGoods','GoodsController@pubGoods')->name('pubGoods');//发布方案
	Route::post('savePubGoods','GoodsController@savePubGoods')->name('savePubGoods');//保存发布方案
	Route::get('/goodsuccess/{id}','GoodsController@goodsuccess')->name('goodsuccess');//成功发布任务
	Route::get('/fileDelete', 'GoodsController@fileDelete');//删除文件
	Route::post('/fileUploads','GoodsController@fileUpload');//方案封面文件上传
	Route::post('/gooddocUploads','GoodsController@gooddocUpload');//方案pdf文件上传
	Route::get('/download/{id}','GoodsController@download')->name('download');//下载商品附件
});

Route::group(['prefix' => 'fuwus'], function() {
	//by quanke 20181128
	Route::get('/','ShopController@index')->name('shopList');//服务商列表
	Route::get('/{shopId}','ShopController@home')->name('shopHome')->where('shopId', '[0-9]+');//服务商店铺首页
	Route::get('/goodsList/{shopId}','ShopController@goodsList')->name('goodsList');//服务商店铺方案
	Route::get('/info/{shopId}','ShopController@info')->name('shopInfo');//服务商店铺档案
	Route::get('/article/{shopId}','ShopController@article')->name('shopArticle');//服务商相关资讯
	Route::get('/ajaxbidTaskList/{uid}','ShopController@ajaxbidTaskList')->name('ajaxbidTaskList');//服务商店铺档案ajax获取选中记录
	Route::get('/ajaxShopGoodsList/{shopId}','ShopController@ajaxShopGoodsList')->name('ajaxShopGoodsList');//店铺ajax获取方案
	Route::get('/ajaxArticleList/{uid}','ShopController@ajaxArticleList')->name('ajaxArticleList');//店铺ajax获取资讯

});

Route::group(['prefix' => 'facs'], function() {
	Route::get('/','IndexController@fananshop')->name('fananshop');//方案超市
	Route::get('/{id}','IndexController@fananshopDetail')->name('fananshopDetail')->where('id', '[0-9]+');//方案超市详情
});

Route::group(['prefix' => 'shop'], function () {

	//heike
	Route::get('/programmeAddCart','IndexController@programmeAddCart')->name('programmeAddCart');//方案加入购物车
	Route::get('/inquiry/{programme_id}','IndexController@inquiry')->name('inquiry');//询价
	Route::post('/inquiryPay','IndexController@inquiryPay')->name('inquiryPay');//询价付款
	Route::get('/leavMessage/{programme_id}','IndexController@leavMessage')->name('leavMessage');//给服务商留言
	Route::get('/leavMessageGetCode','IndexController@leavMessageGetCode')->name('leavMessageGetCode');//给服务商留言->获取手机验证码
	Route::post('/leavInquiry','IndexController@leavInquiryPost')->name('leavInquiryPost');//留言&询价方法提交
    Route::get('/programmeCollect','IndexController@programmeCollect')->name("programmeCollect");//方案收藏方法
	Route::get('/programmeCancelCollect','IndexController@programmeCancelCollect')->name("programmeCancelCollect");//方案取消收藏方法
//    Route::get('/shopcart','IndexController@shopcart')->name('shopcart');//购物车
    Route::get('/payment/{id}','IndexController@payment')->name('payment');//付款页面
	Route::post('/programPay','IndexController@programPay')->name('programPay');//付款处理
	Route::post('/programmeThirdPay','IndexController@programmeThirdPay')->name('programmeThirdPay');//第三方支付
    Route::get('/affirmorder/{id}/{num}','IndexController@affirmorder')->name('affirmorder');//确认订单
	Route::post('/programSub','IndexController@programSub')->name('programSub');//方案订单提交



	Route::get('getSecondCate/{cateId}', 'GoodsController@getSecondCate');//获取二级分类



});
