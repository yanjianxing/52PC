<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Finance\Model\CashoutModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPublishingModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AlipayAuthModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Theme;

class IndexController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->setTitle('后台管理');
    }

    //后台首页
    public function getManage()
    {
        $now = strtotime(date('Y-m-d', time()));
        //$da=date('w');
        $weekDate=[];
        //获取一周的时间
        for($i=6;$i>=0;$i--){
//           switch($da){
//               case 1:
                   $weekDate[]=date('Y-m-d', strtotime('-'.$i .'day', $now));
//                   break;
//               case 2:
//                   $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-1 .'day', $now));
//                   break;
//               case 3:
//                   $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-2 .'day', $now));
//                   break;
//               case 4:
//                   $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-3 .'day', $now));
//                   break;
//               case 5:
//                   $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-4 .'day', $now));
//                   break;
//               case 6:
//                   $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-5 .'day', $now));
//                   break;
//               case 0:
//                   $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-6 .'day', $now));
//                   break;
//           }
        }
        // $today = date('Y-m-d H:i:s', $now);
        //$tomorrow = date('Y-m-d H:i:s', strtotime('+1 day', $now));
        //$yesterday = date('Y-m-d H:i:s', strtotime('-1 day', $now));

        //用户数量
        $userCount=UserModel::count();
        //获取平台所有的收入于支出
        $incomeArr = \CommonClass::incomeArr();
        $outcomeArr = \CommonClass::outcomeArr();
        $platIncome=FinancialModel::whereIn('action', $incomeArr)->sum('cash');//收入
        $platOut=FinancialModel::whereIn('action',$outcomeArr)->sum('cash');//支出
        $platSum=$platIncome-$platOut;
        //获取充值金额
        $rechargeSum=FinancialModel::where('action',3)->sum('cash');
        //获取体现金额
        $forwardSum=FinancialModel::where('action',4)->sum('cash');

        /*
         * 快捷入口
         * */
         //店铺认证
        $shopAuth=ShopModel::where("status",0)->count();
        //获取竞标任务的id
        $jingbiaoId=TaskTypeModel::where('alias',"jingbiao")->pluck('id');
        //任务审核
        $taskAudit=TaskModel::where('type_id',$jingbiaoId)->where("status",1)->where('is_del',0)->count();
        //获取雇佣任务的id
        $guyongId=TaskTypeModel::where('alias',"guyong")->pluck('id');
        //查询雇佣任务
        $guyongTaskCount=TaskModel::where('type_id',$guyongId)->where("status",1)->where('is_del',0)->count();
        //获取快捷任务的id
        //$kuaijieId=TaskTypeModel::where("alias","kuaijie")->pluck('id');
        $kuaijieTaskCount=TaskPublishingModel::where('status',1)->count();
        //提现申请
        $applyFor=CashoutModel::where('status',0)->count();
        //咨询审核
        $consultAudit=ArticleModel::where('status',0)->count();
        //实名认证
        $realAuth=RealnameAuthModel::where('status',0)->count();
        //支付认证
        $payAuth=AlipayAuthModel::where('status',0)->count();
        //企业认证
        $enterpriseAuth=EnterpriseAuthModel::where('status',0)->count();
        //银行卡认证
        $bankAuth=BankAuthModel::where("status",0)->count();
        /*
         * 处理曲线图数据
         * */
        $profitPrice=[];//纯利润
        $buyMember=[];//购买会员
        $buyTool=[];//购买工具
        $addService=[];//增值服务
        $formalities=[];//提现手续
        $information=[];//付费咨询
        $bidProject=[];//竞标项目
        $hireProject=[];//雇佣项目
        $publiProject=[];//一键发布项目
        $employer=[];//雇主
        $facilitator=[];//服务商
        $message=[];//方案消息
        $customization=[];//个性定制
        $consultation=[];//方案咨询
        $vipOrder=[];//vip购买
        //获取竞标分类id
        $bidId=TaskTypeModel::where('alias','jingbiao')->pluck('id');
        //获取雇佣分类id
        $hireId=TaskTypeModel::where('alias','guyong')->pluck('id');
        //获取一键发布分类id
        $publiId=TaskTypeModel::where('alias','kuaijie')->pluck('id');
        //获取所有vip等级
        $vipGrade=VipModel::groupBy("grade")->select("id","grade","name")->orderBy("grade","asc")->get()->toArray();
        foreach($weekDate as $key=>$val){
            //if($key == 0){
                $incomePrice=FinancialModel::where("created_at","like",$val."%")->whereIn('action', $incomeArr)->sum('cash');
                $outPrice=FinancialModel::where("created_at","like",$val."%")->whereIn('action', $outcomeArr)->sum('cash');
                $profitPrice[$key]=floatval($incomePrice-$outPrice);
                $bidProject[$key]=intval(TaskModel::where('type_id',$bidId)->where('status','>',1)->where('status','<>',3)->where('verified_at',"like",$val."%")->count());
                $employer[$key]=intval(UserModel::where("type",1)->where('created_at',"like",$val."%")->count());
                $message[$key]=intval(GoodsModel::whereIn('status',[1,2])->where('updated_at',"like",$val."%")->count());
                if(!$vipGrade){
                    $vipOrder[$key]=0;
                }else{
                    $vipOrder[$key]=intval(VipUserOrderModel::where('vipid',$vipGrade[0]['id'])->where("created_at","like",$val."%")->where("pay_status",2)->count());
                }
//            }else{
//                $incomePrice=FinancialModel::where("created_at",">",$weekDate[$key-1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ," .$val)))->whereIn('action', $incomeArr)->sum('cash');
//                $outPrice=FinancialModel::where("created_at",">",$weekDate[$key-1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ," .$val)))->whereIn('action', $outcomeArr)->sum('cash');
//                $profitPrice[$key]=floatval($incomePrice-$outPrice);
//                $bidProject[$key]=intval(TaskModel::where('type_id',$bidId)->where('status','>',1)->where('status','<>',3)->where("verified_at",">",$weekDate[$key-1])->where("verified_at","<",date("Y-m-d",strtotime("+1 day ," .$val)))->count());
//                $employer[$key]=intval(UserModel::where("type",1)->where("created_at",">",$weekDate[$key-1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ," .$val)))->count());
//                $message[$key]=intval(GoodsModel::whereIn('status',[1,2])->where("updated_at",">",$weekDate[$key-1])->where("updated_at","<",date("Y-m-d",strtotime("+1 day ," .$val)))->count());
//                $vipOrder[$key]=intval(VipUserOrderModel::where('vipid',$vipGrade[0]['id'])->where("created_at",">",$weekDate[$key-1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ," .$val)))->where("pay_status",2)->count());
//            }

        }
        //dd($vipGrade);
        //所有折线图组成一个数组
        $broken=[
            'profitPrice'=>$profitPrice,//纯利润
            'bidProject'=>$bidProject,//竞标任务
            'employer'=>$employer,//雇主
            'messages'=>$message,//方案消息
            'vipOrder'=>$vipOrder,//vip购买
            'vipGrade'=>$vipGrade
        ];
        //获取平台运营数据
        for($i=0;$i<3;$i++){
            switch($i){
                case 0:
                    $searchDay=date("Y-m-d");
                    break;
                case 1:
                    $searchDay=date("Y-m-d",strtotime("-1 day"));
                    break;
                case 2:
                    $searchDay=date("Y-m-d",strtotime("-30 day"));
                    break;
            }
            if($i ==2){
                $platData[$i]['zc']=UserModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->count();
                $platData[$i]['fb']=TaskModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->count();
                $platData[$i]['jb']=WorkModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->distinct("task_id")->count();
                $platData[$i]['xz']=WorkModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->where("status",">",0)->distinct("task_id")->count();
                $platData[$i]['tg']=WorkModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->where("status",">",1)->distinct("task_id")->count();
                $platData[$i]['fa']=GoodsModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->count();
                $platData[$i]['xj']=ProgrammeEnquiryMessageModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->where("type",1)->count();
                $platData[$i]['xs']=ProgrammeOrderModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->where("status",">",1)->count();
                $platData[$i]['vip']=VipUserOrderModel::where("created_at",">",$searchDay)->where("created_at","<",date("Y-m-d",strtotime("+1 day")))->where("pay_status","2")->count();
            }else{
                $platData[$i]['zc']=UserModel::where("created_at","like","%".$searchDay."%")->count();
                $platData[$i]['fb']=TaskModel::where("created_at","like","%".$searchDay."%")->count();
                $platData[$i]['jb']=WorkModel::where("created_at","like","%".$searchDay."%")->distinct("task_id")->count();
                $platData[$i]['xz']=WorkModel::where("created_at","like","%".$searchDay."%")->where("status",">",0)->distinct("task_id")->count();
                $platData[$i]['tg']=WorkModel::where("created_at","like","%".$searchDay."%")->where("status",">",1)->distinct("task_id")->count();
                $platData[$i]['fa']=GoodsModel::where("created_at","like","%".$searchDay."%")->count();
                $platData[$i]['xj']=ProgrammeEnquiryMessageModel::where("created_at","like","%".$searchDay."%")->where("type",1)->count();
                $platData[$i]['xs']=ProgrammeOrderModel::where("created_at","like","%".$searchDay."%")->where("status",">",1)->count();
                $platData[$i]['vip']=VipUserOrderModel::where("created_at","like","%".$searchDay."%")->where("pay_status","2")->count();
            }
        }
        $data = [
            //'maxDay' =>json_encode(7),
            'dateArr' => json_encode($weekDate),
            'userCount'=>$userCount,//用户数量
            'platSum'=>$platSum,//平台总收入
            'rechargeSum'=>$rechargeSum,//充值金额
            'forwardSum'=>$forwardSum,//提现金额
            'shopAuth'=>$shopAuth,//店铺认证
            'taskAudit'=>$taskAudit,//任务审核
            'guyongTaskCount'=>$guyongTaskCount,//查询雇佣任务
            'kuaijieTaskCount'=>$kuaijieTaskCount,//获取快捷任务
            'applyFor'=>$applyFor,//提现申请
            'consultAudit'=>$consultAudit,//咨询审核
            'realAuth'=>$realAuth,//实名认证
            'payAuth'=>$payAuth,//支付认证
            'enterpriseAuth'=>$enterpriseAuth,//企业认证
            'bankAuth'=>$bankAuth,//银行卡认证
            'vipGrade'=>$vipGrade,//vip 等级
            'broken'=>json_encode($broken),
            'username'=> $this->manager['username'],
            'platData'=>$platData,
        ];
        return $this->theme->scope('manage.index', $data)->render();
    }
    /*
     * 类型修改 改变
     * */
     public function echartChoice(Request $request){
         $data=[];
         $now = strtotime(date('Y-m-d', time()));
         $weekDate=[];
         //$da=date('w');
         //获取一周的时间
         for($i=6;$i>=0;$i--){
//             switch($da){
//                 case 1:
                     $weekDate[]=date('Y-m-d', strtotime('-'.$i .'day', $now));
//                     break;
//                 case 2:
//                     $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-1 .'day', $now));
//                     break;
//                 case 3:
//                     $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-2 .'day', $now));
//                     break;
//                 case 4:
//                     $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-3 .'day', $now));
//                     break;
//                 case 5:
//                     $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-4 .'day', $now));
//                     break;
//                 case 6:
//                     $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-5 .'day', $now));
//                     break;
//                 case 0:
//                     $weekDate[$i]=date('Y-m-d', strtotime('+'.$i-6 .'day', $now));
//                     break;
//             }
         }
         //获取平台所有的收入于支出
         $incomeArr = \CommonClass::incomeArr();
         $outcomeArr = \CommonClass::outcomeArr();
          switch($request->get('type')){
              case 'pt':
                  foreach($weekDate as $key=>$val){
                      switch ($request->get('value')){
                          case 1:
                             // if($key == 0){
                                  $incomePrice=FinancialModel::where("created_at","like",$val."%")->whereIn('action', $incomeArr)->sum('cash');
                                  $outPrice=FinancialModel::where("created_at","like",$val."%")->whereIn('action', $outcomeArr)->sum('cash');

//                              }else{
//                                  $incomePrice=FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->whereIn('action', $incomeArr)->sum('cash');
//                                  $outPrice=FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->whereIn('action', $outcomeArr)->sum('cash');
//                              }
                              $data[$key]=floatval($incomePrice-$outPrice);
                              break;
                          case 2:
                             // if($key == 0){
                                  $data[$key]=floatval(FinancialModel::where("created_at","like",$val."%")->where('action',12)->sum('cash'));
//                              }else{
//                                  $data[$key]=floatval(FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->where('action',12)->sum('cash'));
//                              }
                              break;
                          case 3:
                              //if($key ==0){
                                  $data[$key]=floatval(FinancialModel::where("created_at","like",$val."%")->where('action',6)->sum('cash'));
//                              }else{
//                                  $data[$key]=floatval(FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->where('action',6)->sum('cash'));
//                              }
                              break;
                          case 4:
                              //if($key ==0){
                                  $data[$key]=floatval(FinancialModel::where("created_at","like",$val."%")->where('action',5)->sum('cash'));
//                              }else{
//                                  $data[$key]=floatval(FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->where('action',5)->sum('cash'));
//                              }
                              break;
                          case 5:
                              //if($key ==0){
                                  $data[$key]=floatval(FinancialModel::where("created_at","like",$val."%")->where('action',4)->sum('cash'));
//                              }else{
//                                  $data[$key]=floatval(FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->where('action',4)->sum('cash'));
//                              }
                              break;
                          case 6:
                              //if($key ==0){
                                  $data[$key]=floatval(FinancialModel::where("created_at","like",$val."%")->where('action',7)->sum('cash'));
//                              }else{
//                                  $data[$key]=floatval(FinancialModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->where('action',7)->sum('cash'));
//                              }
                              break;
                      }
                  }
                  break;
              case 'xm':
                  //获取竞标分类id
                  $bidId=TaskTypeModel::where('alias','jingbiao')->pluck('id');
                  //获取雇佣分类id
                  $hireId=TaskTypeModel::where('alias','guyong')->pluck('id');
                  //获取一键发布分类id
                  $publiId=TaskTypeModel::where('alias','kuaijie')->pluck('id');
                  //获取所有vip等级
                  //$vipGrade=VipModel::groupBy("grade")->select("id","grade")->get()->toArray();
                  $getValue=json_decode($request->get("value"));

                  //return in_array(1,$request->get("value"));
                  foreach($weekDate as $key=>$val){
                       if(in_array(1,$getValue)){
                          // if($key == 0){
                               $data["jb"][$key]=intval(TaskModel::where('type_id',$bidId)->where('status','>',1)->where('status','<>',3)->where('verified_at',"like",$val."%")->count());
//                           }else{
//                               $data["jb"][$key]=intval(TaskModel::where('type_id',$bidId)->where('status','>',1)->where('status','<>',3)->where("verified_at",">",$weekDate[$key -1])->where("verified_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                           }

                       }
                      if(in_array(2,$getValue)){
                          //if($key ==0){
                              $data["gy"][$key]=intval(TaskModel::where('type_id',$hireId)->where('status','>',1)->where('verified_at',"like",$val."%")->count());
//                          }else{
//                              $data["gy"][$key]=intval(TaskModel::where('type_id',$hireId)->where('status','>',1)->where("verified_at",">",$weekDate[$key -1])->where("verified_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }
                      if(in_array(3,$getValue)){
                          //if($key ==0){
                              $data["yj"][$key]=intval(TaskPublishingModel::where("created_at","like",$val."%")->count());
//                          }else{
//                              $data["yj"][$key]=intval(TaskPublishingModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }
                      if(in_array(4,$getValue)){
                          //if($key ==0){
                              $data["jbs"][$key]=intval(WorkModel::where('created_at',"like",$val."%")->distinct("task_id")->count());
//                          }else{
//                              $data["jbs"][$key]=intval(WorkModel::where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->distinct("task_id")->count());
//                          }
                      }
//                      switch ($request->get('value')){
//                          case 1:
//                              $data[$key]=intval(TaskModel::where('type_id',$bidId)->where('status','>',1)->where('status','<>',3)->where('verified_at',"like",$val."%")->count());
//                              break;
//                          case 2:
//                              $data[$key]=intval(TaskModel::where('type_id',$hireId)->where('status','>',1)->where('verified_at',"like",$val."%")->count());
//                              break;
//                          case 3:
//                              $data[$key]=intval(TaskModel::where('type_id',$publiId)->where('created_at',"like",$val."%")->count());
//                              break;
//                      }
                  }
                  break;
              case "fa":
                  foreach($weekDate as $key=>$val){
                      $getValue=json_decode($request->get("value"));
                      if(in_array(1,$getValue)){
                          //if($key ==0){
                              $data["xx"][$key]=intval(GoodsModel::whereIn('status',[1,2])->where('updated_at',"like",$val."%")->count());
//                          }else{
//                              $data["xx"][$key]=intval(GoodsModel::whereIn('status',[1,2])->where("updated_at",">",$weekDate[$key -1])->where("updated_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }

                      }
                      if(in_array(2,$getValue)){
                         // if($key == 0){
                              $data["gx"][$key]=intval(GoodsModel::whereIn('status',[1,2])->where('is_customized',1)->where('updated_at',"like",$val."%")->count());
//                          }else{
//                              $data["gx"][$key]=intval(GoodsModel::whereIn('status',[1,2])->where('is_customized',1)->where("updated_at",">",$weekDate[$key -1])->where("updated_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }
                      if(in_array(3,$getValue)){
                         // if($key ==0){
                              $data["zx"][$key]=intval(ProgrammeEnquiryMessageModel::where("type",1)->where('created_at',"like",$val."%")->count());
//                          }else{
//                              $data["zx"][$key]=intval(ProgrammeEnquiryMessageModel::where("type",1)->where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }
                      if(in_array(4,$getValue)){
                         // if($key ==0){
                              $data["sfa"][$key]=intval(GoodsModel::whereIn("status",[0,1,2])->where('created_at',"like",$val."%")->count());
//                          }else{
//                              $data["sfa"][$key]=intval(GoodsModel::whereIn("status",[0,1,2])->where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }
//                      switch ($request->get('value')){
//                          case 1:
//                              $data[$key]=intval(GoodsModel::whereIn('status',[1,2])->where('updated_at',"like",$val."%")->count());
//                              break;
//                          case 2:
//                              $data[$key]=intval(GoodsModel::whereIn('status',[1,2])->where('is_customized',1)->where('updated_at',"like",$val."%")->count());
//                              break;
//                          case 3:
//                              $data[$key]=intval(ProgrammeEnquiryMessageModel::where("type",1)->where('created_at',"like",$val."%")->count());
//                              break;
//                      }
                  }
                  break;
              case "yh":
                  foreach($weekDate as $key=>$val){
                      $getValue=json_decode($request->get("value"));
                      if(in_array(1,$getValue)){
                         // if($key == 0){
                              $data['gz'][$key]=intval(UserModel::where("type",1)->where('created_at',"like",$val."%")->count());
//                          }else{
//                              $data['gz'][$key]=intval(UserModel::where("type",1)->where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }else if(in_array(2,$getValue)){
                          //if($key ==0){
                              $data['fws'][$key]=intval($facilitator[$key]=UserModel::where("type",2)->where('created_at',"like",$val."%")->count());
//                          }else{
//                              $data['fws'][$key]=intval($facilitator[$key]=UserModel::where("type",2)->where("created_at",">",$weekDate[$key -1])->where("created_at","<",date("Y-m-d",strtotime("+1 day ,".$val)))->count());
//                          }
                      }
//                      switch ($request->get('value')){
//                          case 1:
//                              $data[$key]=intval(UserModel::where("type",1)->where('created_at',"like",$val."%")->count());
//                              break;
//                          case 2:
//                              $data[$key]=intval($facilitator[$key]=UserModel::where("type",2)->where('created_at',"like",$val."%")->count());
//                              break;
//                      }
                  }
                  break;
              case "vip":
                  //获取所有vip等级
                  $vipGrade=VipModel::groupBy("grade")->orderBy("grade","asc")->lists("id")->toArray();
                  foreach($weekDate as $key=>$val){
                      $getValue=json_decode($request->get("value"));
                      foreach ($vipGrade as $vk=>$vv){
                          $data[$vk][$key]=0;
                          //foreach($getValue as $k=>$v){
                              if(in_array($vv,$getValue)){
                                  $data[$vk][$key]=intval(VipUserOrderModel::where('vipid',$vv)->where("created_at","like",$val."%")->where("pay_status",2)->count());
                              }//else{
                                  //$data[$k][$key]=0;
                             // }
                         // }
                      }
                      
                  }
          }
         return $data;
     }

    /**
     * 文件上传控制
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileUpload(Request $request)
    {
        $file = $request->file('file');
        $relation_type = $request->get('relation_type');
        $result = array();
        //判断关联对象是否存在
        if(!$relation_type){
            $result['status'] = 'failure';
            $result['message'] = '非法操作';
            return $result;
        }

        //判断文件是否上传成功
        $result1 = json_decode(\FileClass::uploadFile($file,$relation_type),true);
        if(!is_array($result1['data']) || $result1['code'] != 200) {
            $result['status'] = 'failure';
            $result['message'] = $result1['message'] ;
            return $result;
        }

        $attachment = AttachmentModel::create([
            'name'       => $result1['data']['name'],
            'type'       => $result1['data']['type'],
            'size'       => $result1['data']['size'],
            'url'        => $result1['data']['url'],
            'disk'       => $result1['data']['disk'],
            'user_id'    => $result1['data']['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $data = $result1['data'];
        $data['id'] = $attachment['id'];
        $html = AttachmentModel::getAttachmentHtml($data);//生成html
        $result['data'] = $data;
        $result['html'] = $html;
        $result['status'] = 'success';
        return $result;
    }

    /**
     * .方案文档上传--文件doc上传控制
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gooddocUpload(Request $request)
    {
        $file = $request->file('file');
        $relation_type = $request->get('relation_type');
        $result = array();
        //判断关联对象是否存在
        if(!$relation_type){
            $result['status'] = 'failure';
            $result['message'] = '非法操作';
            return $result;
        }

        //判断文件是否上传成功
        $result1 = json_decode(\FileClass::uploadFile($file,$relation_type),true);
        if(!is_array($result1['data']) || $result1['code'] != 200) {
            $result['status'] = 'failure';
            $result['message'] = $result1['message'];
            return $result;
        }

        $attachment = AttachmentModel::create([
            'name'       => $result1['data']['name'],
            'type'       => $result1['data']['type'],
            'size'       => $result1['data']['size'],
            'url'        => $result1['data']['url'],
            'disk'       => $result1['data']['disk'],
            'user_id'    => $result1['data']['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $data = $result1['data'];
        $data['id'] = $attachment['id'];
        $html = AttachmentModel::getAttachmentGoodsDocHtml($data);//生成html
        $result['data'] = $data;
        $result['html'] = $html;
        $result['status'] = 'success';
        return $result;
    }

    /**
     * .全局--中电快购列表页--中电快购图片上传
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileUploadszdfastbuy(Request $request)
    {
        $file = $request->file('file');
        $relation_type = $request->get('relation_type');
        $result = array();
        //判断关联对象是否存在
        if(!$relation_type){
            $result['status'] = 'failure';
            $result['message'] = '非法操作';
            return $result;
        }

        //判断文件是否上传成功
        $result1 = json_decode(\FileClass::uploadFile($file,$relation_type),true);
        if(!is_array($result1['data']) || $result1['code'] != 200) {
            $result['status'] = 'failure';
            $result['message'] = $result1['message'];
            return $result;
        }

        $attachment = AttachmentModel::create([
            'name'       => $result1['data']['name'],
            'type'       => $result1['data']['type'],
            'size'       => $result1['data']['size'],
            'url'        => $result1['data']['url'],
            'disk'       => $result1['data']['disk'],
            'user_id'    => $result1['data']['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $data = $result1['data'];
        $data['id'] = $attachment['id'];
        $html = AttachmentModel::getAttachmentzdfastbuyHtml($data);//生成html
        $result['data'] = $data;
        $result['html'] = $html;
        $result['status'] = 'success';
        return $result;
    }
    /**
     * 附件删除
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileDelete(Request $request)
    {
        $result = AttachmentModel::destroy($request->get('id'));
        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => '删除失败！']);
        }
        return response()->json(['errCode' => 1, 'errMsg' => '删除成功！']);
    }

}
