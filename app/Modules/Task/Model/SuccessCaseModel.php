<?php

namespace App\Modules\Task\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class SuccessCaseModel extends Model
{
    protected $table = 'success_case';
    protected $fillable = ['uid','username','title','url','pic','cate_id','bidd_num','pub_uid','view_count','created_at','type','desc','province','city','area','deal_at','cash','status','technology_id','period_starttime','period_endtime','appraise','cate_category','is_recommend','workmanner','workquality','workspead','task_id'];
    public  $timestamps = false;  //关闭自动更新时间戳


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel','id','uid');
    }
    
    public function detail()
    {
        return $this->hasOne('App\Modules\User\Model\UserDetailModel','uid','uid')->select('uid','nickname','avatar');
    }

    public function shop()
    {
        return $this->hasOne('App\Modules\Shop\Models\ShopModel','uid','uid')->select('uid','id','shop_name');
    }

    /**
     * 应用领域
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function field()
    {
        return $this->hasOne('App\Modules\Task\Model\TaskCateModel','id','cate_id')->select('id','name');
    }
    /**
     * 根据用户id查询案例列表
     * @param $uid 用户id
     * @param array $merge 搜索条件
     * @return mixed
     */
    static function getSuccessCaseListByUid($uid,$merge=array())
    {
        $successCaseList = SuccessCaseModel::whereRaw('1 = 1');
        if(isset($merge['title'])){
            $successCaseList = $successCaseList->where('success_case.title','like','%'.$merge['title'].'%');
        }
        $successCaseList = $successCaseList->where('success_case.uid',$uid)->where('success_case.type',1)
            ->leftJoin('cate','cate.id','=','success_case.cate_id')
            ->select('success_case.*','cate.name')
            ->orderBy('success_case.created_at','DESC')
            ->paginate(5);
        return$successCaseList;
    }

    /**
     * 根据案例id查询案例详情
     * @param $id 案例id
     * @return mixed
     */
    static function getSuccessInfoById($id)
    {
        $successInfo = SuccessCaseModel::where('id',$id)->first();
        if($successInfo->cate_id){
            $cateInfo = TaskCateModel::where('id',$successInfo->cate_id)->select('id','pid','name')->first();
            $successInfo['cate_name'] = $cateInfo->name;
            if($cateInfo->pid){
                $successInfo['cate_pid'] = $cateInfo->pid;
            }
        }
        return $successInfo;
    }

    /**
     * 获取某店铺的其他案例
     * @param $uid 用户id
     * @param $id 某一案例id
     */
    static function getOtherSuccessByUid($uid,$id,$limit=5)
    {
        $successCaseList = SuccessCaseModel::where('success_case.uid',$uid)->where('success_case.type',1)
            ->where('success_case.id','!=',$id)
            ->leftJoin('cate','cate.id','=','success_case.cate_id')
            ->select('success_case.*','cate.name')
            ->orderBy('success_case.created_at','DESC')
            ->limit($limit)->get()->toArray();
        return $successCaseList;
    }

}
