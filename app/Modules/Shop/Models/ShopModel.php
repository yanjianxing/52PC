<?php

namespace App\Modules\Shop\Models;

use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\SkillTagsModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopModel extends Model
{
    protected $table = 'shop';
    //
    protected $fillable = [
        'id',
        'uid',
        'type',
        'shop_pic',
        'shop_name',
        'company_name',
        'shop_desc',
        'province',
        'city',
        'area',
        'status',//店铺状态 0=>待审核 3审核不通过  1=>开启  2=>关闭
        'created_at',
        'updated_at',
        'shop_bg',
        'seo_title',
        'seo_keyword',
        'seo_desc',
        'is_recommend',
        'phone',
        'email',
        'openWeb',
        'service_company',
        'delivery_count',//接包数量
        'receive_task_num',//选中数量
        'publish_task_num',//发布数量
        'reason',
        'goods_num',//方案数量
        'job_year',
        'index_sort',
        'shop_grade',
    ];

    public function province()
    {
        return $this->hasOne('App\Modules\User\Model\DistrictModel','id','province');
    }
    public function city()
    {
        return $this->hasOne('App\Modules\User\Model\DistrictModel','id','city');
    }

    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel','id','uid');
    }

    public function provinces()
    {
        return $this->hasOne('App\Modules\User\Model\DistrictModel','id','province');
    }
    public function citys()
    {
        return $this->hasOne('App\Modules\User\Model\DistrictModel','id','city');
    }

    /**
     * 首页 推荐服务商
     * @param int $type 1：推荐服务商 2：VIP服务商 3：好评服务商 4：人气服务商
     * @return array|bool|mixed
     */
    static public function getHomeShopByType($type=1,$data = [])
    {
        $shop = [];
        if(!in_array($type,[1,2,3,4])){
            return $shop;
        }
        if($type == 1){//推荐服务商
            $shop = RecommendModel::getRecommendByCode('HOME_SERVICE','shop',$data);
        }else{
            switch($type){
                case 2://VIP服务商
                    $uidArr = VipUserOrderModel::where('status',1)
                        ->orderBy('created_at','desc')
                        ->select('uid')->limit(6)->get()->toArray();
                    $uidArr = array_flatten($uidArr);
                    $shop = ShopModel::whereIn('uid',$uidArr)
                        ->select('shop_pic', 'shop_name', 'shop_desc','id','uid')
                        ->get()->toArray();
                    break;
                case 3://好评服务商
                    $shop = ShopModel::select('shop_pic', 'shop_name', 'shop_desc','id','uid')->orderBy('good_comment','desc')
                        ->limit(6)->get()->toArray();
                    $uidArr = array_pluck($shop,'uid');
                    break;
                case 4://人气服务商
                    $shop = ShopModel::select('shop_pic', 'shop_name', 'shop_desc','id','uid')->orderBy('view_count','desc')
                        ->limit(6)->get()->toArray();
                    $uidArr = array_pluck($shop,'uid');
                    break;
            }
            if($shop && isset($uidArr)){
                $auth = ShopModel::getShopAuth($uidArr);
                $goods = ShopModel::getGoodsByUid($uidArr);
                foreach($shop as $k => $v){
                    $shop[$k]['auth'] = in_array($v['uid'],array_keys($auth)) ? $auth[$v['uid']] : [];
                    $shop[$k]['goods'] = in_array($v['uid'],array_keys($goods)) ? $goods[$v['uid']] : [];
                }
            }else{
                foreach($shop as $k => $v){
                    $shop[$k]['auth'] = [];
                    $shop[$k]['goods'] = [];
                }
            }
        }
        return $shop;
    }

    /**
     * 首页 推荐服务商
     * @param int $type 1：推荐服务商 2：VIP服务商 3：好评服务商 4：人气服务商
     * @return array|bool|mixed
     */
    static public function getHomeShopByTypeNew($type=1,$data = [])
    {
        $shop = [];
        if(!in_array($type,[1,2,3,4])){
            return $shop;
        }
        switch($type){
            case 1://VIP服务商
                $code = 'HOME_SERVICE';
                break;
            case 2://VIP服务商
                $code = 'HOME_VIPSERVICE';
                break;
            case 3://好评服务商
                $code = 'HOME_GOODSERVICE';
                break;
            case 4://人气服务商
                $code = 'HOME_HITSERVICE';
                break;
            default:
                $code = 'HOME_SERVICE';
                break;
        }
        $shop = RecommendModel::getRecommendByCode($code,'shop',$data);
        return $shop;
    }

    /**
     * 获取店铺列表
     * @param $paginate
     * @param array $merge
     * @return mixed
     */
    static public function getShopList($paginate,$merge=[])
    {
        $list = ShopModel::where('status',1);
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $list = $list->where('shop_name','like','%'.$merge['keywords'].'%');
            //有关键字添加搜索记录
            \CommonClass::get_keyword($merge['keywords'],6);
        }
        if(isset($merge['task_uid']) && !empty($merge['task_uid'])){
            $list = $list->where('uid','!=',$merge['task_uid']);
        }
        if(isset($merge['is_invite']) && $merge['is_invite'] == 1){
            $uidArr = UserVipConfigModel::where('is_invited',1)->lists('uid')->toArray();
            $list = $list->whereIn('uid',$uidArr);
        }
        if(isset($merge['cate_id']) && !empty($merge['cate_id'])){
            $fieldShopId = ShopTagsModel::where('type',1)->where('cate_id',$merge['cate_id'])->lists('shop_id')->toArray();
            $list = $list->whereIn('id',$fieldShopId);
        }
        if(isset($merge['skill_id']) && !empty($merge['skill_id'])){
            $skillShopId = ShopTagsModel::where('type',2)->where('cate_id',$merge['skill_id'])->lists('shop_id')->toArray();
            $list = $list->whereIn('id',$skillShopId);
        }
        if(isset($merge['type']) && !empty($merge['type'])){

            $list = $list->where('type',$merge['type']);
        }
        if(isset($merge['shop_type']) && !empty($merge['shop_type'])){
            switch($merge['shop_type']){
                case 'vip':
                    $userIdArr = VipUserOrderModel::where('status',1)->lists('uid')->toArray();
                    $list = $list->whereIn('uid',$userIdArr);
                    break;
                case 'promise':
                    $userIdArr = AuthRecordModel::where('auth_code','promise')->where('status',1)->lists('uid')->toArray();
                    $list = $list->whereIn('uid',$userIdArr);
                    break;
            }

        }
        if(isset($merge['district']) && !empty($merge['district'])){
            $districtId = $merge['district'];
            $list = $list->where(function($query) use ($districtId){
                $query->where('province',$districtId)->orWhere('city',$districtId);
            });
        }
        $list = $list->with('province','city');
        if(isset($merge['order']) && $merge['order']){
            $list = $list->orderBy($merge['order'],'desc');
        }
        $list = $list->orderBy('is_recommend','desc')->orderBy('index_sort','desc')->orderBy('id','desc')->paginate($paginate);

        return $list;

    }


    /**
     * 获取店铺用户认证信息
     * @param $uidArr
     * @return array
     */
    static public function getShopAuth($uidArr)
    {
        $userAuth = AuthRecordModel::whereIn('uid', $uidArr)
            ->where(function($query){
                $query->where(function($querys){
                    $querys->where('status', 2)->whereIn('auth_code',['bank','alipay']);
                })->orwhere(function($querys){
                    $querys->where('status', 1)->whereIn('auth_code',['realname','enterprise','promise']);
                });
            })->select('uid','auth_code')->get()->toArray();
        $vipAuth = VipUserOrderModel::whereIn('uid',$uidArr)->where('status',1)->select('uid')->get()->toArray();
        $vip = ['auth_code'=>'vip'];
        array_walk($vipAuth, function (&$value, $key, $vip) {
            $value = array_merge($value, $vip);
        }, $vip);
        $authArr = array_merge($userAuth,$vipAuth);
        $authArr = \CommonClass::setArrayKey($authArr,'uid',2,'auth_code');
        return $authArr;
    }

    /**
     * 获取用户店铺售卖方案
     * @param $uidArr
     * @return array
     */
    static public function getGoodsByUid($uidArr)
    {
        $goods = GoodsModel::where('is_delete',0)->where('status',1)->whereIn('uid',$uidArr)
            ->select('id','title','type','cash','uid','shop_id')->get()->toArray();
        $goods = \CommonClass::setArrayKey($goods,'uid',2);
        return $goods;
    }

    /**
     * 获取店铺信息
     * @param $uid
     * @return array
     */
    static public function getShopByUid($uid)
    {
        $shopArr = [];
        if(is_array($uid)){
            $shopArr = self::whereIn('uid',$uid)->with('user')->get()->toArray();
            $provinceId = array_pluck($shopArr,'province');
            $cityId = array_pluck($shopArr,'city');
            $district = DistrictModel::whereIn('id',array_merge($provinceId,$cityId))->select('id','name')->get()->toArray();
            $district = \CommonClass::setArrayKey($district,'id');
            //认证关系
            $auth = self::getShopAuth($uid);
            foreach($shopArr as $k => $v){
                $shopArr[$k]['province'] = in_array($v['province'],array_keys($district)) ? $district[$v['province']] : [];
                $shopArr[$k]['city'] = in_array($v['city'],array_keys($district)) ? $district[$v['city']] : [];
                $shopArr[$k]['auth'] = in_array($v['uid'],array_keys($auth)) ? $auth[$v['uid']] : [];
            }
        }else{
            $shop = self::where('uid',$uid)->with('user')->first();
            if($shop){
                $district = DistrictModel::whereIn('id',[$shop->province,$shop->city])->select('id','name')->get()->toArray();
                $district = \CommonClass::setArrayKey($district,'id');
                $shop->province = in_array($shop['province'],array_keys($district)) ? $district[$shop['province']] : [];
                $shop->city = in_array($shop['city'],array_keys($district)) ? $district[$shop['city']] : [];
                $shopArr = $shop->toArray();
            }
        }

        return $shopArr;
    }

    /**
     * 店铺审核
     * @param int $id 店铺id
     * @param string $action pass:通过 deny:拒绝
     * @return bool
     */
    static function shopDeal($id,$action,$all=[])
    {
        $info = self::find($id);
        if(!$info || $info->status != 0){
            return false;
        }
        if($action == 'pass'){
            $status = DB::transaction(function () use ($info) {
                //获取用户信息
                $userDetial= UserDetailModel::where("uid",$info['uid'])->select("publish_task_num","receive_task_num")->first();
                self::where('id',$info->id)->update([
                    'status'           => 1,//开启
                    'publish_task_num' => $userDetial['publish_task_num'],
                    'receive_task_num' => $userDetial['receive_task_num'],
                    'updated_at'       => date('Y-m-d H:i:s')
                ]);
                if($info->type == 1){
                    $realname =  RealnameAuthModel::where('uid',$info->uid)
                        ->orderBy('id','desc')->first();
                    if($realname && $realname->status == 0){
                        //审核通过最新的实名认证
                        RealnameAuthModel::where('id',$realname->id)->update([
                            'status'     => 1,
                            'auth_time'  => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        AuthRecordModel::where('uid',$info->uid)->where('auth_code','realname')->update([
                            'status'    => 1,
                            'auth_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                }else{
                    $enterprise =  EnterpriseAuthModel::where('uid',$info->uid)
                        ->orderBy('id','desc')->first();
                    if($enterprise && $enterprise->status == 0){
                        //审核通过最新的企业认证
                        EnterpriseAuthModel::where('id',$enterprise->id)->update([
                            'status'     => 1,
                            'auth_time'  => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        AuthRecordModel::where('uid',$info->uid)->where('auth_code','enterprise')->update([
                            'status'    => 1,
                            'auth_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                $userInfo = UserModel::where('id',$info->uid)->first();
                UserModel::sendfreegrant($info->uid,7);//开通店铺自动发放
                $user = [
                    'uid'    => $info->uid,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username' => $userInfo->name
                ];
                \MessageTemplateClass::sendMessage('shop_check_success',$user,$templateArr,$templateArr);

            });
        }else{
            $status = DB::transaction(function () use ($info,$all) {
                self::where('id',$info->id)->update([
                    'status'     => 3,//审核失败
                    'reason'     => isset($all['reason']) ? $all['reason'] : '',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                if($info->type == 1){
                    $realname =  RealnameAuthModel::where('uid',$info->uid)
                        ->orderBy('id','desc')->first();
                    if($realname && $realname->status == 0){
                        //审核失败最新的实名认证
                        RealnameAuthModel::where('uid',$realname->id)->update([
                            'status'     => 2,
                            'reason'     => isset($all['reason']) ? $all['reason'] : '',
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        AuthRecordModel::where('uid',$info->uid)->where('auth_code','realname')->update([
                            'status'    => 2,
                            'auth_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                }else{
                    $enterprise =  EnterpriseAuthModel::where('uid',$info->uid)
                        ->orderBy('id','desc')->first();
                    if($enterprise && $enterprise->status == 0){
                        //审核失败最新的企业认证
                        EnterpriseAuthModel::where('id',$enterprise->id)->update([
                            'status'     => 2,
                            'reason'     => isset($all['reason']) ? $all['reason'] : '',
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        AuthRecordModel::where('uid',$info->uid)->where('auth_code','enterprise')->update([
                            'status'    => 2,
                            'auth_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                $userInfo = UserModel::where('id',$info->uid)->first();
                $user = [
                    'uid'    => $info->uid,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username' => $userInfo->name
                ];
                \MessageTemplateClass::sendMessage('shop_check_failure',$user,$templateArr,$templateArr);
            });
        }

        return is_null($status) ? true : $status;

    }


    /**
     * 根据用户id获取店铺详情
     * @author quanke
     * @param int $uid  用户id
     * @return null
     */
    static function getShopInfoByUid($uid)
    {
        $shopInfo = ShopModel::where('uid',$uid)->first();
        if(!empty($shopInfo)){
            //查询该店铺是否设置技能
            $shopInfoTags = ShopTagsModel::where('shop_id',$shopInfo->id)->get()->toArray();
            if(!empty($shopInfoTags)){
                $tagIds = array();
                foreach($shopInfoTags as $key => $val){
                    $tagIds[] = $val['cate_id'];
                }
                //查询技能详情
                $tags = SkillTagsModel::whereIn('id',$tagIds)->get()->toArray();
                $shopInfo['tags'] = $tags;
            }
            return $shopInfo;
        }else{
            return false;
        }
    }


    /**
     * 根据店铺id获取店铺详情
     * @author quanke
     * @param int $id  店铺id
     * @return null
     */
    static function getShopInfoById($id,$status = 0)
    {
        if($status){//对内店铺信息
            $shopInfo = ShopModel::where('shop.id',$id)
                ->leftJoin('users','users.id','=','shop.uid')
                ->select('shop.*','users.name')->first();
        }else{//对外店铺信息
            $shopInfo = ShopModel::where('shop.id',$id)->where('shop.status',1)
                ->leftJoin('users','users.id','=','shop.uid')
                ->select('shop.*','users.name')->first();
        }

        if(!empty($shopInfo)){
            //查询该店铺入驻行业
            $shopInfoIndustry = ShopTagsModel::shopTag($shopInfo->id,1);
            $shopInfo['industry'] = array_pluck($shopInfoIndustry,'name');
            //查询该店铺擅长技能
            $shopInfoTags = ShopTagsModel::shopTag($shopInfo->id,2);
            $shopInfo['tags'] = array_pluck($shopInfoTags,'name');
            //查询地址
            $shopInfo['province_name'] = '';
            if($shopInfo->province){
                $province = DistrictModel::where('id',$shopInfo->province)->select('id','name')->first();
                if(!empty($province)){
                    $shopInfo['province_name'] = $province->name;
                }

            }
            $shopInfo['city_name'] = '';
            if($shopInfo->city){
                $city = DistrictModel::where('id',$shopInfo->city)->select('id','name')->first();
                if(!empty($city)){
                    $shopInfo['city_name'] = $city->name;
                }
            }
            $shopInfo['area_name'] = '';
            if($shopInfo->area){
                $area = DistrictModel::where('id',$shopInfo->area)->select('id','name')->first();
                if(!empty($city)&&!empty($area)){
                    $shopInfo['area_name'] = $area->name;
                }
            }
            return $shopInfo;
        }else{
            return false;
        }
    }


    /**
     * 保存店铺信息
     * @author quanke
     * @param $data
     * @return mixed
     */
    static function createShopInfo($data)
    {
        $status = DB::transaction(function () use ($data) {
            $arr = array(
                'uid'        =>isset($data['uid']) ? $data['uid'] : '',
                'type'       =>isset($data['type']) ? $data['type'] : '',
                'shop_pic'   => isset($data['shop_pic']) ? $data['shop_pic'] : '',
                'shop_name'  => isset($data['shop_name']) ? $data['shop_name'] : '' ,
                'shop_desc'  =>isset($data['shop_desc']) ? $data['shop_desc'] : '' ,
                'province'   =>isset($data['province']) ? $data['province'] : '',
                'city'       =>isset($data['city']) ? $data['city'] : '' ,
                'area'       => isset($data['area']) ? $data['area'] : '' ,
                'phone'  =>isset($data['phone']) ? $data['phone'] : ''  ,
                'email'  =>isset($data['email']) ? $data['email'] : ''  ,
                'service_company'  => isset($data['service_company']) ? $data['service_company'] : '' ,
                'openWeb'   => isset($data['openWeb']) ? $data['openWeb'] : '' ,
                'job_year'=>isset($data["job_year"]) ? $data["job_year"] : '' ,
                'status'     => 0,//默认开启店铺
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            );
            $result = self::create($arr);
            //存入店铺技能标签关联表
//            if(!empty($data['apply'])){
//                $tagId = explode(',',$data['tags']);
//                foreach($tagId as $value){
//                    $tagData = array(
//                        'shop_id' => $result['id'],
//                        'tag_id' => $value
//                    );
//                    ShopTagsModel::create($tagData);
//                }
//            }

            if(!empty($data['apply'])){
                foreach($data['apply'] as $value){
                    $tagData = array(
                        'shop_id' => $result['id'],
                        'cate_id' => intval($value),
                        'type'=>1,
                    );
                    ShopTagsModel::create($tagData);
                }
            }
            if(!empty($data['skillCate'])){
                foreach($data['skillCate'] as $value){
                    $tagData = array(
                        'shop_id' => $result['id'],
                        'cate_id' => intval($value),
                        'type'=>2,
                    );
                    ShopTagsModel::create($tagData);
                }
            }
            //开发平台
            if(!empty($data['openWeb'])){
                $openWeb=json_decode($data['openWeb']);
                //先进行数据的存储
                $openAllId=[];
                foreach($openWeb as $ok=>$ov){
                    $cate=CateModel::where('type',3)->where("name",$ov)->first();
                    if(!$cate){
                        $insetCate=CateModel::insertGetId(
                            [
                                'type'=>3,
                                'name'=>$ov,
                                'created_at'=>date("Y-m-d H:i:s"),
                                'updated_at'=>date("Y-m-d H:i:s"),
                            ]
                        );
                        $openAllId[]=$insetCate;
                    }else{
                        $openAllId[]=$cate['id'];
                        //dd(1,$openAllId);
                    }
                }
                //查询开放平台id
                foreach($openAllId as $odk=>$odv){
                    $tagData = array(
                        'shop_id' => $result['id'],
                        'cate_id' => intval($odv),
                        'type'=>3,
                    );
                    ShopTagsModel::create($tagData);
                }
            }
            return true;
        });
        return $status;
    }

    /**
     * 修改店铺信息
     * @author quanke
     * @param $data
     * @return mixed
     */
    static function updateShopInfo($data)
    {
        $status = DB::transaction(function () use ($data) {
            $arr = array(
                'uid'        => $data['uid'],
                'type'       => $data['type'],
                'shop_name'  => $data['shop_name'],
                'shop_desc'  => $data['shop_desc'],
                'province'   => $data['province'],
                'city'       => $data['city'],
                'area'       => $data['area'],
                'phone'  => $data['phone'],
                'email'  => $data['email'],
               'job_year'=>$data["job_year"],
                'service_company'  => $data['service_company'],
                'openWeb'   => $data['openWeb'],
                'status'     => 0,//默认开启店铺
                'updated_at' => date('Y-m-d H:i:s'),
            );
            self::where('id',$data['id'])->update($arr);
            //查询店铺所有的标签id
            $oldTags = ShopTagsModel::shopTag($data['id']);
            $oldTags = array_flatten($oldTags);
            $oldTagsStr = implode(',',$oldTags);
            if(!empty($data['apply'])){
                ShopTagsModel::where('shop_id',$data['id'])->where("type",1)->delete();
                foreach($data['apply'] as $value){
                    $tagData = array(
                        'shop_id' => $data['id'],
                        'cate_id' => intval($value),
                        'type'=>1,
                    );
                    ShopTagsModel::create($tagData);
                }
            }
            if(!empty($data['skillCate'])){
                ShopTagsModel::where('shop_id',$data['id'])->where("type",2)->delete();
                foreach($data['skillCate'] as $value){
                    $tagData = array(
                        'shop_id' => $data['id'],
                        'cate_id' => intval($value),
                        'type'=>2,
                    );
                    ShopTagsModel::create($tagData);
                }
            }
//            if($data['tags'] != $oldTagsStr)
//            {
//                //存入店铺技能标签关联表
//                if(!empty($data['tags'])){
//                    //删除原有标签
//                    ShopTagsModel::where('shop_id',$data['id'])->delete();
//                    $tagId = explode(',',$data['tags']);
//                    foreach($tagId as $value){
//                        $tagData = array(
//                            'shop_id' => $data['id'],
//                            'tag_id' => $value
//                        );
//                        ShopTagsModel::create($tagData);
//                    }
//                }
//            }
            return true;
        });
        return $status;
    }


    /**
     * 批量开启店铺
     * @author quanke
     * @param $idArr
     * @return bool
     */
    static function AllShopOpen($idArr)
    {
        //查询批量操作的id数组是否关闭
        $res = ShopModel::whereIn('id',$idArr)->get()->toArray();
        if(!empty($res) && is_array($res)){
            $id = array();
            foreach($res as $k => $v){
                if($v['status'] == 2){
                    $id[] = $v['id'];
                }
            }
        }else{
            $id = array();
        }
        $status = ShopModel::whereIn('id',$id)->update(array('status' => 1));

        return is_null($status) ? true : $status;
    }

    /**
     * 批量关闭店铺(同时取消店铺推荐)
     * @author quanke
     * @param $idArr
     * @return bool
     */
    static function AllShopClose($idArr)
    {
        //查询批量操作的id数组是否关闭
        $res = ShopModel::whereIn('id',$idArr)->get()->toArray();
        if(!empty($res) && is_array($res)){
            $id = array();
            foreach($res as $k => $v){
                if($v['status'] == 1){
                    $id[] = $v['id'];
                }
            }
        }else{
            $id = array();
        }
        $status = ShopModel::whereIn('id',$id)->update(array('status' => 2,'is_recommend' => 0));

        return is_null($status) ? true : $status;
    }

    /**
     * 判断用户是否开启店铺
     * @author quanke
     * @param int $uid 用户id
     * @return int
     */
    static function isOpenShop($uid)
    {
        $shopInfo = ShopModel::where('uid',$uid)->first();
        if(!empty($shopInfo)){
            $isOpenShop = $shopInfo->status;
        }else{
            $isOpenShop = 3;
        }
        return $isOpenShop;
    }



    /**
     * 根据店铺uid获取城市信息
     * @param $uid
     * @return string
     */
    static function getCityByUid($uid){
        $city = ShopModel::join('district', 'shop.city', '=', 'district.id')
            ->select('district.name')->where('shop.uid', $uid)->first();
        $city = $city ? $city->name : '';
        return $city;
    }

    /**
     * 根据用户id获取店铺id
     * @author quanke
     * @param $uid
     * @return string
     */
    static function getShopIdByUid($uid)
    {
        $shopInfo = ShopModel::where('uid',$uid)->first();
        if(!empty($shopInfo)){
            $shopId = $shopInfo->id;
        }else{
            $shopId = '';
        }
        return $shopId;
    }

    public function employ_data()
    {
        return $this->hasMany('App\Modules\Employ\Models\EmployModel', 'employee_uid', 'uid')->where('status', '=', '4');
    }

    /**
     * 根据店铺id获取店铺列表
     * @param $shopIds 店铺id数组
     * @param array $merge 筛选条件
     * @return mixed
     */
    static function getShopListByShopIds($shopIds,$merge=array())
    {
        $shopList = ShopModel::whereRaw('1 = 1');
        if(isset($merge['shop_name'])){
            $shopList = $shopList->where('shop.shop_name','like','%'.$merge['shop_name'].'%');
        }
        $shopList = $shopList->whereIn('shop.id',$shopIds)
            ->with('employ_data')
            ->join('shop_focus','shop_focus.shop_id','=','shop.id')
            ->leftJoin('users','users.id','=','shop.uid')
            ->select('shop.*','users.email_status')
            ->orderby('shop_focus.created_at','DESC')
            ->groupBy('shop.id')
            ->paginate(10);
        if(!empty($shopList->toArray()['data'])){
            $userIds = array();
            $provinceId = array();
            $cityId = array();
            foreach($shopList as $k => $v){
                $userIds[] = $v['uid'];
                $provinceId[] = $v['province'];
                $cityId[] = $v['city'];
                //计算店铺好评率
                if(!empty($v['total_comment'])){
                    $v['comment_rate'] = intval($v['good_comment']/$v['total_comment'])*100;
                }else{
                    $v['comment_rate'] = 100;
                }
            }
            if(!empty($userIds)){
                $userAuthOne = AuthRecordModel::whereIn('uid', $userIds)->where('status', 2)
                    ->whereIn('auth_code',['alipay','bank'])->get()->toArray();
                $userAuthTwo = AuthRecordModel::whereIn('uid', $userIds)->where('status', 1)
                    ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
                $userAuth = array_merge($userAuthOne,$userAuthTwo);
                if(!empty($userAuth)){
                    //根据uid重组数组
                    $auth = array_reduce($userAuth,function(&$auth,$v){
                        $auth[$v['uid']][] = $v['auth_code'];
                        return $auth;
                    });
                }
                if(!empty($auth) && is_array($auth)){
                    foreach($auth as $e => $f){
                        $auth[$e]['uid'] = $e;
                        if(in_array('realname',$f)){
                            $auth[$e]['realname'] = true;
                        }else{
                            $auth[$e]['realname'] = false;
                        }
                        if(in_array('bank',$f)){
                            $auth[$e]['bank'] = true;
                        }else{
                            $auth[$e]['bank'] = false;
                        }
                        if(in_array('alipay',$f)){
                            $auth[$e]['alipay'] = true;
                        }else{
                            $auth[$e]['alipay'] = false;
                        }
                        if(in_array('enterprise',$f)){
                            $auth[$e]['enterprise'] = true;
                        }else{
                            $auth[$e]['enterprise'] = false;
                        }
                    }
                    foreach ($shopList as $key => $item) {
                        //拼接认证信息
                        foreach ($auth as $a => $b) {
                            if ($item->uid == $b['uid']) {
                                $shopList[$key]['auth'] = $b;
                            }
                        }
                    }
                }
            }
            //查询地区一级信息
            if(!empty($provinceId)){
                $provinceArr = DistrictModel::whereIn('id',$provinceId)->get()->toArray();
                if(!empty($provinceArr)){
                    foreach ($shopList as $key => $item) {
                        //拼接认证信息
                        foreach ($provinceArr as $a => $b) {
                            if ($item->province == $b['id']) {
                                $shopList[$key]['province_name'] = $b['name'];
                            }
                        }
                    }
                }
            }
            //查询地区二级信息
            if(!empty($cityId)){
                $cityArr = DistrictModel::whereIn('id',$cityId)->get()->toArray();
                if(!empty($cityArr)){
                    foreach ($shopList as $key => $item) {
                        //拼接认证信息
                        foreach ($cityArr as $a => $b) {
                            if ($item->city == $b['id']) {
                                $shopList[$key]['city_name'] = $b['name'];
                            }
                        }
                    }
                }
            }


            //查询店铺标签标签
            $arrSkill = ShopTagsModel::shopTag($shopIds);
            if(!empty($arrSkill) && is_array($arrSkill)){
                $arrTagId = array();
                foreach ($arrSkill as $item){
                    $arrTagId[] = $item['tag_id'];
                }
                if(!empty($arrTagId)){
                    $arrTagName = TagsModel::select('id', 'tag_name')->whereIn('id', $arrTagId)->get()->toArray();
                    $arrUserTag = array();
                    foreach ($arrSkill as $item){
                        foreach ($arrTagName as $value){
                            if ($item['tag_id'] == $value['id']){
                                $arrUserTag[$item['shop_id']][] = $value['tag_name'];
                            }
                        }
                    }
                    if(!empty($arrUserTag)){
                        foreach ($shopList as $key => $item){
                            foreach ($arrUserTag as $k => $v){
                                if ($item->id == $k){
                                    $shopList[$key]['skill'] = $v;
                                }
                            }
                        }
                    }
                }
            }

        }
        return $shopList;
    }

    /**
     * 添加店铺浏览记录
     * @param int $uid 店铺用户id
     * @param int $shopID 店铺id
     */
    static public function addShopView($uid,$shopID)
    {
        ShopModel::where("uid",$uid)->increment("view_count");
        //$findShop=ShopModel::where("uid",$uid)->find();
        //添加日志记录
        ShopViewLogModel::insert([
           'user_id'    => $uid,
           'shop_id'    => $shopID,
           'uid'        => Auth::check() ? Auth::user()->id : '',
           'create_at'  => date("Y-m-d H:i:s")
        ]);
    }

    //获取单个店铺信息
    static public function shopOneInfo($uid)
    {
        $findShop = ShopModel::where('uid',$uid)->first();
        if(!$findShop){
            return false;
        }
        //获取用户信息
        $user = UserModel::find($uid);
        if(!$user){
            return false;
        }
        $findShop->template = [];
        $findShop->nav_open = [];
        $findShop->nav_pic = '';
        $findShop['shop_template_stauts'] = $user->shop_template_stauts;
        if($findShop['shop_template_stauts'] == 2){
            $shopTemplate = ShopOneModel::where('uid',$uid)->first();
            if($shopTemplate){
                $findShop->shop_pic = $shopTemplate->logo_pic;
                $findShop->nav_pic = $shopTemplate->nav_pic;
                $findShop->nav_open = explode(',',$shopTemplate->nav_open);
            }
            $findShop->template = $shopTemplate;
        }elseif($findShop['shop_template_stauts'] == 3){
            $shopTemplate = ShopTwoModel::where('uid',$uid)->first();
            if($shopTemplate){
                $findShop->shop_pic = $shopTemplate->logo_pic;
                $findShop->nav_pic = $shopTemplate->nav_pic;
                $findShop->nav_open = explode(',',$shopTemplate->nav_open);
            }
            $findShop->template = $shopTemplate;
        }
        //查询该店铺所有的商品
        $goodAll = GoodsModel::where("uid",$uid)->lists("id")->toArray();
        //查询所有成交成功的订单
        $goodCom = ProgrammeOrderModel::whereIn("programme_id",$goodAll)->where("status",5)->sum("number");
        //查询店铺总金额
        $goodPriceCount = ProgrammeOrderModel::getPriceCount($goodAll);

        //判断店铺是否已关注
        $findShop['is_focus'] = false;
        if(Auth::check()){
            $shopFocus = ShopFocusModel::where("shop_id",$findShop['id'])->where("uid",Auth::user()->id)->first();
           if($shopFocus){
               $findShop['is_focus'] = true;
           }
        }
        $district = DistrictModel::whereIn('id',[$findShop['province'],$findShop['city']])->select('id','name')->get()->toArray();
        $district = \CommonClass::setArrayKey($district,'id');
        $findShop['province'] = in_array($findShop['province'],array_keys($district)) ? $district[$findShop['province']]['name'] : '';
        $findShop['city'] = in_array($findShop['city'],array_keys($district)) ? $district[$findShop['city']]['name'] : '';
        $findShop['username'] = $user['name'];
        $findShop['mobile'] = $user['mobile'];
        $findShop['email'] = $user['email'];
        $findShop['goodCom'] = $goodCom;
        $findShop['PriceCount'] = $goodPriceCount;
        return $findShop;
    }
}

