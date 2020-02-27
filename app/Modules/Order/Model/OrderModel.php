<?php

namespace App\Modules\Order\Model;

use App\Console\Commands\UserCoupon;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\CouponModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
class OrderModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'order';

    protected $fillable = [
       'id', 'code', 'title', 'uid', 'cash', 'status', 'invoice_status', 'note', 'created_at'
    ];

    public $timestamps = false;

    protected $hidden = [

    ];


    /**
     * 生成订单编号
     *
     * @return string
     */
    static function randomCode($uid)
    {
        $zero = '';
        for ($i = 0; $i < 6; $i++) {
            $zero .= '0';
        }
        return date('YmdHis') . $zero . $uid;
    }
    /**
     * 创建组订单
     */
    static function createOne($data,$uid)
    {
        $model = new OrderModel();
        $model->code = isset($data['code'])?$data['code']:Self::randomCode($uid);
        $model->title = $data['title'];
        $model->uid = $uid;
        $model->task_id = isset($data['task_id'])?$data['task_id']:'';
        $model->cash = $data['cash'];
        $model->status = isset($data['status'])?$data['status']:0;
        $model->invoice_status = isset($data['invoice_status'])?$data['invoice_status']:0;
        $model->note = isset($data['note'])?$data['note']:'';
        $model->created_at = date('Y-m-d H:i:s', time());

        $model->save();
        return $model;
    }

    /**
     * quanke 20181120
     * 快包任务 购买增值服务创建订单
     * @param int $uid 购买人uid
     * @param float $money  订单金额
     * @param int $task_id 关联任务id
     * @param int $userCoupon 使用的优惠券id
     * @return mixed 返回创建主订单信息信息
     */
    static function buyServicebyTask($uid,$money,$task_id,$userCoupon=0)
    {
        $status = DB::transaction(function() use($uid,$money,$task_id,$userCoupon){
            //产生组订单
            $code = ShopOrderModel::randomCode($uid,'ts');
            $order = [
                'code'       => $code,
                'title'      => '快包任务购买增值服务',
                'uid'        => $uid,
                'cash'       => $money,
                'task_id'    => $task_id,
                'status'     => 0,
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            $order_obj = OrderModel::createOne($order,$uid);
            $service = TaskServiceModel::where('task_id',$task_id)->lists('service_id')->toArray();
            if(!empty($service)){
                $service_ids = array_flatten($service);
                $service = ServiceModel::whereIn('id',$service_ids)->get()->toArray();
                foreach($service as $k => $v) {
                    if($v['identify'] == 'ZHITONGCHE'){
                        TaskModel::where('id', $task_id)->update([
                            'car_cash'   => $v['price'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    $sub_order = [
                        'title'        => $v['title'].'增值服务',
                        'cash'         => $v['price'],
                        'order_id'     => $order_obj->id,
                        'order_code'   => $order_obj->code,
                        'product_id'   => $v['id'],
                        'product_type' => 2,
                        'uid'          => $uid,
                        'status'       => 0,
                        'created_at'   => date('Y-m-d H:i:s',time()),

                    ];
                    SubOrderModel::create($sub_order);
                }
            }
            if($userCoupon > 0){
                //优惠券使用记录
                $coupon = UserCouponModel::find($userCoupon);
                if($coupon){
                    $couponInfo = CouponModel::find($coupon->coupon_id);
                    if($couponInfo){
                        UserCouponLogModel::create([
                            'order_num'         => $code,
                            'uid'               => $uid,
                            'user_coupon_id'    => $userCoupon,
                            'price'             => $couponInfo->price,
                            'created_at'        => date('Y-m-d H:i:s'),
                            'status'            => 1
                        ]);
                    }
                }
            }
            return $order_obj;
        });
        return $status;
    }

    /**
     * 快包项目 托管订单产生
     * @param $uid
     * @param float $money 托管赏金可以为负数（抵扣直通车金额后）
     * @param $task_id
     * @param int $userCoupon 使用的优惠券id
     * @return mixed
     */
    static function bountyOrder($uid,$money,$task_id,$userCoupon=0)
    {
        $status = DB::transaction(function() use($uid,$money,$task_id,$userCoupon){
            $code = self::randomCode($uid);
            //产生组订单
            $order = [
                'code'       => $code,
                'title'      => '赏金托管',
                'uid'        => $uid,
                'cash'       => $money,
                'task_id'    => $task_id,
                'status'     => 0,
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            $order_obj = OrderModel::createOne($order,$uid);
            if($order_obj) {
                $bounty = TaskModel::select('task.bounty')->where('id',$task_id)->first();
                $bounty_order = [
                    'title'         => '赏金托管',
                    'cash'          => $bounty['bounty'],
                    'order_id'      => $order_obj->id,
                    'order_code'    => $order_obj->code,
                    'product_type'  => 1,
                    'uid'           => $uid,
                    'status'        => 0,
                    'created_at'    => date('Y-m-d H:i:s',time()),
                ];
                SubOrderModel::create($bounty_order);
            }
            if($userCoupon > 0){
                //优惠券使用记录
                $coupon = UserCouponModel::find($userCoupon);
                if($coupon){
                    $couponInfo = CouponModel::find($coupon->coupon_id);
                    if($couponInfo){
                        UserCouponLogModel::create([
                            'order_num'         => $code,
                            'uid'               => $uid,
                            'user_coupon_id'    => $userCoupon,
                            'price'             => $couponInfo->price,
                            'created_at'        => date('Y-m-d H:i:s'),
                            'status'            => 1
                        ]);
                    }
                }
            }
            return $order_obj;
        });
        return $status;
    }


    public $transactionData;

    /**
     * 余额充值事务
     *
     * @param $payType
     * @param $data
     * @return bool
     */
    public function recharge($payType, array $data)
    {
        switch ($payType){
            case 'alipay':
            case 'wechat':
                //查询订单
                $orderInfo = OrderModel::where('code', $data['code'])->where('status', 0)->first();
                if (!empty($orderInfo)){
                    $balance=UserDetailModel::where('uid',$orderInfo['uid'])->pluck("balance");
                    $financeInfo = array(
                        // 'code'          => $data['code'],
                        'action'        => 3,
                        'pay_type'      => $payType == 'wechat' ? 3: 2,
                        'pay_account'   => $data['pay_account'],
                        'pay_code'      => $data['pay_code'],
                        'cash'          => $data['money'],
                        'uid'           => $orderInfo['uid'],
                        'status'        => 1,
                        'related_id'    => $orderInfo['id'],
                        'remainder'=>floatval($balance)+floatval($data['money']),
                        'created_at'    => date('Y-m-d H:i:s', time())
                    );
                    $this->transactionData['orderInfo'] = $orderInfo;
                    $this->transactionData['financeInfo'] = $financeInfo;
                    $status = DB::transaction(function (){
                        OrderModel::where('code', $this->transactionData['orderInfo']->code)->update(array('status' => 1));
                        FinancialModel::create($this->transactionData['financeInfo']);
                        $this->transactionData['status'] = UserDetailModel::where('uid', $this->transactionData['orderInfo']->uid)
                            ->increment('balance', $this->transactionData['financeInfo']['cash']);
                    });
                    Log::info($this->transactionData['status']);
                    return is_null($status) ? true : false;
                }
                break;
            case 'unionbank':
                break;
        }
    }

    /**
     * 后台确认充值
     *
     * @param $order
     * @return bool
     */
    static function adminRecharge($order)
    {
        $status = DB::transaction(function() use ($order){
            $order->update(array('status' => 1));
            $data = array(
                'action'     => 3,
                'pay_type'   => 1,
                'cash'       => $order->cash,
                'uid'        => $order->uid,
                'created_at' => date('Y-m-d H:i:s',time())
            );
            FinancialModel::create($data);
            UserDetailModel::where('uid', $order->uid)->increment('balance', $order->cash);
        });

        return is_null($status) ? true : false;
    }
    //根据不同的order将毁掉逻辑处理分发到
    static function dispatcher($order)
    {
        $prefix = [
            'e'=>'employ/success',//表示雇佣
        ];
        //截取订单的第一个字符然后返回所要跳转的路由
        $initial_str = substr($order,0,1);
        if(!empty($prefix[$initial_str]))
        {
            if($initial_str=='e')
            {
                //调用雇佣的支付逻辑
                $result = EmployModel::employResult();
                $route  = $prefix[$initial_str].'/'.$result['id'];
            }
            if(!$result)
                return false;

            return $route;
        }
        return false;
    }





    /**
     * 招标任务 托管赏金创建订单
     * @param int $uid 购买人uid
     * @param float $money  订单金额
     * @param int $task_id 关联任务id
     * @return mixed 返回创建主订单信息信息
     */
    static function bountyOrderByTaskBid($uid,$money,$task_id)
    {
        $status = DB::transaction(function() use($uid,$money,$task_id){
            //产生组订单
            $order = [
                'code' => self::randomCode($uid),
                'title' => '赏金托管',
                'uid' => $uid,
                'cash' => $money,
                'task_id' => $task_id,
                'status' => 0,
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            $order_obj = OrderModel::createOne($order,$uid);
            if($order_obj){
                $bounty = TaskModel::select('task.bounty')->where('id','=',$task_id)->first();
                $bounty_order = [
                    'title' => '赏金托管',
                    'cash' => $bounty['bounty'],
                    'order_id' => $order_obj->id,
                    'order_code' => $order_obj->code,
                    'product_type' => 1,
                    'product_id' => $task_id,
                    'uid' => $uid,
                    'status' => 0,
                    'created_at'=>date('Y-m-d H:i:s',time()),
                ];
                SubOrderModel::create($bounty_order);
            }
            return $order_obj;
        });
        return $status;
    }


}
