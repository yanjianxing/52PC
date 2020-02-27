<?php
/**
 * Created by PhpStorm.
 * Date: 2018/11/09
 * Time: 13:22
 */
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Manage\Http\Requests\PackageRequest;
use App\Modules\Manage\Http\Requests\PrivilegesRequest;
use App\Modules\Manage\Http\Requests\VipAuthRequest;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Manage\Model\VipConfigModel;
use App\Modules\User\Model\UserVipCardModel;
use App\Modules\Vipshop\Models\InterviewModel;
use App\Modules\Vipshop\Models\PackageModel;
use App\Modules\Vipshop\Models\PrivilegesModel;
use App\Modules\Vipshop\Models\ShopPackageModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Cache;
use Theme;
use DB;
use Validator;

class VipController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('VIP列表');
        $this->theme->set('manageType', 'auth');
    }

    /**
     * vip列表
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function vipList(request $request)
    {

        $list = VipModel::whereraw('1=1');
        $by = $request->get('by') ? $request->get('by') : 'vip.created_at';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $list = $list->leftjoin('vip_config','vip_config.id','=','vip.vipconfigid')
                     ->select('vip.*','vip_config.vip_cika_price','vip_config.vip_rika_price')
                     ->orderBy($by,$order)
                     ->paginate($paginate);
        $listArr = $list->toArray();
        $data = ['list' => $listArr,'listobj'=>$list];
        return $this->theme->scope('manage.vipList',$data)->render();
    }

    public function changevipstatus(request $request,$id , $status)
    {
        $url = '/manage/vipList';
        $arr = array('status'=>$status);
        $result = VipModel::where('id',$id)->update($arr);
        if (!$result) {
            return redirect()->to($url)->with(array('message' => '操作失败'));
        }
        return redirect()->to($url)->with(array('message' => '操作成功'));
    }

    public function addvip(Request $request){
        $result = array();
        if($request->get('id')){
            $res = VipModel::whereraw("1=1");
            $res = $res->leftjoin("vip_config",'vip.vipconfigid','=','vip_config.id')
                       ->select("vip.id as vipid","vip.*","vip_config.id as vipconfig_id","vip_config.*")
                       ->where('vip.id',$request->get('id'))
                       ->first();
            $result = $res->toArray();
            if($result['tool_zk']){
                $result['tool_zk']=json_decode($result['tool_zk'],true);
            }
        }
        $vipname = array('2'=>'青铜会员','3'=>'白银会员','4'=>'黄金会员','5'=>'铂金会员','6'=>'王者会员');
        //获取所有的工具
        $toolList=ServiceModel::where("type",4)->where("status",1)->get();
        $data = [
                'id' =>$request->get('id'),
                'result'=>$result,
                'vipname'=>$vipname,
                'toolList'=>$toolList,   
            ];
        $request->get('id') ? $this->theme->setTitle('vip编辑') : $this->theme->setTitle('vip新增');
        return $this->theme->scope('manage.addvip',$data)->render();
    }
    public function postaddvip(Request $request){
        $data = $request->except('_token','vip_rika_pic','vip_cika_pic','vip_pic');
        DB::beginTransaction();
        $data1['jb_times'] = $data['jb_times'];
        $data1['jb_price'] = $data['jb_price'];
        $data1['facs_logo'] = $data['facs_logo'];
        $data1['facs_daohang'] = $data['facs_daohang'];
        $data1['facs_muban'] = $data['facs_muban'];
        $data1['facs_slide'] = $data['facs_slide'];
        $data1['facs_mobile'] = $data['facs_mobile'];
        $data1['facs_start_xunjia'] = $data['facs_start_xunjia'];
        $data1['facs_accept_xunjia'] = $data['facs_accept_xunjia'];
        $data1['facs_yaoqingjb'] = $data['facs_yaoqingjb'];
        $data1['facs_hangye_num'] = $data['facs_hangye_num'];
        $data1['facs_technology_num'] = $data['facs_technology_num'];
        $data1['identity_label'] = $data['identity_label'];
        $data1['identity_mobile'] = $data['identity_mobile'];
        $data1['identity_project'] = $data['identity_project'];
        $data1['retail_fuwushang'] = $data['retail_fuwushang'];
        $data1['retail_fanganchaoshi'] = $data['retail_fanganchaoshi'];
        $data1['appreciation_zhiding'] = $data['appreciation_zhiding'];
        $data1['appreciation_jiaji'] = $data['appreciation_jiaji'];
        $data1['appreciation_duijie'] = $data['appreciation_duijie'];
        $data1['appreciation_zhitongche'] = isset($data['appreciation_zhitongche'])?$data['appreciation_zhitongche']:'';
        $data1['appreciation_lchengyijin'] = isset($data['appreciation_lchengyijin'])?$data['appreciation_lchengyijin']:'';
        $data1['appreciation_hchengyijin'] = isset($data['appreciation_hchengyijin'])?$data['appreciation_hchengyijin']:'';
        $data1['appreciation_zixun'] = $data['appreciation_zixun'];
        $data1['vip_cika'] = $data['vip_cika'];
        $data1['vip_cika_price'] = $data['vip_cika_price'];
        $data1['vip_rika'] = $data['vip_rika'];
        $data1['vip_rika_price'] = $data['vip_rika_price'];
        $data1['created_at'] = date("Y-m-d H:i:s",time());
        $data1['tool_zk']=json_encode($data['tool_zk']);



        $data1['vip_cika_num']=1;
        $data1['vip_rika_num']=3;
        $vip_cika_pic = $request->file('vip_cika_pic');
        if ($vip_cika_pic) {
           $result = \FileClass::uploadFile($vip_cika_pic,'sys');
            $result = json_decode($result,true);
            $data1['vip_cika_pic'] = $result['data']['url'];
        }

        $vip_rika_pic = $request->file('vip_rika_pic');
        if ($vip_rika_pic) {
           $result1 = \FileClass::uploadFile($vip_rika_pic,'sys');
            $result1 = json_decode($result1,true);
            $data1['vip_rika_pic'] = $result1['data']['url'];
        }

        $vip_pic = $request->file('vip_pic');
        if ($vip_pic) {
           $result2 = \FileClass::uploadFile($vip_pic,'sys');
            $result2 = json_decode($result2,true);
            $data2['vip_pic'] = $result2['data']['url'];
        }
        $vipname = array('1'=>'普通会员','2'=>'青铜会员','3'=>'白银会员','4'=>'黄金会员','5'=>'铂金会员','6'=>'王者会员');
        $data2['name'] = $vipname["$data[name]"];
        $data2['price'] = $data['price'];
        $data2['price_time'] = $data['price_time'];
        $data2['grade'] = $data['name'];
        $data2['vip_sale'] = $data['vip_sale'];
        $data2['vip_sale_price'] = $data['vip_sale_price'];
        $data2['vip_recommend'] = $data['vip_recommend'];
        $data2['sort'] = $data['sort'];
        if(!empty($data['id'])){
            $res = VipModel::where('id',$data['id'])->first()->toArray();
            $vipconfigres = VipConfigModel::where('id',$res['vipconfigid'])->first();
            if(!$vipconfigres || !$res){
                return redirect()->back()->with('error','获取配置信息错误！');
            }
            $result = VipConfigModel::where('id',$res['vipconfigid'])->update($data1);
            $result1 = VipModel::where('id',$data['id'])->update($data2);
        }else{
            $result = VipConfigModel::create($data1);
            $data2['vipconfigid'] = $result['id'];
            $data2['created_at'] = date("Y-m-d H:i:s",time());
            $result1 = VipModel::create($data2);
        }
        if($result==='false' || $result1==='false'){
            DB::rollback();
            return redirect()->back()->with('error','操作失败！');
        }
        DB::commit();
        return redirect()->to('manage/vipList')->with('massage','操作成功！');
    }
    /*
    *  @function vip购买记录
    */
    public function vipUserOrder(request $request)
    {
        $list = VipUserOrderModel::whereraw('1=1');
        if($request->get('keyworld')){
            $keyworld = $request->get('keyworld');
            $list = $list->where(function($query) use ($keyworld){
                $query->where('users.name','like','%'.$keyworld.'%')
                      ->orwhere('user_viporder.id',$keyworld);
            });
        }
        if ($request->get('combo') != '' && intval($request->get('combo')) >= 0  && is_numeric($request->get('combo')) ){
            $list = $list->where('user_viporder.type','1')->where("vip.id","=",$request->get('combo'));
        }

        if ($request->get('status') != '' && intval($request->get('status')) > 0  && is_numeric($request->get('status')) ){
            if($request->get('status') !=3){
                $list = $list->where("user_viporder.status",$request->get('status'));
            }else{
                $list = $list->where("user_viporder.frozen",1);
            }

        }

        $timeFrom = $request->get('timefrom') ? $request->get('timefrom') : 'user_viporder.pay_time';
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where($timeFrom,'>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where($timeFrom,'<',$end);
        }
        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = $list->leftjoin('vip','vip.id','=','user_viporder.vipid')
                     ->leftjoin('users','users.id','=','user_viporder.uid')
                     ->select('user_viporder.*','users.name as uname','vip.name as vname','vip.price_time')
                     ->orderBy($by,$order)
                     ->paginate($paginate);
        $listArr = $list->toArray();
        $vipres = VipModel::whereraw('1=1')->get()->toArray();
        $data = [
            'listobj' => $list,
            'list' => $listArr,
            'merge' => $request->all(),
            'keyworld' => $request->get('keyworld'),
            'vipres'   =>$vipres,
            'combo'   =>$request->get('combo'),
            'status'   =>$request->get('status'),
            'start'    =>$request->get('start'),
            'end'    =>$request->get('end')
        ];
        return $this->theme->scope('manage.vipUserOrder',$data)->render();
    }


    /*
*  @function .用户竞标卡列表
*/
    public function userVipCardList(request $request)
    {
        $merge=[
            'name'=>$request->get('name'),
            'mobile'=>$request->get('mobile'),
            'level'=>$request->get('level'),
            'timefrom'=>$request->get('timefrom'),
            'register_time_start'=>$request->get('register_time_start'),
            'register_time_end'=>$request->get('register_time_end'),
        ];
        $list=UserVipCardModel::getUserVipCardList($merge,$paginate=10);
        $data = [
            'list'=>$list,
            'merge'=>$merge,
        ];
        return $this->theme->scope('manage.uservipcardlist',$data)->render();
    }

}