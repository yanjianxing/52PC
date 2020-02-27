<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\Article\Model\ArticleModel;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Http\Controllers\ConfigController;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticlePayModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\SubOrderModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\VipConfigModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeInquiryPayModel;
use App\Modules\Shop\Models\ShopDecorateConfigModel;
use App\Modules\Shop\Models\ShopFocusModel;
use App\Modules\Shop\Models\ShopOneModel;
use App\Modules\Shop\Models\ShopTwoModel;
use App\Modules\Shop\Models\ShopUpgradeModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDepositModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserToolModel;
use App\Modules\User\Model\UserVipCardModel;
use App\Modules\User\Model\UserVipConfigModel;
use App\Modules\Vipshop\Models\PackageModel;
use App\Modules\Vipshop\Models\PrivilegesModel;
use App\Modules\Vipshop\Models\ShopPackageModel;
use App\User;
use Guzzle\Common\Exception\ExceptionCollection;
use Illuminate\Http\Request;
use Auth;
use Crypt;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Mockery\CountValidator\Exception;
use Omnipay;
use QrCode;

class ShopController extends UserCenterController
{

    public function __construct()
    {
        parent::__construct();
        //查看该用户是否开启的店铺
        $shop=ShopModel::where("uid",Auth::user()->id)->where("status",1)->first();
        $this->theme->set("shop_open",false);
        $this->theme->set("shop_com",false);
        if($shop){
            $this->theme->set("shop_open",true);
			$this->theme->set("shopInfo",$shop);
            if($shop['type'] ==2){
                $this->theme->set("shop_com",true);
            }
        }
        $this->initTheme('accepttask');//主题初始化
    }

    /**
     * 店铺设置视图
     * @param Request $request
     * @return mixed
     */
    public function getShop(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('店铺设置');
        $this->theme->set("userShop",1);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shopSet");
        $this->theme->set("userSecondColumn","开通店铺");
        $this->theme->set("userSecondColumnUrl","/user/shop");
        $uid = Auth::User()->id;
        //判断用户有没有进行手机认证
        if(empty(Auth::User()->mobile)){
            return redirect("/user/phoneAuth")->with(['message'=>'请先进行手机认证']);
        }
        //判断用户有没有进行邮箱认证
        if(Auth::User()->email_status !=2){
            //return redirect("/user/emailAuth")->with(['message'=>'请先进行邮箱认证']);
        }
        //判断用户是否实名认证
        $realName = RealnameAuthModel::where('uid',$uid)->orderBy("id","desc")->first();
        //获取企业认证信息
        $enterAuth = EnterpriseAuthModel::where('uid',$uid)->orderBy("id","desc")->first();
        //判断店铺是否进行企业认证
        $companyAuth = EnterpriseAuthModel::isEnterpriseAuth($uid);
        //查询应用领域
        $apply = CateModel::where("type",1)->select("id","name")->get()->toArray();
        //技能标签
        $skillCate = CateModel::where("type",2)->select("id","name")->get()->toArray();
        //开发平台
        $openWeb = CateModel::where("type",3)->select("id","name")->get()->toArray();
        //查询地区一级数据
        $province = DistrictModel::findTree(0);
        //查询地区二级信息
        $city = DistrictModel::findTree($province[0]['id']);
        //查询地区三级信息
        $area = DistrictModel::findTree($city[0]['id']);
        //查询店铺详情
        $shopInfo = ShopModel::getShopInfoByUid($uid);
        $shopApply = null;
        $shopSkill = null;
        if($shopInfo){
            $shopInfo['service_company'] = json_decode($shopInfo['service_company']);
            $shopInfo['openWeb'] = json_decode($shopInfo['openWeb']);
            $city = DistrictModel::findTree($shopInfo['province']);
            $area = DistrictModel::findTree($shopInfo['city']);
            //获取店铺的技能标签
           $shopApply = ShopTagsModel::leftJoin("cate","tag_shop.cate_id",'=',"cate.id")->where("tag_shop.shop_id",$shopInfo['id'])->where("tag_shop.type",1)->select("cate.id","cate.name")->get()->toArray();
           $shopApply = \CommonClass::setArrayKey($shopApply,"id");
           $shopSkill = ShopTagsModel::leftJoin("cate","tag_shop.cate_id",'=',"cate.id")->where("tag_shop.shop_id",$shopInfo['id'])->where("tag_shop.type",2)->select("cate.id","cate.name")->get()->toArray();
           $shopSkill = \CommonClass::setArrayKey($shopSkill,"id");
        }
        //获取用户vip权限信息
        $userVipConfig = UserVipConfigModel::getConfigByUid($uid);
        if(Auth::user()->level ==1){
            $member_name="普通会员";
        }else{
            $member_name=VipModel::where("grade",Auth::user()->level)->pluck("name");
        }
        $data = array(
            'apply'         => $apply,
            'skillCate'     => $skillCate,
            'openWeb'       => $openWeb,
            'realnameInfo'  => $realName,
            'enterAuth'     => $enterAuth,
            'province'      => $province,
            'city'          => $city,
            'area'          => $area,
            'shopInfo'      => $shopInfo,
            'shopApply'     => $shopApply,
            'shopSkill'     => $shopSkill,
            'userVipConfig' => $userVipConfig,
            'member_name'  =>$member_name,
            'companyAuth'=>$companyAuth,
         );
       // }
        return $this->theme->scope('user.shop.usershop',$data)->render();

    }


    /**
     * 保存店铺设置
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postShopInfo(Request $request)
    {
        //$data = $request->except('_token');
        $uid = Auth::User()->id;
        //判断用户有没有进行手机认证
        if(empty(Auth::User()->mobile)){
            return redirect("/user/phoneAuth")->with(['message'=>'请先进行手机认证']);
        }
        //判断用户有没有进行邮箱认证
        if(Auth::User()->email_status !=2){
            //return redirect("/user/emailAuth")->with(['message'=>'请先进行邮箱认证']);
        }
        //获取用户vip权限信息
        $userVipConfig=UserVipConfigModel::getConfigByUid($uid);
        //判断是不是在权限里面
        if(count(json_decode($request->get("apply"))) > $userVipConfig['appliy_num'] || count(json_decode($request->get("skillCate"))) > $userVipConfig['skill_num']){
            return back()->with(["message"=>"标签数量设置超标，解锁更多请购买vip"]);
        }
        //开启企业店铺的时候 需要判断是否开启的企业认证
        if($request->get('shop_type') ==2){
            $enterAuth = EnterpriseAuthModel::where('uid',Auth::user()->id)->where("status","1")->first();
            if(!$enterAuth){
                return redirect("/user/enterpriseAuth")->with(["message"=>"先进行企业认证"]);
            }
        }
       //用户实名认证提交
        //$realName = RealnameAuthModel::where('uid',Auth::user()->id)->orderBy("id","desc")->first();
        //$enterAuth = EnterpriseAuthModel::where('uid',Auth::user()->id)->orderBy("id","desc")->first();
        //if((!$realName || ($realName && $realName['status'] ==2)) && !$request->get('com_name')){
           // $status=RealnameAuthModel::realnameAuthPost($request);
        //}

        //if($request->get('com_name') && (!$enterAuth ||  ($enterAuth && $enterAuth['status'] ==2))){
           // $status=RealnameAuthModel::realnameAuthPost($request);
            //$enterpriseAuth = EnterpriseAuthModel::where('uid',$shopInfo['uid'])->orderBy('id','desc')->first();
        //}
        $shop_pic = $request->file('shop_pic');
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png','JPG', 'GIF', 'JPEG', 'BMP', 'PNG');
        if ($shop_pic) {
            $uploadMsg = json_decode(\FileClass::uploadFile($shop_pic, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['shop_pic'] = $uploadMsg->message;
            } else {
                $data['shop_pic'] = $uploadMsg->data->url;
            }
        }
        $data['uid'] = $uid;
        $data['shop_desc'] = trim($request->get("shop_desc"));
        $data['province']=$request->get("province");
        $data['city']=$request->get("city");
        $data['area']=$request->get("area");
        $data['phone']=$request->get("phone");
        $data['email']=$request->get("email");
        $data['service_company']=json_encode($request->get("service_company"));
        $data['type']=$request->get('shop_type');
        $data['shop_name']=$request->get("shop_name");
        $data['apply']=json_decode($request->get("apply"));
        $data['skillCate']=json_decode($request->get("skillCate"));
        $data['openWeb']=$request->get("openWeb");
        $data['job_year']=$request->get("job_year");
        if($request->get('id') && $request->get('id') != ''){
            $data['id']=$request->get('id');
           //编辑店铺设置
           $shop = ShopModel::where('id',$data['id'])->first();
           if (!isset($data['shop_pic'])) {
               $data['shop_pic'] = $shop->shop_pic;
           }
            $res = ShopModel::updateShopInfo($data);
        }else{
            $res = ShopModel::createShopInfo($data);
            UserDetailModel::where('uid',$uid)->update(['shop_status' => 1]);
        }
        if($res){
            return redirect('/user/shop')->with(array('message' => '保存成功'));
        }else{
            return redirect('/user/shop')->with(array('message' => '保存失败'));
        }


    }

    /*
     * 我的店铺基本信息设置
     * */
    public function shopSet(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('店铺信息');
        $this->theme->set("userShop",3);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","店铺信息");
        $this->theme->set("userSecondColumnUrl","/user/shopSet");
        $uid = Auth::User()->id;
        //查询地区一级信息
        $province = DistrictModel::findTree(0);
        //查询店铺详情
        $shopInfo = ShopModel::getShopInfoByUid($uid);
         //查询地区二级信息
        $city = DistrictModel::findTree($shopInfo['province']);
        //查询地区三级级信息
        $area = DistrictModel::findTree($shopInfo['city']);
        $data=[
            'shopInfo'=>$shopInfo,
            'province'=>$province,
            'city'=>$city,
            'area'=>$area,
        ];
        return $this->theme->scope('user.shop.shopSet',$data)->render();
    }
    //我的店铺设置post 提交
    public function shopSetPost(Request $request){
        $data=$request->except("_token");
        $uid = Auth::User()->id;
        $shopInfo = ShopModel::where("uid",$uid)->where("status",1)->first();
        if(!$shopInfo){
           return redirect("/user/shop")->with(["message"=>"快点开通店铺"]);
        }
        $shop_pic = $request->file('shop_pic');
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png','JPG', 'GIF', 'JPEG', 'BMP', 'PNG');
        //正面照
        if ($shop_pic) {
            $uploadMsg = json_decode(\FileClass::uploadFile($shop_pic, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['shop_pic'] = $uploadMsg->message;
            } else {
                $data['shop_pic'] = $uploadMsg->data->url;
            }
        }
        $data['updated_at']=date("Y-m-d H:i:s");
        $data['status']=0;
        $res=ShopModel::where("id",$data['id'])->update(array_except($data,"id"));
        if($res){
            return back()->with(["message"=>"编辑店铺成功"]);
        }
            return back()->with(["message"=>"编辑店铺失败"]);
    }

    /*
     * 我的店铺升级
     * */
    public function shopUpgrade(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('店铺升级');
        $this->theme->set("userShop",4);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","店铺升级");
        $this->theme->set("userSecondColumnUrl","/user/shopUpgrade");
        $enterAuth = EnterpriseAuthModel::where('uid',Auth::user()->id)->where("status","1")->first();
        if(!$enterAuth){
            return redirect("/user/enterpriseAuth")->with(["message"=>"先进行企业认证"]);
        }
        $uid = Auth::User()->id;
        //判断用户是否实名认证
        //$realName = RealnameAuthModel::where('uid',$uid)->orderBy("id","desc")->first();
        //获取企业认证信息
        $enterAuth=EnterpriseAuthModel::where('uid',$uid)->orderBy("id","desc")->first();
        //查询店铺详情
        $shopInfo = ShopModel::getShopInfoByUid($uid);
        //查看是否有店铺提交申请
        $shopUpgrade=ShopUpgradeModel::where("uid",Auth::user()->id)->where("shop_id",$shopInfo['id'])->first();
        $data = array(
            //'realnameInfo'       => $realName,
            'shopInfo'   =>$shopInfo,
            'shopUpgrade'=>$shopUpgrade,
            'enterAuth' =>$enterAuth
        );
        return $this->theme->scope('user.shop.shopUpgrade',$data)->render();
    }
    /*
     * 我的店铺升级post 提交
     * */
    public function shopUpgradePost(Request $request){
        $realName = RealnameAuthModel::where('uid',Auth::user()->id)->orderBy("id","desc")->first();
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png', 'JPG', 'GIF', 'JPEG', 'BMP', 'PNG');
        $validation_img = $request->file('validation_img');
        //营业执照
        if ($validation_img) {
            $uploadMsg = json_decode(\FileClass::uploadFile($validation_img, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['business_pic'] = $uploadMsg->message;
            } else {
                $realnameInfo['license_img'] = $uploadMsg->data->url;
            }
        }
            $res=DB::transaction(function() use($realName,$request){
                    //存储个人企业认证信息
//                    RealnameAuthModel::where("id",$realName['id'])->update([
//                        "updated_at"=>date("Y-m-d H:i:s"),
//                        'type'=>2,
//                    ]);
                       //RealnameAuthModel::realnameAuthPost($request);
//                    if($request->get("com_name")){
//                        $realnameInfo['company_name']=$request->get("com_name");
//                    }
//                    if($request->get("business_num")){
//                        $realnameInfo['business_license']=$request->get("business_num");
//                    }
//                    $realnameInfo['uid']=Auth::user()->id;
//                    $realnameInfo['created_at']=date("Y-m-d H:i:s");
//                    $realnameInfo['updated_at']=date("Y-m-d H:i:s");
//                    $enterpriseAuth=EnterpriseAuthModel::where("uid",Auth::user()->id)->first();
//                    //dd($realnameInfo,$request->);
//                    if(!$enterpriseAuth){
//                        EnterpriseAuthModel::create($realnameInfo);
//                    }
                //提交认证申请
                $shopUpgrade=ShopUpgradeModel::where("uid",Auth::user()->id)->where("shop_id",$request->get("id"))->first();
                if($shopUpgrade){
                    $res=ShopUpgradeModel::where("uid",Auth::user()->id)->where("shop_id",$request->get("id"))
                        ->update(["updated_at"=>date("Y-m-d H:i:s"),"status"=>0]);
                }else{
                    $res=ShopUpgradeModel::create(
                        [
                            'shop_id'=>$request->get("id"),
                            'uid'=>Auth::user()->id,
                            'status'=>0,
                            'created_at'=>date("Y-m-d H:i;s"),
                        ]
                    );
                }
                return $request;
            });

        if($res){
            return back()->with(["message"=>"提交申请成功"]);
        }
            return back()->with(["message"=>"提交申请失败"]);
    }

    /*
     * 我的店铺装修
     * */
    //我的店铺装修
    public function decoration(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('店铺装修');
        $this->theme->set("userShop",5);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","店铺装修");
        $this->theme->set("userSecondColumnUrl","/user/shopDecoration");
        $shopTemplate=Auth::user()->shop_template;
        $shopTemplate=explode(",",$shopTemplate);
        $data=[
            'user'=>Auth::user(),
            'shopTemplate'=>$shopTemplate
        ];
      return $this->theme->scope('user.shop.decoration', $data)->render();
    }
    //我的店铺模板启用
    public function shopDecorationHandle($type){
        UserModel::where("id",Auth::user()->id)->update(['shop_template_stauts'=>$type]);
        return back()->with(["message"=>"开启成功"]);
    }
    //我的店铺模板装修
    public function shopDecoration($id){
        $uid = Auth::User()->id;
        $shopInfo=ShopModel::shopOneInfo($uid);
        switch($id){
            case 2:
                $shop=ShopOneModel::where("uid",$uid)->first();
                $type=2;
                break;
            case 3:
                $shop=ShopTwoModel::where("uid",$uid)->first();
                $type=3;
                break;
        }
        $articleList=[];$consultList=[];
        if($shop){
            $shopInfo->shop_pic=$shop['logo_pic'];
            $shopInfo->nav_pic=$shop['nav_pic'];
            $shopInfo->nav_open=explode(',',$shop['nav_open']);
            $shop['nav_open']=explode(',',$shop['nav_open']);
            if(!empty($shop['consult'])){
                $consultList=explode(',',$shop['consult']);
                $articleList=ArticleModel::select("id","pic","title","summary")->whereIn("id",$consultList)->get();
            }
        }else{
            $shopInfo->nav_pic=null;
            $shopInfo->nav_open=[0,1,2,3];
            $shop['nav_open']=[0,1,2,3];
        }
        //获取用户的权限
        $getConfigByUid=UserVipConfigModel::getConfigByUid($uid);
        $shopInfo['is_logo']=$getConfigByUid['is_logo'];
        $shopInfo['is_show']=$getConfigByUid['is_show'];
        $shopInfo['is_nav']=$getConfigByUid['is_nav'];
        $shopInfo['is_slide']=$getConfigByUid['is_slide'];
        $shopInfo['service_company']=json_decode($shopInfo['service_company']);
        $this->theme->set("shopInfo",$shopInfo);
        $servieList=ShopDecorateConfigModel::where("type",1)->where("status",$type)->where("uid",$uid)->get();
        $caseList=ShopDecorateConfigModel::where("type",2)->where("status",$type)->where("uid",$uid)->get();
        $bannerList=ShopDecorateConfigModel::where("type",3)->where("status",$type)->where("uid",$uid)->orderBy('sort','asc')->get();
        $teamList=ShopDecorateConfigModel::where("type",4)->where("status",$type)->where("uid",$uid)->get();
        $companyList=ShopDecorateConfigModel::where("type",5)->where("status",$type)->where("uid",$uid)->get();
        $data=[
            'shopInfo'=>$shopInfo,
            'type'=>$type,
            'shop'=>$shop,
            'servieList'=>$servieList,
            'caseList'=>$caseList,
            'bannerList'=>$bannerList,
            'teamList'=>$teamList,
            'companyList'=>$companyList,
            'articleList'=>$articleList,
            'consultList'=>json_encode($consultList)
        ];
        if($type ==2){
            $this->initTheme('shopfitment');
            return $this->theme->scope('user.shop.shopDecoration',$data)->render();
        }else{
            $this->initTheme('edittemplatemain');
            return $this->theme->scope('user.shop.shopDecorationTwo',$data)->render();
        }


    }
    //店铺装修数据提交
    public function shopDecorationPost(Request $request){
         //$data=$request->except("_token");
         if($request->get('logo_pic')){
             $data['logo_pic']=$request->get('logo_pic');
         }
        if($request->get('nav_pic')){
            $data['nav_pic']=$request->get('nav_pic');
        }
        if($request->get('nav_open')){
            $data['nav_open']=implode(',',json_decode($request->get('nav_open')));
        }
        if($request->get('about_us')){
            $data['about_us']=$request->get('about_us');
        }
        if($request->get('about_pic1')){
            $data['about_pic1']=$request->get('about_pic1');
        }
        if($request->get('about_pic2')){
            $data['about_pic2']=$request->get('about_pic2');
        }
        if($request->get('consult')){
            $data['consult']=implode(',',json_decode($request->get('consult')));
        }
        $data['uid']=Auth::user()->id;
        $data['update_at']=date('Y-m-d H:i:s');
        $res=DB::transaction(function()use($request,$data){
            switch($request->get('type')){
                case 2:
                    $shopOne=ShopOneModel::where("uid",Auth::user()->id)->first();
                    if($shopOne){
                        ShopOneModel::where("uid",Auth::user()->id)->where('id',$shopOne['id'])->update($data);
                     }else{
                        $data['created_at']=date('Y-m-d H:i:s');
                        ShopOneModel::create($data);
                    }

                    break;
                case 3:
                    $shopTwo=ShopTwoModel::where("uid",Auth::user()->id)->first();
                    if($shopTwo){
                        ShopTwoModel::where("uid",Auth::user()->id)->where('id',$shopTwo['id'])->update($data);
                    }else{
                        $data['created_at']=date('Y-m-d H:i:s');
                        ShopTwoModel::create($data);
                    }
                    break;
            }
            ShopDecorateConfigModel::postData($request);
            return ;
        });
        return back()->with(["message"=>"发布成功"]);
    }
    //获取咨询
    public function getConsult(Request $request){
        $this->initTheme('ajaxpage');
        switch($request->get("type")){
            case 2:
                $consult=ShopOneModel::where("uid",Auth::user()->id)->pluck("consult");
                break;
            case 3:
                $consult=ShopTwoModel::where("uid",Auth::user()->id)->pluck("consult");
                break;
        }
        $consultList=explode(',',$consult);
        if($request->get("check_id")){
            $consultList=json_decode($request->get("check_id"));
        }
        $data=[
            'consultList'=>$consultList,
            'title'=>$request->get('title'),
            'type'=>$request->get('page'),
        ];
        $article=ArticleModel::where("user_id",Auth::user()->id)->where("status",1);
        if($request->get('title')){
            $article=$article->where("title","like","%".$request->get('title')."%");
        }
        $article=$article->select("id","title","status","created_at")->paginate(5)->setPath('/user/getConsult');
        $view = [
            'article'=>$article,
            'merge' => $data,
        ];
        return $this->theme->scope('/user/shop/consult', $view)->render();
    }
    /*
     * 获取咨询信息
     *
     * */
    public function getConsultAll(Request $request){
        $this->initTheme('ajaxpage');
        $consultid=[];
        if($request->get("check_id")){
            $consultid=json_decode($request->get("check_id"));
        }
        $article=ArticleModel::select("id","title","pic","summary")->whereIn('id',$consultid)->get();
        //return json_encode($article);
        $view = [
            'article'=>$article,
        ];
        return $this->theme->scope('/user/shop/getConsultAll', $view)->render();
    }
    /*
     * 店铺技能标签设置
     * */
    public  function shopLabel(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('标签设置');
        $this->theme->set("userShop",6);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","标签设置");
        $this->theme->set("userSecondColumnUrl","/user/shopLabel");
        $uid = Auth::User()->id;
        //查询应用领域
        $apply=CateModel::where("type",1)->select("id","name")->get()->toArray();
        //技能标签
        $skillCate=CateModel::where("type",2)->select("id","name")->get()->toArray();
        $shopInfo = ShopModel::getShopInfoByUid($uid);
            //获取店铺的技能标签
        $shopApply=ShopTagsModel::leftJoin("cate","tag_shop.cate_id",'=',"cate.id")->where("tag_shop.shop_id",$shopInfo['id'])->where("tag_shop.type",1)->select("cate.id","cate.name")->get()->toArray();
         $shopApply=\CommonClass::setArrayKey($shopApply,"id");
        $shopSkill=ShopTagsModel::leftJoin("cate","tag_shop.cate_id",'=',"cate.id")->where("tag_shop.shop_id",$shopInfo['id'])->where("tag_shop.type",2)->select("cate.id","cate.name")->get()->toArray();
        $shopSkill=\CommonClass::setArrayKey($shopSkill,"id");
        //获取用户vip权限信息
        $userVipConfig=UserVipConfigModel::getConfigByUid($uid);
        //获取当前会员的名称
        if(Auth::user()->level == 1){
            $member_name="普通会员";
        }else{
            $member_name=VipModel::where("grade",Auth::user()->level)->pluck("name");
        }
        $data = array(
            'apply'=>$apply,
            'skillCate'=>$skillCate,
            'shopInfo'   =>$shopInfo,
            'shopApply'=>$shopApply,
            'shopSkill'=>$shopSkill,
            'userVipConfig'=>$userVipConfig,
            'member_name'=>$member_name,
        );
        return $this->theme->scope('user.shop.shopLabel',$data)->render();
    }
    /*
     * 店铺标签提交
     * */
    public function shopLabelPost(Request $request){
        $data=$request->except("_token");
        $uid = Auth::User()->id;
        //获取用户vip权限信息
        $userVipConfig=UserVipConfigModel::getConfigByUid($uid);
        if(!empty($data['apply'])){
            $data['apply']=json_decode($data['apply']);
            if(count($data['apply']) >$userVipConfig['appliy_num']){
                return back()->with(["message"=>"标签数量设置超标，解锁更多请购买vip"]);
            }
            ShopTagsModel::where('shop_id',$data['id'])->where("type",1)->delete();
            foreach($data['apply'] as $value){
                $tagData = array(
                    'shop_id' => $data['id'],
                    'cate_id' => intval($value),
                    'type'=>1,
                );
                ShopTagsModel::create($tagData);
            }
        }
        if(!empty($data['skillCate'])){
            if(count($data['skillCate']) >$userVipConfig['skill_num']){
                return back()->with(["message"=>"标签数量设置超标，解锁更多请购买vip"]);
            }
            $data['skillCate']=json_decode($data['skillCate']);
            ShopTagsModel::where('shop_id',$data['id'])->where("type",2)->delete();
            foreach($data['skillCate'] as $value){
                $tagData = array(
                    'shop_id' => $data['id'],
                    'cate_id' => intval($value),
                    'type'=>2,
                );
                ShopTagsModel::create($tagData);
            }
        }
         return back()->with(["message"=>"操作成功"]);
    }
    /*
     * 方案管理
     * */
    public function shopProgramme(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('方案管理');
        $this->theme->set("userShop",7);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","方案管理");
        $this->theme->set("userSecondColumnUrl","/user/programme");
        $list=GoodsModel::where("uid",Auth::user()->id)->where('is_delete',0);
        if($request->get("title")){
            $list=$list->where("title","like","%".trim($request->get("title"))."%");
        }
        $list=$list->orderBy('sort',"asc")->orderBy("id","desc")->paginate(10);
        //获取用户的权限
        $getConfigByUid=UserVipConfigModel::getConfigByUid(Auth::user()->id);
        //获取该用户方案已上传的个数
        $goodsNum=GoodsModel::where("uid",Auth::user()->id)->count();
        $data=[
            'list'=>$list,
            'merge'=>["title"=>$request->get("title")?$request->get("title"):null],
            'goodsNum'=>$goodsNum,
            'getConfigByUid'=>$getConfigByUid
        ];
        return $this->theme->scope('user.shop.shopProgramme',$data)->render();
    }
    /*
     * 方案管理操作
     * */
    public function shopProgrammeHandle($id,$action){
        $goods=GoodsModel::where("id",$id)->where("uid",Auth::user()->id)->first();
        if(!$goods){
            return back()->with(["message"=>"该咨询不存在"]);
        }
        $message="操作成功";
        switch($action){
            case "up":
                $res=GoodsModel::where("id",$id)->where("uid",Auth::user()->id)->update(["status"=>0]);
                $message="上架申请已提交";
                break;
            case "down":
                $res=GoodsModel::where("id",$id)->where("uid",Auth::user()->id)->update(["status"=>2]);
                $message="下架成功";
                break;
        }
        if($res){
            return back()->with(["message"=>$message]);
        }
        return back()->with(["message"=>"操作失败"]);
    }
    /*
     * 我的方案添加
     *
     * */
    public function shopProgrammeAdd()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('上传方案');
        $this->theme->set("userShop",15);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","方案管理");
        $this->theme->set("userSecondColumnUrl","/user/programmeAdd");
        //判断是否开通店铺
        $findShop=ShopModel::where("uid",Auth::user()->id)->where("status",1)->first();
        if(!$findShop){
            return redirect("/user/shop")->with(["message"=>"你还未申请开通店铺,请先开通店铺再上传方案"]);
        }
        //.上传方案前如果是个人店铺，判断用户是否进行实名认证->无认证，先认证，再上传
        if(isset($findShop['type'])&&!empty($findShop['type'])){
            if($findShop['type']==1){

                //获取个人认证信息
                $realnameInfo = RealnameAuthModel::where('uid',Auth::user()->id)->first();
                if(empty($realnameInfo)){
                    return redirect("/user/realnameAuth")->with(["message"=>"你还未进行实名认证,请先进行实名认证再上传方案"]);
                }
            }
        }
        $cate = TaskCateModel::whereIn('type',[1,2,3,4])->get()->toArray();
        $cate = \CommonClass::setArrayKey($cate,'type',2);
        //应用领域
        $field = in_array(1,array_keys($cate)) ? $cate[1] : [];
        //开发平台
        $plate = in_array(3,array_keys($cate)) ? $cate[3] : [];
        $this->theme->set('ALL_PLATE',$plate);
        //交付形式
        $delivery = in_array(4,array_keys($cate)) ? $cate[4] : [];
        $this->theme->set('DELIVERY',$delivery);
        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];
        $data = [
            'field'      => $field,
            'plate'      => $plate,
            'delivery'   => $delivery,
            'type_arr'   => $typeArr,
        ];
        return $this->theme->scope('user.shop.shopProgrammeAdd',$data)->render();
    }

    /*
     * 方案提交post
     * */
    public function shopProgrammePost(Request $request)
    {
        $data = $request->except('_token');
        $deliveryCateIdStr = '';
        if(isset($data['delivery_cate_id']) && $data['delivery_cate_id']){
            if(is_array($data['delivery_cate_id'])){
                $deliveryCateIdStr = implode(',',$data['delivery_cate_id']);
            }else{
                $deliveryCateIdStr = $data['delivery_cate_id'];
            }
        }
        $arr = [
            'title'                 => $data['title'],
            'cate_id'               => isset($data['cate_id'])?$data['cate_id']:1,
            'type'                  => isset($data['type'])?$data['type']:1,
            'is_customized'         => isset($data['type']) && $data['type'] == 1 && isset($data['is_customized']) ? $data['is_customized'] : 0,
            'ide_cate_id'           => isset($data['ide_cate_id']) && $data['ide_cate_id'] ? $data['ide_cate_id'] : '',
            'ide_desc'              => isset($data['ide_desc']) && $data['ide_desc'] ? $data['ide_desc'] : '',
            'cash'                  => isset($data['type']) && $data['type'] == 1 && isset($data['cash']) ? $data['cash'] : '0.00',
            'application_scene'     => isset($data['application_scene'])?$data['application_scene']:'',
            'performance_parameter' => isset($data['performance_parameter'])?$data['performance_parameter']:'',
            'delivery_cate_id'      => $deliveryCateIdStr,
            'freight'               =>isset($data['freight'])?$data['freight']:0,
            'status'                => 0,
            'is_recommend'          => 0,
            'cover'                 => isset($data['is_default']) && $data['is_default'] ? $data['is_default'] : '',
            'sort'                  => isset($data['sort']) && $data['sort'] ? $data['sort'] : 0,
        ];
        if($request->get('desc')){
            $arr['desc']=$request->get('desc');
        }
        $fileIds = isset($data['file_ids']) && $data['file_ids'] ? $data['file_ids'] : [];
        if(!$request->get('id')){
            $uid = Auth::id();
            $shopId = 0;
            $shop = ShopModel::where('uid',$uid)->first();
            if($shop){
                $shopId = $shop->id;
            }
            $arr['uid']=$uid;$arr['shop_id']=$shopId;
            $times = GoodsModel::where('uid',Auth::id())->where('created_at','>=',date('Y-m-d 00:00:00'))->where('created_at','<=',date('Y-m-d H:i:s'))->count();
            $vipConfig = UserVipConfigModel::getConfigByUid(Auth::id());
            $maxTimes = $vipConfig['scheme_num'];
            if($times >= $maxTimes){
              //  return redirect()->back()->with(array('message' => '今日发布方案次数使用完毕'));
            }
        }else{
            $arr['id']=$data['id'];
        }
        $res = GoodsModel::saveGoodsInfo($arr,$fileIds,[],$request);
        if($res){
            return redirect('/user/programme')->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }
    /*
     *方案详情
     * */
    public function shopProgrammeInfo($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle('方案详情');
        $this->theme->set("userShop",7);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","方案管理");
        $this->theme->set("userSecondColumnUrl","/user/programmeAdd");
        //查询方案信息
        $id = intval($id);
        $goodsInfo = GoodsModel::getGoodsInfoById($id);
        $idsCateIdArr = explode(',',$goodsInfo->ide_cate_id);
        $deliveryCateIdArr = explode(',',$goodsInfo->delivery_cate_id);

        $cate = TaskCateModel::whereIn('type',[1,2,3,4])->get()->toArray();
        $cate = \CommonClass::setArrayKey($cate,'type',2);
        //应用领域
        $field = in_array(1,array_keys($cate)) ? $cate[1] : [];
        //技能标签
        $skill = in_array(2,array_keys($cate)) ? $cate[2] : [];
        //开发平台
        $plate = in_array(3,array_keys($cate)) ? $cate[3] : [];
        //交付形式
        $delivery = in_array(4,array_keys($cate)) ? $cate[4] : [];
        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];
        $attachment = UnionAttachmentModel::where('object_id',$id)->where('object_type',5)->leftJoin('attachment','attachment.id','=','union_attachment.attachment_id')->select('attachment.*')->get()->toArray();
        $attachment = \CommonClass::setArrayKey($attachment,'id');
        //.性能参数处理
        if(isset($goodsInfo['performance_parameter']) && !empty($goodsInfo['performance_parameter']) ){
            if(strpos($goodsInfo['performance_parameter'],',')!==false){
                $goodsInfo['performance_parameter']= explode(',',$goodsInfo['performance_parameter']);
            }
        }
        //.交付形式处理
        if(isset($goodsInfo['delivery_cate_id']) && !empty($goodsInfo['delivery_cate_id']) ){
            $goodsInfo['delivery_cate_id']= explode(',',$goodsInfo['delivery_cate_id']);
        }
        $data = array(
            'goods_info' => $goodsInfo,
            'field'      => $field,
            'skill'      => $skill,
            'plate'      => $plate,
            'delivery'   => $delivery,
            'type_arr'   => $typeArr,
            'attachment' => $attachment,
            'idsCateIdArr' => $idsCateIdArr,
            'deliveryCateIdArr' => $deliveryCateIdArr
        );
        return $this->theme->scope('user.shop.shopProgrammeInfo',$data)->render();
    }
    /*
     * 我的咨询管理
     * */
    public function shopConsult(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle('文章管理');
        $this->theme->set("userIndex",8);
        $this->theme->set("userOneColumn","文章管理");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","文章管理");
        $this->theme->set("userSecondColumnUrl","/user/consult");
        $list = ArticleModel::where("user_id",Auth::user()->id);
        if($request->get("title")){
            $list=$list->where("title","like","%".trim($request->get("title"))."%");
        }
        $list=$list->orderBy("id","desc")->paginate(10);
        $data=[
            'list'=>$list,
            'merge'=>["title"=>$request->get("title")?$request->get("title"):null],
        ];
        return $this->theme->scope('user.shop.shopConsult',$data)->render();
    }
    /*
     * 咨询管理添加
     * */
    public function shopConsultAdd(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle('发布文章');
        $this->theme->set("userIndex",17);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","发布文章");
        $this->theme->set("userSecondColumnUrl","/user/consultAdd");
        //资讯分类
        $m = ArticleCategoryModel::get()->toArray();
        $res = ArticleCategoryModel::_reSort($m,1);
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //获取用户vip配置
        $userVipConfigModel=UserVipConfigModel::getConfigByUid(Auth::user()->id);
        //询价
        $newsprice=ConfigModel::where("alias","news_price")->first();
        $vipsprice = round($newsprice['rule'] * $userVipConfigModel['consult_discount']/10,2);
        $data=[
            'category'=>$res,
            'application'=>$application,
            'newsprice'=>$newsprice,
            'vipsprice'=>$vipsprice

        ];
        return $this->theme->scope('user.shop.shopConsultAdd',$data)->render();
    }
    /*
     * 咨询管理数据添加
     * */
    public function shopConsultAddPost(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data = $request->except('_token');
        $is_free=$data['is_free']?$data['is_free']:1;
        if ($is_free==1){
            $arr = [
                'cat_id'    => isset($data['cat_id']) ? $data['cat_id'] : '',
                'title'     =>isset($data['title']) ? $data['title'] : '',
                'user_id'   => Auth::user()->id,
                'author'    =>isset($data['author']) ? $data['author'] : '',
                'cate_id'   =>isset($data['cate_id']) ? $data['cate_id'] : '',
                'content'   =>isset($data['content']) ? $data['content'] : '',
                'summary'   =>isset($data['content']) ?  mb_substr($data['content'],0,150,'utf-8') : '',
                'pic'       =>isset($data['pic']) ? $data['pic'] : '',
                'from'      => '2',
                'price'     => 0,
                'status'    => 0 ,
                'is_free'    => 1,
                'created_at'=> date("Y-m-d H:i:s"),
                'online_time'=> date("Y-m-d H:i:s"),
            ];
            $res = ArticleModel::create($arr);
            if ($res){
                return redirect()->to('/user/consult')->with(["message"=>"发布成功"]);
            }else{
                return redirect('/user/consultAdd')->with(array('message'=>'未知错误！'));
            }
        }else {
            //获取用户vip配置
            $userVipConfigModel=UserVipConfigModel::getConfigByUid(Auth::user()->id);
            $newsprice=ConfigModel::where("alias","news_price")->first();
            $arr = [
                'cat_id'    => isset($data['cat_id']) ? $data['cat_id'] : '',
                'title'     => isset($data['title']) ? $data['title'] : '',
                'user_id'   => Auth::user()->id,
                'author'    => isset($data['author']) ? $data['author'] : '',
                'cate_id'   => isset($data['cate_id']) ? $data['cate_id'] : '',
                'content'   => isset($data['content']) ? $data['content'] : '',
                'pic'       => isset($data['pic']) ? $data['pic'] : '',
                'from'      => '2',
                'price'     => round($newsprice['rule'] * $userVipConfigModel['consult_discount']/10,2),
                'status'    => 4,
                'is_free'    => 2,
                'created_at'=> date("Y-m-d H:i:s"),
            ];
            if(isset($data['inlineRadio']) && $data['inlineRadio'] == 2){
                $arr['articlefrom'] = '2';
                $arr['reprint_url'] = isset($data['reprint_url']) ? $data['reprint_url'] : '';
            }
            $res = ArticleModel::create($arr);
            if($res){
                //SeoModel::createSeo($data['title'],3,$res['id']);
                return redirect("/user/consultPay/".$res['id']);
            }
            return redirect('/user/consult')->with(array('message'=>'未知错误！'));
        }
    }
    /*
     * 咨询付费
     *
     * *
     * /
     *
     */
    public function shopConsultPay($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle('文章管理支付');
        $this->theme->set("userIndex",17);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","发布文章");
        $this->theme->set("userSecondColumnUrl","/user/consultAdd");
        $userDetial=UserDetailModel::where("uid",Auth::user()->id)->first();
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        //获取咨询内容
        $article=ArticleModel::find($id);
        $balance_pay = false;
        if ($userDetial['balance'] >= $article['price']) {
            $balance_pay = true;
        }
        //根据金额获取对应的优惠券
        $userCoupon=UserCouponModel::getCoupon($article['price'],[0,6]);
        $data=[
            'totalPrice'=>$article['price'],
            'userDetail'=>$userDetial,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'article'=>$article,
            'userCoupon'=>$userCoupon,
        ];
        return $this->theme->scope('user.shop.shopConsultPay',$data)->render();
    }
    /*
     * 咨询管理详情
     * */
    public function shopConsultInfo($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle('文章管理详情');
        $this->theme->set("userIndex",8);
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/shop");
        $this->theme->set("userSecondColumn","文章管理");
        $this->theme->set("userSecondColumnUrl","/user/consult");
        //查询咨询
        $article=ArticleModel::find($id);
        //资讯分类
        $m = ArticleCategoryModel::get()->toArray();
        $res = ArticleCategoryModel::_reSort($m,1);
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //查询会员折扣
//        $zhekou = '1';
//        if(Auth::user()->id){
//            $users = Auth::user()->id;
//            $date = date("Y-m-d H:i:s",time());
//            $zhekouarr = VipUserOrderModel::where("uid",$users)->where("status",1)->where("end_time",">",$date)->first();   //用户会员等级
//            if($zhekouarr){
//                $zhekou = VipConfigModel::join("vip","vip.vipconfigid","=","vip_config.id")->where("vip.id",$zhekouarr['vipid'])->first()->appreciation_zixun;
//            }
//        }
//        //询价
//        $newsprice=ConfigModel::where("alias","news_price")->first();
//        $newsprice = sprintf('%.2f',floatval($newsprice['rule'] * $zhekou));
        $data=[
            'category'=>$res,
            'application'=>$application,
            //'newsprice'=>$newsprice,
            'article'=>$article,

        ];
        return $this->theme->scope('user.shop.shopConsultInfo',$data)->render();
    }
    /*
     * 咨询管理操作
     * */
    public function shopConsultHandle($id,$action){
        $article=ArticleModel::where("id",$id)->where("user_id",Auth::user()->id)->first();
        if(!$article){
            return back()->with(["message"=>"该咨询不存在"]);
        }
        switch($action){
            case "up":
                $res=ArticleModel::where("id",$id)->where("user_id",Auth::user()->id)->update(["status"=>1]);
                break;
            case "down":
                $res=ArticleModel::where("id",$id)->where("user_id",Auth::user()->id)->update(["status"=>3]);
                break;
        }
        if($res){
            return back()->with(["message"=>"操作成功"]);
        }
            return back()->with(["message"=>"操作失败"]);
    }
    /**
     * ajax获取城市、地区数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetCity(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $province = DistrictModel::findTree($id);
        if($province){
            $area = DistrictModel::findTree($province[0]['id']);
        }else{
            $area = array();
        }
        $data = [
            'province' => $province,
            'area' => $area
        ];
        return response()->json($data);
    }

    /**
     * ajax获取地区数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetArea(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $area = DistrictModel::findTree($id);
        $data = [
            'area' => $area,
        ];
        return response()->json($data);
    }

    /**
     * 企业认证文件上传控制
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileUpload(Request $request)
    {
        $file = $request->file('file');
        //将文件上传的数据存入到attachment表中
        $attachment = \FileClass::uploadFile($file, 'user');
        $attachment = json_decode($attachment, true);
        //判断文件是否上传
        if($attachment['code']!=200)
        {
            return response()->json(['errCode' => 0, 'errMsg' => $attachment['message']]);
        }
        $attachment_data = array_add($attachment['data'], 'status', 1);
        $attachment_data['created_at'] = date('Y-m-d H:i:s', time());
        //将记录写入到attchement表中
        $result = AttachmentModel::create($attachment_data);
        $result = json_decode($result, true);
        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => '文件上传失败！']);
        }
        //回传附件id
        return response()->json(['id' => $result['id']]);
    }

    /**
     * 附件删除
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileDelete(Request $request)
    {
        $id = $request->get('id');
        //查询当前的附件
        $file = AttachmentModel::where('id',$id)->first()->toArray();
        if(!$file)
        {
            return response()->json(['errCode' => 0, 'errMsg' => '附件没有上传成功！']);
        }
        //删除附件
        unlink($file['url']);
        $result = AttachmentModel::destroy($id);
        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => '删除失败！']);
        }
        return response()->json(['errCode' => 1, 'errMsg' => '删除成功！']);
    }

    /**
     * 企业认证视图(根据认证状态视图不同) 0=>待验证  1=>成功  2=>失败
     * @return mixed
     */
    public function getEnterpriseAuth()
    {
        $user = Auth::User();
        $companyInfo = EnterpriseAuthModel::where('uid', $user->id)->orderBy('created_at', 'desc')->first();

        //一级行业
        $cateFirst = TaskCateModel::findByPid([0],['id','name']);
        if(!empty($cateFirst)){
            //二级行业
            $cateSecond = TaskCateModel::findByPid([$cateFirst[0]['id']],['id','name']);
        }else{
            $cateSecond = array();
        }
        //查询地区一级数据
        $province = DistrictModel::findTree(0);
        if(!empty($province)){
            //查询地区二级信息
            $city = DistrictModel::findTree($province[0]['id']);
            if(!empty($city)){
                //查询地区三级信息
                $area = DistrictModel::findTree($city[0]['id']);
            }else{
                $area = array();
            }
        }else{
            $city = array();
            $area = array();
        }
        $view = '';
        if (isset($companyInfo->status)) {
            $cateInfo = TaskCateModel::findById($companyInfo->cate_id);
            if($cateInfo){
                $cateName = $cateInfo['name'];
            }else{
                $cateName = '';
            }
            $data = array(
                'company_info' => $companyInfo,
                'cate_name' => $cateName,
            );
            switch ($companyInfo->status) {
                case 0:
                    $view = 'user.waitusershopauth';
                    break;
                case 1:
                    return redirect('/user/shop');
                    break;
                case 2:
                    $view = 'user.usershopauthfail';
                    break;
            }
        } else {
            $data = array(
                'cate_first'  => $cateFirst,
                'cate_second' => $cateSecond,
                'province'    => $province,
                'city'        => $city,
                'area'        => $area
            );
            $view = 'user.usershopqy';
        }
        $this->theme->setTitle('企业认证');
        $this->theme->set('TYPE',3);
        return $this->theme->scope($view, $data)->render();
    }

    /**
     * 保存企业认证信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postEnterpriseAuth(Request $request)
    {
        $data = $request->except('_token');
        $uid = Auth::id();
        $companyInfo = array(
            'uid'              => $uid,
            'company_name'     => $data['company_name'] ? $data['company_name'] : '',
            'cate_id'          => $data['cate_second'] ? $data['cate_second'] : '' ,
            'employee_num'     => $data['employee_num'] ? $data['employee_num'] : '',
            'business_license' => $data['business_license'] ? $data['business_license'] : '',
            'begin_at'         => $data['start'] ? date('Y-m-d H:i:s',strtotime($data['start'])) : '',
            'website'          => $data['website'] ? $data['website'] : '',
            'province'         => $data['province'] ? $data['province'] : '',
            'city'             => $data['city'] ? $data['city'] : '',
            'area'             => $data['area'] ? $data['area'] : '',
            'address'          => $data['address'] ? $data['address'] : '',
            'status'           => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        );
        $authRecordInfo = array(
            'uid'       => $uid,
            'auth_code' => 'enterprise',
            'status'    => 0
        );
        $fileId = !empty($data['file_id']) ? $data['file_id'] : '';
        EnterpriseAuthModel::createEnterpriseAuth($companyInfo,$authRecordInfo,$fileId);
        return redirect('/user/enterpriseAuth');
    }

    /**
     * ajax获取二级行业分类
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetSecondCate(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $cate = TaskCateModel::findByPid([$id]);
        $data = [
            'cate' => $cate
        ];
        return response()->json($data);
    }

    /**
     * 重新认证
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function enterpriseAuthAgain()
    {
        $uid = Auth::user()->id;
        $status = EnterpriseAuthModel::getEnterpriseAuthStatus($uid);
        if($status == 2){
            $res = EnterpriseAuthModel::removeEnterpriseAuth();
            if($res){
                return redirect('/user/enterpriseAuth');
            }
        }
    }


    /**
     * 店铺的案例管理
     * @param Request $request
     * @return mixed
     */
    public function shopSuccessCase(Request $request)
    {
        $uid = Auth::id();
        //判断店铺是否开启
        $isOpenShop = ShopModel::isOpenShop($uid);
        //店铺id
        $shopId = ShopModel::getShopIdByUid($uid);
        $merge = $request->all();
        $uid = Auth::id();
        $successCase = SuccessCaseModel::getSuccessCaseListByUid($uid,$merge);
        $data = array(
            'success_list' => $successCase,
            'merge' => $merge,
            'is_open_shop' => $isOpenShop,
            'shop_id' => $shopId
        );
        $this->theme->setTitle('案例列表');
        $this->theme->set('TYPE',3);
        return $this->theme->scope('user.usershopalgl',$data)->render();
    }

    /**
     * 添加案例视图
     * @return mixed
     */
    public function addShopSuccess()
    {
        $uid = Auth::id();
        //查询店铺id
        $shopId = ShopModel::getShopIdByUid($uid);
        //判断店铺是否开启
        $isOpenShop = ShopModel::isOpenShop($uid);
        //一级行业
        $cateFirst = TaskCateModel::findByPid([0],['id','name']);
        if(!empty($cateFirst)){
            //二级行业
            $cateSecond = TaskCateModel::findByPid([$cateFirst[0]['id']],['id','name']);
        }else{
            $cateSecond = array();
        }
        $data = array(
            'cate_first'  => $cateFirst,
            'cate_second' => $cateSecond,
            'is_open_shop' => $isOpenShop,
            'shop_id' => $shopId
        );
        $this->theme->setTitle('添加案例');
        $this->theme->set('TYPE',3);
        return $this->theme->scope('user.usershopal',$data)->render();
    }

    /**
     * 保存添加案例信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postAddShopSuccess(Request $request)
    {
        $user = Auth::User();
        $file = $request->file('success_pic');
        if (!$file) {
            return redirect()->back()->with('error', '作品封面不能为空');
        }else{
            $result = \FileClass::uploadFile($file, 'sys');
            $result = json_decode($result, true);
            //判断文件是否上传
            if($result['code']!=200)
            {
                return redirect()->back()->with('error', $result['message']);
            }else{
                $pic = $result['data']['url'];
            }
        }
        if (!$request->cate_id) {
            return redirect()->back()->with('error', '作品分类不能为空');
        }

        $data = array(
            'pic'        => $pic,
            'uid'        => $user->id,
            'username'   => $user->name,
            'title'      => $request->title,
            'desc'       => \CommonClass::removeXss($request->description),
            'type'       => 1, //店铺添加类型为1
            'url'        => $request->url,
            'cate_id'    => $request->cate_id,
            'created_at' => date('Y-m-d H:i:s'),
        );
        $res = SuccessCaseModel::insert($data);
        if (!$res){
            return redirect()->back()->with('error', '成功案例添加失败！');
        }else{
            return redirect('/user/myShopSuccessCase')->with('massage', '成功案例添加成功！');
        }

    }

    /**
     * 编辑案例视图
     * @param $id 案例id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function editShopSuccess($id)
    {
        $id = intval($id);
        $uid = Auth::id();
        //查询该案例案例是否属于该用户
        $successInfo = SuccessCaseModel::getSuccessInfoById($id);
        //一级行业
        $cateFirst = TaskCateModel::findByPid([0],['id','name']);
        if(!empty($successInfo->cate_pid)){
            //二级行业
            $cateSecond = TaskCateModel::findByPid([$successInfo->cate_pid],['id','name']);
        }else{
            $cateSecond = TaskCateModel::findByPid([$cateFirst[0]['id']],['id','name']);
        }
        if($successInfo->uid == $uid){
            $data = array(
                'success_info' => $successInfo,
                'cate_first'   => $cateFirst,
                'cate_second'  => $cateSecond
            );
            $this->theme->setTitle('编辑案例');
            $this->theme->set('TYPE',3);
            return $this->theme->scope('user.usershopaledit',$data)->render();
        }else{
            return redirect()->back()->with('error', '案例错误！');
        }
    }

    /**
     * 编辑案例
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEditShopSuccess(Request $request)
    {
        $data = $request->except('_token');
        $successInfo = SuccessCaseModel::getSuccessInfoById($data['id']);
        $file = $request->file('success_pic');
        if (!$file) {
            $pic = $successInfo->pic;
        }else{
            $result = \FileClass::uploadFile($file, 'sys');
            $result = json_decode($result, true);
            //判断文件是否上传
            if($result['code']!=200)
            {
                return redirect()->back()->with('error', $result['message']);
            }else{
                $pic = $result['data']['url'];
            }
        }
        if (!$request->cate_id) {
            return redirect()->back()->with('error', '作品分类不能为空');
        }

        $arr = array(
            'pic'        => $pic,
            'title'      => $request->title,
            'desc'       => \CommonClass::removeXss($request->description),
            'url'        => $request->url,
            'cate_id'    => $request->cate_id,
        );
        $res = SuccessCaseModel::where('id',$data['id'])->update($arr);
        if (!$res){
            return redirect()->back()->with('error', '成功案例编辑失败！');
        }else{
            return redirect('/user/myShopSuccessCase')->with('massage', '成功案例编辑成功！');
        }
    }

    /**
     * 删除店铺案例
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteShopSuccess(Request $request)
    {
        $id = $request->get('id');
        $uid = Auth::id();
        //查询该案例案例是否属于该用户
        $successInfo = SuccessCaseModel::getSuccessInfoById($id);
        if($successInfo->uid == $uid){
            $res = SuccessCaseModel::where('id',$id)->delete();
            if($res){
                $data = array(
                    'code' => 1,
                    'msg' => '删除成功'
                );
            }else{
                $data = array(
                    'code' => 0,
                    'msg' => '删除失败'
                );
            }
        }else{
            $data = array(
                'code' => 0,
                'msg' => '参数错误'
            );
        }
        return  response()->json($data);
    }

    /**
     * 我收藏的店铺
     * @param Request $request
     * @return mixed
     */
    public function myCollectShop(Request $request)
    {
        $uid = Auth::id();
        $merge = $request->all();
        $collectArr = ShopFocusModel::where('uid',$uid)->orderby('created_at','DESC')->get()->toArray();
        $shopList = array();
        if(!empty($collectArr))
        {
            $shopIds = array();
            foreach($collectArr as $k => $v){
                $shopIds[] = $v['shop_id'];
            }
            if(!empty($shopIds)){
                $shopIds = array_unique($shopIds);
                $shopList = ShopModel::getShopListByShopIds($shopIds,$merge);
            }
        }
        $data = array(
            'shop_list' => $shopList,
            'merge' => $merge
        );

        $this->initTheme('usercenter');//主题初始化
        $this->theme->setTitle('我收藏的店铺');
        return $this->theme->scope('user.myshop',$data)->render();
    }
    /*
     * 收藏店铺
     * */
    public function focusShop(Request $request){
        $ShopId = $request->get('shopId');
        $uid = Auth::id();
        switch ($request->get('status')){
            case 0:
                $res = ShopFocusModel::where("uid",$uid)->where("shop_id",$ShopId)->delete();
                break;
            case 1:
                $res = ShopFocusModel::insert([
                    'uid'=>$uid,
                    'shop_id'=>$ShopId,
                    'created_at'=>date("Y-m-d H:i:s")
                ]);
                break;
        }

        if($res){
            $data = array(
                'code' => 1000,
                'msg' => '操作成功'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => '操作失败'
            );
        }
        return $data;
    }
    /**
     * 取消收藏店铺
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelCollect(Request $request)
    {
        $ShopId = $request->get('id');
        $uid = Auth::id();
        $res = ShopFocusModel::where('uid',$uid)->where('shop_id',$ShopId)->delete();
        if($res){
            $data = array(
                'code' => 1,
                'msg' => '操作成功'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => '操作失败'
            );
        }
        return  response()->json($data);
    }

    /*我的店铺*/
    public function myShopHint(Request $request)
    {
        return $this->theme->scope('user.myshophint')->render();
    }



    /*进入我的店铺中转链接*/
    public function switchUrl()
    {
        $uid = Auth::id();
        $this->theme->setUserId($uid);
        //判断用户是否实名认证
        $realName = RealnameAuthModel::where('uid',$uid)->where('status',1)->first();
        if(empty($realName)){
            return $this->theme->scope('user.usershopbefore')->render();
        }else{
            $shopInfo = ShopModel::where('uid',$uid)->first();
            if(empty($shopInfo)){
                return $this->theme->scope('user.myshophint')->render();
            }
            return redirect()->to('/shop/manage/'.$shopInfo['id']);
        }
    }


    /*实名认证提示*/
    public function userShopBefore(Request $request)
    {
        return $this->theme->scope('user.usershopbefore')->render();
    }

//我的vip
    public function myVip(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('VIP会员');
        $this->theme->set("userVip",1);
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/myVip");
        $this->theme->set("userSecondColumn","VIP会员");
        $this->theme->set("userSecondColumnUrl","/user/myVip");
        //获取vip列表
        $vipList=VipModel::getVipList();
        //.获取竞标卡
        $res=UserVipCardModel::getBidCard();
        //获取用户信息
        $userAvatar=UserDetailModel::where("uid",Auth::user()->id)->pluck("avatar");
        //获取所有的等级
        $level=VipModel::select("name","grade")->get()->toArray();
        $level=\CommonClass::setArrayKey($level,"grade");
        $data=[
            'vipList'=>$vipList,
            'userAvatar'=>$userAvatar,
            'user'=>Auth::user(),
            'level' =>$level,
            'biddingcardqt' =>$res['biddingcardqt'],
            'biddingcardby' =>$res['biddingcardby'],
            'biddingcardhj' =>$res['biddingcardhj'],
        ];
        return $this->theme->scope('user.myVip', $data)->render();
    }
    //我的vip
    public function myVipCard(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('竞标卡');
        $this->theme->set("userVip",2);
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/myVipCard");
        $this->theme->set("userSecondColumn","竞标卡");
        $this->theme->set("userSecondColumnUrl","/user/myVipCard");
        //获取vip列表
        $vipList=VipModel::getVipList();
        //.获取竞标卡
        $res=UserVipCardModel::getBidCard();
        //获取用户信息
        $userAvatar=UserDetailModel::where("uid",Auth::user()->id)->pluck("avatar");
        //获取所有的等级
        $level=VipModel::select("name","grade")->get()->toArray();
        $level=\CommonClass::setArrayKey($level,"grade");
        $data=[
            'vipList'=>$vipList,
            'userAvatar'=>$userAvatar,
            'user'=>Auth::user(),
            'level' =>$level,
            'biddingcardqt' =>$res['biddingcardqt'],
            'biddingcardby' =>$res['biddingcardby'],
            'biddingcardhj' =>$res['biddingcardhj'],
        ];
        return $this->theme->scope('user.myVipCard', $data)->render();
    }
    /**
     * vip购买页面
     */
    public function vipPay($id,$action){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('vip购买');
        if($action =="vip"){
            $this->theme->set("userVip",1);
            $this->theme->set("userOneColumn","我的VIP");
            $this->theme->set("userOneColumnUrl","/user/myVip");
            $this->theme->set("userSecondColumn","VIP会员");
            $this->theme->set("userSecondColumnUrl","/user/myVip");
        }else{
            $this->theme->set("userVip",2);
            $this->theme->set("userOneColumn","我的竞标卡");
            $this->theme->set("userOneColumnUrl","/user/myVipCard");
            $this->theme->set("userSecondColumn","竞标卡");
            $this->theme->set("userSecondColumnUrl","/user/myVipCard");
        }
        // UserModel::sendFreeVipCoupon(Auth::user()->id,13);//.自动发放
        $userDetail=UserDetailModel::where("uid",Auth::user()->id)->first();
        $totalPrice=0;
        switch($action){
            case "vip":
                $totalPrice=VipModel::where("id",$id)->pluck("price");
                break;
            case "cika":
                $totalPrice=VipConfigModel::where("id",$id)->pluck("vip_cika_price");
                break;
            case "rika":
                $totalPrice=VipConfigModel::where("id",$id)->pluck("vip_rika_price");
                break;
        }
        //查询用户绑定的银行卡信息
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userDetail['balance'] >= $totalPrice) {
            $balance_pay = true;
        }
        //根据金额获取对应的优惠券
        // .如果是vip购买就是2,2:vip会员  竞标卡购买就是3,3:竞标卡
        if ($action=='vip'){
            $userCoupon=UserCouponModel::getCoupon($totalPrice,[0,2]);
        }elseif($action=='cika' || $action=='rika' ){
            $userCoupon=UserCouponModel::getCoupon($totalPrice,[0,3]);
        }
        $data=[
            'totalPrice'    => $totalPrice,
            'userDetail'    => $userDetail,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'id'            => $id,
            'action'        => $action,
            'userCoupon'    => $userCoupon
        ];
        return $this->theme->scope('user.vipPay', $data)->render();
    }
    /*
     * vip购买数据提交
     * */
    public function vipPayPost(Request $request){
        $this->initTheme('personalindex');
        $data = $request->except('_token');
        $prefix="vp";$money=0;
        switch ($request->get("action")){
            case "vip":
                $this->theme->set("userColumnLeft","userVip");
                $this->theme->setTitle('VIP卡片');
                $this->theme->set("userVip",2);
                $this->theme->set("userOneColumn","myVip");
                $this->theme->set("userOneColumnUrl","/user/myVip");
                $this->theme->set("userSecondColumn","VIP套餐");
                $this->theme->set("userSecondColumnUrl","/user/myVip");
                $prefix="vp";
                $vip=VipModel::select("id","grade","price","price_time","name")->where("id",$data['order_id'])->first();
                $money=$vip['price'];
                break;
            case "cika":
                $this->theme->set("userColumnLeft","userVip");
                $this->theme->setTitle('VIP卡片');
                $this->theme->set("userVip",2);
                $this->theme->set("userOneColumn","myVip");
                $this->theme->set("userOneColumnUrl","/user/myVip");
                $this->theme->set("userSecondColumn","VIP卡片");
                $this->theme->set("userSecondColumnUrl","/user/myVipCard");
                $prefix="ck";
                $vip=VipConfigModel::leftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.id",$data['order_id'])->select("vip_config.id","vip.grade","vip_config.vip_cika_price","vip.name")->first();
                $money=$vip['vip_cika_price'];
                break;
            case "rika":
                $this->theme->set("userColumnLeft","userVip");
                $this->theme->setTitle('myVip');
                $this->theme->set("userVip",1);
                $this->theme->set("userOneColumn","myVip");
                $this->theme->set("userOneColumnUrl","/user/myVip");
                $this->theme->set("userSecondColumn","VIP卡片");
                $this->theme->set("userSecondColumnUrl","/user/myVipCard");
                $prefix="rk";
                $vip=VipConfigModel::leftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.id",$data['order_id'])->select("vip_config.id","vip.grade","vip_config.vip_rika_price","vip.name")->first();
                $money=$vip['vip_rika_price'];
                break;
            case "deposit"://保证金
                $this->theme->set("userColumnLeft","userIndex");
                $this->theme->setTitle("我的保证金");
                $this->theme->set("userIndex","10");
                $this->theme->set("userOneColumn","我是服务商");
                $this->theme->set("userOneColumnUrl","/user/myjointask");
                $this->theme->set("userSecondColumn","我的保证金");
                $this->theme->set("userSecondColumnUrl","/user/myDeposit");
                $prefix="dt";
                $vip=ConfigModel::where("alias","deposit")->where("type","site")->first();
                $money=$vip['rule'];
                break;
            case "tool"://购买工具
                $this->theme->set("userColumnLeft","tool");
                $this->theme->setTitle('工具箱');
                $this->theme->set("tool",1);
                $this->theme->set("userOneColumn","工具箱");
                $this->theme->set("userOneColumnUrl","/user/toolAll");
                $this->theme->set("userSecondColumn","工具箱列表");
                $this->theme->set("userSecondColumnUrl","/user/toolAll");
                $prefix="tl";
                $vip=ServiceModel::find($data['order_id']);
                $toolZK=10;
                if(Auth::user()->level >1){
                    $discount=VipModel::getDiscount(Auth::user()->level);
                    $discountArr=json_decode($discount,true);
                    if($discountArr && isset($discountArr[$vip['id']])){
                        $toolZK=$discountArr[$vip['id']];
                    }
                }
                $money=round($vip['price']*$toolZK/10,2);
                break;
            case "service":
                $this->theme->set("userColumnLeft","userIndex");
                $this->theme->set("userIndex","6");
                $this->theme->set("userOneColumn","我是服务商");
                $this->theme->set("userOneColumnUrl","/user/myjointask");
                if($request->get('soruce') && $request->get('soruce') ==1){
                    $this->theme->set("userSecondColumn","方案咨询");
                    $this->theme->set("userSecondColumnUrl","/user/serviceConsult");
                }else{
                    $this->theme->set("userSecondColumn","方案留言");
                    $this->theme->set("userSecondColumnUrl","/user/serviceLeavMessage");
                }
                $prefix="sc";
                $vip=ConfigModel::where('alias','inquiry_price')->where('type','site')->first();
                $money=$vip["rule"];
                break;
            case "article":
                $this->theme->set("userColumnLeft","userIndex");
                $this->theme->setTitle('文章列表');
                $this->theme->set("userIndex",8);
                $this->theme->set("userOneColumn","个人中心");
                $this->theme->set("userOneColumnUrl","/user/shop");
                $this->theme->set("userSecondColumn","文章管理");
                $this->theme->set("userSecondColumnUrl","/user/consult");
                $prefix="ae";
                $vip=ArticleModel::find($data['order_id']);
                $money=$vip["price"];
                break;
        }
        //订单编号
        $order_num=\CommonClass::createNum($prefix,4);
        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($money == 0) {
            return redirect()->back()->with(['error' => '该订单已支付或者不存在']);
        }
        //查询用户的余额
        $balance = UserDetailModel::where(['uid' => Auth::user()->id])->first();
        $balance = (float)$balance['balance'];
        $resPrice=[];
        $couponPrice=0;
        //查看是否使用优惠券
        if($request->get("action") !='deposit' && $request->get("userCoupon") >0){
            //获取优惠券减免金额
            $resPrice=UserCouponModel::getEndPrice($money,$data['userCoupon']);
            $money=round($resPrice['endPrice'],2);
            $couponPrice=round($resPrice['coupon'],2);
            if($resPrice['endPrice'] == 0){
                $data['pay_canel']=0;
            }
        }
       // dd($money,$data['userCoupon']);
        //如果余额足够就直接余额付款
        if ($balance >= $money && $data['pay_canel'] == 0) {
            if(!isset($data['zfpwd'])){
                return redirect()->back()->with(['error' => '输入支付密码']);
            }
            //验证用户的密码是否正确
            $password = UserModel::encryptPassword($data['zfpwd'], Auth::user()->salt);
            if ($password !=  Auth::user()->alternate_password) {
                return redirect()->back()->with(['error' => '您的支付密码不正确']);
            }
            $res=DB::transaction(function()use($data,$money,$couponPrice,$order_num,$request,$vip,$resPrice,$balance){
                //查询用户现有金额
                $userBalance=UserDetailModel::where("uid",Auth::user()->id)->pluck("balance");
                //用户金额减少
                UserDetailModel::where("uid",Auth::user()->id)->decrement('balance', $money);
                //生成订单日志
                $action=12;
                if($request->get("action")=='deposit'){
                    $action=9;
                }else if($request->get("action")=='tool'){
                    $action=6;
                }else if($request->get("action") =="service"){
                    $action=8;
                }else if($request->get("action") =="article"){
                    $action=7;
                }

                $data['price']=$money;
                $data['order_num']=$order_num;
                if($request->get("action")=='deposit'){
                     //生成保证金订单
                    $orderId=UserDepositModel::insertGetId([
                          'order_num'=>$order_num,
                          'uid'=>Auth::user()->id,
                          'price'=>$money+$couponPrice,
                          'status'=>2,
                          'created_at'=>date("Y-m-d H:i:s"),
                          'payment_at'=>date("Y-m-d H:i:s"),
                      ]);
                    //修改用户保证金
                    UserDetailModel::where("uid",Auth::user()->id)->update(["deposit"=>$money]);
                    AuthRecordModel::create([
                        'auth_id'=>$orderId,
                        'uid'=>Auth::user()->id,
                        'username'=>Auth::user()->name,
                        'auth_code' =>"promise",
                        'status' =>1,
                        'auth_time'=>date("Y-m-d H:i:s")

                    ]);
                    //给用户发送短信
                    UserDepositModel::sendSms(Auth::user()->id,"deposit_sub",$money);
                    $returnParam="deposit";
                }elseif($request->get("action")=='tool'){//购买工具
                    $orderId=UserToolModel::insertGetId([
                                 'price'=>$money+$couponPrice,
                                 'uid'=>Auth::user()->id,
                                 'order_num'=>$order_num,
                                 'tool_id'=>$vip['id'],
                                 'status'=>1,
                                 'created_at'=>date("Y-m-d H:i:s"),
                                 'start_time'=>date("Y-m-d H:i:s"),
                                 'end_time'=>date('Y-m-d H:i:s',strtotime("+1months")),
                                 'pay_status'=>2
                     ]);
                    $returnParam="tool";
                }elseif($request->get("action")=='service'){
                    $orderId=ProgrammeInquiryPayModel::insertGetId([
                              'order_num'=>$order_num,
                               'programme_id'=>$data['order_id'],
                               'uid'=>Auth::user()->id,
                               'price'=>$money+$couponPrice,
                               'created_at'=>date("Y-m-d H:i:s"),
                               'payment_at'=>date("Y-m-d H:i:s"),
                               'status'=>2,
                               'type'=>2,
                      ]);
                    //修改订单状态
                    ProgrammeEnquiryMessageModel::where("id",$data['order_id'])->update(["pay_type"=>2]);
                }elseif($request->get("action")=='article'){
                     $orderId=ArticlePayModel::insertGetId([
                         'order_num'=>$order_num,
                         'article_id'=>$data['order_id'],
                         'uid'=>Auth::user()->id,
                         'price'=>$money+$couponPrice,
                         'status'=>2,
                         'created_at'=>date("Y-m-d H:i:s"),
                         'payment_at'=>date("Y-m-d H:i:s"),
                     ]);
                    ArticleModel::where("id",$data['order_id'])->update(["status"=>1]);
                    $returnParam="article";
                }else{
                    //生成购买订单
                    $data['user_balance']=$balance-$data['price'];
                    $data['uid']=Auth::user()->id;
                    $orderId=VipUserOrderModel::createVipData($data);
                    switch($request->get("action")){
                        case "cika":
                        case "rika":
                            $returnParam="cika";
                            break;
                        case "vip":
                            $returnParam="vip";
                            break;
                    }
                }
                $financial = [
                    'action'     => $action,
                    'pay_type'   => 1,
                    'cash'       => $money+$couponPrice,
                    'uid'        => Auth::user()->id,
                    'status'    =>2,
                    'created_at' => date('Y-m-d H:i:s'),
                    'related_id' =>$orderId,
                    'coupon'    =>isset($resPrice['coupon'])?$resPrice['coupon']:0,
                    'remainder' =>$userBalance-$money,
                ];
                FinancialModel::create($financial);

                if($request->get("action") !='deposit' && $request->get("userCoupon") >0){
                    //处理优惠券
                    UserCouponLogModel::userCouponHandle($order_num,Auth::user()->id,2,$request->get("userCoupon"));
                }
                return $request->get("action");//$returnParam;
            });
            switch($res){
                case "deposit":
                    return redirect('user/myDeposit')->with(["message"=>"支付成功"]);
                    break;
                case "tool":
                    return redirect()->to('/user/toolAll')->with(["message"=>"支付成功"]);
                    break;
                case "service":
                    if($request->get('soruce') && $request->get('soruce') ==1){
                        return redirect()->to('/user/serviceConsult')->with(["message"=>"支付成功"]);
                    }else{
                        return redirect()->to('/user/serviceLeavMessage')->with(["message"=>"支付成功"]);
                    }
                    break;
                case "vip":
                    return redirect()->to('/user/myVip')->with(["message"=>"支付成功"]);
                    break;
                case "article":
                    return redirect()->to('/user/consult')->with(["message"=>"支付成功"]);
                    break;
                case "cika":
                case "rika":
                    return redirect()->to('/user/myVipCard')->with(["message"=>"支付成功"]);
                    break;
            }
        } else if (isset($data['pay_type']) && $data['pay_canel'] == 1) {
            if($request->get("action")=='deposit'){
                //生成保证金订单
                UserDepositModel::create([
                    'order_num'=>$order_num,
                    'uid'=>Auth::user()->id,
                    'price'=>$money,
                    'status'=>1,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'payment_at'=>date("Y-m-d H:i:s"),
                ]);
            }elseif($request->get("action")=='tool'){//购买工具
                UserToolModel::insertGetId([
                    'price'=>$vip['price'],
                    'uid'=>Auth::user()->id,
                    'order_num'=>$order_num,
                    'tool_id'=>$vip['id'],
                    'status'=>0,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'start_time'=>date("Y-m-d H:i:s"),
                    'end_time'=>date('Y-m-d H:i:s',strtotime("+1months")),
                    'pay_status'=>1
                ]);
            }elseif($request->get("action")=='service'){
                ProgrammeInquiryPayModel::create([
                    'order_num'=>$order_num,
                    'programme_id'=>$data['order_id'],
                    'uid'=>Auth::user()->id,
                    'price'=>$money,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'payment_at'=>date("Y-m-d H:i:s"),
                    'status'=>1,
                    'type'=>2,
                ]);
            }elseif($request->get("action")=='article'){
                ArticlePayModel::create([
                    'order_num'=>$order_num,
                    'article_id'=>$data['order_id'],
                    'uid'=>Auth::user()->id,
                    'price'=>$money,
                    'status'=>1,
                    'created_at'=>date("Y-m-d H:i:s")
                ]);
            }else{
                //用户购买vip的次数userBuyVIPCount
                $userBuyVIPCount=VipUserOrderModel::where("uid",Auth::user()->id)->where("pay_status",2)
                    ->count();
                //生成订单
                VipUserOrderModel::create([
                    "order_num"=>$order_num,
                    "uid"=>Auth::user()->id,
                    "price"=>$money,
                    "vipid"=>$vip['id'],
                    "pay_status"=>1,
                    "pay_time"=>date("Y-m-d H:i:s"),
                    "status"=>2,
                    "created_at"=>date("Y-m-d H:i:s"),
                    'level'=>$vip['grade'],
                    'user_balance'=>$balance-$money,
                    'num'=>$userBuyVIPCount +1,
                ]);
            }
            if($request->get("action") !='deposit' && $request->get("userCoupon") >0){
                //处理优惠券
                UserCouponLogModel::userCouponHandle($order_num,Auth::user()->id,1,$request->get("userCoupon"));
            }
            //跳转支付赏金托管流程,返回成功之后就直接执行上面的托管
            if ($data['pay_type'] == 1) {//支付宝支付
                $config = ConfigModel::getPayConfig('alipay');
                $objOminipay = Omnipay::gateway('alipay');
                $objOminipay->setPartner($config['partner']);
                $objOminipay->setKey($config['key']);
                $objOminipay->setSellerEmail($config['sellerEmail']);
                $siteUrl = \CommonClass::getConfig('site_url');
                $objOminipay->setReturnUrl($siteUrl . '/order/pay/alipay/return');
                $objOminipay->setNotifyUrl($siteUrl . '/order/pay/alipay/notify');

                $response = Omnipay::purchase([
                    'out_trade_no' => $order_num, //your site trade no, unique
                    'subject'      => \CommonClass::getConfig('site_name'), //order title
                    'total_fee'    => $money, //order total fee $money
                ])->send();
                $response->redirect();
            } else if ($data['pay_type'] == 2) {//微信支付
                $config = ConfigModel::getPayConfig('wechatpay');
                $wechat = Omnipay::gateway('wechat');
                $wechat->setAppId($config['appId']);
                $wechat->setMchId($config['mchId']);
                $wechat->setAppKey($config['appKey']);
                $out_trade_no = $order_num;
                $params = array(
                    'out_trade_no' => $order_num, // billing id in your system
                    'notify_url'   => \CommonClass::getDomain() . '/order/pay/wechat/notify?out_trade_no=' . $out_trade_no, // URL for asynchronous notify
                    'body'         => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee'    => $money, // Amount with less than 2 decimals places
                    'fee_type'     => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());
                $view = array(
                    'cash'       => $money,
                    'img'        => $img,
                    'order_code' => $order_num,
                    'href_url'   => '/user/index/'
                );
                return $this->theme->scope('task.wechatpay', $view)->render();
            } else if ($data['pay_type'] == 3) {
                dd('银联支付！');
            }
        } else if (isset($data['account']) && $data['pay_canel'] == 2) {//银行卡支付
            dd('银行卡支付！');
        } else{//如果没有选择其他的支付方式
            return redirect()->back()->with(['error' => '请选择一种支付方式']);
        }
    }
    /**
     * vip购买记录
     * @param Request $request
     * @return mixed
     */
    public function vippaylist(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('购买记录');
        $this->theme->set("userVip",3);
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/myVip");
        $this->theme->set("userSecondColumn","我的购买记录");
        $this->theme->set("userSecondColumnUrl","/user/vippaylist");
        //获取用户购买vip记录
        //$userVip=VipUserOrderModel::select("vip.name","user_viporder.*")->leftJoin("vip","user_viporder.vipid","=","vip.id")->where("user_viporder.uid",Auth::user()->id)->orderBy("user_viporder.created_at","desc")->paginate(10);
        $userVip=VipUserOrderModel::select("vip.name","user_viporder.*")->join("vip",function($join){
            $join->on("user_viporder.vipid","=","vip.id")->orOn("user_viporder.vipid","=","vip.vipconfigid");
        })->where("user_viporder.uid",Auth::user()->id)->orderBy("user_viporder.created_at","desc")->paginate(10);
        $data = [
            "userVip"=>$userVip,
        ];
        return $this->theme->scope('user.vippaylist', $data)->render();
    }

    /**
     * vip购买记录详情
     *
     * @param $id
     * @return mixed
     */
    public function vippaylog($id)
    {

        $goodsInfo = ShopPackageModel::findOrFail($id);

        $package = PackageModel::withTrashed()->find($goodsInfo->package_id);

        $goodsInfo = collect($goodsInfo)->put('package_name', $package->title)
            ->put('package_ico', $package->logo)
            ->toArray();

        $arrPrivilege = json_decode($goodsInfo['privileges_package'], true);

        $privileges = PrivilegesModel::withTrashed()->whereIn('id', $arrPrivilege)->get(['title', 'desc']);

        $data = [
            'packageInfo' => $goodsInfo,
            'privileges' => $privileges
        ];

        return $this->theme->scope('user.vippaylog', $data)->render();
    }

    /**
     * vip店铺装修
     *
     * @return mixed
     */
    public function vipshopbar()
    {

        $shopPackage = ShopPackageModel::where(['uid' => Auth::id(), 'status' => 0])->first();

        $havePrivilege = false;

        $nav = [
            ['id' => 1, 'name' => '首页', 'status' => 1],
            ['id' => 2, 'name' => '作品', 'status' => 1],
            ['id' => 3, 'name' => '服务', 'status' => 1],
            ['id' => 4, 'name' => '成功案例', 'status' => 1],
            ['id' => 5, 'name' => '交易评价', 'status' => 1],
            ['id' => 6, 'name' => '关于我们', 'status' => 1],
        ];

        $nav = json_encode($nav);

        $color = 'blue'; $initBanner = []; $initCentral = $initFooter = [];

        $countCentral = 0; $countFooter = 0; $countBanner = 0;

        if (!empty($shopPackage)){

            $arrPrivilege = json_decode($shopPackage['privileges_package'], true);

            $privilege = PrivilegesModel::whereIn('id', $arrPrivilege)->where('code', 'SHOP_DECORATION')->first();

            if (!empty($privilege)){
                $havePrivilege = true;

                $shopInfo = ShopModel::where('uid', Auth::id())->first();

                $nav = $shopInfo['nav_rules'] ? $shopInfo['nav_rules'] : $nav;

                $color = $shopInfo['nav_color'] ? $shopInfo['nav_color'] : $color;

                $arrBannerId = json_decode($shopInfo['banner_rules'], true);

                $banner_ad = AttachmentModel::whereIn('id', $arrBannerId)->get();
                $countBanner = count($arrBannerId);
                if (!empty($banner_ad)){
                    foreach ($banner_ad as $item){
                        $initBanner[] = [
                            'name' => $item['name'],
                            'size' => $item['size'],
                            'id' => $item['id'],
                            'url' => url($item['url'])
                        ];
                    }
                }

                $central_ad = $shopInfo['central_ad']; $footer_ad = $shopInfo['footer_ad'];

                $countCentral = $central_ad ? 1 : 0;

                $countFooter = $footer_ad ? 1 : 0;

                $central_ad = AttachmentModel::where('url', $central_ad)->first();
                if ($central_ad){
                    $initCentral[] = [
                        'name' => $central_ad['name'],
                        'size' => $central_ad['size'],
                        'id' => $central_ad['id'],
                        'url' => url($central_ad['url'])
                    ];
                }

                $footer_ad = AttachmentModel::where('url', $footer_ad)->first();
                if ($footer_ad){
                    $initFooter[] = [
                        'name' => $footer_ad['name'],
                        'size' => $footer_ad['size'],
                        'id' => $footer_ad['id'],
                        'url' => url($footer_ad['url'])
                    ];
                }

            }
        }

        $data = [
            'havePrivilege' => $havePrivilege,
            'nav' => $nav,
            'color' => $color,
            'initBanner' => json_encode($initBanner),
            'initCentral' => json_encode($initCentral),
            'initFooter' => json_encode($initFooter),
            'hiddenBanner' => $initBanner,
            'hiddenCentral' => $initCentral,
            'hiddenFooter' => $initFooter,
            'countCentral' => $countCentral,
            'countFooter' => $countFooter,
            'countBanner' => $countBanner
        ];

        return $this->theme->scope('user.vipshopbar', $data)->render();
    }


    /**
     * 保存店铺装修
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postVipshopbar()
    {
        $privilege = false;

        $shopPackage = ShopPackageModel::where(['uid' => Auth::id(), 'status' => 0])->first();

        if (!empty($shopPackage)){

            $arrPrivilege = json_decode($shopPackage['privileges_package'], true);

            $privilege = PrivilegesModel::whereIn('id', $arrPrivilege)->where('code', 'SHOP_DECORATION')->first();

            if (!empty($privilege)) $privilege = true;
        }

        if (!$privilege) return back()->with(['error' => '无装修特权']);

        $data = Input::get();

        $checkNavArr = array_values(array_flip($data['nav']));

        $nav = $data['navt'];

        $nav_rules = [];
        foreach ($nav as $k => $v){
            if (in_array($k, $checkNavArr)){
                $nav_rules[] = [
                    'id' => $k,
                    'name' => $v,
                    'status' => true,
                ];
            } else {
                $nav_rules[] = [
                    'id' => $k,
                    'name' => $v,
                    'status' => false,
                ];
            }
        }
        if (isset($data['banner'])){
            $banner = AttachmentModel::whereIn('id', $data['banner'])->get();
        }

        $banner_rules = [];

        if (!empty($banner)){
            foreach ($banner as $item) {
                if (in_array($item['id'], $data['banner'])){
                    $banner_rules[] = $item['id'];
                }
            }
        }

        $centralAD = '';
        if (isset($data['centralAD'])){
            $centralAD = AttachmentModel::find($data['centralAD'][0]);
        }

        if (!empty($centralAD)) $centralAD = $centralAD['url'];

        $footerAD = '';
        if (isset($data['footerAD'])){
            $footerAD = AttachmentModel::find($data['footerAD'][0]);
        }

        if (!empty($footerAD)) $footerAD = $footerAD['url'];

        $updatetArr = [
            'nav_color' => $data['color'],
            'nav_rules' => json_encode($nav_rules),
            'banner_rules' => json_encode($banner_rules),
            'central_ad' => $centralAD,
            'footer_ad' => $footerAD
        ];

        $status = ShopModel::where('uid', Auth::id())->update($updatetArr);

        if ($status)
            return redirect('user/vipshopbar')->with(['message' => '保存成功']);

    }

    /**
     * 删除vipshop图片
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delVipshopFile()
    {
        $data = Input::get();
        $id = $data['id'];
        //查询当前的附件
        $file = AttachmentModel::where('id',$id)->first()->toArray();
        if(!$file)
        {
            return response()->json(['errCode' => 0, 'errMsg' => '附件没有上传成功！']);
        }
        //删除附件
        if(is_file($file['url']))
            unlink($file['url']);
        $result = AttachmentModel::destroy($id);
        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => '删除失败！']);
        }
        $shopInfo = ShopModel::where('uid', Auth::id())->first();
        switch ($data['type']){
            case 'banner':
                $banner = json_decode($shopInfo['banner_rules'], true);
                foreach ($banner as $key => $item){
                    if ($item == $id){
                        unset($banner[$key]);
                    }
                }
                break;
            case 'central':
                $shopInfo['central_ad'] = ''; $shopInfo->save();
                break;
            case 'footer':
                $shopInfo['footer_ad'] = ''; $shopInfo->save();
                break;
        }
        return response()->json(['errCode' => 1, 'errMsg' => '删除成功！']);
    }
}