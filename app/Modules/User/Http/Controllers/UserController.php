<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserAdderModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserTagsModel;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\DB;

class UserController extends UserCenterController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('userfinance');//主题初始化
    }

    /**
     * 个人空间--成功案例
     * @return mixed
     */
    public function getPersonCase()
    {
        $uid = Auth::User()->id;
        $userInfo = UserDetailModel::where('uid', $uid)->first();
        //查询技能标签
        $tag = UserTagsModel::getTagsByUserId($uid);
        //获取地区标签
        $addr = UserDetailModel::getAreaByUserId($uid);
        //查询用户的绑定关系
        $authUser = AuthRecordModel::getAuthByUserId($uid);
        $query = SuccessCaseModel::select('success_case.*', 'tc.name as cate_name', 'ud.avatar as user_avatar');
        $list = $query->leftJoin('cate as tc', 'success_case.cate_id', '=', 'tc.id')
            ->leftjoin('user_detail as ud', 'ud.uid', '=', 'success_case.uid')->where('ud.uid', $uid)
            ->paginate(8);
        $listO = $list->toArray();
        $tcName = SuccessCaseModel::select('tc.name')->join('cate as tc', 'success_case.cate_id', '=', 'tc.id')->where('success_case.uid', $uid)->first();
        $domain = \CommonClass::getDomain();

        $data = array(
            'domain' => $domain,
            'addr' => $addr,
            'list' => $listO,
            'list_ob' => $list,
            'introduce' => $userInfo,
            'auth_user' => $authUser,
            'skill_tag' => $tag
        );
        $this->theme->set('TYPE',3);
        $this->theme->setTitle(Auth::User()->name);
        $this->theme->set('keywords',Auth::User()->name);
        $this->theme->set('description',Auth::User()->name);
        return $this->theme->scope('user.space.personcase', $data)->render();
    }


    /**
     * 个人空间--评价详情
     * @return mixed
     */
    public function getPersonEvaluation()
    {
        $uid = Auth::User()->id;
        $userInfo = UserDetailModel::where('uid', $uid)->first();
        //查询技能标签
        $tag = UserTagsModel::getTagsByUserId($uid);
        //获取地区标签
        $addr = UserDetailModel::getAreaByUserId($uid);
        $commentList = CommentModel::join('task', 'comments.task_id', '=', 'task.id')->join('user_detail', 'task.uid', '=', 'user_detail.uid')->where('comments.to_uid', $uid)
            ->leftJoin('users', 'users.id', '=', 'comments.from_uid')->paginate(8);
        //总评论数
        $counts = CommentModel::groupBy('to_uid')->where('to_uid', $uid)->count();
        //总好评数
        $count = CommentModel::groupBy('type')->where('to_uid', $uid)->havingRaw('type=1')->count();
        //好评率
        if ($counts != 0)
            $feedbackRate = ceil($count / $counts * 100);
        else
            $feedbackRate = 100;
        //工作速度平均分
        $avgspeed = round(CommentModel::where('to_uid', $uid)->avg('speed_score'), 1);
        //工作质量平均分
        $avgquality = round(CommentModel::where('to_uid', $uid)->avg('quality_score'), 1);
        //工作态度平均分
        $avgattitude = round(CommentModel::where('to_uid', $uid)->avg('attitude_score'), 1);
        $domain = \CommonClass::getDomain();
        //查询用户的绑定关系
        $authUser = AuthRecordModel::getAuthByUserId($uid);
        $data = array(
            'domain' => $domain,
            'addr' => $addr,
            'introduce' => $userInfo,
            'avgquality' => $avgquality,
            'avgattitude' => $avgattitude,
            'avgspeed' => $avgspeed,
            'feedbackRete' => $feedbackRate,
            'count' => $count,
            'commentList' => $commentList,
            'auth_user' => $authUser,
            'skill_tag' => $tag
        );
        $this->theme->set('TYPE',3);
        $this->theme->setTitle(Auth::User()->name);
        $this->theme->set('keywords',Auth::User()->name);
        $this->theme->set('description',Auth::User()->name);
        return $this->theme->scope('user.space.personevaluation', $data)->render();
    }

    /**
     * @param $id
     * @return mixed
     * 成功案例详情页
     */
    public function getPersonEvaluationDetail($id)
    {
        $uid = Auth::User()->id;
        //$comment = TaskModel::where('id',$id)->first();
        $comment = TaskModel::join('cate', 'task.cate_id', '=', 'cate.id')->where('task.id', $id)->first();
        $successCase = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')->where('success_case.id', $id)->first();
        $viewTimes = array(
            'view_count' => $successCase->view_count + 1
        );
        SuccessCaseModel::where('id', $id)->update($viewTimes);
        $userInfo = UserDetailModel::where('uid', $uid)->first();
        //查询技能标签
        $tag = UserTagsModel::getTagsByUserId($uid);
        //获取地区标签
        $addr = UserDetailModel::getAreaByUserId($uid);

        $domain = \CommonClass::getDomain();
        //查询用户的绑定关系
        $authUser = AuthRecordModel::getAuthByUserId($uid);
        $data = array(
            'successCase' => $successCase,
            'domain' => $domain,
            'addr' => $addr,
            'introduce' => $userInfo,
            'comment' => $comment,
            'auth_user' => $authUser,
            'skill_tag' => $tag
        );
        $this->theme->set('TYPE',3);
        $this->theme->setTitle(Auth::User()->name);
        $this->theme->set('keywords',Auth::User()->name);
        $this->theme->set('description',Auth::User()->name);
        return $this->theme->scope('user.space.personevaluationdetail', $data)->render();
    }

    /**
     * @param $id
     * @return mixed
     * 添加成功案例表单
     */
    public function getAddPersonCase($id)
    {
        $uid = Auth::User()->id;
        $userInfo = UserDetailModel::where('uid', $uid)->first();
        //查询技能标签
        $tag = UserTagsModel::getTagsByUserId($uid);
        //获取地区标签
        $addr = UserDetailModel::getAreaByUserId($uid);
        //查询热门分类
        $hotCate = TaskCateModel::hotCate(6);
        //查询所有的末级分类
        $category_all = TaskCateModel::findByPid([0],['id']);
        $category_all = array_flatten($category_all);
        $category_all = TaskCateModel::findByPid($category_all);

        //查询用户的绑定关系
        $authUser = AuthRecordModel::getAuthByUserId($uid);

        $domain = \CommonClass::getDomain();
        $data = array(
            'domain' => $domain,
            'addr' => $addr,
            'introduce' => $userInfo,
            'hotcate' => $hotCate,
            'category_all' => $category_all,
            'id' => $id,
            'auth_user' => $authUser,
            'skill_tag' => $tag

        );
        $this->theme->set('TYPE',3);
        $this->theme->setTitle(Auth::User()->name);
        $this->theme->set('keywords',Auth::User()->name);
        $this->theme->set('description',Auth::User()->name);
        return $this->theme->scope('user.space.addpersoncase', $data)->render();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * 添加成功案例
     */
    public function postAddCase(Request $request)
    {
        $data = $request->except('_token');
        $user = Auth::User();

        $file = $request->file('pic');
        if (!$file) {
            return redirect()->back()->with('error', '上传文件不能为空');
        }
        if (!$request->cate_id) {
            return redirect()->back()->with('error', '案例分类不能为空');
        }

        $result = \FileClass::uploadFile($file, 'sys');
        $result = json_decode($result, true);
        $data = array(
            'pic' => $result['data']['url'],
            'uid' => $user->id,
            'username'=>$user->name,
            'title' => $request->title,
            'desc' =>\CommonClass::removeXss($request->description),
            'type' => 1,
            'url' => $request->url,
            'cate_id' => $request->get('cate_id'),
            'created_at' => date('Y-m-d H:i:s', time()),
        );
        $result2 = SuccessCaseModel::insert($data);

        if (!$result2)
            return redirect()->back()->with('error', '成功案例添加失败！');


        return redirect('/user/personCase')->with('massage', '成功案例添加成功！');
    }

    /**
     * 编辑成功案例视图
     * @param $id 案例id
     * @return mixed
     */
    public function getEditPersonCase($id)
    {
        $uid = Auth::User()->id;
        $userInfo = UserDetailModel::where('uid', $uid)->first();
        //查询技能标签
        $tag = UserTagsModel::getTagsByUserId($uid);
        //获取地区标签
        $addr = UserDetailModel::getAreaByUserId($uid);
        //查询热门分类
        $hotCate = TaskCateModel::hotCate(6);
        //查询所有的末级分类
        $category_all = TaskCateModel::findByPid([0],['id']);
        $category_all = array_flatten($category_all);
        $category_all = TaskCateModel::findByPid($category_all);

        //查询用户的绑定关系
        $authUser = AuthRecordModel::getAuthByUserId($uid);

        //查询成功案例详情
        $successCase = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')->where('success_case.id', $id)
            ->select('success_case.*','cate.name','cate.id')->first();

        $domain = \CommonClass::getDomain();
        $data = array(
            'domain' => $domain,
            'addr' => $addr,
            'introduce' => $userInfo,
            'hotcate' => $hotCate,
            'category_all' => $category_all,
            'id' => $id,
            'auth_user' => $authUser,
            'skill_tag' => $tag,
            'successCase' => $successCase

        );
        $this->theme->set('TYPE',3);
        $this->theme->setTitle(Auth::User()->name);
        $this->theme->set('keywords',Auth::User()->name);
        $this->theme->set('description',Auth::User()->name);
        return $this->theme->scope('user.space.editpersoncase', $data)->render();
    }

    /**
     * 编辑成功案例
     * @param Request $request
     * @return mixed
     */
    public function postEditCase(Request $request)
    {
        $data = $request->except('_token');
        //查询成功案例详情
        $successCase = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')->where('success_case.id', $data['id'])
            ->select('success_case.*','cate.name','cate.id')->first();
        $user = Auth::User();
        $file = $request->file('pic');
        if ($file) {
            $result = \FileClass::uploadFile($file, 'sys');
            $result = json_decode($result, true);
            $pic = $result['data']['url'];
        }else{
            $pic = $successCase['pic'];
        }
        if (!$request->cate_id) {
            $cateId = $successCase['cate_id'];
        }else{
            $cateId = $request->cate_id;
        }

        $arr = array(
            'pic' => $pic,
            'uid' => $user->id,
            'title' => $request->title,
            'desc' => e($request->description),
            'url' => $request->url,
            'cate_id' => $cateId,
            'created_at' => date('Y-m-d H:i:s', time()),
        );
        $res = SuccessCaseModel::where('success_case.id', $data['id'])->update($arr);
        if (!$res)
            return redirect()->back()->with('error', '成功案例编辑失败！');

        return redirect('/user/personCase')->with('massage', '成功案例编辑成功！');

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 开启关闭服务商
     */
    public function ajaxUpdateCase(Request $request)
    {
        $uid = $request->id;
        $userinfo = UserDetailModel::where('uid', $uid)->first();
        if ($userinfo['shop_status'] == 1) {
            $result = UserDetailModel::where('uid', $uid)->update(['shop_status' => 2]);
        } else {
            $result = UserDetailModel::where('uid', $uid)->update(['shop_status' => 1]);
        }
        if (!$result)
            return response()->json(['error' => '修改失败！']);

        return response()->json(['massage' => '修改成功！', 'window.reload()']);
    }

    /**
     * 修改背景图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxUpdatePic(Request $request)
    {
        $user = Auth::User();
        $file = $request->back;

        $result = \FileClass::uploadFile($file, 'user', array('jpg', 'png', 'jpeg', 'bmp', 'png'));
        $result = json_decode($result, true);
        $backgroundurl = $result['data']['url'];
        $domain = \CommonClass::getDomain();
        return response()->json(['path' => $backgroundurl, 'domain' => $domain]);
    }

    /**
     * 删除背景图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxDelPic(Request $request)
    {
        $uid = $request->id;
        $result = UserDetailModel::where('uid', $uid)->update(['backgroundurl' => '']);
        $domain = \CommonClass::getDomain();
        return response()->json(['domain' => $domain]);
    }


    public function ajaxUpdateBack(Request $request)
    {
        $user = Auth::User();
        echo $backgroundurl = $request->src;
        $data = array(
            'backgroundurl' => $backgroundurl
        );
        $result = UserDetailModel::where('uid', $user->id)->update($data);
        $domain = \CommonClass::getDomain();
        return response()->json(['path' => $backgroundurl, 'domain' => $domain]);
    }

    public function ajaxDeleteSuccess(Request $request)
    {
        $id = $request->get('id');
        $user = Auth::User();
        $uid = $user->id;
        //判断成功案例是否属于该用户
        $successCase = SuccessCaseModel::where('id',$id)->where('uid',$uid)->first();
        if(empty($successCase)){
            $data = array(
                'code' => 0,
                'msg' => '参数错误'
            );
        }else{
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
        }
        return response()->json($data);

    }
    //用户收货地址添加
    public function addUserAdder(Request $request){
        $data=$request->except("_token");
        $data['uid']=Auth::user()->id;
        $data['created_at']=date('Y-m-d H:i:s');
        $data['updated_at']=date('Y-m-d H:i:s');
        $res=DB::transaction(function()use($data){
            UserAdderModel::where("uid",Auth::user()->id)->update(['status'=>1]);
            UserAdderModel::insert([$data]);
            
        });
        return back();
    }
    //添加收货地址进行ajax处理
    public function userAdderAjax(Request $request){
        $this->initTheme('ajaxpage');
        $data=$request->except("addId");
        $data['uid']=Auth::user()->id;
        $data['updated_at']=date('Y-m-d H:i:s');
        $res=DB::transaction(function()use($data,$request){
            if($request->get('addId')){
                if($request->get('status')){
                    UserAdderModel::where("uid",Auth::user()->id)->update(['status'=>1]);
                }else{
                    $data['status']=1;
                }
                UserAdderModel::where("id",$request->get('addId'))->update($data);
            }else{
                $data['created_at']=date('Y-m-d H:i:s');
                UserAdderModel::where("uid",Auth::user()->id)->update(['status'=>1]);
                UserAdderModel::insert([$data]);
            }
        });
        $userAddr=UserAdderModel::where("uid",Auth::user()->id)->orderBy('status','desc')->orderBy('id','desc')->get();
        foreach ($userAddr as $key=>$val){
            $userAddr[$key]['provinceName']=DistrictModel::where('id',$val['province'])->pluck("name");
            $userAddr[$key]['cityName']=DistrictModel::where('id',$val['city'])->pluck("name");
            $userAddr[$key]['cityArea']=DistrictModel::where('id',$val['area'])->pluck("name");
        }
        $view=[
            'userAddr'=>$userAddr,
        ];
        return $this->theme->scope('user.shop.userAdder', $view)->render();
    }
    //获取收货地址的信息
    public function getUserAdder(Request $request){
        //获取地址信息
        $userAdder=UserAdderModel::find($request->get('id'));
        //获取省份
        $province=DistrictModel::where("upid",0)->get()->toArray();
        //获取城市
        $city=DistrictModel::where("upid",$userAdder['province'])->get()->toArray();
        //获取地区
        $area=DistrictModel::where("upid",$userAdder['city'])->get()->toArray();
        return ['userAdder'=>$userAdder,'province'=>$province,'city'=>$city,'area'=>$area];
    }
    //修改收货地址的信息
    public function updateUserAdder(Request $request){
        if(!Auth::check()){//判断是否是登录状态
            return redirect("/login");
        }
         $addId=$request->get('adder_id');
        $data=$request->except("_token","adder_id");
        $data['updated_at']=date('Y-m-d H:i:s');
        $res=DB::transaction(function()use($request,$data,$addId){
            if($request->get('status')){
                UserAdderModel::where("uid",Auth::user()->id)->update(['status'=>1]);
            }else{
                $data['status']=1;
            }
            UserAdderModel::where("id",$addId)->update($data);
        });

        return back();
    }

    /**
     * 删除收货地址
     * @param $id
     * @return array
     */
    public function deleteAddr($id)
    {
        $res = UserAdderModel::where("id",$id)->delete();
        if($res){
            $data = [
                'code' => 1
            ];
        }else{
            $data = [
                'code' => 0
            ];
        }
        return $data;
    }
    //我的购物车
    public function shopCart(){
         $this->initTheme('shopCart');
         $programmeList=ProgrammeOrderModel::leftJoin("goods","programme_order.programme_id","=","goods.id")->leftJoin("attachment","goods.cover","=","attachment.id")->select("goods.title","attachment.url as at_url","programme_order.*")->where("programme_order.uid",Auth::user()->id)->whereIn("programme_order.status",[0,1])->get();
         //dd($programmeList);
         $data=[
             'programmeList'=>$programmeList
         ];
         return $this->theme->scope('user.shop.shopcart', $data)->render();
    }
    //购物车处理
    public function shopCartHandle(Request $request){
        if($request->get('type') !="allDel"){
            $programmeOrder=ProgrammeOrderModel::find($request->get("order_id"));
        }else{
            $_orderId=json_decode($request->get("order_id"));
            $programmeOrder=ProgrammeOrderModel::whereIn("id",$_orderId)->get();
        }
        if(!$programmeOrder){
            $code=1001;
        }elseif($request->get('type') =="reduce" && $programmeOrder['number'] <= 1){
            $code=1003;
        }else{
            $code =1002;
            switch($request->get('type')){
                case "add":
                    $res=$programmeOrder->increment("number");
                    break;
                case "reduce":
                    $res=$programmeOrder->decrement("number");
                    break;
                case "delete":
                    $res=$programmeOrder->delete();
                    break;
                case "allDel":
                    $res=ProgrammeOrderModel::whereIn("id",$_orderId)->delete();
                    break;
            }
            if($res){
                $code =1000;
            }
        }
        return ['code'=>$code];
    }
    /*
     * 购物车提交购买
     * */
    public function shopCartPay(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $goodsID=json_decode($request->get("orderID"));
        $this->initTheme('shopenquiry');
        $goods=ProgrammeOrderModel::select("goods.title","attachment.url as at_url","programme_order.price","programme_order.number","programme_order.freight")
            ->leftJoin("goods","programme_order.programme_id","=","goods.id")->leftJoin('attachment','goods.cover','=','attachment.id')
            ->whereIn('programme_order.id',$goodsID)->get();
        //计算运费以及总价格
        $freightPrice=ProgrammeOrderModel::getFreightAndPrice($goods);
        //获取该用户的地址
        $userAddr=UserAdderModel::where("uid",Auth::user()->id)->orderBy('status','desc')->orderBy('id','desc')->get();
        foreach ($userAddr as $key=>$val){
            $userAddr[$key]['provinceName']=DistrictModel::where('id',$val['province'])->pluck("name");
            $userAddr[$key]['cityName']=DistrictModel::where('id',$val['city'])->pluck("name");
            $userAddr[$key]['cityArea']=DistrictModel::where('id',$val['area'])->pluck("name");
        }
        //获取省份
        $province=DistrictModel::where("upid",0)->get()->toArray();
        //获取城市
        $city=DistrictModel::where("upid",$province[0]['id'])->get()->toArray();
        //获取地区
        $area=DistrictModel::where("upid",$city[0]['id'])->get()->toArray();
        $data=[
            'goods'=>$goods,
            'userAddr'=>$userAddr,
            'province'=>$province,
            'goodsID'=>$goodsID,
            'city'=>$city,
            'area'=>$area,
            'freightPrice'=>$freightPrice
        ];
        return $this->theme->scope('user.shop.cartPay', $data)->render();
    }
    /*
     * 提交订单购买
     * */
    public function shopProgramPay(Request $request){
        if(!Auth::user()->id){
            return redirect("/login");
        }
        $data=$request->except("_token","userAddr_id","inlineRadioOptions","goods_id","com_type");
        $res=DB::transaction(function()use($data,$request){
            $userAdder=UserAdderModel::find($request->get("userAddr_id"));
            $goodAllID=json_decode($request->get('goods_id'));
            // $data['number']=1;
            $data['consignee']=$userAdder['uname'];
            $data['mobile']=$userAdder['umobile'];
            $data['email']=$userAdder['uemail'];
            $data['province']=$userAdder['province'];
            $data['city']=$userAdder['city'];
            $data['area']=$userAdder['area'];
            $data['addr']=$userAdder['address'];
            $data['created_at']=date("Y-m-d H:i:s");
            $data['status']=1;
            if($request->get('is_invoice') ==1){
                $data['invoice_type']=1;
                $data['com_name']=$request->get("com_name");
                $data['invoices_raised']=2;
                if($request->get("com_type") && $request->get("com_type") == 1){
                    $data['com_name']='';
                    $data['invoices_raised']=1;
                }
            }else{
                $data['invoice_type']=2;
            }
            ProgrammeOrderModel::whereIn("id",$goodAllID)->update($data);
            return $request->get('goods_id');
        });
        if($res){
            return redirect("/shop/payment/".$res);
        }
        return back()->with(['message'=>"提交失败"]);
    }

    public function userinfo(){
        echo phpinfo();
    }
}
