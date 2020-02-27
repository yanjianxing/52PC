<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\User\Model\PromoteModel;
use App\Modules\User\Model\PromoteTypeModel;
use App\Modules\Manage\Model\CouponModel;
use App\Modules\Manage\Model\PromoteFreegrantModel;
use App\Modules\Manage\Model\UserCouponModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipConfigModel;

class PromoteController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');

    }

    /**
     * 推广关系
     * @param Request $request
     * @return mixed
     */
    public function promoteRelation(Request $request)
    {
        $merge = $request->all();
        $list = PromoteModel::whereRaw('1=1');
        //推广人查询
        if($request->get('from_name')){
            $list = $list->where('from.nickname','like','%'.$request->get('from_name').'%');
        }
        //被推广人查询
        if($request->get('to_name')){
            $list = $list->where('to.nickname','like','%'.$request->get('to_name').'%');
        }
        //推广时间
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('promote.created_at', '>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('promote.created_at', '<',$end);
        }
        $list = $list->leftJoin('user_detail as from','from.uid','=','promote.from_uid')
            ->leftJoin('user_detail as to','to.uid','=','promote.to_uid')
            ->select('promote.*','from.nickname as from_name','to.nickname as to_name')
            ->orderBy('promote.created_at','DESC')->paginate(10);
        $data = array(
            'merge' => $merge,
            'list' => $list
        );
        $this->theme->setTitle('推广关系');
        return $this->theme->scope('manage.entendrelation',$data)->render();
    }

    /**
     * 推广财务
     * @param Request $request
     * @return mixed
     */
    public function promoteFinance(Request $request)
    {
        $list = PromoteModel::where('promote.status',2);
        $merge = $request->all();
        //推广人查询
        if($request->get('from_name')){
            $list = $list->where('from.nickname','like','%'.$request->get('from_name').'%');
        }
        //被推广人查询
        if($request->get('to_name')){
            $list = $list->where('to.nickname','like','%'.$request->get('to_name').'%');
        }
        //推广时间
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('promote.created_at', '>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('promote.created_at', '<',$end);
        }
        $list = $list->leftJoin('user_detail as from','from.id','=','promote.from_uid')
            ->leftJoin('user_detail as to','to.id','=','promote.to_uid')
            ->select('promote.*','from.nickname as from_name','to.nickname as to_name')
            ->orderBy('promote.created_at','DESC')->paginate(10);
        $data = array(
            'merge' => $merge,
            'list' => $list
        );
        $this->theme->setTitle('推广财务');

        return $this->theme->scope('manage.entendfinance',$data)->render();
    }


    /**
     * 推广配置视图
     * @return mixed
     */
    public function promoteConfig()
    {
        //查询注册推广
        $promoteType = PromoteTypeModel::where('code_name','ZHUCETUIGUANG')->first();
        $data = array(
            'promote_type' => $promoteType
        );
        $this->theme->setTitle('推广配置');
        return $this->theme->scope('manage.entendConfig',$data)->render();
    }

    /**
     * 保存推广配置
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postPromoteConfig(Request $request)
    {
        $data = $request->except('_token');
        $arr = array(
            'is_open' => $data['is_open'],
            'register_price' => $data['register_price'],
            'bags_price' => $data['bags_price'],
            'apply_price' => $data['apply_price'],
            'updated_at' => date('Y-m-d H:i:s',time())
        );
        $res = PromoteTypeModel::where('code_name','ZHUCETUIGUANG')->update($arr);
        if($res){
            return redirect('/manage/promoteConfig')->with(array('message' => '操作成功'));
        }else{
            return redirect('/manage/promoteConfig')->with(array('message' => '操作失败'));
        }
    }


    public function couponList(Request $request){
        $list = CouponModel::whereRaw('1=1');
        if($request->get('name')){
            $list = $list->where('name','like','%'.$request->get('name').'%');
        }
         //状态筛选
        if ($request->get('status') != '' && intval($request->get('status')) >= 0 && intval($request->get('status')) < 99 && is_numeric($request->get('status')) ){
            $list = $list->where('status', $request->get('status'));
        }
        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $list = $list->orderBy($by, $order)->paginate($paginate);
        $data = [
            'list'=>$list,
            'name'=>$request->get('name'),
            'status'=>$request->get('status')
        ];
        $this->theme->setTitle('优惠券列表');
        return $this->theme->scope('manage.couponList',$data)->render();
    }

    public function editCoupon(Request $request){
        $typearr = [0=>'全部',1=>'发包(增值服务)','2'=>'vip会员','3'=>'竞标卡','4'=>'方案询价','5'=>'方案订单','6'=>'文章投稿'];
        $data = array('typearr'=>$typearr);
        if($request->get('id')){
            $result = CouponModel::where('id',$request->get('id'))->first()->toArray();

            $data = [
                'id' =>$request->get('id'),
                'result'=>$result,
                'typearr'=>$typearr,
            ];
        }
        $request->get('id') ? $this->theme->setTitle('优惠券编辑') : $this->theme->setTitle('优惠券新增');
        return $this->theme->scope('manage.editCoupon',$data)->render();
    }

    public function postEditCoupon(Request $request){
        $data = $request->except('_token');
        if(!empty($data['id'])){
            $start_time = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start_time'));
            $data['start_time'] = date('Y-m-d H:i:s',strtotime($start_time));
            $end_time = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end_time'));
            $data['end_time'] = date('Y-m-d H:i:s',strtotime($end_time));
            $result = CouponModel::where('id',$data['id'])->update($data);
            if(false === $result)
                return redirect()->back()->with('error','优惠券编辑失败！');
        }else{
            $start_time = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start_time'));
            $data['start_time'] = date('Y-m-d H:i:s',strtotime($start_time));
            $end_time = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end_time'));
            $data['end_time'] = date('Y-m-d H:i:s',strtotime($end_time));
            $data['created_at'] = date("Y-m-d H:i:s",time());
            $result1 = CouponModel::create($data);
            if(false === $result1)
                return redirect()->back()->with('error','优惠券新增失败！');

        }
        return redirect()->to('manage/couponList')->with('message','操作成功！');
    }

    public function changeCouponStatus(Request $request, $id , $status){
        $url = '/manage/couponList/';
        $arr = array('status'=>$status);
        $result = CouponModel::where('id',$id)->update($arr);
        if (!$result) {
            return redirect()->to($url)->with(array('message' => '操作失败'));
        }
        return redirect()->to($url)->with(array('message' => '操作成功'));
    }

    /**
     * 删除优惠券
     */
    public function couponDelete($id){
        $coupon = CouponModel::where('id', $id)->first();
        $usercoupon=UserCouponModel::where('coupon_id',$id);
        if (!empty($coupon)){
            $status = $coupon->delete();
            if(!empty($usercoupon)){
                $res = $usercoupon->delete();
            }
            if ($status){
                return redirect()->back()->with(['message' => '操作成功']);
            }
        }
        return redirect()->back()->with(['error' => '删除失败']);
    }

    public function userCouponList(Request $request){
        $list = UserCouponModel::whereRaw('1=1');
        if($request->get('keyworld')){
            $keyworld = $request->get('keyworld');
            $list = $list->where(function($query) use ($keyworld){
                    $query->where("coupon.name","like","%".$keyworld."%")
                          ->orwhere("user_coupon.id",$keyworld)
                          ->orwhere("user_detail.nickname","like","%".$keyworld."%");
            });
        }
        if ($request->get('status') != '' && intval($request->get('status')) >= 0 && intval($request->get('status')) < 99 && is_numeric($request->get('status')) ){
            $list = $list->where("user_coupon.status","=",$request->get('status'));
        }
        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $list = $list->leftJoin("user_detail","user_detail.uid","=","user_coupon.uid")
                     ->leftJoin("coupon","coupon.id","=","user_coupon.coupon_id")
                     ->select("user_coupon.*","user_detail.nickname as usersname","coupon.name as couponname")
                     ->orderBy($by, $order)->paginate($paginate);
        $data = [
            'list'=>$list,
            'keyworld'=>$request->get('keyworld'),
            'status'=>$request->get('status'),
        ];
        $this->theme->setTitle('优惠券使用列表');
        return $this->theme->scope('manage.userCouponList',$data)->render();
    }


    public function freegrant(){
        $actionarr = array('1'=>'注册','2'=>'发包(审核通过)','3'=>'竞标','4'=>'选中','5'=>'托管','6'=>'上传方案','7'=>'开通店铺','8'=>'投稿','9'=>'普通会员被选中','10'=>'青铜会员被选中','11'=>'白银会员被选中','12'=>'黄金会员被选中','13'=>'服务商累计被选中','14'=>'vip续费','15'=>'分享','16'=>'雇主累计选中');
        //获取vip列表
        // $prizearr=VipModel::getVipList();
        //获取vip列表
        $vip=VipModel::where("status",2)->select("id","price","name","price_time","vipconfigid","grade","vip_pic")->get()->toArray();
        $vipConfigId=\CommonClass::setArrayKey($vip,"vipconfigid");
        //获取次卡
        $subCard=VipConfigModel::LeftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.vip_cika",1)->whereIn("vip_config.id",array_keys($vipConfigId))
                               ->select("vip.name","vip.grade","vip_config.vip_cika_price","vip_config.id","vip_config.vip_cika_num","vip_config.vip_cika_pic")->get()->toArray();
        //获取发放优惠券
        $coupon = CouponModel::where("is_grant",2)->where("status",2)->select("id","name")->get()->toArray();

        $prizearr = array_merge($vip,$subCard,$coupon);
        // dd($prizearr);

        $data = array(
            'actionarr' => $actionarr,
            'prizearr'  => $prizearr
        );
        $this->theme->setTitle('自动发放配置');
        return $this->theme->scope('manage.freegrant',$data)->render();
    }

    public function postfreegrant(Request $request){
        $data = $request->except('_token');
        $data['created_at'] = date("Y-m-d H:i:s");
        if($data['action'] == '13'){
            if(!empty($data['selstart_time'])){
                $data['selstart_time'] = preg_replace('/([\x80-\xff]*)/i', '', $request->get('selstart_time'));
            }
            if(!empty($data['selend_time'])){
                $data['selend_time'] = preg_replace('/([\x80-\xff]*)/i', '', $request->get('selend_time'));
            }
        }
        
        $result = PromoteFreegrantModel::insert($data);
        if($result){
            return redirect()->back()->with(['message' => '操作成功']);
        }
        return redirect()->back()->with(['message' => '操作失败']);
    }

    public function freegrantlist(Request $request){
        $list = PromoteFreegrantModel::whereRaw('1=1');
        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $actionarr = array('1'=>'注册','2'=>'发包(审核通过)','3'=>'竞标','4'=>'选中','5'=>'托管','6'=>'上传方案','7'=>'开通店铺','8'=>'投稿','9'=>'普通会员被选中','10'=>'青铜会员被选中','11'=>'白银会员被选中','12'=>'黄金会员被选中','13'=>'服务商累计被选中','14'=>'vip续费','15'=>'分享','16'=>'雇主累计选中');
        $prizetypearr = array('1'=>'会员','2'=>'竞标卡','3'=>'优惠券');
        $list = $list->orderBy($by, $order)->paginate($paginate);
        $data = [
            'list'=>$list,
            'actionarr' => $actionarr,
            'prizetypearr' => $prizetypearr,
        ];
        $this->theme->setTitle('自动发放列表');
        return $this->theme->scope('manage.freegrantlist',$data)->render();
    }

    public function changefreegrantstatus(Request $request,$id,$status,$del){
        $arr = array('is_open'=>$status);
        if($id && $status && empty($del)){
            $result = PromoteFreegrantModel::where("id",$id)->update($arr);
            if (!$result) {
                return redirect()->back()->with(array('message' => '操作失败'));
            }
            return redirect()->back()->with(array('message' => '操作成功'));
        }
        if($id && $del && empty($status)){
            $result = PromoteFreegrantModel::where("id",$id)->delete();
            if (!$result) {
                return redirect()->back()->with(array('message' => '操作失败'));
            }
            return redirect()->back()->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }
}

