<?php

namespace App\Modules\Shop\Models;

use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Employ\Models\EmployGoodsModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Task\Model\ServiceModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoodsModel extends Model
{
    //
    protected $table = 'goods';

    protected $primaryKey = 'id';

    protected $fillable = [

        'uid',
        'shop_id',
        'title',
        'cate_id',//应用领域
        'ide_cate_id',
        'skill_id',//技术标签
        'type',
        'is_customized',//是否接受定制 0：type=2  type=1时 1：是 2：否
        'cash',
        'freight',
        'desc',//方案概要
        'delivery_cate_id',//交付形式
        'performance_parameter',//性能参数
        'application_scene',//应用场景
        'cover',//方案封面
        'end_time',
        'status',
        'sales_num',//销售数量
        'view_num',
        'is_delete',
        'created_at',
        'updated_at',
        'index_sort',
        'inquiry_num',//询价数量
        'sort',
        'goods_grade',

        'unit',
        'is_recommend',
        'recommend_end',
        'comments_num',
        'recommend_text',
        'good_comment',
        'seo_title',
        'seo_keyword',
        'seo_desc',
    ];

    /**
     * 方案封面
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cover()
    {
        return $this->hasOne('App\Modules\User\Model\AttachmentModel','id','cover');
    }
    /**
     * 方案封面
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function covers()
    {
        return $this->hasOne('App\Modules\User\Model\AttachmentModel','id','cover');
    }
    /**
     * 应用领域
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function field()
    {
        return $this->hasOne('App\Modules\Task\Model\TaskCateModel','id','cate_id');
    }

    /**
     * 供应商
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel','id','uid');
    }

    /**
     * 首页 方案超市
     * @param int $type 1：推荐方案 2：热门方案 3：最新方案
     * @return array|bool|mixed
     */
    static public function getHomeGoodsByType($type=1)
    {
        $goods = [];
        if(!in_array($type,[1,2,3])){
            return $goods;
        }
        switch($type){
            case 1://推荐方案
                $goods = RecommendModel::getRecommendByCode('HOME_GOODS','goods');
                break;
            case 2://热门方案
                $goods = self::where('is_delete',0)
                    ->select('id','title','uid','shop_id','cash','type','cover')
                    ->with('cover')
                    ->where('status','=','1')
                    ->orderBy('view_num','desc')
                    ->limit(7)->get()->toArray();
                break;
            case 3://最新方案
                $goods = self::where('is_delete',0)
                    ->select('id','title','uid','shop_id','cash','type','cover')
                    ->with('cover')
                    ->where('status','=','1')
                    ->orderBy('created_at','desc')
                    ->limit(7)->get()->toArray();
                break;
        }
        return $goods;
    }

    /**
     * 获取方案列表
     * @param int $paginate
     * @param array $merge
     * @return mixed
     */
    static public function getGoodsList($paginate=10,$merge=[])
    {
        $goods = GoodsModel::where('is_delete',0)->where('goods.status',1);
        if(isset($merge['shop_id']) && !empty($merge['shop_id'])){
            $goods = $goods->where('shop_id',$merge['shop_id'])->orderBy('sort','desc');
        }
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $goods = $goods->where('title','like','%'.$merge['keywords'].'%');
            //添加搜索记录
            $type = isset($merge['type'])? $merge['type'] : '1';
            \CommonClass::get_keyword($merge['keywords'],$type);
        }
        if(isset($merge['cate_id']) && !empty($merge['cate_id'])){
            $goods = $goods->where('cate_id',$merge['cate_id']);
        }
        if(isset($merge['type']) && !empty($merge['type'])){
            $goods = $goods->where('type',$merge['type']);
        }
        $goods = $goods->with('cover','field','user');
        if(isset($merge['order']) && $merge['order']){
            $goods = $goods->orderBy($merge['order'],'desc');
        }
        if(isset($merge['relatedId'])){
            $goods = $goods->whereIn("id",$merge['relatedId']);
        }
        $goods = $goods->with("covers")->orderBy('id','desc')->paginate($paginate);
        return $goods;
    }


    //根据店铺id获取其店铺内所有方案
    static public function getShopsCount($shopid='')
    {
       if(isset($shopid)){
           //当前店铺下的所有方案
           $goodscount= GoodsModel::leftJoin('shop','shop.id','=','goods.shop_id')->where('goods.shop_id',$shopid)->where('goods.status','1')->lists('goods.id')->count();
       }else{
           $goodscount=0;
       }
        return $goodscount;
    }
    /**
     * 查找相似的方案
     * @param $cate_id
     */
    static function findByCate($cate_id)
    {
        $data = self::getGoodsList(5,['cate_id'=>$cate_id]);
        return $data->toArray()['data'];
    }

    /**
     * 修改方案状态
     * @param int $id 商品id
     * @param int $type 1=>上架  2=>下架  3=>审核通过  4=>审核失败  5=>删除
     * @param string $reason 审核失败原因
     * @return string
     */
    static public function changeGoodsStatus($id,$type,$reason='')
    {
        //获取商品状态
        $res = GoodsModel::getGoodsStatus($id);
        $re = '';
        $codeName='';
        $re=DB::transaction(function() use($type,$res,$id,$reason){
            $findGoods=GoodsModel::find($id);
            switch($type){
                case 1 : //上架
                    if($res == 2){
                        $arr = array('status' => 1);
                        $re = GoodsModel::where('id',$id)->update($arr);
                    }
                    break;
                case 2 : //下架
                    if($res == 1){
                        $arr = array('status' => 2);
                        $re = GoodsModel::where('id',$id)->update($arr);
                    }
                    $codeName="goods_no_sale";
                    break;
                case 3 ://审核通过
                    if($res == 0){
                        $arr = array('status' => 1);
                        $re = GoodsModel::where('id',$id)->update($arr);
                    }
                    $codeName="goods_check_success";
                    break;
                case 4 : //审核失败
                    if($res == 0){
                        $arr = array('status' => 3 ,'recommend_text' => $reason);
                        $re = GoodsModel::where('id',$id)->update($arr);
                        $info = GoodsModel::find($id);
                        ShopModel::where('id',$info->shop_id)->increment('goods_num');
                    }
                    $codeName="goods_check_failure";
                    break;
                case 5 : //删除
                    if($res == 0 || $res == 2 || $res == 3){
                        $arr = array('is_delete' => 1);
                        $re = GoodsModel::where('id',$id)->update($arr);
                    }
                    break;
            }
            //发送短信
            if(in_array($type,[2,3,4])){
                $userInfo=UserModel::find($findGoods['uid']);
                $user = [
                    'uid'    =>$userInfo['id'],
                    'email'  =>$userInfo['email'],
                    'mobile' => $userInfo['mobile']
                ];
                $templateArr = [
                    'username' =>$userInfo['username'],
                    'title'     =>$findGoods['title'],
                    'reason'   =>$reason
                ];
                \MessageTemplateClass::sendMessage($codeName,$user,$templateArr,$templateArr);
            }
            return $id;
        });

        return $re;
    }

    /**
     * 获取方案状态
     * @param int $id 商品id
     * @return null
     */
    static public function getGoodsStatus($id)
    {
        $res = GoodsModel::where('id',$id)->where('is_delete',0)->select('status')->first();
        if(!empty($res)){
            $status = $res->status;
        }else{
            $status = null;
        }
        return $status;
    }

    /**
     * 修改方案信息
     * @param $data
     * @return mixed
     */
    static function saveGoodsInfo($data,$fileIds=[],$gooddoc=[], $request)
    {
        $status = DB::transaction(function () use ($data,$fileIds,$gooddoc,$request) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            if(isset($data['id']) && !empty($data['id'])){
                self::where('id',$data['id'])->update($data);
            }else{
                unset($data['id']);
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = self::create($data);
                $data['id'] = $result['id'];
                //SeoModel::createSeo($data['title'],2,$result['id']);
            }

            UnionAttachmentModel::where('object_id',$data['id'])->where('object_type',5)->delete();//删除封面前先删除原来的。
            if(!empty($fileIds)){
                $fileData = [];
                foreach($fileIds as $value){
                    $fileData[] = [
                        'object_id'     => $data['id'],
                        'object_type'   => 5,
                        'attachment_id' => $value,
                        'created_at'    => date('Y-m-d H:i:s')
                    ];
                }
                if($fileData){
                    UnionAttachmentModel::insert($fileData);
                }

            }
//            UnionAttachmentModel::where('object_id',$data['id'])->where('object_type',7)->delete();//.方案封面保存原来的--不点击提交按钮页面就删除原来的
            if(!empty($gooddoc)){
                $gooddata = [];
                foreach($gooddoc as $value){
                    $gooddata[] = [
                        'object_id'     => $data['id'],
                        'object_type'   => 7,
                        'attachment_id' => $value,
                        'created_at'    => date('Y-m-d H:i:s')
                    ];
                }
                if($gooddata){
                    UnionAttachmentModel::insert($gooddata);
                }

            }

            if(isset($data['id']) && !empty($data['id'])){
                //删除seo标签
                SeoRelatedModel::where("related_id",$data['id'])->where("type",2)->delete();
                //添加seo标签
                if($request->get("seo_laber")){
                    SeoModel::seoHandle(1,2,[$data['id']],$request->get("seo_laber"));
                }
            }
            return $data;
        });
        return $status;
    }


    /**
     * （弃用）
     * @param $uid
     * @param $data
     * @return mixed
     */
    static public function serviceList($uid,$data)
    {
        $service = self::select('goods.*','us.name')->where('goods.uid',$uid)->where('goods.type',2)->where('goods.is_delete',0);
        //状态筛选
        if(isset($data['status']) && $data['status']!='all')
        {
            $service->where('goods.status',intval($data['status']));
        }
        //时间筛选
        if(isset($data['time']) && $data['time']!='all')
        {
            $time = date('Y-m-d H:i:s',strtotime("-".intval($data['time'])." month"));
            $service->where('goods.created_at','>',$time);
        }

        $service = $service->leftjoin('users as us','us.id','=','goods.uid')
            ->orderBy('created_at','DESC')
            ->paginate(5);
        return $service;
    }

    /**
     * （弃用）
     * 统计服务数据
     * @param $uid
     */
    static public function serviceStatistics($uid)
    {
        //上架服务数量
        $added_service = self::where('type',2)->where('status',1)->where('uid',$uid)->count();

        $service_ids = self::where('type',2)->where('uid',$uid)->lists('id');
        $employ_ids = EmployGoodsModel::whereIn('service_id',$service_ids)->lists('employ_id');
        $success_service = EmployModel::whereIn('id',$employ_ids)->where('status',4)->count();
        $service_money = EmployModel::whereIn('id',$employ_ids)->sum('bounty');

        //可用提现金额
        $balance = UserDetailModel::where('uid',$uid)->first();
        $balance = $balance['balance'];
        $data = [
            'added_service'=>$added_service,
            'success_service'=>$success_service,
            'service_money'=>$service_money,
            'balance'=>$balance
        ];
        return $data;
    }

    /**
     * （弃用）
     * 根据商品id查询商品详情
     * @param int $id 商品id
     * @param $where 筛选条件
     * @return mixed
     */
    static public function getGoodsInfoById($id,$where=array())
    {
        if(isset($where['status'])){
            $goodsInfo = GoodsModel::where('id',$id)->where('is_delete',0)->where('status',$where['status'])->first();
        }elseif(isset($where['is_delete'])){
            $goodsInfo = GoodsModel::where('id',$id)->first();
        }else{
            $goodsInfo = GoodsModel::where('id',$id)->where('is_delete',0)->first();
        }

        if(!empty($goodsInfo)){
            //查询商品分类
            if(!empty($goodsInfo->cate_id)){
                $cate = TaskCateModel::where('id',$goodsInfo->cate_id)->first();
                if(!empty($cate)){
                    $parentCate = TaskCateModel::where('id',$cate->pid)->first();
                    $goodsInfo['cate_name'] = $cate->name;
                    $goodsInfo['cate_pid'] = $cate->pid;
                    if(!empty($parentCate)){
                        $goodsInfo['cate_pname'] = $parentCate->name;
                    }else{
                        $goodsInfo['cate_pname'] = '';
                    }
                }else{
                    $goodsInfo['cate_name'] = '';
                    $goodsInfo['cate_pname'] = '';
                    $goodsInfo['cate_pid'] = '';
                }
            }else{
                $goodsInfo['cate_name'] = '';
                $goodsInfo['cate_pname'] = '';
                $goodsInfo['cate_pid'] = '';
            }
            //查询店主名称
            $user = UserModel::where('id',$goodsInfo->uid)->first();
            if(!empty($user)){
                $goodsInfo['name'] = $user->name;
                $goodsInfo['mobile'] = $user->mobile;
            }else{
                $goodsInfo['name'] = '';
                $goodsInfo['mobile'] = '';
            }
            //计算该商品的好评率
            if(!empty($goodsInfo->comments_num)){
                $goodsInfo['comment_rate'] = ($goodsInfo->good_comment/$goodsInfo->comments_num)*100;
            }else{
                $goodsInfo['comment_rate'] = 100;
            }
            //速度平均分
            $avgSpeed = round(GoodsCommentModel::where('goods_id', $id)->avg('speed_score'), 1);
            //质量平均分
            $avgQuality = round(GoodsCommentModel::where('goods_id', $id)->avg('quality_score'), 1);
            //态度平均分
            $avgAttitude = round(GoodsCommentModel::where('goods_id', $id)->avg('attitude_score'), 1);
            //计算综合得分
            $goodsInfo['avg_score'] = round(($avgSpeed+$avgQuality+$avgAttitude)/3,1);
        }

        return $goodsInfo;
    }






    /**
     * （弃用）
     * 获取商品列表
     * @param $uid 用户id
     * @param array $merge
     * @return mixed
     */
    static public function getGoodsListByUid($uid,$merge=array())
    {
        $goodsList = GoodsModel::whereRaw('1 = 1');
        //状态筛选
        if(isset($merge['status'])){
            switch($merge['status']){
                case 1://待审核
                    $status = 0;
                    $goodsList = $goodsList->where('goods.status',$status);
                    break;
                case 2://售卖中
                    $status = 1;
                    $goodsList = $goodsList->where('goods.status',$status);
                    break;
                case 3://下架
                    $status = 2;
                    $goodsList = $goodsList->where('goods.status',$status);
                    break;
                case 4: //审核失败
                    $status = 3;
                    $goodsList = $goodsList->where('goods.status',$status);
                    break;

            }
        }
        //发布时间筛选
        if(isset($merge['sometime'])){
            switch($merge['sometime']){
                case 1://一个月
                    $start = date('Y-m-d H:i:s',(time()-30*24*3600));
                    $goodsList = $goodsList->where('goods.created_at','>',$start);
                    break;
                case 2://三月内
                    $start = date('Y-m-d H:i:s',(time()-90*24*3600));
                    $goodsList = $goodsList->where('goods.created_at','>',$start);
                    break;
                case 3://六月内
                    $start = date('Y-m-d H:i:s',(time()-180*24*3600));
                    $goodsList = $goodsList->where('goods.created_at','>',$start);
                    break;
            }
        }
        $goodsList = $goodsList->where('goods.uid',$uid)->where('goods.type',1)->where('is_delete',0)
            ->leftJoin('cate','cate.id','=','goods.cate_id')
            ->select('goods.*','cate.name')
            ->orderBy('goods.created_at','DESC')
            ->paginate(5);
        return$goodsList;

    }

    /**
     * （弃用）
     * 商品统计数据
     * @param $uid 用户id
     * @return array
     */
    static public function goodsStatistics($uid)
    {

        //累计交易
        $goodsIds = self::where('type',1)->where('uid',$uid)->lists('id');
        //累计交易次数
        $buyCount = ShopOrderModel::whereIn('object_id',$goodsIds)->where('object_type',2)
            ->whereIn('status',[2,4,5])->count();
        //正在交易次数
        $onBuyCount = ShopOrderModel::whereIn('object_id',$goodsIds)->where('object_type',2)
            ->whereIn('status',[1,3])->count();
        //累计收入
        $buyIncome = ShopOrderModel::whereIn('object_id',$goodsIds)->where('object_type',2)
            ->whereIn('status',[2,4])->sum('cash');
        //可用提现金额
        $balance = UserDetailModel::where('uid',$uid)->first();
        $balance = $balance['balance'];
        $data = [
            'buy_count' => $buyCount,
            'on_buy_count' => $onBuyCount,
            'buy_income' => $buyIncome,
            'balance'=>$balance
        ];
        return $data;
    }

    /**
     * （弃用）
     * @param $data
     * @return mixed
     */
    static public function serviceCreate($data)
    {
        $status = DB::transaction(function() use($data)
        {
            $result = self::create($data);
            //处理附件
            if (!empty($data['file_id'])) {
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($data['file_id']);
                $file_able_ids = array_flatten($file_able_ids);

                foreach ($file_able_ids as $v) {
                    $arrAttachment[] = [
                        'object_id' => $result->id,
                        'object_type' => 4,
                        'attachment_id' => $v,
                        'created_at' => date('Y-m-d H:i:s', time())
                    ];

                }
                UnionAttachmentModel::insert($arrAttachment);
                //修改附件的发布状态
                $attachmentModel = new AttachmentModel();
                $attachmentModel->statusChange($file_able_ids);
            }
            return $result;
        });

        return $status;
    }

    /**
     * （弃用）
     * 提交编辑后的服务
     * @param $service
     */
    static public function updateService($service)
    {
        $status = DB::transaction(function() use($service){
            $update_data = [
                'title'=>e($service['title']),
                'desc'=>$service['desc'],
                'cate_id'=>$service['secondCate'],
                'cash'=>$service['cash'],
                'cover'=>$service['cover'],
                'updated_at'=>date('Y-m-d H-i:s',time()),
            ];

            self::where('id',$service['id'])->update($update_data);
            //处理附件
            if (!empty($service['file_id']))
            {
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($service['file_id']);
                $file_able_ids = array_flatten($file_able_ids);

                foreach ($file_able_ids as $v) {
                    $arrAttachment[] = [
                        'object_id' => $service['id'],
                        'object_type' => 4,
                        'attachment_id' => $v,
                        'created_at' => date('Y-m-d H:i:s', time())
                    ];
                }
                //创建新的附件关系
                UnionAttachmentModel::insert($arrAttachment);
                //修改附件的发布状态
                $attachmentModel = new AttachmentModel();
                $attachmentModel->statusChange($file_able_ids);
            }
        });

        return is_null($status)?true:false;
    }

    /**
     * （弃用）
     * 支付推荐到商城之后操作
     * @param $money
     * @param $uid
     * @param $goods_id
     * @param $order_id
     * @param int $type 支付方式 1:余额 2:支付宝 3:微信
     * @return bool
     */
    static public function servicePay($money,$uid,$goods_id,$order_id,$type=1)
    {
        $status = DB::transaction(function() use($money,$uid,$goods_id,$order_id,$type)
        {
            $time = time();
            $map = [
                0=>3600*24,
                1=>3600*24*30,
                2=>3600*24*90,
                3=>3600*24*180,
                4=>3600*24*365,
            ];
            //扣除用户的钱
            UserDetailModel::where('uid',$uid)->decrement('balance',$money);
            //产生财务记录
            $financial = [
                'action' => 5,
                'pay_type' => $type,
                'cash' => $money,
                'uid' => $uid,
                'created_at' => date('Y-m-d H:i:s', time())
            ];
            FinancialModel::create($financial);

            //查询当前的单位
            $unit = \CommonClass::getConfig('recommend_service_unit');
            $recommend_end = date('Y-m-d H:i:d',$time+$map[$unit]);
            //修改商品的推荐状态，和计算商品被推送到商城的时间
            //判断当前商品是否已经推送到商城
            $goods = self::where('id',$goods_id)->first();
            if($goods['is_recommend']==1)
            {
                //判断推荐到商场的过期时间,防止过期当时调度没有处理状况
                if($time>strtotime($goods['recommend_end']))
                {
                    self::where('id',$goods_id)->update(['recommend_end'=>$recommend_end]);
                }else{
                    $recommend_end = date('Y-m-d',strtotime($goods['recommend_end'])+$map[$unit]);
                    self::where('id',$goods_id)->update(['recommend_end'=>$recommend_end]);
                }
            }else{
                self::where('id',$goods_id)->update(['is_recommend'=>1,'recommend_end'=>$recommend_end]);
            }
            //在goods_service中写入记录
            $service = ServiceModel::where('identify','FUWUTUIJIAN')->first();
            GoodsServiceModel::create(['service_id'=>$service['id'],'goods_id'=>$goods['id']]);
            //修改订单状态
            ShopOrderModel::where('id',$order_id)->update(['status'=>1,'pay_time'=>$time]);
        });

        return is_null($status)?true:false;
    }

    /**
     * （弃用）
     * 修改购买商品增值服务到期时间
     * @param $goods_id 商品id
     * @return mixed
     */
    static public function getServiceEnd($goods_id)
    {
        $time = time();
        $map = [
            0=>3600*24,
            1=>3600*24*30,
            2=>3600*24*90,
            3=>3600*24*180,
            4=>3600*24*365,
        ];
        //查询当前的单位
        $unit = \CommonClass::getConfig('recommend_goods_unit');
        $recommend_end = $time+$map[$unit];
        //修改商品的推荐状态，和计算商品被推送到商城的时间
        //判断当前商品是否已经推送到商城
        $goods = self::where('id',$goods_id)->first();
        if($goods['is_recommend']==1)
        {
            //判断推荐到商场的过期时间,防止过期当时调度没有处理状况
            if($time>strtotime($goods['recommend_end']))
            {
                $res = self::where('id',$goods_id)->update(['recommend_end'=>date('Y-m-d H:i:s',$recommend_end)]);
            }else{
                $recommend_end = strtotime($goods['recommend_end'])+$map[$unit];
                $res = self::where('id',$goods_id)->update(['recommend_end'=>date('Y-m-d H:i:s',$recommend_end)]);
            }
        }else{
            $res = self::where('id',$goods_id)->update(['is_recommend'=>1,'recommend_end'=>date('Y-m-d H:i:s',$recommend_end)]);
        }
        return $res;

    }
}
