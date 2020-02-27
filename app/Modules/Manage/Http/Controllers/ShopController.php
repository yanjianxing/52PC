<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Employ\Models\EmployGoodsModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Employ\Models\UnionRightsModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\Shop\Models\ShopUpgradeModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Vipshop\Models\ShopPackageModel;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('店铺管理');
        $this->theme->set('manageType', 'auth');
    }

    /**
     * 店铺列表视图
     *
     * @param Request $request
     * @return mixed
     */
    public function shopList(Request $request)
    {
        $merge = $request->all();
        $shopList = ShopModel::whereRaw('1 = 1');
        //关键字筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $shopList = $shopList->where(function($query) use ($keywords){
                $query->where('shop.shop_name', 'like', '%' . $keywords . '%')
                    ->orWhere('shop.id', 'like', '%' . $keywords . '%')
                    ->orWhere('users.name', 'like', '%' . $keywords . '%');

            });
        }
        //店铺类型
        if ($request->get('type')) {
            $shopList = $shopList->where('shop.type', $request->get('type'));
        }
        //店铺状态筛选
        if (($request->get('status') || $request->get('status') == '0') && $request->get('status') != -1) {
            $shopList = $shopList->where('shop.status', $request->get('status'));
        }
        //时间筛选
        if ($request->get('time_type')) {
            if ($request->get('start')) {
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d 00:00:00',strtotime($start));
                $shopList = $shopList->where($request->get('time_type'), '>=', $start);
            }
            if ($request->get('end')) {
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d 23:59:59',strtotime($end));
                $shopList = $shopList->where($request->get('time_type'), '<=', $end);
            }
        }
        if (($request->get('is_recommend') || $request->get('is_recommend') == '0') && $request->get('is_recommend') != -1) {
            $shopList = $shopList->where('shop.is_recommend', $request->get('is_recommend'))->where('shop.status','1');
        }
        $by = $request->get('by') ? $request->get('by') : 'created_at';
        $by = 'shop.'.$by;
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $shopList = $shopList->leftJoin('users', 'users.id', '=', 'shop.uid')
            ->select('shop.*', 'users.name')
            ->orderBy($by, $order)->paginate($paginate);
        if ($shopList) {
            $ShopId = array_pluck($shopList->toArray()['data'],'id');
            //店铺方案数量统计
            $goods = GoodsModel::whereIn('shop_id', $ShopId)->where('status',1)->groupBy('shop_id')->get(['shop_id',DB::raw('COUNT(id) as value')])->toArray();
            $goods = \CommonClass::setArrayKey($goods,'shop_id');
            foreach ($shopList as $k => $v) {
                $v->goods_num = in_array($v['id'],array_keys($goods)) ? $goods[$v['id']]['value'] : 0;

            }
        }

        $data = array(
            'merge' => $merge,
            'shop' => $shopList,
        );
        $this->theme->setTitle('店铺信息');
        return $this->theme->scope('manage.shop.shoplist', $data)->render();
    }


    /**
     * 店铺详情
     * @param int $id 店铺id
     */
    public function shopInfo($id)
    {
        $id = intval($id);
        //获取上一项id
        $preId = ShopModel::where('id', '>', $id)->min('id');
        //获取下一项id
        $nextId = ShopModel::where('id', '<', $id)->max('id');
        $shopInfo = ShopModel::getShopInfoById($id,1);
        if(!$shopInfo){
            return redirect()->back()->with(['message' => '参数错误']);
        }
        //查询店铺认证信息
        $realnameAuth = [];
        $enterpriseAuth = [];
        if($shopInfo['type'] == 1){
            $realnameAuth = RealnameAuthModel::where('uid',$shopInfo['uid'])->orderBy('id','desc')->first();
        }else{
            $enterpriseAuth = EnterpriseAuthModel::where('uid',$shopInfo['uid'])->orderBy('id','desc')->first();
        }
        $shopInfo['service_company']=json_decode($shopInfo['service_company']);
        $shopInfo['openWeb']=json_decode($shopInfo['openWeb']);
        $data = array(
            'shop_info'  => $shopInfo,
            'realname'   => $realnameAuth,
            'enterprise' => $enterpriseAuth,
            'pre_id'     => $preId,
            'next_id'    => $nextId
        );
        $this->theme->setTitle('店铺详情');
        return $this->theme->scope('manage.shop.shopinfo', $data)->render();
    }
    /*
     * 店铺编辑
     * */
    public function shopEdit($id){
        $shopInfo = ShopModel::getShopInfoById($id,1);
        if(!$shopInfo){
            return redirect()->back()->with(['message' => '参数错误']);
        }
        if(!empty($shopInfo['service_company'])){
            $shopInfo['service_company']=json_decode($shopInfo['service_company']);
        }
        //省份
        $province=DistrictModel::findTree(0);
        //城市
        $city=DistrictModel::findTree($shopInfo['province']);

        //地区
        $area=DistrictModel::findTree($shopInfo['city']);
        //获取技能标签
        $skill=CateModel::whereIn("type",[1,2,3])->select("type","id","name")->get()->toArray();
        $skill=\CommonClass::setArrayKey($skill,'type',2);
       //获取店铺的应用领域
        $shopApply=ShopTagsModel::where("shop_id",$id)->where("type",1)->lists("cate_id")->toArray();
        //获取店铺的技能标签
        $shopSkill=ShopTagsModel::where("shop_id",$id)->where("type",2)->lists("cate_id")->toArray();
        //获取店铺的开发平台
        $shopPlatform=ShopTagsModel::where("shop_id",$id)->where("type",3)->lists("cate_id")->toArray();
        $data = array(
            'shopInfo'  => $shopInfo,
            'province' =>$province,
            'city'     =>$city,
            'area'    =>$area,
            'skill'=>$skill,
            'shopApply'=>$shopApply,
            'shopSkill'=>$shopSkill,
            'shopPlatform'=>$shopPlatform
        );
        $this->theme->setTitle('店铺编辑');
        return $this->theme->scope('manage.shop.shopEdit', $data)->render();
    }
    /*
     * 店铺编辑提交
     * */
    public function shopEditPost(Request $request){
        $data=$request->except('_token','shop_pic','shop_id');
        $shopId=$request->get('shop_id');
        $shop=ShopModel::find($shopId);
        if(!$shop){
            return back()->with(["message"=>"该店铺不存在"]);
        }
        //店铺封面
        if($request->file('shop_pic')){
            $file=$request->file('shop_pic');
            $result = \FileClass::uploadFile($file,'user');
            $result = json_decode($result,true);
            $data['shop_pic'] = $result['data']['url'];
        }
        $res=DB::transaction(function()use($data,$shopId,$shop){
            $data['service_company']=isset($data['service_company'])?json_encode($data['service_company']):'';
            $data['platform']=isset($data['platform'])?$data['platform']:[];
            //获取所有开放平台信息
            $open=CateModel::whereIn("id",$data['platform'])->lists("name")->toArray();
            //店铺信息修改
            ShopModel::where("id",$shopId)->update([
                'shop_name'=>$data['shop_name'],
                'job_year'=>$data['job_year'],
                'province'=>$data['province'],
                'city'=>isset($data['city'])?$data['city']:'',
                'area'=>isset($data['area'])?$data['area']:'',
                'shop_desc'=>$data['shop_desc'],
                'openWeb'=>json_encode($open),
                'status'=>isset($data['status'])?$data['status']:$data['originalstatus'],
                'shop_pic'=>isset($data['shop_pic'])?$data['shop_pic']:$shop['shop_pic'],
                'service_company'=>isset($data['service_company'])?$data['service_company']:'',
                'updated_at'=>date("Y-m-d H:i:s"),
                'is_recommend'=>isset($data['is_recommend'])?$data['is_recommend']:'0',
                'index_sort'=>isset($data['index_sort'])?$data['index_sort']:'0',
                'shop_grade'=>isset($data['shop_grade'])?$data['shop_grade']:'A',
            ]);
            //修改店铺技能
            //先删除店铺技能
            ShopTagsModel::where("shop_id",$shopId)->whereIn('type',[1,2,3])->delete();
            $arr=[];
            $i=0;
            //应用领域
            if(isset($data['appliy'])){
                foreach($data['appliy'] as $av){
                    $arr[$i]['cate_id']=$av;
                    $arr[$i]['shop_id']=$shopId;
                    $arr[$i]['type']=1;
                    $i++;
                }
            }
            //技能标签
            if(isset($data['skillTag'])){
                foreach($data['skillTag'] as $sv){
                    $arr[$i]['cate_id']=$sv;
                    $arr[$i]['shop_id']=$shopId;
                    $arr[$i]['type']=2;
                    $i++;
                }
            }
            //开放平台
            if(isset($data['platform'])){
                foreach($data['platform'] as $pv){
                    $arr[$i]['cate_id']=$pv;
                    $arr[$i]['shop_id']=$shopId;
                    $arr[$i]['type']=3;
                    $i++;
                }
            }
            $shopTags=ShopTagsModel::insert($arr);
            return $shopTags;
        });
        if($res){
            return  redirect("/manage/shopList")->with(['message'=>'编辑成功']);
        }
             return back()->with(['message'=>'编辑失败']);
    }
    /**
     * 店铺审核
     * @param int $id 店铺id
     * @param string $action pass:通过 deny:拒绝
     * @return \Illuminate\Http\RedirectResponse
     */
    public function dealShop($id,$action,Request $request)
    {
        if(!in_array($action,['pass','deny'])){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        $all = $request->all();
        $res = ShopModel::shopDeal($id,$action,$all);
        if($res){
            return redirect()->back()->with(['message' => '操作成功']);
        }else{
            return redirect()->back()->with(['error' => '操作失败']);
        }
    }

    /**
     * 修改店铺信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateShopInfo(Request $request)
    {
        $data = $request->except('_token');
        $data['seo_desc'] = trim($data['seo_desc']);
        $res = ShopModel::where('id', $data['id'])->update($data);
        if ($res) {
            return redirect('/manage/shopInfo/' . $data['id'])->with(array('message' => '操作成功'));
        }
        return redirect('/manage/shopInfo/' . $data['id'])->with(array('message' => '操作失败'));
    }

    /**
     * 开启店铺
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function openShop($id)
    {
        $id = intval($id);
        //查询是否是关闭状态
        $shopInfo = ShopModel::where('id', $id)->first();
        if ($shopInfo->status == 2) {
            $arr = array(
                'status' => 1,
            );
            $res = ShopModel::where('id', $id)->update($arr);
            UserDetailModel::where('uid',$shopInfo->uid)->update(['shop_status' => 1]);
            if ($res) {
                return redirect()->back()->with(array('message' => '操作成功'));
            } else {
                return redirect()->back()->with(array('message' => '操作失败'));
            }
        } else {
            return redirect()->back()->with(array('message' => '操作成功'));
        }

    }

    /**
     * 关闭店铺(同时取消推荐)
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function closeShop($id)
    {
        $id = intval($id);
        //查询是否是开启状态
        $shopInfo = ShopModel::where('id', $id)->first();
        if ($shopInfo->status == 1) {
            $arr = array(
                'status' => 2,
                'is_recommend' => 0
            );
            $res = ShopModel::where('id', $id)->update($arr);
            UserDetailModel::where('uid',$shopInfo->uid)->update(['shop_status' => 2]);
            if ($res) {
                return redirect()->back()->with(array('message' => '操作成功'));
            } else {
                return redirect()->back()->with(array('message' => '操作失败'));
            }
        } else {
            return redirect()->back()->with(array('message' => '操作成功'));
        }
    }

    /**
     * 批量开启店铺
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function allOpenShop(Request $request)
    {
        $ids = $request->get('ids');
        $idArr = explode(',', $ids);
        $res = ShopModel::AllShopOpen($idArr);
        if ($res) {
            $data = array(
                'code' => 1,
                'msg' => '操作成功'
            );
        } else {
            $data = array(
                'code' => 0,
                'msg' => '操作失败'
            );
        }
        return response()->json($data);
    }

    /**
     * 批量关闭店铺
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function allCloseShop(Request $request)
    {
        $ids = $request->get('ids');
        $idArr = explode(',', $ids);
        $res = ShopModel::AllShopClose($idArr);
        if ($res) {
            $data = array(
                'code' => 1,
                'msg' => '操作成功'
            );
        } else {
            $data = array(
                'code' => 0,
                'msg' => '操作失败'
            );
        }
        return response()->json($data);
    }

    /**
     * 推荐店铺到威客商城
     * @param int $id 店铺id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function recommendShop($id)
    {
        $id = intval($id);
        //查询店铺是否开启
        $shop = ShopModel::where('id', $id)->first();
        if ($shop->status == 1) {
            $arr = array(
                'is_recommend' => 1
            );
            $res = ShopModel::where('id', $id)->update($arr);
            if ($res) {
                return redirect()->back()->with(array('message' => '操作成功'));
            } else {
                return redirect()->back()->with(array('message' => '操作失败'));
            }
        } else {
            return redirect()->back()->with(array('message' => '操作失败'));
        }
    }

    /**
     * 取消推荐店铺到威客商城
     * @param $id 店铺id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeRecommendShop($id)
    {
        $id = intval($id);
        //查询店铺是否开启
        $shop = ShopModel::where('id', $id)->first();
        if ($shop->status == 1) {
            $arr = array(
                'is_recommend' => 0
            );
            $res = ShopModel::where('id', $id)->update($arr);
            if ($res) {
                return redirect()->back()->with(array('message' => '操作成功'));
            } else {
                return redirect()->back()->with(array('message' => '操作失败'));
            }
        } else {
            return redirect()->back()->with(array('message' => '操作失败'));
        }
    }

    public function shopUpgrade(Request $request)
    {
        $merge = $request->all();
        $shopList = ShopUpgradeModel::whereRaw('1 = 1');

        //店铺称筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $shopList = $shopList->where(function($query) use ($keywords){
                $query->where('shop.shop_name', 'like', '%' . $keywords . '%')
                    ->orWhere('shop_upgrade.id', 'like', '%' . $keywords . '%')
                    ->orWhere('users.name', 'like', '%' . $keywords . '%');
            });
        }
        //状态
        if (($request->get('status') || $request->get('status') == '0') && $request->get('status') != -1) {
            $shopList = $shopList->where('shop_upgrade.status', $request->get('status'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $shopList = $shopList->where('shop_upgrade.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $shopList = $shopList->where('shop_upgrade.created_at', '<=', $end);
        }
        $by = $request->get('by') ? $request->get('by') : 'shop.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $shopList = $shopList->leftJoin('shop', 'shop.id', '=', 'shop_upgrade.shop_id')->leftJoin('users', 'users.id', '=', 'shop_upgrade.uid')
            ->select('shop.id as shop_id','shop.shop_name', 'users.name','shop_upgrade.created_at','shop_upgrade.uid','shop_upgrade.status','shop_upgrade.id')
            ->orderBy($by, $order)->paginate($paginate);
        $data = array(
            'merge' => $merge,
            'shop'  => $shopList,
        );
        $this->theme->setTitle('店铺升级信息');
        return $this->theme->scope('manage.shop.upgradelist', $data)->render();
    }

    public function shopUpgradeInfo($id)
    {
        $id = intval($id);
        //获取上一项id
        $preId = ShopUpgradeModel::where('id', '>', $id)->min('id');
        //获取下一项id
        $nextId = ShopUpgradeModel::where('id', '<', $id)->max('id');
        $info = ShopUpgradeModel::find($id);
        if(!$info){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        //查询店铺认证信息
        $enterpriseAuth = EnterpriseAuthModel::where('uid',$info['uid'])->orderBy('id','desc')->first();
        if(!$enterpriseAuth){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        $shopInfo = ShopModel::getShopInfoById($info->shop_id,1);

        $data = array(
            'shop_info'  => $shopInfo,
            'info'       => $info,
            'enterprise' => $enterpriseAuth,
            'pre_id'     => $preId,
            'next_id'    => $nextId
        );
        $this->theme->setTitle('店铺详情');
        return $this->theme->scope('manage.shop.upgradeinfo', $data)->render();
    }

    /**
     * 店铺升级审核
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function dealShopUpgrade($id,$action,Request $request)
    {
        if(!in_array($action,['pass','deny'])){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        if($action == 'pass'){
            $res = ShopUpgradeModel::shopUpgradePass($id);
        }else{
            $reason = $request->get('reason') ? $request->get('reason') : '';
            $res = ShopUpgradeModel::shopUpgradeDeny($id,$reason);
        }
        if($res){
            return redirect()->back()->with(['message' => '操作成功']);
        }else{
            return redirect()->back()->with(['error' => '操作失败']);
        }
    }




    /**
     * 店铺配置视图
     * @return mixed
     */
    public function shopConfig()
    {
        $shopConfig = ConfigModel::getConfigByType('shop_config');
        //查询推荐作品增值服务工具
        $goodsService = ServiceModel::where('identify','ZUOPINTUIJIAN')->first();
        //查询推荐服务增值服务工具
        $service = ServiceModel::where('identify','FUWUTUIJIAN')->first();
        $data = array(
            'shop_config' => $shopConfig,
            'goods_service' => $goodsService,
            'service' => $service,
        );
        $this->theme->setTitle('店铺配置');
        return $this->theme->scope('manage.shopconfig', $data)->render();
    }

    /**
     * 保存店铺配置
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postShopConfig(Request $request)
    {
        $data = $request->all();
        $configData = array(
            'goods_check' => $data['goods_check'],
            'service_check' => $data['service_check'],
            'recommend_goods_unit' => $data['goods_unit'],
            'recommend_service_unit' => $data['service_unit']
        );
        ConfigModel::updateConfig($configData);
        Cache::forget('shop_config');
        //修改推荐作品增值服务工具价格
        ServiceModel::where('identify','ZUOPINTUIJIAN')
            ->update(['price'=>$data['recommend_goods_price'],'status' => $data['is_goods_recommend']]);
        //修改推荐服务增值服务工具价格
        ServiceModel::where('identify','FUWUTUIJIAN')
            ->update(['price'=>$data['recommend_service_price'],'status' => $data['is_service_recommend']]);

        return redirect('/manage/shopConfig')->with(array('message' => '操作成功'));
    }

    /**
     * 交易反馈列表
     * @param Request $request
     * @return mixed
     */
    public function rightsList(Request $request)
    {
        $merge = $request->all();
        $rightsList = UnionRightsModel::whereRaw('1 = 1')->where('is_delete',0);
        //维权人
        if ($request->get('username')) {
            $rightsList = $rightsList->where('users.name', 'like', '%' . $request->get('username') . '%');
        }
        //维权类型
        if ($request->get('reportType') && $request->get('reportType') != 0) {
            $rightsList = $rightsList->where('union_rights.type', $request->get('reportType'));
        }
        //维权对象类型
        if ($request->get('objectType') && $request->get('objectType') != 0) {
            $rightsList = $rightsList->where('union_rights.object_type', $request->get('objectType'));
        }
        //维权状态
        if ($request->get('reportStatus') && $request->get('reportStatus') != 0) {
            switch ($request->get('reportStatus')) {
                case 1:
                    $status = 1;
                    $rightsList = $rightsList->where('union_rights.status', $status);
                    break;
                case 2:
                    $status = 0;
                    $rightsList = $rightsList->where('union_rights.status', $status);
                    break;
                case 3:
                    $status = 2;
                    $rightsList = $rightsList->where('union_rights.status', $status);
                    break;
            }
        }
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $rightsList = $rightsList->where('union_rights.created_at', '>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $rightsList = $rightsList->where('union_rights.created_at', '<',$end);
        }
        $by = $request->get('by') ? $request->get('by') : 'union_rights.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $rightsList = $rightsList->leftJoin('users', 'users.id', '=', 'union_rights.from_uid')
            ->select('union_rights.*', 'users.name')
            ->orderBy($by, $order)->paginate($paginate);
        if (!empty($rightsList)) {
            $goodsId = array();
            $employId = array();
            foreach ($rightsList as $k => $v) {
                if ($v->object_type == 2) {
                    $goodsId[] = $v->object_id;
                } elseif ($v->object_type == 1) {
                    $employId[] = $v->object_id;
                }
            }
            if (!empty($goodsId)) {
                $goods = ShopOrderModel::whereIn('id', $goodsId)->select('id', 'title')->get();
            } else {
                $goods = array();
            }
            if (!empty($employId)) {
                $employ = EmployModel::whereIn('id', $employId)->select('id', 'title')->get();
            } else {
                $employ = array();
            }
            foreach ($rightsList as $k => $v) {
                if (!empty($goods)) {
                    foreach ($goods as $a => $b) {
                        if ($v->object_id == $b->id) {
                            $v->title = $b->title;
                        }
                    }
                }

                if (!empty($employ)) {
                    foreach ($employ as $a => $b) {
                        if ($v->object_id == $b->id) {
                            $v->title = $b->title;
                        }
                    }
                }
            }
        }

        $data = array(
            'merge' => $merge,
            'rights_list' => $rightsList,
        );
        $this->theme->setTitle('交易维权');
        return $this->theme->scope('manage.rightslist', $data)->render();
    }

    /**
     * 维权详情
     * @param $id  维权id
     * @return mixed
     */
    public function shopRightsInfo($id)
    {
        $id = intval($id);
        //获取上一项id
        $preId = UnionRightsModel::where('id', '>', $id)->min('id');
        //获取下一项id
        $nextId = UnionRightsModel::where('id', '<', $id)->max('id');
        $rightsInfo = UnionRightsModel::rightsInfoById($id);

        $employ = [];
        if($rightsInfo['object_type']==1)
        {
            //查询employ
            $employ = EmployModel::where('id',$rightsInfo['object_id'])->first();
        }
        $data = array(
            'rights_info' => $rightsInfo,
            'pre_id' => $preId,
            'next_id' => $nextId,
            'employ'=>$employ
        );
        $this->theme->setTitle('交易维权详情');
        return $this->theme->scope('manage.rightsinfo', $data)->render();
    }

    /**
     * 下载附件
     * @param $id 附件id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($id)
    {
        $pathToFile = AttachmentModel::where('id', $id)->first();
        $pathToFile = $pathToFile['url'];
        return response()->download($pathToFile);
    }


    /**
     * 维权成功
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function ShopRightsSuccess(Request $request,$id)
    {
        $id = intval($id);
        $rightsInfo = UnionRightsModel::rightsInfoById($id);
        $shopOrder = ShopOrderModel::where('id',$rightsInfo->object_id)->first();
        $fromPrice = $shopOrder->cash;
        $domain = \CommonClass::getDomain();
        //商品购买维权
        if($rightsInfo->object_type == 2){
            $status = UnionRightsModel::dealGoodsRights($id,$fromPrice);
            if($status){
                $shopRightsTem = MessageTemplateModel::where('code_name','shop_rights')->where('is_open',1)->first();
                if($shopRightsTem){
                    $siteName = \CommonClass::getConfig('site_name');//必要条件
                    //给维权方发信息
                    $fromNewArr = array(
                        'username' => $rightsInfo->from_name,
                        'href' => $domain.'/shop/buyGoods/'.$shopOrder->object_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权成立，您获得金额为'.$fromPrice.'元',
                        'website' => $siteName

                    );
                    if($shopRightsTem->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('shop_rights',$rightsInfo->from_uid,2,$fromNewArr,$shopRightsTem['name']);
                    }

                    if($shopRightsTem->is_send_email == 1){
                        $email = $rightsInfo->from_email;
                        \MessageTemplateClass::sendEmailByCode('shop_rights',$email,$fromNewArr,$shopRightsTem['name']);
                    }
                    //给被维权方发信息
                    $toNewArr = array(
                        'username' => $rightsInfo->to_name,
                        'href' => $domain.'/shop/buyGoods/'.$shopOrder->object_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权成立，您获得金额为0元',
                        'website' => $siteName

                    );
                    if($shopRightsTem->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('shop_rights',$rightsInfo->to_uid,2,$toNewArr,$shopRightsTem['name']);
                    }

                    if($shopRightsTem->is_send_email == 1){
                        $email = $rightsInfo->to_email;
                        \MessageTemplateClass::sendEmailByCode('shop_rights',$email,$toNewArr,$shopRightsTem['name']);
                    }
                }
            }
        }
        return redirect('/manage/shopRightsInfo/'.$id);

    }

    /**
     * 维权不成立
     * @param $id 维权id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function ShopRightsFailure($id)
    {
        $id = intval($id);
        $rightsInfo = UnionRightsModel::rightsInfoById($id);
        $shopOrder = ShopOrderModel::where('id',$rightsInfo->object_id)->first();
        $domain = \CommonClass::getDomain();
        if($rightsInfo->object_type == 2){
            $status = UnionRightsModel::dealGoodsRightsFailure($id);
            if($status){
                $shopRightsTem = MessageTemplateModel::where('code_name','shop_rights')->where('is_open',1)->first();
                if($shopRightsTem){
                    $siteName = \CommonClass::getConfig('site_name');//必要条件
                    //给维权方发信息
                    $fromNewArr = array(
                        'username' => $rightsInfo->from_name,
                        'href' => $domain.'/shop/buyGoods/'.$shopOrder->object_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权不成立',
                        'website' => $siteName

                    );
                    if($shopRightsTem->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('shop_rights',$rightsInfo->from_uid,2,$fromNewArr,$shopRightsTem['name']);
                    }

                    if($shopRightsTem->is_send_email == 1){
                        $email = $rightsInfo->from_email;
                        \MessageTemplateClass::sendEmailByCode('shop_rights',$email,$fromNewArr,$shopRightsTem['name']);
                    }
                    //给被维权方发信息
                    $toNewArr = array(
                        'username' => $rightsInfo->to_name,
                        'href' => $domain.'/shop/buyGoods/'.$shopOrder->object_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权不成立',
                        'website' => $siteName

                    );
                    if($shopRightsTem->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('shop_rights',$rightsInfo->to_uid,2,$toNewArr,$shopRightsTem['name']);
                    }

                    if($shopRightsTem->is_send_email == 1){
                        $email = $rightsInfo->to_email;
                        \MessageTemplateClass::sendEmailByCode('shop_rights',$email,$toNewArr,$shopRightsTem['name']);
                    }
                }
            }
        }
        return redirect('/manage/shopRightsInfo/'.$id);
    }



    /**
     * 雇佣维权成功
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    static function serviceRightsSuccess(Request $request)
    {
        $id = $request->get('id');
        $domain = \CommonClass::getDomain();
        //验证金额
        if((!$request->get('to_price') && $request->get('to_price')!=0) || (!$request->get('from_price') && $request->get('from_price')!=0))
        {
            return redirect()->back()->with(['error'=>'请填写金额！']);
        }
        $rightsInfo = UnionRightsModel::rightsInfoById($id);
        //查询雇佣金额
        $employ_info = EmployModel::where('id',$rightsInfo['object_id'])->whereIn('status',[7,8])->first();
        //查询服务id
        $serviceInfo = EmployGoodsModel::where('employ_id',$rightsInfo['object_id'])->first();
        if(!$employ_info)
        {
            return redirect()->back()->with(['error'=>'维权雇佣任务不存在！']);
        }
        $toPrice = $request->get('to_price');
        $fromPrice = $request->get('from_price');
        if(($toPrice+$fromPrice)!=$employ_info['bounty'])
        {
            return redirect()->back()->with(['error'=>'请正确分配金额！']);
        }
        //商品购买维权
        if($rightsInfo->object_type == 1){
            $status = UnionRightsModel::dealSeriviceRights($id,$fromPrice,$toPrice);
            if($status){
                $shopRightsTem = MessageTemplateModel::where('code_name','shop_rights')
                    ->where('is_open',1)->first();
                if($shopRightsTem){
                    $siteName = \CommonClass::getConfig('site_name');//必要条件
                    //给维权方发信息
                    $fromNewArr = array(
                        'username' => $rightsInfo->from_name,
                        'href' => $domain.'/shop/buyservice/'.$serviceInfo->service_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权成立，您获得金额为'.$fromPrice.'元',
                        'website' => $siteName

                    );
                    if($shopRightsTem->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('shop_rights',$rightsInfo->from_uid,2,$fromNewArr,$shopRightsTem['name']);
                    }

                    if($shopRightsTem->is_send_email == 1){
                        $email = $rightsInfo->from_email;
                        \MessageTemplateClass::sendEmailByCode('shop_rights',$email,$fromNewArr,$shopRightsTem['name']);
                    }

                    //给被维权方发信息
                    $toNewArr = array(
                        'username' => $rightsInfo->to_name,
                        'href' => $domain.'/shop/buyservice/'.$serviceInfo->service_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权成立，您获得金额为'.$toPrice.'元',
                        'website' => $siteName

                    );
                    if($shopRightsTem->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('shop_rights',$rightsInfo->to_uid,2,$toNewArr,$shopRightsTem['name']);
                    }

                    if($shopRightsTem->is_send_email == 1){
                        $email = $rightsInfo->to_email;
                        \MessageTemplateClass::sendEmailByCode('shop_rights',$email,$toNewArr,$shopRightsTem['name']);
                    }
                }
            }
        }
        return redirect('/manage/shopRightsInfo/'.$id);
    }


    /**
     * 雇佣维权失败
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function serviceRightsFailure($id)
    {
        $id = intval($id);
        $domain = \CommonClass::getDomain();
        $rightsInfo = UnionRightsModel::rightsInfoById($id);
        //查询服务id
        $serviceInfo = EmployGoodsModel::where('employ_id',$rightsInfo['object_id'])->first();
        if($rightsInfo['object_type']==1)
        {
            $result = UnionRightsModel::serviceRightsHandel($id);
            if($result){
                $shopRightsTem = MessageTemplateModel::where('code_name','shop_rights')
                    ->where('is_open',1)->where('is_on_site',1)->first();
                if($shopRightsTem){
                    $siteName = \CommonClass::getConfig('site_name');//必要条件
                    //给维权方发信息
                    $fromNewArr = array(
                        'username' => $rightsInfo->from_name,
                        'href' => $domain.'/shop/buyservice/'.$serviceInfo->service_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权不成立',
                        'website' => $siteName

                    );
                    $fromMessageContent = MessageTemplateModel::sendMessage('shop_rights',$fromNewArr);
                    $messageFrom = [
                        'message_title'=>$shopRightsTem['name'],
                        'code'=>'trading_rights_result',
                        'message_content'=>$fromMessageContent,
                        'js_id'=>$rightsInfo->from_uid,
                        'message_type'=>2,
                        'receive_time'=>date('Y-m-d H:i:s',time()),
                        'status'=>0,
                    ];
                    MessageReceiveModel::create($messageFrom);
                    //给被维权方发信息
                    $toNewArr = array(
                        'username' => $rightsInfo->to_name,
                        'href' => $domain.'/shop/buyservice/'.$serviceInfo->service_id,
                        'trade_name' => $rightsInfo->title,
                        'content' => '维权不成立',
                        'website' => $siteName

                    );
                    $toMessageContent = MessageTemplateModel::sendMessage('shop_rights',$toNewArr);
                    $messageTo = [
                        'message_title'=>$shopRightsTem['name'],
                        'code'=>'trading_rights_result',
                        'message_content'=>$toMessageContent,
                        'js_id'=>$rightsInfo->to_uid,
                        'message_type'=>2,
                        'receive_time'=>date('Y-m-d H:i:s',time()),
                        'status'=>0,
                    ];
                    MessageReceiveModel::create($messageTo);
                }
            }
        }
        return redirect('/manage/shopRightsInfo/'.$id);
    }


    /**
     * 删除维权
     * @param $id 维权id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function deleteShopRights($id)
    {
        $id = intval($id);
        $rightsInfo = UnionRightsModel::where('id',$id)->first();
        if($rightsInfo->is_delete == 0 && $rightsInfo->status != 0){
            UnionRightsModel::where('id',$id)->update(['is_delete' => 1]);
        }
        return redirect('/manage/ShopRightsList');
    }




    /**
     * vip首页配置
     * @return mixed
     */
    public function vipshopConfig()
    {
        $vipConfig = ConfigModel::getVipConfigByType('vip');
        $data = [
            'vipConfig' => $vipConfig
        ];
        return $this->theme->scope('manage.vipConfig',$data)->render();
    }

    /**
     * vip套餐管理
     * @return mixed
     */
    public function vipPackageList()
    {
        return $this->theme->scope('manage.vipPackageList')->render();
    }
    /**
     * 添加套餐
     * @return mixed
     */
    public function addPackage()
    {
        return $this->theme->scope('manage.addPackage')->render();
    }
    /**
     * vip特权列表
     * @return mixed
     */
    public function vipInfoList()
    {
        return $this->theme->scope('manage.vipInfoList')->render();
    }

    /**
     * vip特权列表
     * @return mixed
     */
    public function vipInfoAdd()
    {
        return $this->theme->scope('manage.vipInfoAdd')->render();
    }

    /**
     * vip店铺
     * @return mixed
     */
    public function vipShopList(Request $request)
    {
        $data = $request->all();
        $packageInfo = ShopPackageModel::packageInfo();
        $shopPackageList = ShopPackageModel::shopPackageList($data);
        $shopPackage = [
            'package' => $packageInfo,
            'shopPackageList' => $shopPackageList,
            'merge' => $data
        ];
        return $this->theme->scope('manage.vipShopList',$shopPackage)->render();
    }
    /**
     * vip店铺查看
     * @return mixed
     */
    public function vipShopAuth()
    {
        return $this->theme->scope('manage.vipShopAuth')->render();
    }

    /**
     * vip访谈列表
     * @return mixed
     */
    public function vipDetailsList()
    {
        return $this->theme->scope('manage.vipDetailsList')->render();
    }
    /**
     * vip添加访谈
     * @return mixed
     */
    public function vipDetailsAuth()
    {
        return $this->theme->scope('manage.vipDetailsAuth')->render();
    }
}
