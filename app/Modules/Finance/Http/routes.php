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
Route::group(['prefix' => 'anli'], function() {
    Route::get('/','PayController@succecase')->name('succecase');//成功案例
    Route::get('/{id}','PayController@casedetailpage')->name('casedetailpage')->where('id', '[0-9]+');//案例详情(成功案例)

});

Route::get('/about','PayController@helpcenter')->name('helpcenter');//帮助中心
Route::group(['prefix' => 'finance'], function() {

    Route::get('/collection/{collectionid}/{status}','PayController@collection')->name('collection');//收藏

    Route::get('/rechargerecord','PayController@rechargeRecord')->name('rechargeRecord');//充值记录

    Route::get('/Withdrawalrecord','PayController@WithdrawalRecord')->name('WithdrawalRecord');//提现记录

});

Route::group(['prefix' => 'finance', 'middleware' => 'auth'], function() {
    //用户收支列表
    Route::get('/list', 'PayController@getFinanceList')->name('financeList');
    Route::get('/mycoupon','PayController@mycoupon')->name('mycoupon');//我的现金券
    Route::get('/mycouponGet/{id}','PayController@mycouponGet')->name('mycouponGet');//领取优惠卷
    //用户充值页面
    Route::get('/cash', 'PayController@getCash')->name('cashDetail');
    //用户充值处理
    Route::post('/cash', 'PayController@postCash')->name('cashCreate');
    //微信支付页面
    Route::get('/wechatPay/{order}', 'PayController@getWechatPay')->name('wechatPayPage');
    //银联同步回调
    Route::get('/pay/unionpay/return', 'PayController@unionpayReturn')->name('unionpayCreate');
    //确认支付验证订单状态
    Route::get('/verifyOrder/{orderCode}', 'PayController@verifyOrder')->name('verifyOrderDetail');

    //用户提现页面
    Route::get('cashout', 'PayController@getCashout')->name('cashoutPage');
    //用户提现处理
    Route::post('cashout', 'PayController@postCashout')->name('cashoutCreate');
    //提现详情
    Route::get('cashoutInfo/{cashoutInfo}', 'PayController@getCashoutInfo')->name('cashoutDetail');
    Route::post('cashoutInfo', 'PayController@postCashoutInfo')->name('cashoutInfoCreate');
    //提现提示页面
    Route::get('/waitcashout', 'PayController@waitcashout')->name('waitcashoutPage');

    //资产详细
    Route::get('/assetDetail','PayController@assetdetail')->name('assetDetail');
    Route::get('/assetDetailminute/{id}','PayController@assetDetailminute')->name('assetDetailminute');


    //店铺付款页面
    Route::get('/getpay/{id}', 'PayController@getpay')->name('getpay');
    //发布商品增值服务余额支付
    Route::post('balancePayment', 'PayController@balancePayment')->name('balancePayment');
    //第三方支付购买店铺增值服务
    Route::post('thirdPayment', 'PayController@thirdPayment')->name('thirdPayment');

    //店铺付款成功页面
    Route::get('/shopsuccess/{id}', 'PayController@shopsuccess')->name('shopsuccess');


});




