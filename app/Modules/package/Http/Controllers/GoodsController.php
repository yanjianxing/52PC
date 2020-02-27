<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Employ\Models\UnionRightsModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Shop\Models\ShopFocusModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Shop\Models\GoodsModel;
use Auth;
use DB;
use Omnipay;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GoodsController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('shop');
    }

    /**
     * 商品购买视图
     * @param Request $request
     * @param $id 商品id
     * @author quanke
     * @return mixed
     */
    public function buyGoods(Request $request, $id)
    {
        $id = intval($id);
        $merge = $request->all();
        if ($request->get('page')) {
            $page = $request->get('page');
        } else {
            $page = 1;
        }
        if ($request->get('comment_type')) {
            $type = $request->get('comment_type');
        } else {
            $type = 0;
        }
        //查询商品详情
        $goodsInfo = GoodsModel::getGoodsInfoById($id, array('is_delete' => 1)); //查询包含下架或删除的作品
        $shopId = $goodsInfo->shop_id;
        $this->theme->set('SHOPID', $shopId);
        $this->theme->setUserId($goodsInfo->uid);
        $isRights = false;
        if (!empty($goodsInfo)) {
            //判断是否登录
            if (Auth::check()) {
                $uid = Auth::id();
                //判断发布作品用户是否登录者
                if ($goodsInfo->uid == $uid) {
                    $owner = true; //是否是自己发布的商品
                    $isContract = false; //自己不能联系自己
                    $collectShop = false;

                } else {
                    $owner = false;
                    $isContract = true;
                    //是否收藏该店铺
                    $collectShopArr = ShopFocusModel::shopFocusStatus($shopId);
                    if (!empty($collectShopArr)) {
                        $collectShop = 1;//已经收藏
                    } else {
                        $collectShop = 0;//没有收藏店铺
                    }
                }
                //判断用户是否购买该商品
                $isBuy = ShopOrderModel::isBuy($uid, $id, 2);
                if ($isBuy == true && $owner == false) {
                    //查询是否评论该商品
                    $isComment = GoodsCommentModel::isComment($id, $uid);
                } else {
                    $isComment = false;
                    //判断用户对该商品是否在维权中
                    $isRights = ShopOrderModel::isRights($uid, $id, 2);
                }
                //判断商品是否上架
                if ($goodsInfo->status == 1) {
                    $isOk = true;
                } else {
                    if ($isBuy == true) {
                        $isOk = true;
                    } else {
                        if ($goodsInfo->is_delete == 0) {
                            $isOk = true;
                        } else {
                            $isOk = false;
                        }
                    }
                }

            } else {
                $owner = false;
                if ($goodsInfo->status == 1) {
                    $isOk = true;
                } else {
                    if ($goodsInfo->is_delete == 0) {
                        $isOk = true;
                    } else {
                        $isOk = false;
                    }
                }
                $isBuy = false;
                $isContract = true;
                $isComment = false;
                $collectShop = false; //未登录不显示是否收藏图标
            }
            //查询商品源文件
            $unionAtt = UnionAttachmentModel::where(['object_id' => $id, 'object_type' => 4])->get();
            if (!empty($unionAtt)) {
                $attachmentId = array();
                foreach ($unionAtt as $k => $v) {
                    $attachmentId[] = $v['attachment_id'];
                }
                $attachment = AttachmentModel::whereIn('id', $attachmentId)->get();
            } else {
                $attachment = array();
            }

            //对该商品的评价
            $commentAbout = GoodsCommentModel::getCommentByGoodsId($id, $page, $type);
            //店铺其他商品
            $userId = $goodsInfo->uid;
            $goodsList = GoodsModel::where('goods.uid', $userId)->where('goods.shop_id', $shopId)->where('goods.is_delete', 0)
                ->where('goods.status', 1)->where('goods.type', 1)->where('goods.id', '!=', $id)
                ->leftJoin('cate', 'cate.id', '=', 'goods.cate_id')->select('goods.*', 'cate.name')
                ->orderBy('goods.updated_at', 'DESC')->limit(4)->get()->toArray();
            //店铺所有者联系方式信息
            $contactInfo = UserDetailModel::where('uid', $goodsInfo->uid)
                ->select('mobile', 'mobile_status', 'qq', 'qq_status', 'wechat', 'wechat_status')->first();
            //商品平台佣金
            $tradeRate = \CommonClass::getConfig('trade_rate');
            $data = array(
                'goods_info' => $goodsInfo,
                'is_buy' => $isBuy,
                'is_contract' => $isContract,
                'is_comment' => $isComment,
                'collect_shop' => $collectShop,
                'goods_list' => $goodsList,
                'comment_about' => $commentAbout,
                'attachment' => $attachment,
                'owner' => $owner,
                'contactInfo' => $contactInfo,
                'merge' => $merge,
                'trade_rate' => $tradeRate,
                'is_rights' => $isRights

            );
            if ($goodsInfo->seo_title) {
                $this->theme->setTitle($goodsInfo->seo_title);
                $this->theme->set('keywords', $goodsInfo->seo_keyword);
                $this->theme->set('description', $goodsInfo->seo_desc);
            } else {
                $this->theme->setTitle('作品详情');
                if (!empty($goodsInfo->title)) {
                    $this->theme->setTitle($goodsInfo->title);
                }
            }
            if ($isOk == true) {
                return $this->theme->scope('shop.buygoods', $data)->render();

            } else {
                abort('404');
            }
        } else {
            abort('404');
        }

    }

    /**
     * 添加商品评论
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function addGoodsComment(Request $request)
    {
        $data = $request->except('_token');
        $uid = Auth::id();
        //查询订单完成信息
        $goodsOrder = ShopOrderModel::where('uid', $uid)->where('object_id', $data['goods_id'])
            ->where('object_type', 2)->where('status', 2)->first()->toArray();
        $commentArr = array(
            'uid' => $data['uid'],
            'goods_id' => $data['goods_id'],
            'type' => $data['type'],
            'speed_score' => $data['speed_score'],
            'quality_score' => $data['quality_score'],
            'attitude_score' => $data['attitude_score'],
            'comment_desc' => $data['comment'],
            'created_at' => date('Y-m-d H:i:s'),
            'comment_by' => 1,
        );
        $res = GoodsCommentModel::createGoodsComment($commentArr, $goodsOrder);
        if ($res) {
            return redirect('/shop/buyGoods/' . $data['goods_id']);
        }
    }

    /**
     * ajax获取评论内容
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetGoodsComment(Request $request)
    {
        $id = $request->get('id');
        $page = $request->get('page') ? intval($request->get('page')) : 1;
        $type = $request->get('type') ? intval($request->get('type')) : 0;
        //获取评论列表
        $goodsComment = GoodsCommentModel::getCommentByGoodsId($id, $page, $type);
        $domain = \CommonClass::getDomain();
        if (!empty($goodsComment)) {
            $data = array(
                'code' => 1,
                'msg' => 'success',
                'data' => $goodsComment,
                'domain' => $domain
            );
        } else {
            $data = array(
                'code' => 0,
                'msg' => 'failure'
            );
        }
        return response()->json($data);
    }

    /**
     * 商品订单视图
     * @param Request $request
     * @param $id 商品id
     * @return mixed
     */
    public function orders(Request $request, $id)
    {
        $id = intval($id);
        $uid = Auth::id();
        $isBuy = ShopOrderModel::isBuy($uid, $id, 2);
        $isRights = ShopOrderModel::isRights($uid, $id, 2);
        if ($isBuy == true || $isRights == true) {
            return redirect('/shop/buyGoods/' . $id);
        }
        $goodsInfo = GoodsModel::getGoodsInfoById($id);
        $shopId = $goodsInfo->shop_id;
        $this->theme->set('SHOPID', $shopId);
        $this->theme->setUserId($goodsInfo->uid);
        $data = array(
            'goods_info' => $goodsInfo,
            'uid' => $uid
        );
        $this->theme->setTitle('商品订单');
        return $this->theme->scope('shop.orders', $data)->render();
    }

    /**
     * 创建商品订单
     * @param Request $request
     * @return mixed
     */
    public function postOrder(Request $request)
    {
        $uid = Auth::id();
        $data = $request->all();
        //查询商品交易成功平台提成比例
        $tradeRateArr = ConfigModel::getConfigByAlias('trade_rate');
        if ($tradeRateArr) {
            $tradeRate = $tradeRateArr->rule;
        } else {
            $tradeRate = 0;
        }
        //查询该用户是否已有该商品待付款的订单
        $order = ShopOrderModel::where('uid', $uid)->where('object_id', $data['goods_id'])
            ->where('object_type', 2)->where('status', 0)->first();
        if (empty($order)) {
            $arr = array(
                'code' => ShopOrderModel::randomCode($uid, 'bg'),
                'title' => '购买作品' . $data['title'],
                'uid' => $uid,
                'object_id' => $data['goods_id'],
                'object_type' => 2, //购买商品
                'cash' => $data['pay_cash'],
                'status' => 0, //未支付
                'created_at' => date('Y-m-d H:i:s', time()),
                'trade_rate' => $tradeRate
            );
            //判断之前是否购买该商品
            $re = ShopOrderModel::isBuy($uid, $data['goods_id'], 2);
            //判断不是商品发布者
            $isPublish = GoodsModel::where('id', $data['goods_id'])->first();
            if ($isPublish->uid == $uid) {
                $data = array(
                    'code' => 0,
                    'msg' => '您是商品发布人，无需购买'
                );
            } else if ($isPublish->status != 1) {
                $data = array(
                    'code' => 0,
                    'msg' => '该商品已经失效'
                );
            } else {
                if ($re == false) {
                    //保存订单信息
                    $res = ShopOrderModel::create($arr);
                    if ($res) {
                        $data = array(
                            'code' => 1,
                            'msg' => 'success',
                            'data' => $res->id
                        );
                    } else {
                        $data = array(
                            'code' => 0,
                            'msg' => '订单生成失败'
                        );
                    }
                } else {
                    $data = array(
                        'code' => 2,
                        'msg' => '已经购买该商品，无需继续购买'
                    );
                }
            }
        } else {
            $data = array(
                'code' => 1,
                'msg' => 'success',
                'data' => $order->id
            );
        }
        return response()->json($data);
    }

    /**
     * 支付视图
     * @param $id 订单id
     * @return mixed
     */
    public function pay($id)
    {
        $uid = Auth::id();
        //判断订单是否真实
        $res = ShopOrderModel::where('id', $id)->first();
        if (!empty($res) && $uid == $res->uid) {
            if ($res->status == 0) {
                //查询账户余额
                $userInfo = UserDetailModel::where('uid', $uid)->where('balance_status', 0)->select('balance')->first();
                if (!empty($userInfo)) {
                    $balance = $userInfo->balance;
                } else {
                    $balance = 0.00;
                }
                $balance_pay = false;
                if ($balance >= $res->cash) {
                    $balance_pay = true;
                }
                //查询用户绑定的银行卡信息
                $bank = BankAuthModel::where('uid', '=', $id)->where('status', '=', 4)->get();
                //判断第三方支付是否开启
                $payConfig = ConfigModel::getConfigByType('thirdpay');
                $data = array(
                    'id' => $res->id,  //订单id
                    'pay_cash' => $res->cash, //订单金额
                    'balance' => $balance, //账户余额
                    'balance_pay' => $balance_pay, //账户余额是否充足
                    'bank' => $bank,
                    'pay_config' => $payConfig
                );
                $this->theme->setTitle('商品订单支付');

                //根据商品id获取店铺id
                $goods = GoodsModel::where('id',$res->object_id)->first();
                $this->theme->set('SHOPID', $goods->shop_id);
                $this->theme->setUserId($goods->uid);

                return $this->theme->scope('shop.pay', $data)->render();
            } else {
                return redirect('shop/buyGoods/' . $res->object_id);
            }
        } else {
            abort('404');
        }
    }

    /**
     * 商品订单支付
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postPayOrder(Request $request)
    {
        $user = Auth::user();
        $data = $request->except('_token');
        $data['id'] = intval($data['id']);
        //查询订单数据
        $orderInfo = ShopOrderModel::where('id', $data['id'])->first();
        //如果余额足够就直接余额付款
        if ($data['pay_canel'] == 0) {
            //验证用户的密码是否正确
            $password = UserModel::encryptPassword($data['password'], $user->salt);
            if ($password != $user->alternate_password) {
                return redirect()->back()->with(['error' => '您的支付密码不正确']);
            } else {
                $res = ShopOrderModel::buyShopGoods($user->id, $data['id']);
                if ($res) {
                    //查询商品数据
                    $goodsInfo = GoodsModel::where('id', $orderInfo->object_id)->first();
                    //修改商品销量
                    $salesNum = intval($goodsInfo->sales_num + 1);
                    GoodsModel::where('id', $goodsInfo->id)->update(['sales_num' => $salesNum]);
                    return redirect('/shop/confirm/' . $orderInfo->id);
                } else {
                    return redirect()->back()->with(['error' => '支付失败']);
                }
            }
        } else if (isset($data['pay_type']) && $data['pay_canel'] == 1) {

            if ($data['pay_type'] == 1) {//支付宝支付
                $config = ConfigModel::getPayConfig('alipay');
                $objOminipay = Omnipay::gateway('alipay');
                $objOminipay->setPartner($config['partner']);
                $objOminipay->setKey($config['key']);
                $objOminipay->setSellerEmail($config['sellerEmail']);
                $objOminipay->setReturnUrl(env('ALIPAY_RETURN_URL', url('/order/pay/alipay/return'))); //同步回调
                $objOminipay->setNotifyUrl(env('ALIPAY_NOTIFY_URL', url('/order/pay/alipay/notify'))); //异步回调

                $response = Omnipay::purchase([
                    'out_trade_no' => $orderInfo->code, //your site trade no, unique
                    'subject' => \CommonClass::getConfig('site_name'), //order title
                    'total_fee' => $orderInfo->cash, //order total fee $money
                ])->send();
                $response->redirect();

            } else if ($data['pay_type'] == 2) {//微信支付
                $config = ConfigModel::getPayConfig('wechatpay');
                $wechat = Omnipay::gateway('wechat');
                $wechat->setAppId($config['appId']);
                $wechat->setMchId($config['mchId']);
                $wechat->setAppKey($config['appKey']);
                $params = array(
                    'out_trade_no' => $orderInfo->code, // billing id in your system
                    'notify_url' => env('WECHAT_NOTIFY_URL', url('order/pay/wechat/notify')), // URL for asynchronous notify
                    'body' => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee' => $orderInfo->cash, // Amount with less than 2 decimals places
                    'fee_type' => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());

                $view = array(
                    'cash' => $orderInfo->cash,
                    'img' => $img
                );
                return $this->theme->scope('pay.wechatpay', $view)->render();

            } else if ($data['pay_type'] == 3) {
                dd('银联支付！');
            }
        } else if (isset($data['account']) && $data['pay_canel'] == 2) {//银行卡支付
            dd('银行卡支付！');
        } else {//如果没有选择其他的支付方式
            return redirect()->back()->with(['error' => '请选择一种支付方式']);
        }
    }

    /**
     * 商品确认源文件视图
     * @param $id 订单id
     * @return mixed
     */
    public function confirm($id)
    {
        $id = intval($id);
        $orderInfo = ShopOrderModel::where('id', $id)->where('status', 1)->where('object_type', 2)->first();//已支付购买商品订单
        if (!empty($orderInfo)) {
            //商品信息
            $goodsInfo = GoodsModel::getGoodsInfoById($orderInfo->object_id, ['status' => 1]);
            //查询商品文件
            $unionAtt = UnionAttachmentModel::where(['object_id' => $orderInfo->object_id, 'object_type' => 4])->get();
            if (!empty($unionAtt)) {
                $attachmentId = array();
                foreach ($unionAtt as $k => $v) {
                    $attachmentId[] = $v['attachment_id'];
                }
                $attachment = AttachmentModel::whereIn('id', $attachmentId)->get();
            } else {
                $attachment = array();
            }

            $data = array(
                'id' => $id,
                'goods_info' => $goodsInfo,
                'attachment' => $attachment
            );
            $this->theme->setTitle('商品确认');

            $this->theme->set('SHOPID', $goodsInfo->shop_id);
            $this->theme->setUserId($goodsInfo->uid);


            return $this->theme->scope('shop.confirm', $data)->render();
        } else {
            abort('404');
        }

    }

    /**
     * 确认源文件
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postConfirm(Request $request)
    {
        $data = $request->except('_token');
        $orderInfo = ShopOrderModel::where('id', $data['id'])->where('object_type', 2)->first();
        $res = ShopOrderModel::confirmGoods($data['id'], Auth::id());
        if ($res) {
            return redirect('shop/buyGoods/' . $orderInfo->object_id . '?comment_type=0');
        }

    }

    /**
     * 下载商品附件
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
     * 保存维权信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postRightsInfo(Request $request)
    {
        $data = $request->except('_token');
        //查询订单信息
        $orderInfo = ShopOrderModel::where('id', $data['order_id'])->first();
        if (!empty($orderInfo)) {
            //查询商品信息
            $goodsInfo = GoodsModel::where('id', $orderInfo->object_id)->first();
            if (!empty($goodsInfo)) {
                $toUid = $goodsInfo->uid;
            } else {
                $toUid = '';
            }
        } else {
            $toUid = '';
        }

        $rightsArr = array(
            'type' => $data['type'],
            'object_id' => $data['order_id'],
            'object_type' => 2,//购买商品维权
            'desc' => $data['desc'],
            'status' => 0,//未处理
            'from_uid' => Auth::id(),
            'to_uid' => $toUid,
            'created_at' => date('Y-m-d H:i:s')
        );
        $orderId = $data['order_id'];
        $res = UnionRightsModel::buyGoodsRights($rightsArr, $orderId);
        if ($res) {
            //跳转到我购买的商品维权中
            return redirect('user/myBuyGoods?status=4');
        }
    }


}
