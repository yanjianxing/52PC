<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Finance\Model\CashoutModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ManagerModel;
use App\Modules\Manage\Model\MenuPermissionModel;
use App\Modules\Manage\Model\ModuleTypeModel;
use App\Modules\Manage\Model\Permission;
use App\Modules\Manage\Model\PermissionRoleModel;
use App\Modules\Manage\Model\Role;
use App\Modules\Manage\Model\RoleUserModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkCommentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Toplan\TaskBalance\Task;

class UserController extends ManageController
{
	//
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->setTitle('用户管理');
        $this->theme->set('manageType', 'User');
    }

    /**
     * 普通用户列表
     *
     * @param Request $request
     * @return mixed
     */
    public function getUserList(Request $request)
    {
        $list = UserModel::select('users.name', 'user_detail.created_at','user_detail.realname',  'users.id', 'users.status',"users.type","users.source","user_detail.auth_type","users.level",'users.last_login_time','user_detail.nickname','user_detail.publish_task_num','user_detail.receive_task_num', 'delivery_count', 'goods_num', 'inquiry_num', 'sales_num','users.mobile','users.email')
            ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid');
        if ($request->get('username')){
            $list = $list->where(function($query) use($request){
                 $query->where('users.name','like', '%'.$request->get('username').'%')->orWhere('users.id','like','%'.$request->get('username').'%')->orWhere('users.mobile','like','%'.$request->get('username').'%')->orWhere('user_detail.nickname','like','%'.$request->get('username').'%')
                ->orWhere('users.email','like','%'.$request->get('username').'%');
            });
        }
        //注册身份
        if($request->get('auth_type') && $request->get('auth_type') != -1){
            $list=$list->where("user_detail.auth_type",$request->get('auth_type'));
        }

        if ($request->get('status') && $request->get('status') != -1){
                $list = $list->where('users.status', $request->get('status'));
        }
        //时间筛选
        $timeType = 'users.created_at';
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where($timeType,'>',$start);

        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $list = $list->where($timeType,'<',$end);
        }
        //登录时间筛选
        if($request->get('login_start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('login_start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where("users.last_login_time",'>',$start);

        }
        if($request->get('login_end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('login_end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $list = $list->where("users.last_login_time",'<',$end);
        }
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $by = $request->get('by') ? $request->get('by') : 'created_at';
        $list = $list->orderBy($by, $order);
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = $list->paginate($paginate);

        $data = [
            'status'    =>$request->get('status'),
            'list'      => $list,
            'paginate'  => $paginate,
            'order'     => $order,
            'by'        => $request->get('by'),
            'uid'       => $request->get('uid'),
            'username'  => $request->get('username'),
        ];
        $search = [
            'status'        => $request->get('status'),
            'paginate'      => $paginate,
            'order'         => $order,
            'by'            => $request->get('by'),
            'username'      => $request->get('username'),
            'start'         => $request->get('start'),
            'end'           => $request->get('end'),
            'login_start'   => $request->get('login_start'),
            'login_end'     => $request->get('login_end'),
            'auth_type'     => $request->get('auth_type')
        ];
        $data['search'] = $search;
        $this->theme->setTitle('平台用户');
 		return $this->theme->scope('manage.userList', $data)->render();
    }

    /**
     * 处理用户
     *
     * @param $uid
     * @param $action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleUser($uid, $action,Request $request)
    {
        switch ($action){
            case 'enable':
                $status = 1;
                break;
            case 'disable':
                $status = 2;
                break;
        }
        $arr = [
            'status' => $status
        ];
        if(isset($status) && $status == 2){
            if($request->get('forbidden_at') == 0){
                $forbidden_at = '0000-00-00 00:00:00';
            }else{
                $forbidden_at = date('Y-m-d H:i:s',strtotime('+'.$request->get('forbidden_at').' day'));
            }
            $arr['forbidden_at'] = $forbidden_at;
        }
        $res = UserModel::where('id', $uid)->update($arr);
        if (isset($res) && $res){
            return back()->with(['message' => '操作成功']);
        }
        return back()->with(['message' => '操作失败']);

    }

    /**
     * 添加普通用户视图
     *
     * @return mixed
     */
    public function getUserAdd()
    {
        $province = DistrictModel::findTree(0);
        $data = [
            'province' => $province
        ];
 		return $this->theme->scope('manage.userAdd', $data)->render();
    }

    /**
     * 添加用户表单提交
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postUserAdd(Request $request)
    {
        //dd($request->all());
        $salt = \CommonClass::random(4);
        $data = [
            'name' => $request->get('name'),
            'realname' => $request->get('realname'),
            'mobile' => $request->get('mobile'),
            'qq' => $request->get('qq'),
            'email' => $request->get('email'),
            'province' => $request->get('province'),
            'city' => $request->get('city'),
            'area' => $request->get('area'),
            'password' => UserModel::encryptPassword($request->get('password'), $salt),
            'salt' => $salt
        ];
        $status = UserModel::addUser($data);
        if ($status)
            return redirect('manage/userList')->with(['message' => '操作成功']);
    }

    /**
     * 检查用户名
     *
     * @param Request $request
     * @return string
     */
    public function checkUserName(Request $request){
        $username = $request->get('param');
        $status = UserModel::where('name', $username)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '用户名不可用';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }

    /**
     * 检测邮箱是否可用
     *
     * @param Request $request
     * @return string
     */
    public function checkEmail(Request $request){
        $email = $request->get('param');

        $status = UserModel::where('email', $email)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '邮箱已占用';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }
    /*
     * 编辑用户资料视图
     * */
    public function getUserEdit($uid){
        $info = UserModel::select('user_detail.*','users.name','users.birth_date', 'users.email','users.id')
            ->where('users.id', $uid)
            ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')->first()->toArray();

        $province = DistrictModel::findTree(0);

        //获取职能数据
        $functional=TaskCateModel::where("type",6)->get();
        //获取职位等级
        $jobLevel=TaskCateModel::where("type",7)->get();
        $data = [
            'info' => $info,
            'province' => $province,
            'city' => DistrictModel::getDistrictName($info['city']),
            'area' => DistrictModel::getDistrictName($info['area']),
            'functional'=>$functional,
            'jobLevel'=>$jobLevel,
        ];
        return $this->theme->scope('manage.userEdit', $data)->render();
    }
    /**
     * 用户详情页视图
     *
     * @param $uid
     * @return mixed
     */
    public function getUserDetail($uid)
    {
        $info = UserModel::select('users.name','users.last_login_time','user_detail.nickname', 'user_detail.realname','user_detail.avatar', 'user_detail.mobile', 'user_detail.qq', 'users.email', 'user_detail.province','user_detail.created_at'
            , 'user_detail.city','user_detail.introduce','user_detail.area','user_detail.sex','user_detail.wechat','user_detail.function','user_detail.job_level','user_detail.complay_name', 'users.id','users.level','users.status','users.source'
            ,'shop.id as shop_id','shop.shop_name')
            ->where('users.id', $uid)
            ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->leftJoin('shop','users.id','=','shop.uid')
            ->first();
        if (isset($info)){
            $info=$info->toArray();
        }
        $province = DistrictModel::findTree(0);
        //查看认证信息
        $userAuthRecord=AuthRecordModel::where("uid",$uid)->lists("auth_code")->toArray();
        //发包记录
        $fbjl=TaskModel::where('uid',$uid)->select("id","title","bounty","status","field_id","type_id","created_at","verified_at","cate_id","delivery_deadline","from_to","province","city")->orderBy("created_at","desc")->get();
        //接包记录
        $jbjl = WorkModel::leftJoin('task','work.task_id','=','task.id')->where('work.status',1)->where('work.uid',$uid)->select("task.id","task.title","task.bounty","task.status","task.field_id","task.type_id","task.created_at","task.verified_at","task.cate_id","task.delivery_deadline","task.from_to","task.province","task.city")->orderBy("task.created_at","desc")->get();
        //上传方案
        $scfa = GoodsModel::where('uid',$uid)->select("id","title","status","type","cate_id","created_at","view_num","sales_num")->get();
        //询价记录
        $xjjl= ProgrammeEnquiryMessageModel::leftJoin("goods","programme_enquiry_message.programme_id","=","goods.id")->leftJoin('users',"goods.uid","=","users.id")
            ->where("programme_enquiry_message.uid",$uid)->where("programme_enquiry_message.type",1)->select("goods.id","goods.title","goods.status","goods.type","goods.cate_id","programme_enquiry_message.created_at","goods.view_num","goods.sales_num","users.name","users.mobile","users.email")->orderBy("programme_enquiry_message.created_at","desc")->get();

        //$xjjl=ProgrammeEnquiryMessageModel::leftJoin('goods','programme_enquiry_message.programme_id','=','goods.id')->leftJoin('users',"goods.uid","=","users.id")->where('programme_enquiry_message.uid',$uid)->select("goods.id","goods.title","goods.status","goods.type","goods.cate_id","goods.created_at","goods.view_num","goods.sales_num","users.name","users.mobile","users.email")->get();
        //销售记录
        $xsjl=ProgrammeOrderModel::leftJoin("goods","programme_order.programme_id","=",'goods.id')->leftJoin('users',"goods.uid","=","users.id")->where("programme_order.uid",$uid)->select("goods.id","goods.title","goods.status","goods.type","goods.cate_id","programme_order.created_at","goods.view_num","goods.sales_num","users.name","users.mobile","users.email")->orderBy("programme_order.created_at","desc")->get();
        //财务流水
        $cwls=FinancialModel::where("uid",$uid)->select("id","action","status","created_at")->orderBy("created_at","desc")->get();
        //vip购买记录
        $vipjl=VipUserOrderModel::where("uid",$uid)->orderBy("created_at","desc")->get();
        //评价记录
        $pjjl=CommentModel::leftJoin('task','comments.task_id',"=","task.id")->leftJoin('users','task.uid','=','users.id')->where("comments.from_uid",$uid)->select("users.id","task.title","users.name","users.mobile","users.email","comments.comment")->orderBy("comments.created_at","desc")->get();
        //获取所有的应用领域
        $field=CateModel::where("type",1)->select("id","name")->get()->toArray();
        $field=\CommonClass::setArrayKey($field,'id');
        //获取技能标签
        $cateId=CateModel::where("type",2)->select("id","name")->get()->toArray();
        $cateId=\CommonClass::setArrayKey($cateId,'id');
        //获取所有的项目类型
        $taskType=TaskTypeModel::where("status",1)->select("id","name","alias")->get()->toArray();
        $taskType=\CommonClass::setArrayKey($taskType,"id");
        //查询所有的地区
        $districtAll=DistrictModel::all()->toArray();
        $districtAll=\CommonClass::setArrayKey($districtAll,"id");
        //获取职能和职位等级的技术标签
        $functionJobLevel=TaskCateModel::whereIn("type",[6,7])->get()->toArray();
        $functionJobLevel=\CommonClass::setArrayKey($functionJobLevel,"id");
        $data = [
            'info' => $info,
            'province' => $province,
            'city' => DistrictModel::getDistrictName($info['city']),
            'area' => DistrictModel::getDistrictName($info['area']),
            'userAuthRecord'=>$userAuthRecord,
            'districtAll' =>$districtAll,
            'fbjl'=>$fbjl,
            'jbjl'=>$jbjl,
            'scfa'=>$scfa,
            'xjjl'=>$xjjl,
            'xsjl'=>$xsjl,
            'cwls'=>$cwls,
            'vipjl'=>$vipjl,
            'pjjl'=>$pjjl,
            'field'=>$field,
            'cateId'=>$cateId,
            'taskType'=>$taskType,
            'functionJobLevel'=>$functionJobLevel,
        ];
 		return $this->theme->scope('manage.userDetail', $data)->render();
    }

    /**
     * 编辑用户资料
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postUserEdit(Request $request)
    {
        //查询手机号码是否可设置
        if(!empty($request->get('mobile'))){
            $isExistsUser = UserModel::where('id','!=',$request->get('uid'))->where('mobile',$request->get('mobile'))->exists();
            $isExistsDetail = UserDetailModel::where('uid','!=',$request->get('uid'))->where('mobile',$request->get('mobile'))->exists();
            if($isExistsUser || $isExistsDetail){
                return redirect('/manage/userEdit/'.$request->get('uid'))->with(['message' => '手机号已经被占用']);
            }
        }
        //用户密码
        $user = UserModel::find($request->get('uid'));
        if(empty($request->get('password'))){
            $password = $user->password;
        }else{
            $password = UserModel::encryptPassword($request->get('password'), $user->salt);
        }
        $data = [
            'uid' => $request->get('uid'),
            'realname' => $request->get('realname'),
            'mobile' => $request->get('mobile'),
            'qq' => $request->get('qq'),
            'email' => $request->get('email'),
            'province' => $request->get('province'),
            'city' => $request->get('city'),
            'area' => $request->get('area'),
            'password' => $password,
            'birth_date'=>$request->get("birth_date"),
            'sex'=>$request->get("sex"),
            'function'=>$request->get("function"),
            'job_level'=>$request->get("job_level"),
            'address'=>$request->get("address"),
            'introduce'=>$request->get("introduce"),
            'wechat'=>$request->get("wechat"),
            'nickname'=>$request->get("nickname"),
        ];
        //.用户头像
        if($request->file('avatar')){
            $file=$request->file('avatar');
            $result = \FileClass::uploadFile($file,'user');
            $result = json_decode($result,true);
            $data['avatar'] = $result['data']['url'];
        }
        //.获取用户原来的图像
        $avatar=UserDetailModel::where('uid',$request->get('uid'))->pluck('avatar');
        $data['avatar'] =isset($data['avatar']) ? $data['avatar'] : $avatar ;//.用户头像
        $status = UserModel::editUser($data);
        if ($status)
            return redirect('manage/userList')->with(['message' => '操作成功']);

    }

    /**
     * 系统用户列表
     *
     * @param Request $request
     * @return mixed
     */
   	public function getManagerList(Request $request)
   	{
        $merge = $request->all();
        $list = ManagerModel::select('manager.id','manager.username','roles.display_name','manager.status','manager.email','manager.telephone','manager.QQ')->leftJoin('role_user','manager.id','=','role_user.user_id')
           ->leftJoin('roles','roles.id','=','role_user.role_id');
        $roles = Role::get();
        if($request->get('uid')){
            $list = $list->where('manager.id',$request->get('uid'));
        }
        if($request->get('username')){
            $list=$list->where(function($query)use($request){
                  $query->where('manager.username','like','%'. $request->get('username').'%')
                        ->orWhere('manager.id','like','%'. $request->get('username').'%')
                       ->orWhere('manager.telephone','like','%'. $request->get('username').'%');
            });
        }
        if($request->get('QQ')){

            $list = $list->where('manager.QQ','like','%'. $request->get('QQ').'%');
        }
        if($request->get('email')){

            $list = $list->where('manager.email','like','%'. $request->get('email').'%');
        }
        if($request->get('display_name') && $request->get('display_name') != '全部'){
            $list = $list->where('roles.id',$request->get('display_name'));
        }
        if($request->get('telephone')){

            $list = $list->where('manager.telephone','like','%'. $request->get('telephone').'%');
        }
        if ($request->get('status')!=""){
            $list = $list->where('manager.status', $request->get('status'));
        }
        if($request->get('role_id')!=""){
            $list = $list->where('roles.id', $request->get('role_id'));
        }

        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = $list->orderBy('manager.id',$order)->paginate($paginate);
        $listArr = $list->toArray();
        $data = array(
            'merge' => $merge,
            'listArr' => $listArr,
            'status'=>$request->get('status'),
            'by' => $request->get('by'),
            'order' => $order,
            'display_name'=>$request->get('display_name'),
            'uid'=>$request->get('uid'),
            'username'=>$request->get('username'),
            'QQ'=>$request->get('QQ'),
            'email'=>$request->get('email'),
            'telephone'=>$request->get('telephone'),
            'list'=>$list,
            'roles'=>$roles,
            'role_id'=>$request->get('role_id'),
       );
        $this->theme->setTitle('系统用户');
		return $this->theme->scope('manage.managerList',$data)->render();
   	}

    /**
     * 处理用户
     *
     * @param $uid
     * @param $action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleManage($uid, $action)
    {
        switch ($action){
            case 'enable':
                $status = 1;
                break;
            case 'disable':
                $status = 2;
                break;
        }
        $status = ManagerModel::where('id', $uid)->update(['status' => $status]);
        if ($status)
            return back()->with(['message' => '操作成功']);
    }

    /**
     * 验证系统用户名
     *
     * @param Request $request
     * @return string
     */
    public function checkManageName(Request $request){
        $username = $request->get('param');
        $status = ManagerModel::where('username', $username)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '用户名不可用';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }

    /**
     * 验证系统用户邮箱
     *
     * @param Request $request
     * @return string
     */
    public function checkManageEmail(Request $request){
        $email = $request->get('param');

        $status = ManagerModel::where('email', $email)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '邮箱已占用';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }

    /**
     * 批量删除用户
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postManagerDeleteAll(Request $request){
       // dd($request->all());
        $data = $request->except('_token');
        //var_dump($data['chk']);exit;
        if(!$data['chk']){
            return  redirect('manage/managerList')->with(array('message' => '操作失败'));
        }
        $status = DB::transaction(function () use ($data) {
            foreach ($data['chk'] as $id) {
                ManagerModel::where('id', $id)->delete();
               RoleUserModel::where('user_id', $id)->delete();
            }
        });
        if(is_null($status))
        {
            return redirect()->to('manage/managerList')->with(array('message' => '操作成功'));
        }
        return  redirect()->to('manage/managerList')->with(array('message' => '操作失败'));
    }

    /**
     *删除用户
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function managerDel($id){
        $status = DB::transaction(function () use ($id){
            ManagerModel::where('id',$id)->delete();
            RoleUserModel::where('user_id',$id)->delete();
        });

        if (is_null($status))
            return redirect()->to('manage/managerList')->with(['message' => '操作成功']);
    }
    /**
     * 添加用户视图
     *
     * @return mixed
     */
   	public function managerAdd()
   	{
        $roles = Role::get();
        $data = array(
            'roles'=>$roles
        );
		return $this->theme->scope('manage.managerAdd',$data)->render();
   	}

    /**
     * 系统用户表单提交
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postManagerAdd(Request $request)
    {
        $status = DB::transaction(function () use ($request) {
            $salt = \CommonClass::random(4);
            $data = [
                'username' => $request->get('username'),
                'realname' => $request->get('realname'),
                'telephone' => $request->get('telephone'),
                'QQ' => $request->get('QQ'),
                'email' => $request->get('email'),
                'password' => ManagerModel::encryptPassword($request->get('password'), $salt),
                'birth' => $request->get('birth'),
                'salt' => $salt,
                'created_at' => date('Y-m-d H:i:s', time()),
                'updated_at' => date('Y-m-d H:i:s', time())
            ];
            ManagerModel::insert($data);
            $user = ManagerModel::where('username',$request->get('username'))->first();
            if($request->get('role_id'))
              $user->attachRole($request->get('role_id'));
        });
        if (is_null($status))
            return redirect('manage/managerList')->with(['message' => '操作成功']);
    }

    /**
     * 系统用户详情
     *
     * @param $id
     * @return mixed
     */
   	public function managerDetail($id)
   	{
        $info = ManagerModel::select('manager.id','manager.username','manager.status','manager.email','manager.telephone','manager.QQ','manager.password','role_user.role_id')->leftJoin('role_user','manager.id','=','role_user.user_id')
            ->leftJoin('roles','roles.id','=','role_user.role_id')->where('manager.id',$id)->first();
        $roles = Role::get();
        $data = array(
            'roles'=>$roles,
            'info'=>$info,

        );
		return $this->theme->scope('manage.managerDetail',$data)->render();
   	}

    /**
     * 编辑用户资料
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postManagerDetail(Request $request)
    {
        $status = DB::transaction(function () use ($request) {
            $id = $request->get('uid');
            if(!ManagerModel::where('id',$id)->where('password',$request->get('password'))->first()) {
                $salt = \CommonClass::random(4);
                $data = array(
                    'realname' => $request->get('realname'),
                    'telephone' => $request->get('telephone'),
                    'QQ' => $request->get('QQ'),
                    'password' => ManagerModel::encryptPassword($request->get('password'), $salt),
                    'birth' => $request->get('birth'),
                    'salt' => $salt,
                    'updated_at' => date('Y-m-d H:i:s', time())
                );
            }else{
                $data = array(
                    'realname' => $request->get('realname'),
                    'telephone' => $request->get('telephone'),
                    'QQ' => $request->get('QQ'),
                    'birth' => $request->get('birth'),
                    'updated_at' => date('Y-m-d H:i:s', time())
                );
            }
            ManagerModel::where('id', $id)->update($data);
            $user = ManagerModel::where('id',$id)->first();
            RoleUserModel::where('user_id',$id)->delete();
            $user->attachRole($request->get('role_id'));
//            if(!RoleUserModel::where('user_id',$id)->where('role_id',$request->get('role_id'))->first())
//                $user->attachRole($request->get('role_id'));

        });
       if (is_null($status))
            return redirect('manage/managerList')->with(['message' => '操作成功']);
    }


    /**
     * 系统组列表
     *
     * @return mixed
     */
    public function getRolesList(Request $request)
    {
        $list =  Role::select('roles.id','roles.display_name','roles.updated_at');
        if($request->get('roleName')){
            $list=$list->where(function ($query)use($request){
                $query->where("roles.name","like","%".$request->get('roleName')."%")
                    ->orWhere("roles.id","like","%".$request->get('roleName')."%");
            });
        }
        $list =$list->orderBy('roles.id','DESC')->paginate(10);
        foreach($list as $key=>$val){
            $list[$key]['userNum']=RoleUserModel::where("role_id",$val['id'])->count();
        }
        $data = array(
            'list'=>$list,
            'roleName'=>$request->get("roleName"),
        );
        $this->theme->setTitle('角色管理');
        return $this->theme->scope('manage.rolesList',$data)->render();
    }

    /**
     * 添加系统组视图
     *
     * @return mixed
     */
    public function getRolesAdd()
    {
        $tree_menu = Permission::getPermissionMenu();
        $data = array(
            'list' =>$tree_menu,
        );
        return $this->theme->scope('manage.rolesAdd',$data)->render();
    }
    /**
     * 添加系统组
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postRolesAdd(Request $request)
    {
        if(!count($request->get('id'))){
            return redirect('manage/rolesAdd')->with(['message' => '请设置用户组权限']);
        }
        //查询该用户组是否存在
        $findRole=Role::where("name",trim($request->get('name')))->first();
        if($findRole){//存在时
            return back()->with(["message"=>"该用户组已存在"]);
        }
        $status = DB::transaction(function () use ($request) {
            $data = array(
                'name' => $request->get('name'),
                'display_name'=>$request->get('display_name'),
                'created_at' => date('Y-m-d H:i:s', time()),
                'updated_at' => date('Y-m-d H:i:s', time())
            );
            $role_id = Role::insertGetId($data);
            $data2 = [];
            foreach ($request->get('id') as $id) {
                if($id != 'on'){
                    $data2[] = array(
                        'permission_id' => $id,
                        'role_id' => $role_id
                    );
                }
            }
            PermissionRoleModel::insert($data2);
        });
        if (is_null($status))
            return redirect('manage/rolesList')->with(['message' => '操作成功']);
    }

    /**
     * 删除系统组列表
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getRolesDel($id)
    {
        $status = DB::transaction(function () use ($id) {
            Role::where('id', $id)->delete();
            PermissionRoleModel::where('role_id',$id)->delete();
        });
        if (is_null($status))
            return redirect()->to('manage/rolesList')->with(['message' => '操作成功']);
    }

    /**
     * 系统组详情页
     *
     * @param $id
     * @return mixed
     */
    public function getRolesDetail($id)
    {
        $tree_menu = Permission::getPermissionMenu();

        $info1 = Role::where('id',$id)->first();
        $info = Role::select('roles.name','permissions.id','permissions.display_name')->join('permission_role','roles.id','=','permission_role.role_id')
            ->join('permissions','permissions.id','=','permission_role.permission_id')->where('roles.id',$id)->get();
        $ids = array();
        foreach ($info as $v) {
            $ids[] .= $v['id'];
        }
        $data = array(
            'ids'=>$ids,
            'info1'=>$info1,
            'info'=>$info,
            'list'=>$tree_menu,
        );
        return $this->theme->scope('manage.rolesDetail',$data)->render();
    }

    /**
     * 更新系统组详情页
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postRolesDetail(Request $request)
    {
        $status = DB::transaction(function () use ($request) {
            $rid = $request->get('rid');
            $data = array(
                'name' => $request->get('name'),
                'display_name'=>$request->get('display_name'),
                'created_at' => date('Y-m-d H:i:s', time()),
                'updated_at' => date('Y-m-d H:i:s', time())
            );
            Role::where('id', $rid)->update($data);
            

            $data2 = [];
            $role_id = $rid;
            if(array_unique($request->get('id'))) {
                foreach (array_unique($request->get('id')) as $id) {
                    if($id != 'on'){
                        $data2[] = array(
                            'permission_id' => $id,
                            'role_id' => $role_id
                        );
                    }

                }
            }
            if(!empty($data2)){
                PermissionRoleModel::where('role_id', $rid)->delete();
                PermissionRoleModel::insert($data2);
            }

        });
        if (is_null($status))
            return redirect('manage/rolesList')->with(['message' => '操作成功']);
    }

    /**
     * 权限列表
     *
     * @return mixed
     */
    public function getPermissionsList(Request $request)
    {
        $merge = $request->all();
        $list = Permission::select('permissions.id','permissions.name','permissions.display_name','permissions.module_type','menu.name as menu_name')
            ->leftJoin('menu','menu.id','=','permissions.module_type');
        if ($request->get('id')){
            $list = $list->where('permissions.id', $request->get('id'));
        }
        if ($request->get('display_name')){
            $list = $list->where('permissions.display_name','like','%'. $request->get('display_name').'%');
        }
        if ($request->get('name')){
            $list = $list->where('permissions.name','like','%'.  $request->get('name').'%');
        }
        $order = $request->get('order') ? $request->get('order') : 'desc';
        if ($request->get('module_type')!=""){
            $list = $list->where('permissions.module_type', $request->get('module_type'));
        }
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = $list->orderBy('permissions.id',$order)->paginate($paginate);
        $listArr = $list->toArray();
        $type = ModuleTypeModel::get();
        $data = array(
            'merge' => $merge,
            'listArr' => $listArr,
            'id'=>$request->get('id'),
            'display_name'=>$request->get('display_name'),
            'name'=>$request->get('name'),
            'module_type'=>$request->get('module_type'),
            'type'=>$type,
            'list'=>$list,
            'paginate' => $paginate,
        );
        $this->theme->setTitle('权限设置');
        return $this->theme->scope('manage.permissionsList',$data)->render();
    }

    /**
     * 添加权限视图
     *
     * @return mixed
     */
    public function getPermissionsAdd()
    {
        $modules = ModuleTypeModel::get();
        $data = array(
            'modules'=>$modules
        );
        return $this->theme->scope('manage.permissionsAdd',$data)->render();
    }

    /**
     * 添加权限
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postPermissionsAdd(Request $request)
    {
        $data = $request->except('_token');
        $status = DB::transaction(function() use($data){
            $re =  Permission::insertGetId($data);
            //创建权限和菜单之间的关系
            $permission_user = ['menu_id'=>$data['module_type'],'permission_id'=>$re];
            MenuPermissionModel::insert($permission_user);
        });

        if(is_null($status))
            return redirect('manage/permissionsList')->with(['message' => '操作成功']);
    }

    /**
     * 删除权限
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getPermissionsDel($id){
        $re = Permission::where('id',$id)->delete();
        if($re)
            return redirect()->to('manage/permissionsList')->with(['message' => '操作成功']);
    }

    /**
     * 权限详情页
     *
     * @param $id
     * @return mixed
     */
    public function getPermissionsDetail($id)
    {
        //获取上一项id
        $preId = Permission::where('id', '>', $id)->min('id');
        //获取下一项id
        $nextId = Permission::where('id', '<', $id)->max('id');
        $info = Permission::select('permissions.*','mp.menu_id')
            ->where('permissions.id',$id)
            ->join('menu_permission as mp','permissions.id','=','mp.permission_id')
            ->first();
        $modules = ModuleTypeModel::get();
        $data = array(
            'modules'=>$modules,
            'info'=>$info,
            'preId'=>$preId,
            'nextId'=>$nextId
        );
        return $this->theme->scope('manage.permissionsDetail',$data)->render();
    }

    /**
     * 更新权限
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postPermissionsDetail(Request $request)
    {
        $id = $request->get('id');
        $menu_id = $request->get('menu_id');
        $data = $request->except('id','_token','menu_id');
        $re = Permission::where('id',$id)->update($data);
        $permission = Permission::where('id',$id)->first();
        //删除原有的权限菜单关系
        $result1 = MenuPermissionModel::where('permission_id',$permission['id'])->delete();
        $result = MenuPermissionModel::firstOrCreate(['menu_id'=>$menu_id,'permission_id'=>$permission['id']]);
        if($re || $result)
            return redirect('manage/permissionsList')->with(['message' => '操作成功']);

    }
}
