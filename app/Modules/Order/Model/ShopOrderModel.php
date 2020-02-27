<?php

namespace App\Modules\Order\Model;

use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\GoodsServiceModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Order\Model\OrderModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

class ShopOrderModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'shop_order';

    protected $fillable = [
        'code', 'title', 'uid', 'cash', 'status', 'invoice_status', 'note', 'object_type','object_id',
        'created_at','pay_time','trade_rate'
    ];

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = [

    ];


    /**
     * 商品订单生成
     *
     * @param $uid
     * @param string $specific ['ps' : 发布服务, 'pg' : 发布作品, 'bg' : '购买作品', 'bs' : '购买服务', 'ss' : '购买商城推荐增值服务', 'vipshop' : '购买vip店铺']
     * @return string
     */
    static function randomCode($uid, $specific = '')
    {
        return $specific . time() . str_random(4) . $uid;
    }

    /**
     * 通过订单号检测订单类型
     *
     * @param $shopCode
     * @return string
     */
    static function handleOrderCode($shopCode)
    {
        $specific = substr($shopCode, 0, 2);
        switch ($specific){
            case 'ps':
                $type = 'pub service';
                break;
            case 'pg':
                $type = 'pub goods';
                break;
            case 'bg':
                $type = 'buy goods';
                break;
            case 'bs':
                $type = 'buy service';
                break;
            case 'ss':
                $type = 'buy shop service';
                break;
            case 'ep':
                $type = 'employ';
                break;
            case 'vs':
                $type = 'vipshop';
                break;
            case 'ts': //任务增值服务
                $type  = 'task service';
                break;
            case 'fa'://购买方案
                $type ='buy programme';
                break;
            case 'zx'://资讯支付
                $type = 'payarticle';
                break;
            case 'xj'://询价购买
                $type="buy inquiry";
                break;
            case 'vp':
                $type ="vip";
                break;
            case  "ck":
                $type ="ck";
                break;
            case "rk":
                $type ="rk";
                break;
            case "dt":
                $type="deposit";
                break;
            case "tl":
                $type="tool";
                break;
            case "sc":
                $type="service";
                break;
            case "ae":
                $type="article";
                break;
            default:
                $info = OrderModel::where('code', $shopCode)->first();
                if (!empty($info)){
                    if (is_null($info->task_id)){
                        $type = 'cash';
                    } else {
                        $type = 'pub task';
                    }
                }
                break;
        }

        return $type;
    }

    static function employOrder($uid,$money,$data)
    {
        $employ_order = [
            'code'=>self::randomCode($uid,'ep'),
            'title'=>'雇佣托管',
            'uid'=>$uid,
            'object_id'=>$data['id'],
            'object_type'=>1,
            'cash'=>$money,
            'status'=>0,
        ];

        $result = self::create($employ_order);

        return $result;
    }

    //发布服务购买增值工具
    static function serviceOrder($uid,$money,$id)
    {
        $employ_order = [
            'code'=>self::randomCode($uid,'ps'),
            'title'=>'购买增值工具',
            'uid'=>$uid,
            'object_id'=>$id,
            'object_type'=>1,
            'cash'=>$money,
            'status'=>0,
        ];

        $result = self::create($employ_order);

        return $result;
    }
    /**
     * 余额购买商品增值服务
     *
     * @param $good_id
     * @return bool
     */
    static function buyShopService($good_id)
    {
        $status = DB::transaction(function () use ($good_id){
            //查询是否开启推荐商品增值工具
            $serviceTool = ServiceModel::where('identify','ZUOPINTUIJIAN')->first();
            if(!empty($serviceTool) && $serviceTool->status == 1){
                $cash = $serviceTool->price;
            }else{
                $cash = 0.00;
            }
            //扣费
            UserDetailModel::where('uid', Auth::id())->decrement('balance', $cash);
            //查询是否已生成订单
            $orderInfo = ShopOrderModel::where('object_type',3)->where('object_id',$serviceTool->id)->where('status',0)->first();
            if(empty($orderInfo)){
                //写入增值服务商品关系表
                $serviceGoodsId = GoodsServiceModel::insertGetId(['service_id' => $serviceTool->id, 'goods_id' => $good_id]);
                //写入订单表
                $goods_order = [
                    'code' => self::randomCode(Auth::id(),'pg'),
                    'title' => '购买作品推荐增值服务',
                    'uid' => Auth::id(),
                    'object_id' => $serviceGoodsId,
                    'object_type' => 3,
                    'cash' => $cash,
                    'status' => 1,
                    'created_at'=> date('Y-m-d H:i:s'),
                    'pay_time' => date('Y-m-d H:i:s')
                ];
                ShopOrderModel::create($goods_order);

            }else{
                ShopOrderModel::where('id',$orderInfo->id)->update(['status' => 1,'pay_time' => date('Y-m-d H:i:s')]);
            }

            //写入财务记录
            $finance = [
                'action' => 5,
                'pay_type' => 1,
                'cash' => $cash,
                'uid' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ];
            FinancialModel::create($finance);
            //更新商品状态
            GoodsModel::getServiceEnd($good_id);
        });
        return is_null($status) ? true : false;
    }


    /**
     * 第三方购买商品增值服务
     * @param $orderCode
     * @param array $data
     * @return bool
     */
    static function thirdBuyShopService($orderCode, array $data)
    {
        $status = DB::transaction(function() use ($orderCode, $data){
            $shopOrder = ShopOrderModel::where('code', $orderCode)->first();
            //更新订单状态
            $shopOrder->update(['status' => 1,'pay_time' => date('Y-m-d H:i:s')]);
            //更新商品状态
            $goodsId = GoodsServiceModel::where('id',$shopOrder->object_id)->first()->goods_id;
            GoodsModel::getServiceEnd($goodsId);
            $finance = [
                'action' => 5,
                'pay_type' => $data['pay_type'],
                'pay_account' => $data['pay_account'],
                'pay_code' => $data['pay_code'],
                'cash' => $data['money'],
                'uid' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ];
            FinancialModel::create($finance);
        });

        return is_null($status) ? true : false;
    }

    /**
     * 判断用户是否购买某种对象
     * @param $uid 用户id
     * @param $objectId  对象编号
     * @param $objectType 对象类型 1：雇佣 2：购买商品 3：推荐增值服务
     * @return bool
     */
    static function isBuy($uid,$objectId,$objectType)
    {
        $shopOrderInfo = ShopOrderModel::where('uid',$uid)->where('object_id',$objectId)
            ->where('object_type',$objectType)->whereIn('status',[1,2,4])->first();
        if(!empty($shopOrderInfo)){
            $isBuy = true;
        }else{
            $isBuy = false;
        }
        return $isBuy;
    }

    /**
     * 判断用户购买的某对象是否处于维权中
     * @param $uid 用户id
     * @param $objectId  对象编号
     * @param $objectType 对象类型 1：雇佣 2：购买商品 3：推荐增值服务
     * @return bool
     */
    static function isRights($uid,$objectId,$objectType)
    {
        $shopOrderInfo = ShopOrderModel::where('uid',$uid)->where('object_id',$objectId)
            ->where('object_type',$objectType)->where('status',3)->first();
        if(!empty($shopOrderInfo)){
            $isRights = true;
        }else{
            $isRights = false;
        }
        return $isRights;
    }

    /**
     * 余额支付购买商品
     * @param $uid  用户id
     * @param $orderId  订单id
     * @return bool
     */
    static function buyShopGoods($uid,$orderId)
    {
        $status = DB::transaction(function () use ($uid,$orderId){
            $orderInfo = ShopOrderModel::where('id',$orderId)->first();
            //扣余额
            UserDetailModel::where('uid', $uid)->decrement('balance', $orderInfo->cash);
            //修改商品订单状态
            $array = array(
                'status' => 1,
                'pay_time' => date('Y-m-d H:i:s')
            );
            ShopOrderModel::where('id',$orderId)->update($array);

            //写入财务记录
            $finance = [
                'action' => 6,//购买商品
                'pay_type' => 1,
                'cash' => $orderInfo->cash,
                'uid' => $uid,
                'created_at' => date('Y-m-d H:i:s')
            ];
            FinancialModel::create($finance);
        });
        return is_null($status) ? true : false;
    }

    /**
     * 第三方支付购买商品
     * @param $orderCode
     * @param array $data
     * @return bool
     */
    static function thirdBuyGoods($orderCode, array $data)
    {
        $status = DB::transaction(function() use ($orderCode, $data){
            $shopOrder = ShopOrderModel::where('code', $orderCode)->first();
            //更新订单状态
            $shopOrder->update(['status' => 1,'pay_time' => date('Y-m-d H:i:s')]);
            $finance = [
                'action' => 6,
                'pay_type' => $data['pay_type'],
                'pay_account' => $data['pay_account'],
                'pay_code' => $data['pay_code'],
                'cash' => $data['money'],
                'uid' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ];
            FinancialModel::create($finance);

        });

        return is_null($status) ? true : false;
    }


    /**
     * 获取用户购买的某种对象
     * @param int $uid 用户id
     * @param $type 2=>商品
     * @param array $merge
     * @return mixed
     */
    static function myBuyGoods($uid,$type,$merge=array(),$paginate=5)
    {
        $buyGoods = ShopOrderModel::whereRaw('1 = 1');
        //app状态筛选(和web端条件不一致)
        if(isset($merge['type']) && $merge['type'] != 0){
            switch($merge['type']){
                case 2://已付款
                    $status = 1;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 3://交易完成
                    $status = [2,4];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;
                case 4://维权 待付款
                    $status = [0,3,5];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;

            }
        }
        //状态筛选
        if(isset($merge['status']) && $merge['status'] != 0){
            switch($merge['status']){
                case 1://代付款
                    $status = 0;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 2://已付款
                    $status = 1;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 3://交易完成
                    $status = [2,4];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;
                case 4://维权处理
                    $status = 3;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 5://维权结束
                    $status = 5;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;

            }
        }
        //发布时间筛选
        if(isset($merge['sometime'])){
            switch($merge['sometime']){
                case 1://一个月
                    $start = date('Y-m-d H:i:s',(time()-30*24*3600));
                    $buyGoods = $buyGoods->where('shop_order.pay_time','>',$start);
                    break;
                case 2://三月内
                    $start = date('Y-m-d H:i:s',(time()-90*24*3600));
                    $buyGoods = $buyGoods->where('shop_order.pay_time','>',$start);
                    break;
                case 3://六月内
                    $start = date('Y-m-d H:i:s',(time()-180*24*3600));
                    $buyGoods = $buyGoods->where('shop_order.pay_time','>',$start);
                    break;
            }
        }
        $buyGoods = $buyGoods->where('shop_order.uid',$uid)->where('shop_order.object_type',$type)
            ->leftJoin('goods','goods.id','=','shop_order.object_id')
            ->leftJoin('users','users.id','=','shop_order.uid')
            ->select('shop_order.*','goods.title','goods.desc','goods.cate_id','goods.cover','goods.unit','users.name','goods.sales_num','goods.comments_num','goods.good_comment')
            ->orderBy('shop_order.created_at','DESC')
            ->paginate($paginate);
        if(!empty($buyGoods)){
            $cateIds = array();
            foreach($buyGoods as $k => $v){
                $cateIds[] = $v->cate_id;
            }
            if(!empty($cateIds)){
                $cateArr = TaskCateModel::whereIn('id',$cateIds)->select('id','name')->get();
            }else{
                $cateArr = array();
            }
            if(!empty($cateArr)){
                foreach($buyGoods as $k => $v){
                    foreach($cateArr as $a => $b){
                        if($v->cate_id == $b->id){
                            $v->cate_name = $b->name;
                        }
                    }
                }
            }
        }
        return $buyGoods;

    }

    /**
     * 根据订单id获取购买商品类型订单详情
     * @param int $id 订单id
     * @return mixed
     */
    static function getGoodsOrderInfoById($id)
    {
        $orderInfo = ShopOrderModel::where('id',$id)->where('object_type',2)->first();
        if(!empty($orderInfo)){
            //查询下单人详情
            $userInfo = UserModel::where('id',$orderInfo->uid)->first();
            if($userInfo){
                $orderInfo['username'] = $userInfo->name;
            }else{
                $orderInfo['username'] = '';
            }
            //查询商品详情
            $goodsInfo = GoodsModel::where('id',$orderInfo->object_id)->first();
            if(!empty($goodsInfo)){
                $orderInfo['goods_name'] = $goodsInfo->title;
                //查询商品分类
                $cateInfo = TaskCateModel::where('id',$goodsInfo->cate_id)->select('id','pid','name')->first();
                if(!empty($cateInfo)){
                    $orderInfo['cate_sec_name'] = $cateInfo->name;
                    $cateFirst = TaskCateModel::where('id',$cateInfo->pid)->select('id','name')->first();
                    if(!empty($cateFirst)){
                        $orderInfo['cate_fir_name'] = $cateFirst->name;
                    }else{
                        $orderInfo['cate_fir_name'] = '';
                    }
                }else{
                    $orderInfo['cate_sec_name'] = '';
                    $orderInfo['cate_fir_name'] = '';
                }
            }else{
                $orderInfo['goods_name'] = '';
                $orderInfo['cate_sec_name'] = '';
                $orderInfo['cate_fir_name'] = '';
            }
        }
        return $orderInfo;
    }


    /**
     * 确认源文件结算订单
     * @param int $id 订单id
     * @param int $uid 用户id
     * @return mixed
     */
    static function confirmGoods($id,$uid)
    {
        $status = DB::transaction(function () use ($id,$uid) {
            $shopOrder = ShopOrderModel::where('id',$id)->where('uid',$uid)->where('status',1)->first();
            ShopOrderModel::where('id',$id)->update(['status' => 2,'confirm_time' => date('Y-m-d H:i:s')]);
            //计算交易平台提成金额
            if(!empty($shopOrder->trade_rate)){
                $tradePay = $shopOrder->cash*$shopOrder->trade_rate*0.01;
            }else{
                $tradePay = 0;
            }
            //结算金额
            $cash = $shopOrder->cash - $tradePay;
            //查询商品详情
            $goodsInfo = GoodsModel::where('id', $shopOrder->object_id)->first();
            //交易完成后给卖家结算交易金额
            UserDetailModel::where('uid', $goodsInfo->uid)->increment('balance', $cash);
            //产生一笔财务流水，接受任务产生收益
            $finance_data = [
                'action' => 9, //出售商品
                'pay_type' => 1,
                'cash' => $cash,
                'uid' => $goodsInfo->uid,
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($finance_data);
            return true;
        });
        return $status;
    }

    /**
     * 某人卖出的作品
     * @param int $uid  用户id
     * @param int $type 类型
     * @param array $merge  搜索条件
     * @return mixed
     */
    static public function sellGoodsList($uid,$type,$merge,$paginate=5)
    {
        //根据用户id查询用户所有发布商品的商品id
        $goods = GoodsModel::where('uid',$uid)->where('type',1)->get()->toArray();
        if(!empty($goods) && is_array($goods)){
            foreach($goods as $k => $v){
                $goodsId[] = $v['id'];
            }
        }
        $buyGoods = ShopOrderModel::whereRaw('1 = 1');
        //app状态筛选(和web端条件不一致)
        if(isset($merge['type']) && $merge['type'] != 0){
            switch($merge['type']){
                case 2://已付款
                    $status = 1;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 3://交易完成
                    $status = [2,4];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;
                case 4://维权 待付款
                    $status = [0,3,5];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;

            }
        }
        //状态筛选
        if(isset($merge['status'])){
            switch($merge['status']){
                case 1://代付款
                    $status = 0;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 2://已付款
                    $status = 1;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 3://交易完成
                    $status = [2,4];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;
                case 4://维权处理
                    $status = 3;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                case 5://维权结束
                    $status = 5;
                    $buyGoods = $buyGoods->where('shop_order.status',$status);
                    break;
                default:
                    $status = [1,2,3,4,5];
                    $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
                    break;

            }
        }else{
            $status = [1,2,3,4,5];
            $buyGoods = $buyGoods->whereIn('shop_order.status',$status);
        }
        //发布时间筛选
        if(isset($merge['sometime'])){
            switch($merge['sometime']){
                case 1://一个月
                    $start = date('Y-m-d H:i:s',(time()-30*24*3600));
                    $buyGoods = $buyGoods->where('shop_order.pay_time','>',$start);
                    break;
                case 2://三月内
                    $start = date('Y-m-d H:i:s',(time()-90*24*3600));
                    $buyGoods = $buyGoods->where('shop_order.pay_time','>',$start);
                    break;
                case 3://六月内
                    $start = date('Y-m-d H:i:s',(time()-180*24*3600));
                    $buyGoods = $buyGoods->where('shop_order.pay_time','>',$start);
                    break;
            }
        }
        if(!empty($goodsId)){
            $buyGoods = $buyGoods->whereIn('shop_order.object_id',$goodsId)->where('shop_order.object_type',$type)
                ->leftJoin('goods','goods.id','=','shop_order.object_id')
                ->leftJoin('users','users.id','=','shop_order.uid')
                ->leftJoin('user_detail','user_detail.uid','=','shop_order.uid')
                ->select('shop_order.*','goods.title','goods.desc','goods.cate_id','goods.cover','goods.unit','users.name','user_detail.avatar','goods.sales_num','goods.comments_num','goods.good_comment')
                ->orderBy('shop_order.created_at','DESC')
                ->paginate($paginate);
            if(!empty($buyGoods)){
                $cateIds = array();
                foreach($buyGoods as $k => $v){
                    $cateIds[] = $v->cate_id;
                }
                if(!empty($cateIds)){
                    $cateArr = TaskCateModel::whereIn('id',$cateIds)->select('id','name')->get();
                }else{
                    $cateArr = array();
                }
                if(!empty($cateArr)){
                    foreach($buyGoods as $k => $v){
                        foreach($cateArr as $a => $b){
                            if($v->cate_id == $b->id){
                                $v->cate_name = $b->name;
                            }
                        }
                    }
                }
            }
        }else{
            $buyGoods = array();
        }

        return$buyGoods;

    }


    /**
     * 我购买的作品或服务雇佣的数量统计
     * @param int $uid 用户id
     * @param int $type 类型 1:作品 2:服务雇佣
     * @param array|string $status  状态
     * @return mixed
     */
    static public function buyOrderCount($uid,$type,$status)
    {
        if($type == 1){
            $count = ShopOrderModel::where('uid',$uid)->where('object_type',2)->whereIn('status',$status)->count();
        }else{
            if(!is_array($status)){
                $status = explode(',', $status);
            }

            $count = EmployModel::where('employer_uid', $uid)->where('bounty_status', 1)->whereIn('employ.status', $status)->count();

        }
        return $count;
    }

    /**
     * 我卖出的作品或服务雇佣的数量统计
     * @param $uid
     * @param $type
     * @param $status
     * @return mixed
     */
    static public function saleOrderCount($uid,$type,$status)
    {
        if($type == 1){

            //根据用户id查询用户所有发布商品的商品id
            $goods = GoodsModel::where('uid',$uid)->where('type',1)->get()->toArray();
            $goodsId = [];
            if(!empty($goods) && is_array($goods)){
                foreach($goods as $k => $v){
                    $goodsId[] = $v['id'];
                }
            }
            $count = ShopOrderModel::whereIn('object_id',$goodsId)->where('object_type',2)->whereIn('status',$status)->count();

        }else{
            if(!is_array($status)){
                $status = explode(',', $status);
            }

            $count = EmployModel::where('employee_uid', $uid)->where('bounty_status', 1)->whereIn('employ.status', $status)->count();

        }
        return $count;
    }
}
