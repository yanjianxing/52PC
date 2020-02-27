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

Route::group(['prefix' => 'kb','middleware' => 'auth'], function() {

	//任务发布
	Route::get('/create','IndexController@create')->name('taskCreatePage');//创建任务页面
	Route::get('/release/{id}','IndexController@release')->name('releaseDetail');//编辑任务
	Route::post('/createTask','IndexController@createTask')->name('taskCreate');//创建任务提交
	Route::get('/tasksuccess/{id}','IndexController@tasksuccess')->name('tasksuccess');//成功发布任务

	Route::get('/buyServiceTask/{id}','PayController@buyServiceTask')->name('buyServiceTask');//购买增值服务页面
	Route::post('/buyServiceTask','PayController@postBuyServiceTask')->name('postBuyServiceTask');//支付增值服务


	Route::post('/collectionTask','IndexController@postCollectionTask');//收藏/取消收藏任务
	Route::get('/download/{id}','DetailController@download')->name('download');//任务详情下载附件
	Route::get('/ajaxShopList/{taskId}/{uid}','DetailController@ajaxShopList')->name('ajaxShopList');//ajax获取邀约高手列表
	Route::get('/inviteUser','DetailController@inviteUser')->name('inviteUser');//邀约用户参与任务
	Route::get('/winBid/{work_id}/{task_id}','DetailController@winBid')->name('winBid');//任务详情中标按钮

	Route::get('/payType/{id}','DetailController@payType')->name('payType');//签订协议（确认交付阶段）
	Route::post('/postPayType','DetailController@postPayType')->name('postPayType');//雇主保存协议
	Route::get('/checkPayType/{taskid}/{status}','DetailController@checkPayType')->name('checkPayType');//威客是否同意协议
	Route::get('/payTypeAgain/{id}','DetailController@payTypeAgain')->name('payTypeAgain');//雇主再次编辑协议

	Route::get('/bounty/{id}','PayController@bounty')->name('bountyPage');//赏金托管
	Route::post('/bountyUpdate','PayController@bountyUpdate')->name('bountyUpdate');//任务赏金托管提交

	Route::get('/work/{id}','DetailController@work')->name('taskworkPage');//任务详情申请竞标
	Route::post('/workCreate','DetailController@workCreate')->name('workCreate');//任务详情竞标投稿

	Route::get('/delivery/{id}','DetailController@delivery')->name('taskdeliveryPage');//任务详情申请验收
	Route::post('/delivery','DetailController@deliverCreate')->name('deliverCreate');//任务详情交付提交

	Route::get('/bidWorkCheck','DetailController@bidWorkCheck')->name('bidWorkCheck');//雇主验收交付稿件

	Route::post('/ajaxBidRights','DetailController@ajaxBidRights')->name('ajaxBidRights');//任务维权
	Route::post('/ajaxFeedback','DetailController@ajaxFeedback')->name('ajaxFeedback');//.任务(项目)反馈

	Route::get('/evaluate/{id}','DetailController@evaluate')->name('evaluatePage');//任务详情评价
	Route::post('/evaluateCreate','DetailController@evaluateCreate')->name('evaluateCreate');//任务详情评价提交

	Route::get('/recordLogin/{type}','DetailController@recordLogin')->name('recordLogin');//去登录回到原来页面
});


Route::group(['prefix'=>'kb'],function(){

	//一键发布任务
	Route::post('/fastPub','IndexController@fastPub')->name('fastPub');//保存一键发布任务
	Route::post('/fastPubPhone','IndexController@fastPubPhone')->name('fastPubPhone');//保存一键发布任务手机端
	Route::post('/createTaskPhone','IndexController@createTaskPhone')->name('createTaskPhone');//手机端创建任务提交
	Route::post('/sendTaskCode','IndexController@sendTaskCode')->name('sendTaskCode');//发送验证码
	//任务大厅
	Route::get('/','IndexController@tasks')->name('taskList');//快包项目列表页面

	//任务详情
	Route::get('/{id}','DetailController@index')->name('taskDetailPage')->where('id', '[0-9]+');//任务详情页面

	Route::post('/fileUpload','IndexController@fileUpload')->name('fileCreate');//创建任务文件上传
	Route::get('/fileDelete','IndexController@fileDelet')->name('fileDelete');//创建任务文件上传删除
	//地区三级联动
	Route::get('/ajaxcity','IndexController@ajaxcity')->name('ajaxcity');//任务发布地区三级联动(城市联动)
	Route::get('/ajaxarea','IndexController@ajaxarea')->name('ajaxarea');//任务发布地区三级联动(地区联动)
	Route::get('/ajaxField','IndexController@ajaxField')->name('ajaxField');//获取领域

	Route::get('/ajaxWorksList/{id}','DetailController@ajaxWorksList')->name('ajaxWorksList')->where('id', '[0-9]+');;//ajax任务投标列表
	Route::get('/ajaxdeliveryList/{id}','DetailController@ajaxDeliveryList')->name('ajaxDeliveryList')->where('id', '[0-9]+');;//ajax任务交付列表


	//ajax分页
	Route::get('/ajaxPageWorks/{id}','DetailController@ajaxPageWorks')->name('ajaxPageWorks');//任务详情稿件筛选与分页
	Route::get('/ajaxPageDelivery/{id}','DetailController@ajaxPageDelivery')->name('ajaxPageDelivery');//任务详情交付类容分页
	Route::get('/ajaxPageComment/{id}','DetailController@ajaxPageComment')->name('ajaxPageComment');//任务详情评价筛选与分页

	//收藏任务
	Route::get('/collectionTask/{task_id}','IndexController@collectionTask');

	Route::get('/modelpacksend','IndexController@modelpacksend')->name('modelpacksend');//推广落地页h5

	Route::get('/modelfastpack','IndexController@modelfastpack')->name('modelfastpack');//快速发包的落地



});
