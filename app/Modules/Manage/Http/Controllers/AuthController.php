<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AlipayAuthModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('认证管理');
        $this->theme->set('manageType', 'auth');
    }


    /**
     * 实名认证详情
     *
     * @param $id
     * @return mixed
     */
    public function realnameAuth($id)
    {
        $id = intval($id);
        $realnameInfo = RealnameAuthModel::where('id', $id)->first();
        if (!empty($realnameInfo)) {
            $data = array(
                'realname' => $realnameInfo
            );
            return $this->theme->scope('manage.realnameauthinfo', $data)->render();
        }
    }

    /**
     * 实名认证列表视图
     *
     * @param Request $request
     * @return mixed
     */
    public function realnameAuthList(Request $request)
    {
        $merge = $request->all();
        $realNameList = RealnameAuthModel::whereRaw('1=1')->where('realname_auth.type','1')->where('is_del',0);
        //用户名
        if ($request->get('username')) {
            $realNameList = $realNameList->where(function($query)use($request){
                $query->where('realname_auth.username','like','%' . $request->get('username') . '%')
                       ->orWhere('realname_auth.id','like','%' . $request->get('username') . '%')
                       ->orWhere('realname_auth.realname','like','%' . $request->get('username') . '%')
                       ->orWhere('user_detail.nickname','like','%' . $request->get('username') . '%');

            });
        }
//        //真实姓名
//        if ($request->get('real_name')) {
//            $realNameList = $realNameList->where('realname','like','%' . $request->get('real_name') . '%');
//        }
        //认证状态筛选
        if ($request->get('status')) {
            switch($request->get('status')){
                case 1:
                    $status = 0;
                    $realNameList = $realNameList->where('realname_auth.status',$status);
                    break;
                case 2:
                    $status = 1;
                    $realNameList = $realNameList->where('realname_auth.status',$status);
                    break;
                case 3:
                    $status = 2;
                    $realNameList = $realNameList->where('realname_auth.status',$status);
                    break;
            }
        }
        //时间筛选
        if($request->get('time_type')){
            $timeType = $request->get('time_type');
            $timeType = 'realname_auth.'.$timeType;
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d 00:00:00',strtotime($start));
                $realNameList = $realNameList->where($timeType,'>',$start);

            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d 23:59:59',strtotime($end)+24*60*60);
                $realNameList = $realNameList->where($timeType,'<',$end);
            }

        }

        $by = $request->get('by') ? $request->get('by') : 'realname_auth.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $realNameList = $realNameList->leftJoin('user_detail','user_detail.uid','=','realname_auth.uid')->select('realname_auth.*','user_detail.nickname')->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'merge' => $merge,
            'realname' => $realNameList,
        );


        $this->breadcrumb->add(array(
            array(
                'label' => '实名认证',
                'url' => '/manage/realnameAuthList'
            ),
            array(
                'label' => '认证列表'
            )
        ));
        $this->theme->set('manageAction', 'realname');
        return $this->theme->scope('manage.realnamelist', $data)->render();
    }


    /**
     * 实名认证处理
     *
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function realnameAuthHandle($id, $action,Request $request)
    {
        $id = intval($id);
        switch ($action) {
            //审核通过
            case 'pass':
                $status = RealnameAuthModel::realnameAuthPass($id);
                break;
            //审核失败
            case 'deny':
                $reason = $request->get('reason')?$request->get('reason'):'';
                $status = RealnameAuthModel::realnameAuthDeny($id,$reason);
                break;
            //软删除记录
            case 'del':
                $status=RealnameAuthModel::where("id",$id)->update(["is_del"=>1]);
        }
        if ($status)
            return back()->with(array('message' => '操作成功'));
    }
    /**
     * 查看用户信息
     *
     * @param $id
     * @return mixed
     */
    public function getBankAuth($id)
    {
        $id = intval($id);
        $info = BankAuthModel::where('id', $id)->first();
        $depositArea=explode(',',$info['deposit_area']);
        $province=null;$city=null;$area=null;
        if(isset($depositArea[0])){//省份
            $province = DistrictModel::where('id',$depositArea[0])->pluck("name");
        }
        if(isset($depositArea[1])){//城市
            $city = DistrictModel::where('id',$depositArea[1])->pluck("name");
        }
        if(isset($depositArea[2])){//地区
            $area = DistrictModel::where('id',$depositArea[2])->pluck("name");
        }
        if (!empty($info)){
            $userDetail = UserDetailModel::where('uid',$info->uid)->first();
            $data = array(
                'bank'      => $info,
                'province'  => $province,
                'city'      => $city,
                'area'      => $area,
                'userInfo'  => $userDetail
            );
            return $this->theme->scope('manage.bankauthinfo', $data)->render();
        }
        return redirect()->back()->with(['error' => '参数错误']);
    }


    /**
     * 银行认证打款
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function bankAuthPay(Request $request)
    {
        $authId = intval($request->get('authId'));
        $pay_to_user_cash = $request->get('pay_to_user_cash');

        $status = BankAuthModel::where('id', $authId)->update(array('pay_to_user_cash' => $pay_to_user_cash, 'status' => 1));
        if ($status)
            return redirect('manage/bankAuthList');
    }



    /**
     * 支付宝认证列表
     *
     * @param Request $request
     * @return mixed
     */
    public function alipayAuthList(Request $request)
    {
        $merge = $request->all();
        $aliPayList = AlipayAuthModel::whereRaw('1=1')->where("alipay_auth.is_del",0);
        //支付宝姓名
        if ($request->get('username')) {
            $aliPayList = $aliPayList->where(function ($query)use($request){
                $query->where('alipay_auth.alipay_name','like','%'.$request->get('username').'%')
                      ->orWhere('alipay_auth.username','like','%'.$request->get('username').'%')
                      ->orWhere('alipay_auth.alipay_account','like','%'.$request->get('username').'%')
                      ->orWhere('alipay_auth.id','like','%'.$request->get('username').'%')
                      ->orWhere('user_detail.nickname','like','%'.$request->get('username').'%');
            });
        }
//        //用户名
//        if ($request->get('username')) {
//            $aliPayList = $aliPayList->where('username','like','%'.$request->get('username').'%');
//        }
//        //支付宝账户
//        if ($request->get('alipay_account')) {
//            $aliPayList = $aliPayList->where('alipay_account','like','%'.$request->get('alipay_account').'%');
//        }
        //认证状态筛选
        if ($request->get('status')) {
            switch($request->get('status')){
                case 1:
                    $status = 0;
                    $aliPayList = $aliPayList->where(function($query) use($status){
                        $query->where('alipay_auth.status',$status)
                            ->orWhereNull('alipay_auth.status');
                    });
                    break;
                case 2:
                    $status = 1;
                    $aliPayList = $aliPayList->where('alipay_auth.status',$status);
                    break;
                case 3:
                    $status = 2;
                    $aliPayList = $aliPayList->where('alipay_auth.status',$status);
                    break;
                case 4:
                    $status = 3;
                    $aliPayList = $aliPayList->where('alipay_auth.status',$status);
                    break;
            }
        }
        //时间筛选
        if($request->get('time_type')){
            $timeType = $request->get('time_type');
            $timeType = 'alipay_auth.'.$timeType;
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d H:i:s',strtotime($start));
                $aliPayList = $aliPayList->where($timeType,'>',$start);

            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d H:i:s',strtotime($end) +24*60*60);
                $aliPayList = $aliPayList->where($timeType,'<',$end);
            }

        }
        $by = $request->get('by') ? $request->get('by') : 'alipay_auth.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $aliPayList = $aliPayList->leftJoin('user_detail','user_detail.uid','=','alipay_auth.uid')->select('alipay_auth.*','user_detail.nickname')->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'merge' => $merge,
            'alipay' => $aliPayList,
        );

        $this->breadcrumb->add(array(
            array(
                'label' => '支付宝认证',
                'url' => '/manage/alipayAuthList'
            ),
            array(
                'label' => '认证列表'
            )
        ));
        $this->theme->set('manageAction', 'alipay');
        return $this->theme->scope('manage.alipaylist', $data)->render();
    }


    /**
     * 支付宝认证处理
     *
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function alipayAuthHandle($id, $action,Request $request)
    {
        $id = intval($id);
        switch ($action) {
            //审核通过
            case 'pass':
                $status = AlipayAuthModel::alipayAuthPass($id);
                break;
            //审核失败
            case 'deny':
                $reason = $request->get('reason') ? $request->get('reason') : '';
                $status = AlipayAuthModel::alipayAuthDeny($id,$reason);
                break;
            //删除
            case 'del':
                $status = AlipayAuthModel::where('id',$id)->update(['is_del'=>1]);
                break;
        }
        if ($status)
            return redirect('/manage/alipayAuthList')->with(array('message' => '操作成功'));
    }


    /**
     * 支付宝认证详情
     *
     * @param $id
     * @return mixed
     */
    public function getAlipayAuth($id)
    {
        $id = intval($id);
        $info = AlipayAuthModel::where('id', $id)->first();

        if (!empty($info)){
            $data = array(
                'alipay' => $info
            );
            return $this->theme->scope('manage.alipayauthinfo', $data)->render();
        }
    }

    /**
     * 支付宝后台打款
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function alipayAuthPay(Request $request)
    {
        $authId = intval($request->get('authId'));
        $pay_to_user_cash = $request->get('pay_to_user_cash');

        $status = AlipayAuthModel::where('id', $authId)->update(array('pay_to_user_cash' => $pay_to_user_cash, 'status' => 1));
        if ($status ){
            $info = AlipayAuthModel::find($authId);
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('alipay_auth_pay',$user,$templateArr,$templateArr);
            return redirect('manage/alipayAuthList');
        }
            
    }

    /**
     * 银行认证列表
     *
     * @param Request $request
     * @return mixed
     */
    public function bankAuthList(Request $request)
    {
        $merge = $request->all();
        $bankList = BankAuthModel::whereRaw('1 = 1')->where("bank_auth.is_del",0);
        //银行账户
//        if ($request->get('bankAccount')) {
//            $bankList = $bankList->where('bank_account','like','%'.$request->get('bankAccount').'%');
//        }
        //用户名
        if ($request->get('username')) {
            $bankList = $bankList->where(function($query)use($request){
                $query->where('bank_auth.username','like','%'.$request->get('username').'%')
                    ->orWhere('bank_auth.bank_account','like','%'.$request->get('username').'%')
                    ->orWhere('bank_auth.id','like','%'.$request->get('username').'%')
                    ->orWhere('user_detail.nickname','like','%'.$request->get('username').'%');
            });
        }
        //认证状态筛选
        if ($request->get('status')) {
            switch($request->get('status')){
                case 1:
                    $status = 0;
                    $bankList = $bankList->where('bank_auth.status',$status);
                    break;
                case 2:
                    $status = 1;
                    $bankList = $bankList->where('bank_auth.status',$status);
                    break;
                case 3:
                    $status = 2;
                    $bankList = $bankList->where('bank_auth.status',$status);
                    break;
                case 4:
                    $status = 3;
                    $bankList = $bankList->where('bank_auth.status',$status);
                    break;
            }
        }
        //时间筛选
        if($request->get('time_type')){
            $timeType = $request->get('time_type');
            $timeType = 'bank_auth.'.$timeType;
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d H:i:s',strtotime($start));
                $bankList = $bankList->where($timeType,'>',$start);

            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
                $bankList = $bankList->where($timeType,'<',$end);
            }

        }
        $by = $request->get('by') ? $request->get('by') : 'bank_auth.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $bankList = $bankList
            ->leftJoin('user_detail','user_detail.uid','=','bank_auth.uid')
            ->select('bank_auth.*','user_detail.nickname')
            ->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'merge' => $merge,
            'bank' => $bankList,
        );

        $this->breadcrumb->add(array(
            array(
                'label' => '银行认证',
                'url' => '/manage/bankAuthList'
            ),
            array(
                'label' => '认证列表'
            )
        ));
        $this->theme->set('manageAction', 'bank');
        return $this->theme->scope('manage.banklist', $data)->render();
    }


    /**
     * 银行认证处理
     *
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function bankAuthHandle($id, $action,Request $request)
    {
        $id = intval($id);
        switch ($action) {
            //后台已打款
            case 'pass':
                $status = BankAuthModel::bankAuthPass($id);
                break;
            //审核失败
            case 'deny':
                $reason = $request->get('reason') ? $request->get('reason') : '';
                $status = BankAuthModel::bankAuthDeny($id,$reason);
                break;
            //删除
            case 'del':
                $status=BankAuthModel::where("id",$id)->update(['is_del'=>1]);
        }
        if ($status)
            return redirect('/manage/bankAuthList')->with(array('message' => '操作成功'));
    }

    /**
     * 银行认证批量审核
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function bankAuthMultiHandle(Request $request)
    {
        if (!$request->get('ckb')) {
            return \CommonClass::adminShowMessage('参数错误');
        }
        $objAuthRecord = new AuthRecordModel();
        $status = $objAuthRecord->multiHandle($request->get('ckb'), 'bank', 'pass');
        if ($status)
            return back();
    }

    /**
     * 企业认证列表视图
     *
     * @param Request $request
     * @return mixed
     */
    public function enterpriseAuthList(Request $request)
    {
        $merge = $request->all();
        $enterpriseList = EnterpriseAuthModel::whereRaw('1 = 1')->where("enterprise_auth.is_del",0);

        //认证用户筛选
        if ($request->get('name')) {
            $enterpriseList = $enterpriseList->where(function($query)use($request){
                $query->where('users.name','like',"%".$request->get('name')."%")
                        ->orWhere('enterprise_auth.id','like',"%".$request->get('name')."%")
                        ->orWhere('user_detail.nickname','like',"%".$request->get('name')."%")
                        ->orWhere('enterprise_auth.company_name','like',"%".$request->get('name')."%");

            });
        }
        //认证公司名称筛选
//        if ($request->get('company_name')) {
//            $enterpriseList = $enterpriseList->where('enterprise_auth.company_name','like','%'.$request->get('company_name').'%');
//        }
        //认证状态筛选
        if ($request->get('status')) {
            switch($request->get('status')){
                case 1:
                    $status = 1;
                    break;
                case 2:
                    $status = 2;
                    break;
                case 3:
                    $status = 0;
                    break;
                default:
                    $status = 0;
            }
            $enterpriseList = $enterpriseList->where('enterprise_auth.status',$status);
        }
        //时间筛选
        if($request->get('time_type')){
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d H:i:s',strtotime($start));
                $enterpriseList = $enterpriseList->where($request->get('time_type'),'>',$start);
            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
                $enterpriseList = $enterpriseList->where($request->get('time_type'),'<',$end);
            }

        }
        $by = $request->get('by') ? $request->get('by') : 'enterprise_auth.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $enterpriseList = $enterpriseList->leftJoin('users','users.id','=','enterprise_auth.uid')->leftJoin('user_detail','user_detail.uid','=','enterprise_auth.uid')
            ->select('enterprise_auth.*','users.name','user_detail.nickname')
            ->orderBy($by, $order)->paginate($paginate);
        if($enterpriseList)
        {
            //查询企业认证的行业末级分类
            $cateId = array();
            foreach($enterpriseList as $k => $v){
                $cateId[] = $v['cate_id'];
            }
            $cate = TaskCateModel::whereIn('id',$cateId)->get();
            foreach($enterpriseList as $k => $v){
                foreach($cate as $key => $value){
                    if($v->cate_id == $value->id){
                        $enterpriseList[$k]['cate_name'] = $value->name;
                    }
                }
            }
        }
        $data = array(
            'merge' => $merge,
            'enterprise' => $enterpriseList,
        );

        $this->breadcrumb->add(array(
            array(
                'label' => '企业认证',
                'url' => '/manage/enterpriseAuthList'
            ),
            array(
                'label' => '认证列表'
            )
        ));
        $this->theme->set('manageAction', 'enterprise');
        return $this->theme->scope('manage.enterpriselist', $data)->render();
    }

    /**
     * 企业认证操作
     * @param $id
     * @param $action 'pass' => 审核通过  'deny' => 审核失败
     * @return \Illuminate\Http\RedirectResponse
     */
    public function enterpriseAuthHandle($id, $action,Request $request)
    {
        $id = intval($id);
        switch ($action) {
            //审核通过
            case 'pass':
                $status = EnterpriseAuthModel::enterpriseAuthPass($id);
                break;
            //审核失败
            case 'deny':
                $reason = $request->get('reason')?$request->get('reason'): '';
                $status = EnterpriseAuthModel::enterpriseAuthDeny($id,$reason);
                break;
            //软删除
            case 'del':
                $status = EnterpriseAuthModel::where('id',$id)->update(['is_del',1]);
                break;
        }
        if ($status){
            return redirect('/manage/enterpriseAuthList')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 企业认证详情
     *
     * @param $id
     * @return mixed
     */
    public function enterpriseAuth($id)
    {
        $id = intval($id);
        //获取上一项id
        $preId = EnterpriseAuthModel::where('id','>',$id)->min('id');
        //获取下一项id
        $nextId = EnterpriseAuthModel::where('id','<',$id)->max('id');
        //获取认证详情
        $enterpriseInfo = EnterpriseAuthModel::getEnterpriseInfo($id);
        //获取认证状态
        $enterpriseStatus = EnterpriseAuthModel::getEnterpriseAuthStatus($enterpriseInfo['uid']);
        if (!empty($enterpriseInfo)) {
            $data = array(
                'enterprise' => $enterpriseInfo,
                'enterprise_status' => $enterpriseStatus,
                'pre_id' => $preId,
                'next_id' => $nextId
            );
            return $this->theme->scope('manage.enterpriseauthinfo', $data)->render();
        }
    }

    /**
     * 企业认证批量审核通过
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function allEnterprisePass(Request $request)
    {
        $ids = $request->get('ids');
        $idArr = explode(',',$ids);
        $res = EnterpriseAuthModel::AllEnterpriseAuthPass($idArr);
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
        return response()->json($data);
    }

    /**
     * 企业认证批量审核不通过
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function allEnterpriseDeny(Request $request)
    {
        $ids = $request->get('ids');
        $idArr = explode(',',$ids);
        $res = EnterpriseAuthModel::AllEnterpriseAuthDeny($idArr);
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
        return response()->json($data);
    }
}
