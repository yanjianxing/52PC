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
Route::get('/manage/login', 'Auth\AuthController@getLogin')->name('loginCreatePage');
Route::group(['middleware' => 'systemlog'], function() {
    Route::post('/manage/login', 'Auth\AuthController@postLogin')->name('loginCreate');
});
Route::get('/manage/logout', 'Auth\AuthController@getLogout')->name('logout');

Route::get('/ajaxcity','AreaController@ajaxCity')->name('ajaxCity');//地区管理筛选（城市）
Route::get('/ajaxarea','AreaController@ajaxArea')->name('ajaxArea');//地区管理筛选（地区）
Route::get('/manage/fileDelete', 'IndexController@fileDelete');//删除文件
Route::post('/manage/fileUploads','IndexController@fileUpload');//方案封面文件上传
Route::post('/manage/gooddocUploads','IndexController@gooddocUpload');//.方案pdf文件上传
Route::post('/manage/fileUploadszdfastbuy','IndexController@fileUploadszdfastbuy');//.中电快购图片上传
Route::get('/manage/searchUser','TaskController@searchUser')->name('searchUser');//筛选用户
Route::get('/manage/searchPush','TaskController@searchPush')->name('searchPush');//多对多推送筛选用户
Route::get('/manage/posttsPush','TaskController@posttsPush')->name('posttsPush');//多对多推送ajax

Route::get('/manage/searchSite','MessageController@searchSite')->name('searchSite');//站内信发送
Route::post('/manage/SitePush','MessageController@SitePush')->name('SitePush');//站内信发送

Route::group(['prefix' => 'manage', 'middleware' => ['manageauth', 'RolePermission','systemlog']], function() {
    Route::get('/', 'IndexController@getManage')->name('backstagePage');//后台首页
    Route::get('/echartChoice', 'IndexController@echartChoice')->name('echartChoice');//echart图片改变
    //RBAC路由
    Route::get('/addRole', 'IndexController@addRole')->name('roleCreate');
    Route::get('/addPermission', 'IndexController@addPermission')->name('permissionCreate');
    Route::get('/attachRole', 'IndexController@attachRole')->name('attachRoleCreate');
    Route::get('/attachPermission', 'IndexController@attachPermission')->name('attachPermissionCreate');
    //实名认证管理路由
    Route::get('/realnameAuthList', 'AuthController@realnameAuthList')->name('realnameAuthList');//实名认证列表
    Route::get('/realnameAuthHandle/{id}/{action}', 'AuthController@realnameAuthHandle')->name('realnameAuthHandle');//实名认证处理
    Route::get('/realnameAuth/{id}', 'AuthController@realnameAuth')->name('realnameAuth');//实名认证详情
    
    //支付宝认证管理路由
    Route::get('/alipayAuthList', 'AuthController@alipayAuthList')->name('alipayAuthList');//支付宝认证列表
    Route::get('alipayAuth/{id}', 'AuthController@getAlipayAuth')->name('alipayAuth');//支付宝认证详情

    //银行认证管理路由
    Route::get('/bankAuthList', 'AuthController@bankAuthList')->name('bankAuthList');//银行认证列表
    Route::get('/bankAuth/{id}', 'AuthController@getBankAuth')->name('bankAuth');//银行认证列表
    //任务管理路由
    Route::get('/taskList', 'TaskController@taskList')->name('taskList');//任务列表
    //任务推送
    Route::post("/taskPush",'TaskController@taskPush')->name('taskPush');//任务推送
    Route::get('/taskRecommend/{id}', 'TaskController@taskRecommend')->name('taskRecommend');//任务推荐
    Route::get('/taskDetail/{id}', 'TaskController@taskDetail')->name('taskDetail');//任务详情

    Route::get('/taskFollow/{id}', 'TaskController@taskFollow')->name('taskFollow');//任务跟进


    Route::get('/taskFollowList', 'TaskController@taskFollowList')->name('taskFollowList');//任务跟进列表
    Route::get('/taskFollowAdd', 'TaskController@taskFollowAdd')->name('taskFollowAdd');//任务跟进新增
    Route::get('/taskFollowExit/{id}', 'TaskController@taskFollowExit')->name('taskFollowExit');//任务跟进编辑
    Route::get('/taskFollowDel/{id}', 'TaskController@taskFollowDel')->name('taskFollowDel');//任务跟进删除
    Route::post('/taskFollowOpera', 'TaskController@taskFollowOpera')->name('taskFollowOpera');//任务跟进添加/编辑
    /*快捷任务路由管理*/
    Route::get('requireList',"PublishController@requireList")->name('requireList');//需求列表
    Route::get('requireDetail/{id}',"PublishController@requireDetail")->name('requireDetail');//需求详情
    Route::get('/require/createTask/{id}',"PublishController@createTask")->name('createTask');//需求创建任务
    Route::post('/require/createTaskData',"PublishController@createTaskData")->name('createTaskData');//需求创建任务
    Route::get('requireRefuse/{id}',"PublishController@refuse")->name('refuse');//需求拒绝
    Route::get('requireDel/{id}',"PublishController@delete")->name('delete');//需求软删除

    //招标管理路由
	Route::get('/bidList', 'BidController@bidList')->name('bidList');//任务列表
    Route::get('/taskBidFollow/{id}', 'BidController@taskBidFollow')->name('taskBidFollow');//雇佣任务跟进
	Route::get('/bidDetail/{id}', 'BidController@bidDetail')->name('bidDetail');//任务详情
	Route::get('/bidConfig/{id}', 'BidController@bidConfig')->name('bidConfig');//任务配置
    //财务管理路由
    Route::get('/financeList', 'FinanceController@financeList')->name('financeList');//财务报表
    Route::get('/financeListExport', 'FinanceController@financeListExport')->name('financeListExportCreate');//导出网站流水记录
    // Route::get('/financeList', 'FinanceController@financeStatement')->name('financeStatementList');//财务报表
    Route::get('/financeRecharge', 'FinanceController@financeRecharge')->name('financeRechargeList');//财务报表-充值记录
    Route::get('/financeWithdraw', 'FinanceController@financeWithdraw')->name('financeWithdrawList');//财务报表-提现记录
    Route::get('/financeWithdrawExport/{param}', 'FinanceController@financeWithdrawExport')->name('financeWithdrawExportCreate');//提现记录导出
    Route::get('/financeProfit', 'FinanceController@financeProfit')->name('financeProfitList');//财务报表-利润统计
    //保证金管理
    Route::get('/depositList', 'FinanceController@depositList')->name('depositList');
    //保证金申请处理
    Route::get('/depositHandle/{id}/{action}', 'FinanceController@depositHandle')->name('depositHandle');
    //地区管理路由
    Route::get('/area','AreaController@areaList')->name('areaList');//地区管理列表

    //行业管理路由
    Route::get('/industry','IndustryController@industryList')->name('industryList');//行业管理列表

    Route::get('/tasktemplate/{id}','IndustryController@taskTemplates')->name('taskTemplates');//行业实例页面
    Route::get('/industryAdd/{type}','IndustryController@industryAdd')->name('industryAdd');//新增行业
    Route::post('/industryAddData','IndustryController@industryAddData')->name('industryAddData');//行业数据提交
    Route::get('/industryInfo/{id}','IndustryController@industryInfo')->name('industryDetail');//编辑行业分类图标
    Route::post('/industryInfo','IndustryController@postIndustryInfo')->name('postIndustryDetail');//编辑行业分类图标
    /*
    * 技能标签
    * */
    Route::get("/skillList","IndustryController@skillList")->name("skillList");//技能标签列表
    Route::get("/skillAdd/{type}","IndustryController@skillAdd")->name("skillAdd");//技能标签添加
    Route::get("/skillUpdate/{id}","IndustryController@skillUpdate")->name("skillUpdate");//技能标签修改
    /*
     * 开放平台
     * */
    Route::get("/tradPlatform","IndustryController@tradPlatform")->name("tradPlatform");//开放平台列表
    Route::get("/tradPlatformAdd/{type}","IndustryController@tradPlatformAdd")->name("tradPlatformAdd");//开放平台添加
    Route::get("/tradPlatformUpdate/{id}","IndustryController@tradPlatformUpdate")->name("tradPlatformUpdate");//开放平台修改

    /*
     * 交付形式
     * */
    Route::get("/transactionList","IndustryController@transactionList")->name("transactionList");//交付形式
    Route::get("/transactionAdd/{type}","IndustryController@transactionAdd")->name("transactionAdd");//交易形式
    Route::get("/transactionUpdate/{id}","IndustryController@transactionUpdate")->name("transactionUpdate");
    /*
     * 职能
     * */
    Route::get("/functionalList","IndustryController@functionalList")->name("functionalList");
    Route::get("/functionalAdd/{type}","IndustryController@functionalAdd")->name("functionalAdd");//职能添加
    Route::get("/functionalUpdate/{id}","IndustryController@functionalUpdate")->name("functionalUpdate");//职能修改
    /*
     * 职位等级
     * */
    Route::get("/jobLevelList","IndustryController@jobLevelList")->name("jobLevelList");
    Route::get("/jobLevelAdd/{type}","IndustryController@jobLevelAdd")->name("jobLevelAdd");//职位级别添加
    Route::get("/jobLevelUpdate/{id}","IndustryController@jobLevelUpdate")->name("jobLevelUpdate");//职位级别修改

    Route::get('/userFinance', 'FinanceController@userFinance')->name('userFinanceCreate');//用户流水记录
    Route::get('/cashoutList', 'FinanceController@cashoutList')->name('cashoutList');//提现审核列表

    Route::get('cashoutInfo/{id}', 'FinanceController@cashoutInfo')->name('cashoutDetail');//提现记录详情

    Route::get('userRecharge', 'FinanceController@getUserRecharge')->name('userRechargePage');//后台充值视图
    Route::get('rechargeList', 'FinanceController@rechargeList')->name('rechargeList');// 用户充值订单列表

    //全局配置
    Route::get('/config', 'ConfigController@getConfigBasic')->name('configDetail');//
    Route::get('/config/basic', 'ConfigController@getConfigBasic')->name('basicConfigDetail');//基本配置
    Route::get('/config/seo', 'ConfigController@getConfigSEO')->name('seoConfigDetail');//seo配置
    Route::get('/config/nav', 'ConfigController@getConfigNav')->name('navConfigDetail');//获取导航配置
    Route::get('/config/nav/{id}/delete', 'ConfigController@deleteConfigNav')->name('configNavDelete');//删除导航
    Route::get('/config/attachment', 'ConfigController@getAttachmentConfig')->name('attachmentConfigDetail');//附件配置

    Route::get('/config/site', 'ConfigController@getConfigSite')->name('siteConfigDetail');//站点配置视图
    Route::get('/config/email', 'ConfigController@getConfigEmail')->name('emailConfigDetail');//邮箱配置视图
    Route::get('/config/link', 'ConfigController@configLink')->name('configLink');//站点配置关注链接
    Route::get('/config/phone', 'ConfigController@getConfigPhone')->name('phoneConfigDetail');//短信配置视图
    Route::get('/config/user', 'ConfigController@getConfigUser')->name('getConfigUser');//普通会员配置
    Route::post('/config/user', 'ConfigController@configUserPost')->name('configUserPost');//普通会员配置数据提交
    Route::get('/config/appalipay', 'ConfigController@getConfigAppAliPay')->name('getConfigAppAliPay');//app支付宝支付配置视图
    Route::get('/config/appwechat', 'ConfigController@getConfigAppWeChat')->name('getConfigAppWeChat');//app微信支付配置视图
    Route::get('/config/appMessage', 'ConfigController@getConfigAppMessage')->name('getConfigAppMessage');//app聊天配置视图
    Route::get('/config/wechatpublic', 'ConfigController@getConfigWeChatPublic')->name('getConfigWeChatPublic');//微信端配置视图


    //任务配置
    Route::get('/taskConfig','TaskConfigController@index')->name('taskConfig');//任务配置页面

    //接口管理
    Route::get('payConfig', 'InterfaceController@getPayConfig')->name('payConfigDetail');//支付配置
    Route::get('thirdPay', 'InterfaceController@getThirdPay')->name('thirdPayDetail');//第三方支付配置列表
    Route::get('thirdPayEdit/{id}', 'InterfaceController@getThirdPayEdit')->name('thirdPayUpdatePage');//配置支付接口视图
    //第三方登陆
    Route::get('thirdLogin', 'InterfaceController@getThirdLogin')->name('thirdLoginPage');//第三方登录授权配置


    //资讯中心路由
    Route::get('/article/{upID}','ArticleController@articleList')->name('articleList'); //资讯中心文章列表
    Route::get('/articleFooter/{upID}','ArticleController@articleList')->name('articleFooterList'); //页脚配置文章列表
    Route::get('/addArticle/{upID}','ArticleController@addArticle')->name('articleCreatePage'); //添加资讯文章视图
    Route::get('/addArticleFooter/{upID}','ArticleController@addArticle')->name('articleFooterCreatePage'); //添加页脚文章视图

    Route::get('/editArticle/{id}/{upID}','ArticleController@editArticle')->name('articleUpdatePage'); //编辑资讯文章视图
    Route::get('/editArticleFooter/{id}/{upID}','ArticleController@editArticle')->name('articleFooterUpdatePage'); //编辑页脚文章视图
    Route::post('/editArticle', 'ArticleController@postEditArticle')->name('articleUpdate'); //编辑文章

    //资讯中心分类路由serviceSellingPlan
    Route::get('/categoryList/{upID}','ArticleCategoryController@categoryList')->name('categoryList'); //资讯文章分类列表
    Route::get('/categoryFooterList/{upID}','ArticleCategoryController@categoryList')->name('categoryFooterList'); //页脚文章分类列表
    Route::get('/categoryAdd/{upID}','ArticleCategoryController@categoryAdd')->name('categoryCreatePage'); //添加资讯文章分类视图
    Route::get('/categoryEdit/{id}/{upID}','ArticleCategoryController@categoryEdit')->name('categoryUpdatePage');//编辑资讯文章分类视图

    Route::get('/getChildCateList/{id}','ArticleCategoryController@getChildCateList')->name('getChildCateList'); //页脚文章分类列表
    Route::get('/categoryFooterAdd/{upID}','ArticleCategoryController@categoryAdd')->name('categoryFooterCreatePage'); //添加页脚文章分类视图
    Route::get('/categoryFooterEdit/{id}/{upID}','ArticleCategoryController@categoryEdit')->name('categoryFooterUpdatePage');//编辑页脚文章分类视图
    Route::get('/add/{upID}','ArticleCategoryController@add')->name('addCategory');//进入新建视图 判断资讯或页脚
    Route::get('/edit/{id}/{upID}','ArticleCategoryController@edit')->name('editCategory');//进入编辑视图 判断资讯或页脚


    //后台成功案例
    Route::get('/successCaseList','SuccessCaseController@successCaseList')->name('successCaseList');//成功案例列表
    Route::get('/successcaseadd','SuccessCaseController@create')->name('successCaseCreatePage');//成功案例添加页面

    //后台资讯
    Route::get('/newsList/{upID}','newsController@newsList')->name('newsList');//资讯列表
    Route::get('/recommended1/{id}/{action}', 'newsController@recommended1')->name('recommended1');//是否推荐1
    Route::get('/recommended2/{id}/{action}', 'newsController@recommended2')->name('recommended2');//是否推荐2
    Route::get('/editNews/{id}/{upID}','newsController@editNews')->name('editNews');//编辑资讯视图
    Route::post('/editNews','newsController@postEditNews')->name('postEditNews');//保存编辑资讯
    Route::get('/addNews/{upID}','newsController@addNews')->name('addNews'); //添加资讯视图
    Route::post('/addNews', 'newsController@postNews')->name('postNews'); //添加资讯
    Route::get('/changeArticleStatus/{id}/{upID}/{status}','newsController@changestatus')->name('changeArticleStatus'); //更改资讯状态
    Route::get('/getNewsComment', 'newsController@getNewsComment')->name('getNewsComment'); //资讯评论
    Route::get('/newsChangeStatus/{id}/{status}/{del}', 'newsController@newsChangeStatus')->name('newsChangeStatus'); //更改资讯评论状态
    
    //后台专题
    Route::get('/specialList/{upID}','newsController@specialList')->name('specialList'); //专题列表
    Route::get('/addSpecial/{upID}','newsController@addSpecial')->name('addSpecial'); //新增列表
    Route::post('/addSpecial','newsController@postSpecial')->name('postSpecial'); //新增保存
    Route::get('/editSpecial/{id}/{upID}','newsController@editSpecial')->name('editSpecial'); //专题编辑
    Route::post('/editSpecial','newsController@postEditSpecial')->name('postEditSpecial'); //专题编辑保存
    Route::get('/editSpecialNews/{id}/{specialid}/{upID}','newsController@editSpecialNews')->name('editSpecialNews'); //专题文章编辑
    Route::post('/editSpecialNews','newsController@postEditSpecialNews')->name('postEditSpecialNews'); //专题文章编辑保存
    Route::get('/ajaxnews','newsController@ajaxnews')->name('ajaxnews');//专题新增ajax
    Route::get('/ajaxspecialnews','newsController@ajaxspecialnews')->name('ajaxspecialnews');//专题文章新增删除ajax


    //自定义导航
    Route::get('/navList','NavController@navList')->name('navList'); //自定义导航列表
    Route::get('/addNav','NavController@addNav')->name('navCreatePage');  //添加自定义导航视图
    Route::get('/editNav/{id}','NavController@editNav')->name('navUpdatePage'); //编辑自定义导航视图

    //用户管理
    Route::get('/userList', 'UserController@getUserList')->name('userList');//普通用户列表
    Route::get('/userAdd', 'UserController@getUserAdd')->name('userCreatePage');//添加用户视图
    Route::get('/userEdit/{uid}', 'UserController@getUserEdit')->name('userUpdatePage');//用户编辑
    Route::get('/userDetail/{uid}', 'UserController@getUserDetail')->name('userDetail');//用户详情
    Route::get('/managerList', 'UserController@getManagerList')->name('managerList');//系统用户列表
    Route::get('/managerAdd', 'UserController@managerAdd')->name('managerCreatePage');//系统用户添加视图
    Route::get('/managerDetail/{id}', 'UserController@managerDetail')->name('managerDetail');//系统用户详情


    Route::get('/rolesList', 'UserController@getRolesList')->name('rolesList');//用户组列表
    Route::get('/rolesAdd', 'UserController@getRolesAdd')->name('rolesCreatePage');//用户组添加视图
    Route::get('/rolesDetail/{id}', 'UserController@getRolesDetail')->name('rolesDetail');//用户组详情

    Route::get('/permissionsList', 'UserController@getPermissionsList')->name('permissionsList');//权限列表
    Route::get('/permissionsAdd', 'UserController@getPermissionsAdd')->name('permissionsCreatePage');//权限添加视图
    Route::get('/permissionsDetail/{id}', 'UserController@getPermissionsDetail')->name('permissionsDetail');//权限详情

    //后台权限添加
    Route::get('/menuList/{id}/{level}','MenuController@getMenuList')->name('getMenuList');
    Route::get('/addMenu/{id?}','MenuController@addMenu')->name('addMenu');
    Route::get('/menuUpdate/{id}','MenuController@menuUpdate')->name('menuUpdate');

    //用户举报
    Route::get('/reportList','TaskReportController@reportList')->name('reportList');//用户举报列表
    Route::get('/reportDetail/{id}','TaskReportController@reportDetail')->name('reportDetail');//用户举报详情

    //交易维权
    Route::get('/rightsList','TaskRightsController@rightsList')->name('rightsList');//交易维权列表
    Route::get('/rightsDetail/{id}','TaskRightsController@rightsDetail')->name('rightsDetail');//交易维权详情

    Route::get('/taskFeedbackList','TaskRightsController@taskFeedbackList')->name('taskFeedbackList'); //.项目反馈

    //增值工具
    Route::get('/serviceList','ServiceController@serviceList')->name('adServiceList'); //增值工具列表
    Route::get('/addService','ServiceController@addService')->name('addServiceCreatePage'); //添加增值工具视图
    Route::get('/editService/{id}','ServiceController@editService')->name('addServiceUpdatePage');//编辑增值工具视图
    Route::get('/serviceBuy','ServiceController@serviceBuy')->name('serviceBuyList'); //增值服务购买列表
    Route::get('/toolBuy','ServiceController@toolBuy')->name('toolBuyList'); //工具购买列表
    //友情链接
    Route::get('/link', 'LinkController@linkList')->name('linkList');//友情链接列表
    Route::post('/addlink', 'LinkController@postAdd')->name('linkCreate');//友情链接添加
    Route::get('/editlink/{id}', 'LinkController@getEdit')->name('linkUpdatePage');//友情链接详情


    //投诉建议
    Route::get('/feedbackList', 'FeedbackController@listInfo')->name('feedbackList');//查看投诉建议列表信息
    Route::get('/feedbackDetail/{id}', 'FeedbackController@feedbackDetail')->name('feedbackDetail');//查看投诉建议详情

    //热词管理
    Route::get('/hotwordsList','HotwordsController@hotwordsInfo')->name('hotwordsList');//热词列表
    Route::post('/hotwordsCreate','HotwordsController@hotwordsCreate')->name('hotwordsCreate');//添加热词

    //站长工具
    Route::get('attachmentList', 'ToolController@getAttachmentList')->name('attachmentList');//附件管理列表

    //短信模板
    Route::get('/messageList','MessageController@messageList')->name('messageList');//模板列表
    Route::get('/editMessage/{id}','MessageController@editMessage')->name('messageUpdatePage'); //编辑模版视图

    //站内群发
    Route::get('/messageSite','MessageController@messageSite')->name('messageSite');//站内群发

    //系统日志
    Route::get('/systemLogList','SystemLogController@systemLogList')->name('systemLogList');//系统日志列表

    //用户互评
    Route::get('/getCommentList','TaskCommentController@getCommentList')->name('commentList');//用户互评列表页面
	
    //协议管理
    Route::get('/agreementList','AgreementController@agreementList')->name('agreementList'); //协议列表
    Route::get('/seachKeyword','AgreementController@seachKeyword')->name('seachKeyword'); //.搜索关键词列表
    Route::get('/zdfastbuy','AgreementController@zdfastbuy')->name('zdfastbuy'); //.中电快购列表
    Route::get('/addZdfastbuy','AgreementController@addZdfastbuy')->name('addZdfastbuy'); //.中电快购列表页添加
    Route::post('/delZdfastbuy','AgreementController@delZdfastbuy')->name('delZdfastbuy'); //.中电快购列表页删除
    Route::post('/saveZdfastbuy', 'AgreementController@saveZdfastbuy')->name('saveZdfastbuy');//保存方案信息

    Route::get('/addAgreement','AgreementController@addAgreement')->name('agreementCreatePage');//添加协议视图
    Route::get('/editAgreement/{id}','AgreementController@editAgreement')->name('agreementUpdatePage');//编辑协议视图
    //seo标签管理
    Route::get('/seoLabelList','SeoController@seoLabelList')->name('seoLabelList'); //seo列表
    Route::get('/seoLabelAction','SeoController@seoLabelAction')->name('seoLabelAction'); //seo标签添加或者修改
    Route::post('/seoData','SeoController@seoData')->name('seoData'); //方法存储
    Route::get('/seoLabelDelete/{id}','SeoController@seoLabelDelete')->name('seoLabelDelete'); //seo删除
    Route::post('/seoLabelDelAll','SeoController@seoLabelDelAll')->name('seoLabelDelAll'); //seo删除
    Route::post('/seoLabelHandle','SeoController@seoLabelHandle')->name('seoLabelHandle'); //方法存储
    Route::get('/seoTempDownload','SeoController@seoTempDownload')->name('seoTempDownload'); //seo模板下载
    Route::post('/seoTempUp','SeoController@seoTempUp')->name('seoTempUp'); //seo批量导入
    //批量导入标签
    Route::get('/seoLabelAll','SeoController@seoLabelAll')->name('seoLabelAll'); //批量导入标签
    //模板管理
    Route::get('/skin','AgreementController@skin')->name('manageSkin');//模板管理页面
    //关于我们
    Route::get('/aboutUs','ConfigController@aboutUs')->name('aboutUs');

    //雇佣管理
    Route::get('/employConfig','EmployController@employConfig')->name('employConfig');//雇佣配置
    Route::get('/employList','EmployController@employList')->name('employList');//雇佣列表
    Route::get('/employEdit/{id}','EmployController@employEdit')->name('employEdit');//雇佣编辑页面

    //企业认证管理路由
    Route::get('/enterpriseAuthList', 'AuthController@enterpriseAuthList')->name('enterpriseAuthList');//企业认证列表
    Route::get('/enterpriseAuth/{id}', 'AuthController@enterpriseAuth')->name('enterpriseAuth');//企业认证详情


    //店铺管理路由
    Route::get('/shopList', 'ShopController@shopList')->name('shopList');//店铺列表
    Route::get('/shopInfo/{id}', 'ShopController@shopInfo')->name('shopInfo');//店铺详情
    Route::get('/shopEdit/{id}', 'ShopController@shopEdit')->name('shopEdit')->where('id', '[0-9]+');//店铺详情
    Route::post('/shopEdit', 'ShopController@shopEditPost')->name('shopEditPost');//店铺编辑提交
    Route::get('/shopUpgrade', 'ShopController@shopUpgrade')->name('shopUpgrade');//店铺升级申请列表
    Route::get('/shopUpgradeInfo/{id}', 'ShopController@shopUpgradeInfo')->name('shopUpgradeInfo');//店铺升级详情

    Route::get('/shopConfig', 'ShopController@shopConfig')->name('shopConfig');//店铺配置视图


    Route::get('/goodsList', 'GoodsController@goodsList')->name('goodsList');//方案列表
    Route::get('/goodsInfo/{id}', 'GoodsController@goodsInfo')->name('goodsInfo');//方案详情
    Route::get('/goodsComment/{id}', 'GoodsController@goodsComment')->name('goodsComment');//商品评价详情
    Route::get('/addGoods', 'GoodsController@addGoods')->name('addGoods');//方案添加
    Route::get('/goodsFollowAdd', 'GoodsController@goodsFollowAdd')->name('goodsFollowAdd');//方案跟进添加


    Route::get('/goodsConfig', 'GoodsController@goodsConfig')->name('goodsConfig');//商品流程配置视图

    Route::get('/ShopRightsList', 'ShopOrderController@rightsList')->name('ShopRightsList');//方案订单维权列表
    Route::get('/shopRightsInfo/{id}', 'ShopOrderController@shopRightsInfo')->name('shopRightsInfo');//方案订单维权详情


    Route::get('/shopOrderList', 'ShopOrderController@orderList')->name('shopOrderList');//店铺方案订单列表
    Route::get('/shopOrderInfo/{id}', 'ShopOrderController@shopOrderInfo')->name('shopOrderInfo');//店铺方案订单详情
    Route::get('/shopEnquiryList', 'ShopOrderController@enquiryList')->name('shopEnquiryList');//店铺方案询价列表
    Route::get('/shopMessageList', 'ShopOrderController@messageList')->name('shopMeaageList');//店铺方案留言列表
    Route::get('/orderFollow/{id}', 'ShopOrderController@orderFollow')->name('orderFollow');//跟进记录
    Route::get('/orderFollowAdd', 'ShopOrderController@orderFollowAdd')->name('orderFollowAdd');//跟进添加

    Route::get('/goodsServiceList','GoodsServiceController@goodsServiceList')->name('goodsServiceList');//店铺服务列表
    Route::get('/serviceOrderList','GoodsServiceController@serviceOrderList')->name('serviceOrderList');//店铺订单列表
    Route::get('/serviceOrderInfo/{id}','GoodsServiceController@serviceOrderInfo')->name('serviceOrderInfo');//店铺订单详情
    Route::get('/serviceConfig','GoodsServiceController@serviceConfig')->name('serviceConfig');//店铺流程配置
    Route::get('/serviceInfo/{id}','GoodsServiceController@serviceInfo')->name('serviceInfo');//店铺流程配置

    Route::get('/serviceComments/{id}','GoodsServiceController@serviceComments')->name('serviceComments');//店铺流程配置

    Route::get('/serviceOrderEdit/{id}','GoodsServiceController@serviceOrderEdit')->name('serviceOrderEdit');//服务订单修改


    Route::get('/questionList','QuestionController@getList')->name('questionList');//问答列表

    Route::get('/getDetail/{id}','QuestionController@getDetail')->name('getDetail');//问答详情

    Route::get('/getDetailAnswer/{id}','QuestionController@getDetailAnswer')->name('getDetailAnswer');//问答回答
    Route::get('/questionConfig','QuestionController@getConfig')->name('questionConfig');//问答配置


    Route::get('/promoteConfig','PromoteController@promoteConfig')->name('promoteConfig');//推广配置视图

    Route::get('/promoteRelation','PromoteController@promoteRelation')->name('promoteRelation');//推广关系
    Route::get('/promoteFinance','PromoteController@promoteFinance')->name('promoteFinance');//推广财务

    Route::get('/couponList','PromoteController@couponList')->name('couponList');//优惠券列表
    Route::get('/editCoupon','PromoteController@editCoupon')->name('editCoupon');//编辑新增优惠券
    Route::post('/postEditCoupon','PromoteController@postEditCoupon')->name('postEditCoupon');//优惠券编辑新增保存
    Route::get('/changeCouponStatus/{id}/{status}','PromoteController@changeCouponStatus')->name('changeCouponStatus');//优惠券状态修改
    Route::get('/couponDelete/{id}','PromoteController@couponDelete')->name('couponDelete');//优惠券删除
    Route::get('/userCouponList','PromoteController@userCouponList')->name('userCouponList');//优惠券使用列表
    Route::get('/substationConfig','SubstationController@substationConfig')->name('substationConfig');//分站配置


    Route::get('/freegrant','PromoteController@freegrant')->name('freegrant');//自动发放设置
    Route::post('/freegrant','PromoteController@postfreegrant')->name('postfreegrant');//自动发放设置
    Route::get('/freegrantlist','PromoteController@freegrantlist')->name('freegrantlist');//自动发放列表

    Route::get('/changefreegrantstatus/{id}/{status}/{del}','PromoteController@changefreegrantstatus')->name('changefreegrantstatus');//自动发放列表

    //vip店铺
    Route::get('/vipConfig', 'VipShopController@vipShopConfig')->name('vipConfig');//vip首页配置
    Route::get('/vipPackageList', 'VipShopController@vipPackageList')->name('vipPackageList');//vip套餐管理
    Route::get('/addPackagePage', 'VipShopController@addPackagePage')->name('addPackagePage');//vip添加套餐管理
    Route::get('/vipInfoList', 'VipShopController@vipInfoList')->name('vipInfoList');//vip特权列表
    Route::get('/vipShopList', 'VipShopController@vipShopList')->name('vipShopList');//vip店铺
    Route::get('/vipShopAuth/{id}', 'VipShopController@vipShopAuth')->name('vipShopAuth');//vip店铺查看
    Route::get('/vipDetailsList', 'VipShopController@vipDetailsList')->name('vipDetailsList');//vip访谈列表
    Route::get('/vipDetailsAuth', 'VipShopController@vipDetailsAuth')->name('vipDetailsAuth');//vip添加访谈
    Route::get('/editPackagePage/{id}','VipShopController@editPackagePage')->name('editPackagePage');//编辑套餐页面视图
    Route::get('/editInterviewPage/{id}','VipShopController@editInterviewPage')->name('editInterviewPage');//编辑访谈视图
    Route::get('/addPrivilegesPage', 'VipShopController@addPrivilegesPage')->name('addPrivilegesPage');//添加特权视图
    Route::get('/editPrivilegesPage/{id}','VipShopController@editPrivilegesPage')->name('editPrivilegesPage');//编辑特权视图

    Route::get('/vipList', 'VipController@vipList')->name('vipList');//vip列表
    Route::get('/changevipstatus/{id}/{status}', 'VipController@changevipstatus')->name('changevipstatus');//vip列表
    Route::get('/addvip', 'VipController@addvip')->name('addvip');//vip新增
    Route::post('/postaddvip', 'VipController@postaddvip')->name('postaddvip');//vip新增保存
    Route::get('/vipUserOrder', 'VipController@vipUserOrder')->name('vipUserOrder');//vip购买记录
    Route::get('/userVipCardList', 'VipController@userVipCardList')->name('userVipCardList');//.用户竞标卡列表

    //工具 接入交付台
    Route::get('/keeLoad', 'KeeController@keeLoad')->name('keeLoad');//kee接入展示页面

    Route::get('/activity', 'ActivityController@activityList')->name('activityList'); //活动管理
    Route::get('/addActivity', 'ActivityController@addActivity')->name('addActivity'); //添加活动
    Route::get('/editActivity/{id}', 'ActivityController@editActivity')->name('editActivity'); //编辑活动


    Route::get('/special2', 'ActivityController@special2List')->name('special2List');//IC检测报告列表

    Route::get('/activitylists', 'ActivityController@activitylists')->name('activitylists');//活动列表

    Route::get('/lottery', 'ActivityController@lottery')->name('lottery'); //世键抽奖



});


Route::group(['prefix' => 'manage', 'middleware' => ['manageauth','systemlog']], function() {
    Route::post('/addActivity', 'ActivityController@postAddActivity')->name('postAddActivity');


    //支付宝认证管理路由

    Route::get('/alipayAuthHandle/{id}/{action}', 'AuthController@alipayAuthHandle')->name('alipayAuthHandle');//支付宝认证处理
    Route::post('/alipayAuthMultiHandle', 'AuthController@alipayAuthMultiHandle')->name('alipayAuthMultiHandle');//支付宝认证批量处理
    Route::post('alipayAuthPay', 'AuthController@alipayAuthPay')->name('alipayAuthPayCreate');//支付宝后台打款

    //银行认证管理路由
    Route::get('/bankAuthHandle/{id}/{action}', 'AuthController@bankAuthHandle')->name('bankAuthHandle');//银行认证处理
    Route::post('/bankAuthMultiHandle', 'AuthController@bankAuthMultiHandle')->name('bankAuthMultiHandle');//银行认证批量审核
    Route::post('bankAuthPay', 'AuthController@bankAuthPay')->name('bankAuthPayCreate');//银行后台支付


    Route::get('/enterpriseAuthHandle/{id}/{action}', 'AuthController@enterpriseAuthHandle')->name('enterpriseAuthHandle');//企业认证处理

    //任务管理路由
    Route::get('/taskAdd', 'TaskController@taskAdd')->name('taskAdd');//添加竞标任务
    Route::post('/taskAddData', 'TaskController@taskAddData')->name('taskAddData');//添加竞标任务数据
    Route::get('/taskHandle/{id}/{action}', 'TaskController@taskHandle')->name('taskUpdate');//任务处理
    Route::post('/taskFollowData', 'TaskController@taskFollowData')->name('taskFollowData');//任务跟进数据提交
    Route::post('/taskMultiHandle', 'TaskController@taskMultiHandle')->name('taskMultiUpdate');//任务批量处理
    Route::get('/taskDel/{id}', 'TaskController@taskDel')->name('taskDel');//任务删除
    Route::post('/taskDetailUpdate', 'TaskController@taskDetailUpdate')->name('taskDetailUpdate');//任务详情提交
    Route::get('/taskMassageDelete/{id}', 'TaskController@taskMassageDelete')->name('taskMassageDelete');//删除任务留言
    //雇佣管理路由
    Route::post('/bidConfigUpdate', 'BidController@bidConfigUpdate')->name('bidConfigUpdate');//任务配置修改

    //财务管理路由
    Route::get('/userFinanceListExport', 'FinanceController@userFinanceListExport')->name('userFinanceListExportCreate');//用户流水导出
    Route::get('/financeRechargeExport/{param}', 'FinanceController@financeRechargeExport')->name('financeRechargeExportCreate');//充值记录导出
    Route::get('/financeWithdrawExport/{param}', 'FinanceController@financeWithdrawExport')->name('financeWithdrawExportCreate');//提现记录导出


    //地区管理路由
    Route::post('/areaCreate','AreaController@areaCreate')->name('areaCreate');//地区管理添加
    Route::get('/areaDelete/{id}','AreaController@areaDelete')->name('areaDelete');//地区管理删除
    Route::get('/ajaxcity','AreaController@ajaxCity')->name('ajaxCity');//地区管理筛选（城市）
    Route::get('/ajaxarea','AreaController@ajaxArea')->name('ajaxArea');//地区管理筛选（地区）

    //行业管理路由
    Route::post('/industryCreate','IndustryController@industryCreate')->name('industryCreate');//行业管理提交
    Route::get('/industryDelete/{id}','IndustryController@industryDelete')->name('industryDelete');//行业管理删除
    Route::get('/ajaxSecond','IndustryController@ajaxSecond')->name('ajaxSecond');//行业管理筛选（城市）
    Route::get('/ajaxThird','IndustryController@ajaxThird')->name('ajaxThird');//行业管理筛选（地区）
    Route::get('/tasktemplate/{id}','IndustryController@taskTemplates')->name('taskTemplates');//行业实例页面
    Route::post('/templateCreate','IndustryController@templateCreate')->name('templateCreate');//行业实例添加控制器
   

    Route::get('/cashoutHandle/{id}/{action}', 'FinanceController@cashoutHandle')->name('cashoutUpdate');//提现审核处理

    Route::post('userRecharge', 'FinanceController@postUserRecharge')->name('userRechargeUpdate');//后台用户充值
    Route::get('confirmRechargeOrder/{order}', 'FinanceController@confirmRechargeOrder')->name('confirmRechargeOrder');//后台确认订单充值

    //全局配置
    Route::post('/config/basic', 'ConfigController@saveConfigBasic')->name('configBasicUpdate');//保存基本配置
    Route::post('/config/seo', 'ConfigController@saveConfigSEO')->name('configSeoUpdate');//保存seo配置
    Route::post('/config/nav', 'ConfigController@postConfigNav')->name('configNavCreate');//新增导航
    Route::post('/config/attachment', 'ConfigController@postAttachmentConfig')->name('attachmentConfigCreate');//保存附件配置信息
    Route::post('/config/site', 'ConfigController@saveConfigSite')->name('configSiteUpdate');//保存站点配置
    Route::post('/config/email', 'ConfigController@saveConfigEmail')->name('configEmailUpdate');//保存邮箱配置
    Route::post('/config/sendEmail', 'ConfigController@sendEmail')->name('sendEmail');//发送测试邮件
    Route::post('/config/link', 'ConfigController@link')->name('postConfigLink');//站点配置关注链接
    Route::post('/config/phone', 'ConfigController@saveConfigPhone')->name('configphoneUpdate');//保存短信配置
    Route::post('/config/appalipay', 'ConfigController@saveConfigAppAliPay')->name('configAppAliPayUpdate');//保存app支付宝支付配置
    Route::post('/config/appwechat', 'ConfigController@saveConfigAppWeChat')->name('configAppWeChatUpdate');//保存app微信支付配置
    Route::post('/config/appMessage', 'ConfigController@saveConfigAppMessage')->name('configAppMessageUpdate');//保存app聊天配置
    Route::post('/config/wechatpublic', 'ConfigController@saveConfigWeChatPublic')->name('configWeChatPublicUpdate');//保存微信端配置

    //任务配置
    Route::post('/taskConfigUpdate','TaskConfigController@update')->name('taskConfigUpdate');//任务配置提交
    Route::get('/ajaxUpdateSys','TaskConfigController@ajaxUpdateSys')->name('ajaxUpdateSys');//任务配置开关


    //接口管理
    Route::post('payConfig', 'InterfaceController@postPayConfig')->name('payConfigUpdate');//保存支付配置
    Route::get('thirdPayHandle/{id}/{action}', 'InterfaceController@thirdPayHandle')->name('thirdPayStatusUpdate');//启用/禁用支付接口
    Route::post('thirdPayEdit', 'InterfaceController@postThirdPayEdit')->name('thirdPayUpdate');//保存支付配置

    //第三方登陆
    Route::post('thirdLogin', 'InterfaceController@postThirdLogin')->name('thirdLoginCreate');//保存第三方登录配置

    //资讯中心路由
    Route::post('/addArticle', 'ArticleController@postArticle')->name('articleCreate'); //添加文章
    Route::get('/articleDel/','ArticleController@articleDel')->name('articleDel'); //.删除文章（资讯）
    Route::get('/articleDelete/{id}/{upID}','ArticleController@articleDelete')->name('articleDelete'); //删除文章
    Route::post('/editArticle', 'ArticleController@postEditArticle')->name('articleUpdate'); //编辑文章
    Route::post('/allDelete/{upID}', 'ArticleController@allDelete')->name('allDelete'); //批量删除文章

    //资讯中心分类路由
    Route::get('/categoryDelete/{id}/{upID}','ArticleCategoryController@categoryDelete')->name('categoryDelete'); //删除文章分类
    Route::post('/categoryAdd', 'ArticleCategoryController@postCategory')->name('categoryCreate');//添加文章分类
    Route::post('/categoryEdit','ArticleCategoryController@postEditCategory')->name('categoryUpdate');//编辑文章分类
    Route::post('/categoryAllDelete','ArticleCategoryController@cateAllDelete')->name('categoryAllDelete');//批量删除文章分类

    //后台成功案例
    Route::post('/successCaseUpdate','SuccessCaseController@update')->name('successCaseCreate');//成功案例提交页面
    Route::get('/successCaseDel/{id}','SuccessCaseController@successCaseDel')->name('successCaseDel');//成功案例删除
    Route::post('/ajaxGetSecondCate','SuccessCaseController@ajaxGetSecondCate')->name('ajaxGetSecondCate');//成功案例提交页面
    Route::get('/releasetatus/{id}/{status}','SuccessCaseController@releasetatus')->name('releasetatus');//成功案例删除

    //自定义导航
    Route::post('/addNav','NavController@postAddNav')->name('navCreate'); //添加自定义导航
    Route::post('/editNav','NavController@postEditNav')->name('navUpdate'); //编辑自定义导航
    Route::get('/deleteNav/{id}','NavController@deleteNav')->name('navDelete');//删除自定义导航
    Route::get('/isFirst/{id}','NavController@isFirst')->name('isFirst'); //设为首页

    //用户管理

    Route::get('/handleUser/{uid}/{action}', 'UserController@handleUser')->name('userStatusUpdate');//用户处理
    Route::post('/userAdd', 'UserController@postUserAdd')->name('userCreate');//添加用户
    Route::post('checkUserName', 'UserController@checkUserName')->name('checkUserName');//检测用户名是否存在
    Route::post('checkEmail', 'UserController@checkEmail')->name('checkEmail');//检测邮箱
    Route::post('/userEdit', 'UserController@postUserEdit')->name('userUpdate');//用户详情更新
    Route::get('/handleManage/{uid}/{action}', 'UserController@handleManage')->name('userStatusUpdate');//系统用户处理
    Route::post('/managerAdd', 'UserController@postManagerAdd')->name('managerCreate');//系统用户添加
    Route::post('checkManageName', 'UserController@checkManageName')->name('checkManageName');//检测系统用户名
    Route::post('checkManageEmail', 'UserController@checkManageEmail')->name('checkManageEmail');//检测系统用户邮箱
    Route::post('/managerDetail', 'UserController@postManagerDetail')->name('managerDetailUpdate');//更新系统用户
    Route::get('/managerDel/{id}', 'UserController@managerDel')->name('managerDelete');//系统用户删除
    Route::post('/managerDeleteAll', 'UserController@postManagerDeleteAll')->name('managerAllDelete');//系统用户批量删除

    Route::post('/rolesAdd', 'UserController@postRolesAdd')->name('rolesCreate');//用户组添加
    Route::get('/rolesDel/{id}', 'UserController@getRolesDel')->name('rolesDelete');//用户组删除
    Route::post('/rolesDetail', 'UserController@postRolesDetail')->name('rolesDetailUpdate');//用户组更新


    Route::post('/permissionsAdd', 'UserController@postPermissionsAdd')->name('permissionsCreate');//权限添加
    Route::get('/permissionsDel/{id}', 'UserController@getPermissionsDel')->name('permissionsDelete');//删除权限
    Route::post('/permissionsDetail', 'UserController@postPermissionsDetail')->name('postPermissionsDetailUpdate');//权限更新

    //后台权限添加
    Route::post('/menuCreate','MenuController@menuCreate')->name('menuCreate');
    Route::get('/menuDelete/{id}','MenuController@menuDelete')->name('menuDelete');
    Route::post('/updateMenu','MenuController@updateMenu')->name('updateMenu');
    //用户举报
    Route::get('/reportList','TaskReportController@reportList')->name('reportList');//用户举报列表

    Route::get('/reportDelet/{id}','TaskReportController@reportDelet')->name('reportDelete');//用户举报单个删除
    Route::post('/reportDeletGroup','TaskReportController@reportDeletGroup')->name('reportGroupDelete');//用户举报批量删除
    Route::post('/handleReport','TaskReportController@handleReport')->name('reportUpdate');//用户举报处理
    //交易维权
    Route::get('/bidRightsList','TaskRightsController@bidRightsList')->name('bidRightsList');//雇佣交易维权列表
    Route::get('/rightsDelet/{id}','TaskRightsController@rightsDelet')->name('rightsDelete');//交易维权单个删除
    Route::post('/rightsDeletGroup','TaskRightsController@rightsDeletGroup')->name('rightsGroupDelete');//交易维权批量删除
    Route::get('/bidRightsDetail/{id}','TaskRightsController@bidRightsDetail')->name('bidRightsDetail');//雇佣交易维权详情
    Route::post('/handleRights','TaskRightsController@handleRights')->name('handleRightsCreate');//交易维权处理
    Route::get('/cancelRights/{id}','TaskRightsController@cancelRights')->name('cancelRights');//交易维权取消

    //增值工具
    Route::post('/addService','ServiceController@postAddService')->name('addServiceCreate');//添加增值工具
    Route::post('/postEditService','ServiceController@postEditService')->name('addServiceUpdate');//编辑增值工具
    Route::get('/deleteService/{id}','ServiceController@deleteService')->name('addServiceDelete');//删除增值工具


    //友情链接
    Route::get('/deletelink/{id}', 'LinkController@getDeleteLink')->name('linkDelete');//友情链接删除
    Route::post('/allDeleteLink', 'LinkController@allDeleteLink')->name('allLinkDelete');//友情链接批量删除
    Route::get('/handleLink/{id}/{action}', 'LinkController@handleLink')->name('linkStatusUpdate');//友情链接处理
    Route::post('/updatelink/{id}', 'LinkController@postUpdateLink')->name('linkUpdate');//友情链接更新


    //投诉建议
    Route::get('/feedbackReplay/{id}', 'FeedbackController@feedbackReplay')->name('feedbackReplayUpdate');//回复某个投诉建议
    Route::get('/deleteFeedback/{id}', 'FeedbackController@deletefeedback')->name('feedbackDelete');//删除某个投诉建议
    Route::get('/feedbackUpdate', 'FeedbackController@feedbackUpdate')->name('feedbackUpdate');//修改某个投诉建议

    //热词管理
    Route::get('/listorderUpdate','HotwordsController@listorderUpdate')->name('listorderUpdate');//热词排序修改
    Route::get('/hotwordsDelete/{id}','HotwordsController@hotwordsDelete')->name('hotwordsDelete');//删除热词信息
    Route::get('/hotwordsMulDelte','HotwordsController@hotwordsMulDelte')->name('hotwordsMulDelete');//批量删除热词信息

    //站长工具
    Route::get('attachmentDel/{id}', 'ToolController@attachmentDel')->name('attachmentDelete');//附件删除处理


    //短信模板
    Route::post('/editMessage','MessageController@postEditMessage')->name('messageUpdate'); //编辑模版
    Route::get('/changeStatus/{id}/{isName}/{status}','MessageController@changeStatus')->name('messageStatusUpdate'); //改变模版状态

    //系统日志
    Route::get('/systemLogDelete/{id}','SystemLogController@systemLogDelete')->name('systemLogDelete');//删除某个系统日志信息
    Route::get('/systemLogDeleteAll','SystemLogController@systemLogDeleteAll')->name('systemLogDeleteAll');//清空日志
    Route::post('/systemLogMulDelete','SystemLogController@systemLogMulDelete')->name('systemLogMulDelete');//批量删除

    //用户互评
    Route::get('/commentDel/{id}','TaskCommentController@commentDel')->name('commentDelete');//用户互评删除按钮

    //协议管理
    Route::post('/addAgreement','AgreementController@postAddAgreement')->name('agreementCreate');//添加协议
    Route::post('/editAgreement','AgreementController@postEditAgreement')->name('agreementUpdate');//编辑协议
    Route::get('/deleteAgreement/{id}','AgreementController@deleteAgreement')->name('agreementDelete');//删除协议

    //模板管理
    Route::get('/skinChange/{name}','AgreementController@skinChange')->name('skinChange');//模板更换
    Route::get('/skinSet/{number}','AgreementController@skinSet')->name('skinSet');//经典模板选择
    //关于我们
    Route::get('/aboutUs','ConfigController@aboutUs')->name('aboutUs');

    //雇佣管理
    Route::post('/employUpdate','EmployController@employUpdate')->name('employUpdate');//雇佣修改控制器
    Route::get('/employDelete/{id}','EmployController@employDelete')->name('employDelete');//删除雇佣数据
    Route::get('/download/{id}','EmployController@download')->name('download');//下载附件
    Route::post('/configUpdate','EmployController@configUpdate')->name('configUpdate');//雇佣配置提交

    //企业认证管理路由
    Route::get('/enterpriseAuth/{id}', 'AuthController@enterpriseAuth')->name('enterpriseAuth');//企业认证详情
    Route::post('/allEnterprisePass', 'AuthController@allEnterprisePass')->name('allEnterprisePass');//企业认证批量通过
    Route::post('/allEnterpriseDeny', 'AuthController@allEnterpriseDeny')->name('allEnterpriseDeny');//企业认证批量失败

    //店铺管理路由
    Route::get('/dealShop/{id}/{action}', 'ShopController@dealShop')->name('dealShop');///店铺审核
    Route::get('/openShop/{id}', 'ShopController@openShop')->name('openShop');//开启店铺
    Route::get('/closeShop/{id}', 'ShopController@closeShop')->name('closeShop');//关闭店铺
    Route::get('/recommendShop/{id}', 'ShopController@recommendShop')->name('recommendShop');//推荐店铺
    Route::get('/removeRecommendShop/{id}', 'ShopController@removeRecommendShop')->name('removeRecommendShop');//取消推荐店铺
    Route::get('/dealShopUpgrade/{id}/{action}', 'ShopController@dealShopUpgrade')->name('dealShopUpgrade');///店铺升级审核

    Route::post('/saveGoodsInfo', 'GoodsController@saveGoodsInfo')->name('saveGoodsInfo');//保存方案信息
    Route::post('/changeGoodsStatus', 'GoodsController@changeGoodsStatus')->name('changeGoodsStatus');//修改方案状态
    Route::post('/checkGoodsDeny', 'GoodsController@checkGoodsDeny')->name('checkGoodsDeny');//方案审核失败
    Route::post('/ajaxGetSecondCate', 'GoodsController@ajaxGetSecondCate')->name('ajaxGetSecondCate');//获取二级行业分类
    Route::post('/saveGoodsFollow', 'GoodsController@saveGoodsFollow')->name('saveGoodsFollow');//保存方案跟进信息
    Route::get('/goodsFollowDelete/{id}', 'GoodsController@goodsFollowDelete')->name('goodsFollowDelete');//删除方案跟进信息

    Route::post('/ShopRightsSuccess/{id}', 'ShopOrderController@ShopRightsSuccess')->name('ShopRightsSuccess');//方案维权处理维权成功
    Route::get('/ShopRightsFailure/{id}', 'ShopOrderController@ShopRightsFailure')->name('ShopRightsFailure');//方案维权无效
    Route::post('/saveOrderFollow', 'ShopOrderController@saveOrderFollow')->name('saveOrderFollow');//保存跟进信息
    Route::get('/orderFollowDelete/{id}', 'ShopOrderController@orderFollowDelete')->name('orderFollowDelete');//删除跟进

    Route::post('/allOpenShop', 'ShopController@allOpenShop')->name('allOpenShop');//批量开启店铺
    Route::post('/allCloseShop', 'ShopController@allCloseShop')->name('allCloseShop');//批量关闭店铺
    Route::post('/updateShopInfo', 'ShopController@updateShopInfo')->name('updateShopInfo');//后台修改店铺详情
    Route::post('/postShopConfig', 'ShopController@postShopConfig')->name('postShopConfig');//保存店铺配置
    Route::post('/postGoodsConfig', 'GoodsController@postGoodsConfig')->name('postGoodsConfig');//保存商品流程配置

    Route::post('/download', 'ShopController@download')->name('download');//下载附件

    Route::post('/serviceConfigUpdate','GoodsServiceController@serviceConfigUpdate')->name('serviceConfigUpdate');//店铺流程配置提交
    Route::post('/saveServiceInfo','GoodsServiceController@saveServiceInfo')->name('saveServiceInfo');//店铺流程配置
    Route::get('/checkServiceDeny','GoodsServiceController@checkServiceDeny')->name('checkServiceDeny');//店铺服务审核失败
    Route::post('/checkServiceDeny','GoodsServiceController@checkServiceDeny')->name('checkServiceDeny');//店铺服务审核失败
    Route::get('/changeServiceStatus','GoodsServiceController@changeServiceStatus')->name('changeServiceStatus');//店铺服务状态修改

    Route::post('/serviceOrderUpdate','GoodsServiceController@serviceOrderUpdate')->name('serviceOrderUpdate');//服务订单修改提交



    Route::get('/verify/{id}/{status}','QuestionController@verify')->name('verify');//问答验证
    Route::post('/postDetail','QuestionController@postDetail')->name('postDetail');//问答详情修改
    Route::post('/postConfig','QuestionController@postConfig')->name('postConfig');//问答配置修改
    Route::get('/ajaxCategory/{id}','QuestionController@ajaxCategory')->name('ajaxCategory');//问答类别切换
    Route::get('/questionDelete/{id}','QuestionController@questionDelete')->name('questionDelete');//问答类别切换


    Route::get('/download/{id}','TaskController@download');

    Route::post('/promoteConfig','PromoteController@postPromoteConfig')->name('postPromoteConfig');//推广配置


    Route::post('/addSubstation','SubstationController@postAdd')->name('addSubstation');//添加分站点
    Route::get('/deleteSubstation/{id}','SubstationController@deleteSub')->name('deleteSubstation');//删除分站配置
    Route::post('/postEditSubstation','SubstationController@editSub')->name('postEditSubstation');//编辑分站配置
    Route::post('/changeSubstation','SubstationController@changeSubstation')->name('changeSubstation');//改变分站状态


    //vip店铺
    Route::post('/config/vip', 'VipShopController@vipConfigUpdate')->name('vipConfigUpdate');//保存vip配置信息
    Route::get('/packageStatus/{id}','VipShopController@updatePackageStatus')->name('packageStatusUpdate');//更改套餐状态
    Route::get('/packageDelete/{id}','VipShopController@packageDelete')->name('packageDelete');//删除套餐

    Route::post('/addPackage','VipShopController@addPackage')->name('addPackage');//添加套餐
    Route::get('/interviewDelete/{id}','VipShopController@interviewDelete')->name('interviewDelete');//删除访谈
    Route::post('/addInterview','VipShopController@addInterview')->name('addInterview');//添加访谈
    Route::get('/editInterviewPage/{id}','VipShopController@editInterviewPage')->name('editInterviewPage');//编辑访谈视图
    Route::post('/editInterview/{id}','VipShopController@editInterview')->name('interviewUpdate');//编辑访谈
    Route::post('/endTimeUpdate','VipShopController@endTimeUpdate')->name('endTimeUpdate');//编辑购买记录的到期时间
    Route::get('/privilegesDelete/{id}','VipShopController@privilegesDelete')->name('privilegesDelete');//删除特权
    Route::get('/updateStatus/{id}','VipShopController@updateStatus')->name('statusUpdate');//启用或停用特权
    Route::get('/updateRecommend/{id}','VipShopController@updateRecommend')->name('recommendUpdate');//推荐或取消推荐特权
    Route::get('/addPrivilegesPage', 'VipShopController@addPrivilegesPage')->name('addPrivilegesPage');//添加特权视图
    Route::post('/addPrivileges','VipShopController@addPrivileges')->name('addPrivileges');//添加特权
    Route::post('/updatePrivileges/{id}','VipShopController@updatePrivileges')->name('privilegesUpdate');//编辑特权
    Route::post('/editPackage/{id}','VipShopController@editPackage')->name('packageUpdate');//编辑套餐

    //工具 接入交付台
    Route::get('/keeLoadFirst', 'KeeController@keeLoadFirst')->name('keeLoadFirst');//首次申请kee接入
    Route::get('/keeLoadAgain', 'KeeController@keeLoadAgain')->name('keeLoadAgain');//再次申请接入kee
    Route::get('/isOpenKee', 'KeeController@isOpenKee')->name('isOpenKee');//是否开启kee

    // 报表路由
    Route::get('/case_baobiao', 'BaoBiaoController@case_baobiao')->name('case_baobiao');//案例报表
    Route::get('/xunjia_baobiao', 'BaoBiaoController@xunjia_baobiao')->name('xunjia_baobiao');//询价报表
    Route::get('/xiaoshou_baobiao', 'BaoBiaoController@xiaoshou_baobiao')->name('xiaoshou_baobiao');//案例报表
    Route::get('/dingzhi_baobiao', 'BaoBiaoController@dingzhi_baobiao')->name('dingzhi_baobiao');//案例报表

    Route::get('/fabao_baobiao', 'BaoBiaoController@fabao_baobiao')->name('fabao_baobiao');//发包报表
    Route::get('/jiebao_baobiao', 'BaoBiaoController@jiebao_baobiao')->name('jiebao_baobiao');//接包报表
    Route::get('/xuanzhong_baobiao', 'BaoBiaoController@xuanzhong_baobiao')->name('xuanzhong_baobiao');//选中报表
    Route::get('/tuoguan_baobiao', 'BaoBiaoController@tuoguan_baobiao')->name('tuoguan_baobiao');//托管报表
    Route::get('/jiean_baobiao', 'BaoBiaoController@jiean_baobiao')->name('jiean_baobiao');//结案报表

    Route::get('/yonghu_baobiao', 'BaoBiaoController@yonghu_baobiao')->name('yonghu_baobiao');//用户报表
    Route::get('/yonghulogin_baobiao', 'BaoBiaoController@yonghulogin_baobiao')->name('yonghulogin_baobiao');//用户登录报表
    Route::get('/huiyuan_baobiao', 'BaoBiaoController@huiyuan_baobiao')->name('huiyuan_baobiao');//会员报表
    Route::get('/yanshou_baobiao', 'BaoBiaoController@yanshou_baobiao')->name('yanshou_baobiao');//验收报表
});

