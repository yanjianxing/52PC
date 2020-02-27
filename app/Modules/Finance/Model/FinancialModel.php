<?php

namespace App\Modules\Finance\Model;

use App\Modules\Manage\Http\Controllers\TaskRightsController;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Task\Model\TaskRightsModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Modules\Manage\Model\VipModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class FinancialModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'financial';

    protected $fillable = [
        'order_code', 'action', 'pay_type', 'pay_account', 'pay_code', 'cash', 'uid', 'created_at','related_id','coupon','status','remainder'
    ];

    public $timestamps = false;

    /**
     * 创建一条用户的财务
     */
    static function createOne($data)
    {
        $model = new FinancialModel();
        $model->action = $data['action'];
        $model->pay_type = isset($data['pay_type'])?$data['pay_type']:'';
        $model->pay_account = isset($data['pay_account'])?$data['pay_account']:'';
        $model->pay_code = isset($data['pay_code'])?$data['pay_code']:'';
        $model->cash = $data['cash'];
        $model->uid = $data['uid'];
        $model->created_at = date('Y-m-d H:i:s', time());
        $model->coupon=isset($data['coupon'])?$data['coupon']:'';
        $model->status=isset($data['status'])?$data['status']:1;
        $model->related_id=isset($data['related_id'])?$data['related_id']:0;
        $model->remainder = isset($data['remainder'])?$data['remainder']:0;
        $model->pay_account=isset($data['pay_account'])?$data['pay_account']:'';
        $result = $model->save();

        return $result;
    }

    /**
     * 手续费计算
     *
     * @param $cash
     * @return mixed
     */
    static function getFees($cash)
    {
        $config = ConfigModel::getConfigByAlias('cash');
        $config->rule = json_decode($config->rule, true);

        if ($cash <= 1500){
            $fee = $cash * ($config->rule['per_charge_1'] / 100) + $config->rule['per_cash_1'];
        } elseif ($cash > 1500 && $cash <= 15000){
            $fee = $cash * ($config->rule['per_charge_2'] / 100) + $config->rule['per_cash_2'];
        }elseif($cash>15000){
            $fee = $cash * ($config->rule['per_charge_3'] / 100) + $config->rule['per_cash_3'];
        }
        return $fee;
    }

    /*
    *获取用户流水列表
    *@return $result
    *
    */
    static function getFinancialList($keyword=[],$by="id",$order="desc",$paginate=""){
        $userFinance = self::leftJoin('user_detail', 'financial.uid', '=', 'user_detail.uid')
            ->leftJoin('users', 'financial.uid', '=', 'users.id');
        $allcomeArr = \CommonClass::allcomeArr();
        $userFinance = $userFinance->whereIn('financial.action',$allcomeArr);
        if(!empty($keyword) && isset($keyword['uid']) && !empty($keyword['uid'])){
            $userFinance = $userFinance->where('financial.uid', $keyword['uid']);
        }
        if(!empty($keyword) && isset($keyword['username'])  && !empty($keyword['username'])){
            $userFinance = $userFinance->where('users.name', 'like', '%'.trim($keyword['username']).'%');
        }
        if(!empty($keyword) && isset($keyword['action'])  && !empty($keyword['action'])){
            if($keyword['action'] ==1){
                $userFinance = $userFinance->whereIn('financial.action',[1,23]);
            }elseif($keyword['action'] ==2){
                $userFinance = $userFinance->whereIn('financial.action',[2,8]);
            }
            else{
                $userFinance = $userFinance->where('financial.action', $keyword['action']);
            }

        }
        if(!empty($keyword) && isset($keyword['start'])  && !empty($keyword['start'])){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $keyword['start']);
            $start = date('Y-m-d H:i:s',strtotime($start));
            $userFinance = $userFinance->where('financial.created_at', '>', $start);
        }
        if(!empty($keyword) && isset($keyword['end'])  && !empty($keyword['end'])){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $keyword['end']);
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $userFinance = $userFinance->where('financial.created_at', '<', $end);
        }
        if(!isset($keyword['action']) || empty($keyword['action']))$keyword['action'] = '';
        switch ($keyword['action']) {
            case '1':
            case '23':
                $result = $userFinance->leftJoin('task', 'financial.related_id','=','task.id')
                    ->select('financial.*',  'user_detail.nickname as uname','users.type as utype','users.name as usersname','task.title as gtitle');
                break;
            case '2':
                $result = $userFinance->leftJoin('programme_order', 'financial.related_id','=','programme_order.id')
                        ->leftJoin('goods','programme_order.programme_id','=',"goods.id")
                        ->select('financial.*',  'user_detail.nickname as uname','users.type as utype','users.name as usersname','goods.title as gtitle');
                break;
            case '3':
            case '4':
                $result = $userFinance->select('financial.*', 'user_detail.balance', 'user_detail.nickname as name','users.name as usersname');
                break;
            case '5':
                $result = $userFinance->leftJoin('sub_order', 'financial.related_id','=','sub_order.id')
                        ->select('financial.*', 'user_detail.balance', 'user_detail.nickname as uname','users.name as usersname','sub_order.title as stitle');
                break;
            case '6'://购买工具
                $result=$userFinance->leftJoin('user_tool', 'financial.related_id','=','user_tool.id')
                       ->leftJoin("service","user_tool.tool_id","=","service.id")
                    ->select('financial.*', 'user_detail.balance', 'user_detail.nickname as uname','users.name as usersname','service.title as stitle');
                break;
            case '7':
                $result = $userFinance->leftJoin('article', 'financial.related_id','=','article.id')
                        ->select('financial.*','article.title','article.id as artid','article.from', 'user_detail.balance', 'user_detail.nickname as name','users.name as usersname');
                break;
            case '8':
            case '9':
                $result = $userFinance->select('financial.*','user_detail.realname', 'user_detail.balance', 'user_detail.nickname as uname','users.name as usersname');
                break;
            case '10':
                // $result=TaskRightsModel::where("task_rights.status",1)->leftjoin("users as fu","task_rights.from_uid","=","fu.id")
                //             ->leftJoin("users as tu","task_rights.to_uid","=","tu.id")->leftJoin("task","task_rights.task_id","=","task.id")
                //     ->select('task_rights.*','task.title','task.bounty','fu.name as fname',"tu.name as tname");
                $result = $userFinance->leftJoin('task_rights', 'financial.related_id','=','task_rights.id')
                   ->leftJoin("task","task_rights.task_id","=","task.id")
                   ->leftjoin("users as fu","task_rights.from_uid","=","fu.id")
                   ->leftJoin("users as tu","task_rights.to_uid","=","tu.id")
                   ->select('financial.*','task.title','fu.name as fname','fu.id as fuid','tu.id as tuid',"tu.name as tname");
                break;
            case '11':
                $result = $userFinance->leftJoin('promote', 'financial.related_id','=','promote.id')
                        ->select('financial.*','promote.to_uid','promote.type as protype', 'user_detail.balance', 'user_detail.nickname as usersname','users.name as usersname');
                break;
            case '12':
                $result = $userFinance->leftJoin('user_viporder','financial.related_id','=','user_viporder.id')
                          ->select('financial.*','user_viporder.vipid','user_detail.realname', 'user_detail.nickname as uname','users.name as usersname', 'user_detail.balance');
                break;
            default:
               $result = $userFinance->select('financial.*', 'user_detail.balance', 'user_detail.nickname as name','users.name as usersname');
                break;
        }
        $result=$result->orderBy($by, $order);
        if($paginate){
            $result = $result->paginate($paginate);
            foreach ($result as $key => $value) {
                if(isset($value['vipid'])){
                    $vipInfo = VipModel::where('id',$value['vipid'])->first();
                    if($vipInfo){
                        $vipname=$vipInfo['name'];
                    }
                    else{
                        $vipname="--";
                    }

                }    //购买会员会员名称
                $result[$key]['vipname'] = isset($vipname) ? $vipname : '';
                if(isset($value['to_uid'])){$toname = UserModel::where('id',$value['to_uid'])->first()->name;}   //推广注册人
                $result[$key]['toname'] = isset($toname) ? $toname : '';
            }
        }else{
            $result = $result->get()->chunk(100);
            foreach ($result as $key=>$chunk) {
                foreach ($chunk as $k => $v) {
                    if(isset($v['vipid'])){$vipname = VipModel::where('id',$v['vipid'])->first()->name;} 
                    $result[$key][$k]['vipname'] = isset($vipname) ? $vipname : '';
                    if(isset($v['to_uid'])){$toname = UserModel::where('id',$v['to_uid'])->first()->name;}   //推广注册人
                    $result[$key][$k]['toname'] = isset($toname) ? $toname : '';
                }
            }
        }
        return $result;
    }
}
