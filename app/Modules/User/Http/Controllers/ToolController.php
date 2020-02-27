<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController as BasicUserCenterController;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\SubOrderModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDepositModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserTagsModel;
use App\Modules\User\Model\UserToolModel;
use Auth;
use Illuminate\Http\Request;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Session;
use Theme;

class ToolController extends BasicUserCenterController
{

    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
    }
    //工具箱列表
    public function toolAll(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('广告类别');
        $this->theme->set("userVip",5);
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/toolAll");
        $this->theme->set("userSecondColumn","广告类别");
        $this->theme->set("userSecondColumnUrl","/user/toolAll");
        //获取工具列表
        $toolList=ServiceModel::where("type",4)->where("status",1)->get();
        //根据当前的获取工具的折扣
        $toolZK=[];
        if(Auth::user()->level >1){
            $discount=VipModel::getDiscount(Auth::user()->level);
            $toolZK=json_decode($discount,true);
        }
        $data=[
            'toolList'=>$toolList,
            'toolZK' =>$toolZK,
        ];
        return $this->theme->scope('user.tool.toolAll', $data)->render();
    }
    //工具购买
    public function  toolPay($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('广告类别');
        $this->theme->set("userVip",5);
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/toolAll");
        $this->theme->set("userSecondColumn","广告类别");
        $this->theme->set("userSecondColumnUrl","/user/toolAll");
        $userDetial=UserDetailModel::where("uid",Auth::user()->id)->first();
        $toolZK=10;
        if(Auth::user()->level >1){
            $discount=VipModel::getDiscount(Auth::user()->level);
            $discountArr=json_decode($discount,true);
            if(isset($discountArr[$id])){
                $toolZK=$discountArr[$id];
            }
        }
        $service=ServiceModel::find($id);
        $service['price']=round($service['price']*$toolZK/10,2);
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userDetial['balance'] >= $service['price']) {
            $balance_pay = true;
        }
        //根据金额获取对应的优惠券
        $userCoupon=UserCouponModel::getCoupon($service['price'],[0,1]);
        //dd($userCoupon);
        $data=[
            'totalPrice'=>$service['price'],
            'userDetail'=>$userDetial,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'service'=>$service,
            'userCoupon' =>$userCoupon,
        ];
        return $this->theme->scope('user.tool.toolPay', $data)->render();
    }
    //工具箱购买列表
    public function toolPayLog(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle('我的购买记录');
        $this->theme->set("userVip",6);
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/toolAll");
        $this->theme->set("userSecondColumn","我的购买记录");
        $this->theme->set("userSecondColumnUrl","/user/toolPayLog");
        $list=UserToolModel::leftJoin("service","user_tool.tool_id","=","service.id")->where("user_tool.uid",Auth::user()->id)
            ->where("user_tool.pay_status",2)->select("user_tool.*","service.title")
            ->orderBy("user_tool.created_at","desc")->paginate(10);
        //$list=SubOrderModel::where("uid",Auth::user()->id)->where("note","tool")->orderBy("id","desc")
         //     ->paginate(10);
        $data=[
            'list'=>$list,
        ];
        return $this->theme->scope('user.tool.toolPayLog', $data)->render();
    }
}
