<?php

namespace App\Modules\Shop\Models;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Pay\OrderModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\SkillTagsModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;
class ProgrammeOrderModel extends Model
{
    protected $table = 'programme_order';
    //
    public $timestamps = false;
    protected $fillable = [
        'id',
        'uid',
        'order_num',
        'programme_id',
        'number',
        'nickname',
        'consignee',
        'mobile',
        'email',
        'province',
        'city',
        'area',
        'addr',
        'is_invoice',
        'invoice_type',
        'invoices_raised',
        'com_name',
        'taxpayer_num',
        'receiver_mobile',
        'receiver_email',
        'regist_addre',
        'com_mobile',
        'open_bank',
        'bank_account',
        'receiver_uname',
        'tax_province',
        'tax_city',
        'tax_area',
        'status',
        'pre_status',
        'price',
        'freight',
        'created_at',
        'payment_at',
        'confirm_at',
        'complete_at',
        'send_at',
        'bill_no',//运单编号
        'rights_at',
        'rights_desc',//维权原因
        'end_at',
    ];
    //获取店铺交易交易额
  static  public function getPriceCount($goodAllId){
      $programmeAll=self::whereIn("programme_id",$goodAllId)->get()->toArray();
      $priceCount="";
      foreach ($programmeAll as $key=>$val){
          $priceCount+=$val['freight'];
          $priceCount+=$val['price']*$val['number'];
      }
      return $priceCount;
  }
 //计算运费以及总交易额
    static  public function getFreightAndPrice($goods){
        $data['freight']=0;
        $data['totalPrice']=0;
        foreach ($goods as $key=>$val){
            $data['freight']+=$val['freight'];
            $data['totalPrice']+=$val['price']*$val['number'];
        }
        return $data;
    }
    //方案购买成功后，事务处理
    //优惠券的金额
    static  public function programmeSuccessHandle($data,$findProgramme,$pay_type,$coupon=0){
	//方案所属用户金额添加
        $ownedUsers=GoodsModel::where("id",$findProgramme['programme_id'])->select("uid","title")->first();
        $ownedUsersInfo=UserDetailModel::LeftJoin('users',"user_detail.uid","=","users.id")->where("user_detail.uid",$ownedUsers['uid'])->select("user_detail.balance","users.*")->first();
        //给用户生成记录  流程修改 在确定收货的地方添加这个
//        FinancialModel::createOne(
//            ["action"=>2,"pay_type"=>$pay_type,"cash"=>$findProgramme['price']*$findProgramme['number'] + $findProgramme['freight'],
//                "uid"=>$ownedUsers['uid'],"created_at"=>date("Y-m-d H:i:s"),"related_id"=>$findProgramme['id'],'status' =>1,'remainder'=>$ownedUsersInfo['balance']+$findProgramme['price'] + $findProgramme['freight']]
//        );
        //订单状态修改
        ProgrammeOrderModel::where("id",$findProgramme['id'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
       //流程修改在确定收货的地方进行这个
        // UserDetailModel::where("uid",$ownedUsers['uid'])->increment("balance",$findProgramme['price']*$findProgramme['number'] + $findProgramme['freight']);
        //子订单状态修改
        ProgrammeOrderSubModel::where("order_id",$findProgramme['id'])->update(["status"=>2]);
        //给平台生成记录
        //获取购买用户的信息
        $userBuyBalance=UserDetailModel::where("uid",$findProgramme['uid'])->pluck("balance");
        FinancialModel::createOne(
            ["action"=>2,"pay_type"=>$pay_type,"cash"=>$findProgramme['price']*$findProgramme['number'] + $findProgramme['freight'],
                "uid"=>$findProgramme['uid'],"created_at"=>date("Y-m-d H:i:s"),"related_id"=>$findProgramme['id'],'status' =>2,'remainder'=>$userBuyBalance,
                "coupon"=>$coupon
            ]
        );
        //$userBuyBalance-$findProgramme['price']*$findProgramme['number'] - $findProgramme['freight']+$coupon
        //商品数量添加
         GoodsModel::where("id",$findProgramme['programme_id'])->increment("sales_num",$findProgramme['number']);
        //给方案拥有者发送站内信
        $user = [
                       'uid'    =>$ownedUsersInfo['id'],
                       'email'  => $ownedUsersInfo['email'],
                       'mobile' => $ownedUsersInfo['mobile']
                   ];
                   $templateArr = [
                       'username' =>$ownedUsersInfo['name'],
                       'title'     =>$ownedUsers['title'],
                   ];
                   \MessageTemplateClass::sendMessage("employee_goods_sell",$user,$templateArr,$templateArr);
    }
    //方案详情
    static public function programmeOrderInfo($id){
        $programmeOrder=ProgrammeOrderModel::leftJoin("goods","programme_order.programme_id","=","goods.id")
            ->leftJoin("attachment","goods.cover","=","attachment.id")
            ->where("programme_order.id",$id)->select("attachment.url","programme_order.*","goods.title")->first();
        $programmeOrder['province']=DistrictModel::where("id",$programmeOrder['province'])->pluck("name");
        $programmeOrder['city']=DistrictModel::where("id",$programmeOrder['city'])->pluck("name");
        $programmeOrder['area']=DistrictModel::where("id",$programmeOrder['area'])->pluck("name");
        $programmeOrder['tax_province']=DistrictModel::where("id",$programmeOrder['tax_province'])->pluck("name");
        $programmeOrder['tax_city']=DistrictModel::where("id",$programmeOrder['tax_city'])->pluck("name");
        $programmeOrder['tax_area']=DistrictModel::where("id",$programmeOrder['tax_area'])->pluck("name");
        return $programmeOrder;
    }
    //订单维权
    static  public function orderRights($id,$programme,$rightsDesc=''){
        $res=DB::transaction(function()use($id,$programme,$rightsDesc){
            $programme->update([
                'status'=>6,
                'rights_at'=>date("Y-m-d H:i:s"),
                'pre_status'=>$programme['status'],
                'rights_desc' => $rightsDesc
            ]);
            //获取方案
            $goods=GoodsModel::find($programme['programme_id']);
            $role=2;$from_uid=$goods['uid'];$to_uid=Auth::User()->id;
            $desc="卖家维权";
            if(Auth::User()->id ==$programme['uid']){
                $role=1;
                $from_uid=Auth::User()->id;
                $to_uid=$goods['uid'];
                $desc="买家维权";
            }
            //生成维权记录
            DB::table("programme_order_rights")->insert([
                'programme_order_id'=>$programme['id'],
                'role'=>$role,
                'from_uid'=>$from_uid,
                'to_uid'=>$to_uid,
                'created_at'=>date("Y-m-d H:i:s"),
                'goods_name'=>$goods['title'],
                'desc'=>$desc,
            ]);
            return $id;
        });
        return $res;
    }
}

