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

Route::group(['prefix' => 'api'], function() {
	Route::get('/user/sendCode', 'UserController@sendCode');//发送手机验证码
	Route::post('/user/register', 'UserController@register');//注册
	Route::post('/user/login', 'UserController@login');//登录
	Route::get('/user/vertify', 'UserController@vertify');//找回密码验证
	Route::post('/user/passwordReset', 'UserController@passwordReset');//找回密码
	Route::get('/pay/checkConfig','PayController@checkThirdConfig');//检查配置信息
	Route::post('oauth','UserController@oauthLogin');//创建第三方登录信息
	Route::get('/taskCate','UserInfoController@taskCate');//获取分类父级信息及其对应的子级信息
	Route::get('/hotCate','UserInfoController@hotCate');//获取热门的分类信息

	Route::get('/task/district', 'UserInfoController@district');//获取省市区信息
	Route::get('/work/detail','UserInfoController@showWorkDetail');//根据稿件id查询稿件详情
	Route::get('/user/hotService','UserInfoController@hotService');//获取首页热门服务信息
	Route::get('/user/slideInfo','UserInfoController@slideInfo');//我是雇主首页顶部幻灯片信息
	Route::get('/user/serviceByCate','UserInfoController@serviceByCate');//获取热门服务商信息
	Route::get('/user/serviceList','UserInfoController@serviceList');//获取服务商列表信息

	Route::get('/user/hotShop','UserInfoController@hotShop');//获取首页热门店铺信息

	Route::get('/task/hotTask','UserInfoController@hotTask');//获取首页热门任务信息
	Route::post('updateSpelling', 'UserInfoController@updateSpelling');//更新地区表中的拼音字段信息
	Route::get('/task/taskByCate','UserInfoController@taskByCate');//获取一级分类下的任务信息
	Route::get('/tasks','UserController@getTaskList');//任务大厅
	Route::get('/user/skill', 'UserInfoController@skill');//获取技能标签一级分类信息
	Route::get('/user/workerDetail','UserInfoController@workerDetail');//威客详情

	//任务详情
	Route::get('/myTask/detail','UserInfoController@showTaskDetail');//根据任务id查询任务详情
	Route::get('/task/deliveryList','UserInfoController@deliveryList');//根据任务id查询任务交付内容
	Route::get('/task/rightList','UserInfoController@rightList');//根据任务id查询任务维权内容
	Route::get('/task/commentList','UserInfoController@commentList');//根据任务id查询任务评价内容

	Route::get('/task/rightDetail','UserInfoController@rightDetail');//根据稿件id查询维权详情


	Route::get('/agreementDetail','UserController@agreementDetail');//协议详情

	Route::get('/hasIm','UserController@hasIM');//判断是否有IM工具
	Route::get('/user/secondSkill', 'UserInfoController@secondSkill');//获取某个一级技能标签分类下的子分类信息

	Route::get('/user/getAllSkill', 'UserInfoController@getAllSkill');//获取所有分类(是否有技能选中)

	Route::get('/user/phoneCodeVertiy', 'UserController@phoneCodeVertiy');//验证手机验证码的正确性
	Route::get('/user/caseInfo', 'UserInfoController@caseInfo');//获取个人案例信息
	Route::get('/androidVersion', 'UserController@version');//获取app安卓当前的版本号
	Route::get('/iosVersion', 'UserController@iosVersion');//获取appios当前的版本号
	Route::get('/work/rateInfo','GoodsController@workRateInfo');//获取作品平台抽佣
	Route::get('/work/recommendInfo','GoodsController@workRecommendInfo');//获取推荐作品开启信息
	Route::get('/service/rateInfo','GoodsController@serviceRateInfo');//获取服务平台抽佣
	Route::get('/service/recommendInfo','GoodsController@serviceRecommendInfo');//获取推荐服务开启信息
	Route::get('/shop/collectStatus','ShopController@collectStatus');//获取店铺被收藏的状态
	Route::get('/shop/isEmploy','ShopController@isEmploy');//判断是否可以雇佣
	Route::get('/shop/detail','ShopController@shopInfo');//获取威客店铺信息
	Route::get('/shop/workList','ShopController@workList');//获取店铺全部作品信息
	Route::get('/shop/successList','ShopController@successList');//获取店铺下的成功案例信息
	Route::get('/shop/goodDetail','ShopController@goodDetail');//获取商品详情
	Route::get('/shop/goodComment','ShopController@goodComment');//获取商品评价
	Route::get('/shop/goodContent','ShopController@goodContent');//获取商品内容
	Route::get('/shopList','ShopController@shopList');//威客商城(店铺列表或店铺筛选列表)
	Route::get('/commodityList','ShopController@commodityList');//威客商城（作品或服务列表或筛选列表）
	Route::get('/shop/serviceList','ShopController@serviceList');//获取店铺全部服务信息
	Route::get('/shop/serviceEmploy','EmployController@serviceEmploy');//获取被雇佣服务信息

	Route::get('/shop/shopDetail','ShopController@shopDetail');//获取威客店铺信息详情
	Route::get('/shop/userDetail','ShopController@userDetail');//获取威客用户信息详情

	Route::get('/user/messageNum','UserController@messageNum');//系统消息、交易动态未读消息数

	Route::get('/shop/commentList','ShopController@commentList');//获取店铺全部评价信息
	Route::get('/shop/taskCommentList','ShopController@taskCommentList');//威客任务评价列表

	Route::get('/getMessageAppKey','UserController@getMessageAppKey');//获取im聊天(百川云旺)appkey

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

Route::group(['prefix' => 'api', 'middleware' => ['web.auth']], function () {
	Route::post('/user/updatePassword', 'UserController@updatePassword');//修改密码
	Route::post('/user/updatePayCode', 'UserController@updatePayCode');//修改支付密码
	Route::post('/user/payCodeReset', 'UserController@payCodeReset');//支付密码找回

	Route::post('/auth/realnameAuth', 'AuthController@realnameAuth');//实名认证或企业认证信息创建
	Route::post('/auth/bankAuth', 'AuthController@bankAuth');//银行卡认证信息创建
	Route::get('/auth/getBankAuth', 'AuthController@getBankAuth');//获取银行认证的银行名称信息
	Route::get('/auth/bankAuthInfo', 'AuthController@bankAuthInfo');//获取银行卡认证的信息
	Route::get('/auth/realnameAuthInfo', 'AuthController@realnameAuthInfo');//获取实名认证信息
	Route::post('/auth/alipayAuth', 'AuthController@alipayAuth');//支付宝认证信息创建
	Route::get('/auth/alipayAuthInfo', 'AuthController@alipayAuthInfo');//获取支付宝认证信息
	Route::post('/auth/verifyAlipayAuthCash', 'AuthController@verifyAlipayAuthCash');//验证支付宝认证金额信息
	Route::post('/auth/verifyBankAuthCash', 'AuthController@verifyBankAuthCash');//验证银行卡认证金额信息

	Route::get('/user/myfocus', 'UserInfoController@myfocus');//我收藏的任务列表
	Route::post('/user/deleteFocus', 'UserInfoController@deleteFocus');//删除我收藏的某个任务

	Route::post('/user/deleteUser', 'UserInfoController@deleteUser');//删除我关注的某个人
	Route::post('/user/skillSave', 'UserInfoController@skillSave');//用户技能标签存储或修改


	Route::get('/user/addFocus','UserInfoController@insertFocusTask');//收藏任务


	Route::get('/myTask/index', 'TaskController@myPubTasks');//雇主的任务列表
	Route::get('/myTask/indexCount', 'TaskController@myPubTasksCount');//雇主的任务统计
	//Route::get('/myTask/draftsList', 'TaskController@draftsList');//雇主草稿箱任务
	Route::get('/myTask/editTask', 'TaskController@editTask');//雇主未发布的任务编辑获取详情


	Route::get('/myTask/getTaskType', 'TaskController@getTaskType');//创建任务选择任务类型
	Route::get('/myTask/gettaskService', 'TaskController@gettaskService');//创建任务增值服务
	Route::post('/myTask/createTask', 'TaskController@createTask');//创建任务

	Route::get('/myTask/bountyBidTask', 'TaskController@bountyBidTask');// 招标任务确认托管赏金页面

	Route::get('/myTask/paySection', 'TaskController@paySection');// 招标任务确认付款方式
	Route::post('/myTask/postPayType', 'TaskController@postPayType');// 保存招标任务确认付款方式
	Route::get('/myTask/dealPayType', 'TaskController@dealPayType');// 招标任务威客处理付款方式

	Route::get('/myTask/myAccept','TaskController@myAcceptTask');//威客的任务列表
	Route::get('/myTask/myAcceptCount','TaskController@myAcceptTasksCount');//威客的任务数量统计

	Route::get('/work/applauseRate','TaskController@applauseRate');//根据用户id查询其好评率

	Route::get('/work/winBid','TaskController@workWinBid');//稿件中标
	Route::post('/work/createWinBid','TaskController@createWinBidWork');//创建中标稿件
	Route::post('/work/createDelivery','TaskController@createDeliveryWork');//创建交付稿件
	Route::get('/work/deliveryAgree','TaskController@deliveryWorkAgree');//交付稿件验收
	Route::post('/work/deliveryRight','TaskController@deliveryWorkRight');//交付稿件维权
	Route::post('/work/evaluate','TaskController@evaluateCreate');//交易评论
	Route::post('/work/comment','TaskController@commentCreate');//稿件回复
	Route::get('/work/getEvaluate','TaskController@getEvaluate');//查看评价信息
	Route::post('/fileUpload','TaskController@fileUpload');//附件上传
	Route::get('/fileDelete','TaskController@fileDelete');//附件删除

	Route::get('/user/getUserInfo', 'UserController@getUserInfo');//获取用户信息详情
	Route::get('/user/personCase', 'UserInfoController@personCase');//获取用户的个人案例信息
	Route::post('/user/addCase', 'UserInfoController@addCase');//添加个人案例信息

	Route::post('/user/caseUpdate', 'UserInfoController@caseUpdate');//修改个人案例信息

	Route::get('/user/getNickname', 'UserController@getNickname');//获取用户昵称
	Route::post('/user/updateNickname', 'UserController@updateNickname');//修改个人昵称
	Route::get('/user/getAvatar', 'UserController@getAvatar');//获取用户头像
	Route::post('/user/updateAvatar', 'UserController@updateAvatar');//修改个人头像
	Route::post('/user/updateUserInfo', 'UserController@updateUserInfo');//修改用户信息
	Route::get('/user/messageList', 'UserController@messageList');//我的消息

	Route::get('/user/myTalk', 'UserInfoController@myTalk');//获取临时消息
	Route::get('/user/myAttention', 'UserInfoController@myAttention');//获取我的关注
	Route::post('/user/addAttention', 'UserInfoController@addAttention');//加关注
	Route::post('/user/addMessage', 'UserInfoController@addMessage');//提交聊天信息
	Route::post('/user/updateMessStatus', 'UserInfoController@updateMessStatus');//将未读消息修改为已读
	Route::post('/user/deleteTalk', 'UserInfoController@deleteTalk');//删除好友

	Route::post('/pay/bountyByBalance','PayController@taskDepositByBalance');//赏金托管之余额支付(财务模块下)
	Route::get('/pay/orderInfo','PayController@createOrderInfo');//根据任务id创建订单信息(财务模块下)
	Route::get('/pay/balance','PayController@balance');//钱包余额
	Route::post('/pay/cashOut','PayController@cashOut');//提现
	Route::get('/pay/bankAccount','PayController@bankAccount');//已认证银行卡列表
	Route::get('/pay/alipayAccount','PayController@alipayAccount');//已认证支付宝信息列表
	Route::get('/pay/financeList','PayController@financeList');//财务流水

	Route::get('/user/loginOut','UserController@loginOut');//退出登录

	Route::get('/auth/bankList','AuthController@bankList');//获取银行卡列表信息
	Route::get('/auth/alipayList','AuthController@alipayList');//获取支付宝列表信息





	Route::post('/user/feedbackInfo', 'UserInfoController@feedbackInfo');//意见反馈
	Route::get('/user/helpCenter','UserInfoController@helpCenter');//帮助中心

	Route::get('/user/passwordCheck','UserInfoController@passwordCheck');//检查是否要修改密码
	Route::get('/user/moneyConfig','UserInfoController@moneyConfig');//用户余额和每日提现金额查询

	Route::get('/user/getCash','UserInfoController@getCash');//获取用户充值信息
	Route::post('/user/postCash','PayController@postCash');//用户充值信息处理


	Route::get('/noPubTask','TaskController@noPubTask');//草稿箱
	Route::get('/noPubTaskDelete','TaskController@noPubTaskDelete');//草稿箱任务删除



	Route::post('/user/sendMessage','UserController@sendMessage');//没有IM服务发送站内消息
	Route::get('/agreeDelivery','TaskController@agreeDelivery');//雇主端协议交付稿件详情
	Route::get('/guestDelivery','TaskController@guestDelivery');//威客端协议交付稿件详情

	Route::get('/user/ImMessageList','UserController@ImMessageList');//IM聊天历史消息
	Route::get('/user/becomeFriend','UserController@becomeFriend');//发起IM聊天时成为临时联系人
	Route::get('/user/ImMessageInsert','UserController@ImMessageInsert');//IM聊天消息同步

	Route::get('/user/isFocusUser','UserController@isFocusUser');//判断某一用户是否被关注
	Route::get('/user/headPic','UserController@headPic');//获取某个人的头像
	Route::get('/user/buyerInfo','UserInfoController@buyerInfo');//获取雇主的我的信息
	Route::get('/user/workerInfo','UserInfoController@workerInfo');//获取威客的我的信息
	Route::get('/user/aboutUs','UserInfoController@aboutUs');//关于我们

	Route::post('/user/messageStatus','UserController@messageStatus');//将系统消息、交易动态由未读更新为已读

	Route::get('/shop/isPub','GoodsController@isPub');//根据店铺的开启状态判断能否发布商品
	Route::post('/shop/fileUpload','GoodsController@fileUpload');//附件上传
	Route::post('/shop/pubGoods','GoodsController@pubGoods');//发布作品
	Route::post('/shop/pubService','GoodsController@pubService');//发布服务
	Route::get('/shop/myCollect','GoodsController@myCollectShop');//我收藏的店铺列表及筛选
	Route::post('/shop/collect','ShopController@collectShop');//收藏店铺
	Route::post('/shop/cancelCollect','ShopController@cancelCollect');//取消收藏店铺
	Route::get('/user/workList','GoodsController@myWorkList');//我发布的作品或我发布的作品筛选
	Route::get('/user/offerList','GoodsController@myOfferList');//我发布的服务或我发布的服务筛选

	Route::get('/user/myBuyGoodsCount','GoodsController@buyOrderCount');//我购买的作品或服务的订单数量统计
	Route::get('/user/mySaleGoodsCount','GoodsController@saleOrderCount');//我卖出的作品或服务的订单数量统计
	Route::get('/user/myBuyGoods','GoodsController@goodsOrderList');//我购买的作品或服务的订单列表和筛选
	Route::get('/user/mySaleGoods','GoodsController@saleOrderList');//我卖出的作品或服务的订单列表和筛选
	Route::get('/user/getShop','ShopController@getShop');//店铺设置信息查询
	Route::post('/user/postShopInfo','ShopController@postShopInfo');//保存店铺设置信息
	Route::get('/user/getShopSkill','ShopController@getShopSkill');//店铺技能信息查询
	Route::get('/user/myShop','ShopController@myShop');//我的店铺
	Route::get('/user/againEnterprise','AuthController@enterpriseAuthRestart');//重新企业认证
	Route::post('/user/enterpriseAuth','AuthController@enterpriseAuth');//保存企业认证信息
	Route::post('/user/saveShopBg','ShopController@saveShopBg');//修改店铺背景图片
	Route::get('/user/changeShopStatus','ShopController@changeShopStatus');//开启或关闭店铺

	Route::post('/user/createEmploy','EmployController@createEmploy');//创建雇佣
	Route::post('/user/cashPayEmploy','EmployController@cashPayEmploy');//余额支付雇佣托管赏金
	Route::post('/user/ThirdCashEmployPay','EmployController@ThirdCashEmployPay');//第三方支付雇佣托管赏金
	Route::get('/user/employDetail','EmployController@employDetail');//获取雇佣订单详情信息
	Route::get('/user/employUserDetail','EmployController@employUserDetail');//获取雇佣订单另一方用户详情信息
	Route::get('/user/employServiceDetail','EmployController@employServiceDetail');//获取雇佣订单服务详情信息
	Route::get('/user/employWorkDetail','EmployController@employWorkDetail');//获取雇佣订单作品详情信息
	Route::get('/user/employCommentDetail','EmployController@employCommentDetail');//获取雇佣订单评论详情信息


	Route::get('/user/dealEmploy','EmployController@dealEmploy');//（取消、接受、拒绝）雇佣
	Route::post('/user/workEmployCreate','EmployController@workCreate');//威客投稿
	Route::post('/user/acceptEmployWork','EmployController@acceptEmployWork');//雇主验收
	Route::post('/user/employRights','EmployController@employRights');//雇主或威客维权
	Route::post('/user/employEvaluate','EmployController@employEvaluate');//雇主或威客评论


	Route::get('/user/buyGoodsDetail','GoodsController@buyGoodsDetail');//我购买作品的订单详情
	Route::get('/user/saleGoodsDetail','GoodsController@saleGoodsDetail');//我卖出作品的订单详情
	Route::get('/user/buyGoods','GoodsController@buyGoods');//购买作品生成订单
	Route::get('/user/confirmGoods','GoodsController@confirmGoods');//作品确认验收订单
	Route::post('/user/rightGoods','GoodsController@rightGoods');//作品维权订单
	Route::post('/user/commentGoods','GoodsController@commentGoods');//作品评价订单
	Route::post('/user/cashPayGoods','GoodsController@cashPayGoods');//余额支付作品购买
	Route::get('/user/ThirdCashGoodsPay','GoodsController@ThirdCashGoodsPay');//第三方支付购买作品
	Route::get('/user/getComment','GoodsController@getComment');//雇主获取购买作品的评价信息


});




Route::any('api/alipay/notify','PayNotifyController@alipayNotify');//支付宝充值回调地址
Route::any('api/wechatpay/notify', 'PayNotifyController@wechatpayNotify');//微信充值回调地址