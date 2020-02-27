<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/10/10
 * Time: 10:54
 */
namespace App\Modules\Api\Http\Controllers;

use App\Http\Requests;
use App\Modules\Employ\Models\EmployCommentsModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Employ\Models\UnionRightsModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiBaseController;
use Illuminate\Support\Facades\Input;
use Omnipay;
use Validator;
use Illuminate\Support\Facades\Crypt;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\Shop\Models\ShopFocusModel;
use App\Modules\Task\Model\ServiceModel;
use DB;

class GoodsController extends ApiBaseController
{
    /**
     * 是否可以发布商品
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function isPub(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        //判断店铺是否开启
        $isOpenShop = ShopModel::isOpenShop($tokenInfo['uid']);

        if ($isOpenShop != 1) {
            if ($isOpenShop == 2) {
                return $this->formateResponse(1002, '您的店铺已关闭');
            } else {
                return $this->formateResponse(1003, '您的店铺还没设置');
            }
        }
        return $this->formateResponse(1000, 'success');
    }

    /**
     * 附件上传
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function fileUpload(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $file = $request->file('file');
        //将文件上传的数据存入到attachment表中
        $attachment = \FileClass::uploadFile($file, 'user');
        $attachment = json_decode($attachment, true);
        //判断文件是否上传
        if ($attachment['code'] != 200) {
            return $this->formateResponse(2001, $attachment['message']);
        }
        $attachment_data = array_add($attachment['data'], 'status', 1);
        $attachment_data['created_at'] = date('Y-m-d H:i:s', time());
        $attachment_data['user_id'] = $tokenInfo['uid'];
        //将记录写入attachment表中
        $result = AttachmentModel::create($attachment_data);
        $data = AttachmentModel::where('id', $result['id'])->first();
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        if (isset($data)) {
            $data->url = $data->url ? $domain->rule . '/' . $data->url : $data->url;
        }
        if ($result) {
            return $this->formateResponse(1000, 'success', $data);
        } else {
            return $this->formateResponse(2002, '文件上传失败');
        }
    }

    /**
     * 发布作品
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function pubGoods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'desc' => 'required|string',
            'first_cate' => 'required',
            'second_cate' => 'required',
            'cash' => 'required|numeric',
            'cover' => 'required'

        ], [
            'title.required' => '请输入作品标题',
            'title.string' => '请输入正确的标题格式',
            'title.max' => '标题长度不得超过50个字符',

            'desc.required' => '请输入作品描述',
            'desc.string' => '请输入描述正确的格式',

            'first_cate.required' => '请选择作品分类',
            'second_cate.required' => '请选择作品子分类',

            'cash.required' => '请输入作品金额',
            'cash.numeric' => '请输入正确的金额格式',

            'cover.required' => '请上传作品封面'
        ]);
        //获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001,$error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        //根据用户id获取店铺id
        $shopId = ShopModel::getShopIdByUid($tokenInfo['uid']);

        $data = $request->all();
        $data['cate_id'] = $data['second_cate'];

        //查询商品最小金额
        $minPriceArr = ConfigModel::getConfigByAlias('min_price');
        if (!empty($minPriceArr)) {
            $minPrice = $minPriceArr->rule;
        } else {
            $minPrice = 0;
        }
        if ($minPrice > 0 && $data['cash'] < $minPrice) {
            return $this->formateResponse(1004, '作品金额不能小于最低配置值');
        }
        isset($data['is_recommend']) ? $is_service = true : $is_service = false;
        //处理封面
        $cover = $request->file('cover');
        $result = \FileClass::uploadFile($cover, 'sys');
        if ($result) {
            $result = json_decode($result, true);
            $data['cover'] = $result['data']['url'];
        }
        //判断配置项商品上架是否需要审核
        $config = ConfigModel::getConfigByAlias('goods_check');
        if (!empty($config) && $config->rule == 1) {
            $goodsCheck = 0;
        } else {
            $goodsCheck = 1;
        }
        $data['status'] = $goodsCheck;
        $data['is_recommend'] = 0;
        $data['uid'] = $tokenInfo['uid'];
        $data['shop_id'] = $shopId;
        $res = DB::transaction(function () use ($data) {
            $goods = GoodsModel::create($data);
            //处理附件
            //$data['file_id'] = json_decode($data['file_id'],true);//[{"0":3516}]
            if (!empty($data['file_id'])) {
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($data['file_id']);
                $data['file_id'] = array_flatten($file_able_ids);
                $arrAttachment = array();
                foreach ($data['file_id'] as $v) {
                    $arrAttachment[] = [
                        'object_id' => $goods->id,
                        'object_type' => 4,
                        'attachment_id' => $v,
                        'created_at' => date('Y-m-d H:i:s', time())
                    ];
                }
                UnionAttachmentModel::insert($arrAttachment);
            }
            return $goods;
        });
        if (!isset($res)) {
            return $this->formateResponse(1005, '作品发布失败');
        }
        return $this->formateResponse(1000, '作品发布成功', $res);

    }

    /**
     * 发布服务
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function pubService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'desc' => 'required|string',
            'first_cate' => 'required',
            'second_cate' => 'required',
            'cash' => 'required|numeric',
            'cover' => 'required'

        ], [
            'title.required' => '请输入服务标题',
            'title.string' => '请输入正确的标题格式',
            'title.max' => '标题长度不得超过50个字符',

            'desc.required' => '请输入服务描述',
            'desc.string' => '请输入描述正确的格式',

            'first_cate.required' => '请选择服务分类',
            'second_cate.required' => '请选择服务子分类',

            'cash.required' => '请输入服务金额',
            'cash.numeric' => '请输入正确的金额格式',
            'cover.required' => '请上传服务封面'
        ]);
        //获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        //根据用户id获取店铺id
        $shopId = ShopModel::getShopIdByUid($tokenInfo['uid']);

        $data = $request->all();
        $data['cate_id'] = $data['second_cate'];

        //查询服务最小金额
        $minPriceArr = \CommonClass::getConfig('employ_bounty_min_limit');
        if (!$minPriceArr) {
            $minPrice = $minPriceArr;
        } else {
            $minPrice = 0;
        }
        if ($minPrice > 0 && $data['cash'] < $minPrice) {
            return $this->formateResponse(1004, '服务金额不能小于最低配置值');
        }
        //处理封面
        $cover = $request->file('cover');
        $result = \FileClass::uploadFile($cover, 'sys');
        if ($result) {
            $result = json_decode($result, true);
            $data['cover'] = $result['data']['url'];
        }
        //判断配置项商品上架是否需要审核
        $config = ConfigModel::getConfigByAlias('service_check');
        if (!empty($config) && $config->rule == 1) {
            $goodsCheck = 0;
        } else {
            $goodsCheck = 1;
        }
        $data['status'] = $goodsCheck;
        $data['is_recommend'] = 0;
        $data['uid'] = $tokenInfo['uid'];
        $data['shop_id'] = $shopId;
        $goods = GoodsModel::create($data);
        if (!isset($goods)) {
            return $this->formateResponse(1005, '服务发布失败');
        }
        return $this->formateResponse(1000, '服务发布成功', $goods);

    }


    /**
     * 我收藏的店铺列表及筛选
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myCollectShop(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $merge = $request->all();
        $collectArr = ShopFocusModel::where('uid', $tokenInfo['uid'])->orderby('created_at', 'DESC')->get()->toArray();
        $shopList = array();
        if (!empty($collectArr)) {
            $shopIds = array_unique(array_pluck($collectArr, 'shop_id'));
            $shopList = ShopModel::getShopListByShopIds($shopIds, $merge)->toArray();
            if ($shopList['total']) {
                $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
                foreach ($shopList['data'] as $k => $v) {
                    $shopList['data'][$k]['shop_pic'] = $v['shop_pic'] ? $domain->rule . '/' . $v['shop_pic'] : $v['shop_pic'];
                    //$shopList['data'][$k]['employ_num'] = count($v['employ_data']);
                    $shopList['data'][$k]['city_name'] = $v['province_name'] . $v['city_name'];
                    $shopList['data'][$k]['cate_name'] = isset($v['skill']) ? $v['skill'] : [];
                    $shopList['data'][$k]['good_comment'] = $v['good_comment'] ? $v['good_comment'] : 0;

                    if(isset($v['auth']['enterprise']) && $v['auth']['enterprise']){
                        $shopList['data'][$k]['isEnterprise'] = 1;
                    }else{
                        $shopList['data'][$k]['isEnterprise'] = 0;
                    }
                    if(isset($v['auth']['bank']) && $v['auth']['bank']){
                        $shopList['data'][$k]['bank'] = 1;
                    }else{
                        $shopList['data'][$k]['bank'] = 0;
                    }
                    if(isset($v['auth']['alipay']) && $v['auth']['alipay']){
                        $shopList['data'][$k]['alipay'] = 1;
                    }else{
                        $shopList['data'][$k]['alipay'] = 0;
                    }
                    if($v['email_status'] == 2){
                        $shopList['data'][$k]['email'] = 1;
                    }else{
                        $shopList['data'][$k]['email'] = 0;
                    }
                    if(isset($v['auth']['realname']) && $v['auth']['realname']){
                        $shopList['data'][$k]['realname'] = 1;
                    }else{
                        $shopList['data'][$k]['realname'] = 0;
                    }

                    $shopList['data'][$k] = array_except($shopList['data'][$k], ['employ_data', 'province_name', 'uid', 'type', 'shop_desc', 'province', 'city', 'status', 'created_at', 'updated_at', 'shop_bg', 'seo_title', 'seo_keyword', 'seo_desc', 'is_recommend', 'comment_rate', 'employ_num', 'skill', 'nav_rules', 'nav_color', 'banner_rules', 'central_ad', 'footer_ad','email_status','auth']);
                    //$shopList['data'][$k]['shop_desc'] = htmlspecialchars_decode($v['shop_desc']);

                }
            }
        }
        return $this->formateResponse(1000, '获取我收藏的店铺列表信息成功', $shopList);

    }


    /**
     * 获取作品平台抽佣
     *
     * @return \Illuminate\Http\Response
     */
    public function workRateInfo()
    {
        $workRate = ConfigModel::where('alias', 'trade_rate')->first();
        $percent = $workRate->rule;
        return $this->formateResponse(1000, '获取作品平台抽佣信息成功', ['percent' => $percent]);

    }

    /**
     * 获取推荐作品配置信息
     *
     * @return \Illuminate\Http\Response
     */
    public function workRecommendInfo()
    {
        $configInfo = [];
        //查询是否开启推荐商品增值工具
        $isOpenArr = ServiceModel::where(['identify' => 'ZUOPINTUIJIAN', 'type' => 2, 'status' => 1])->first();
        if (!empty($isOpenArr)) {
            $configInfo['isOpen'] = 1;
            $configInfo['price'] = $isOpenArr->price;
            //查询推荐增值服务有效期
            $unitAbout = ConfigModel::getConfigByAlias('recommend_goods_unit');
            $configInfo['unit'] = $unitAbout->rule;
        } else {
            $configInfo['isOpen'] = 0;
        }
        return $this->formateResponse(1000, '获取推荐作品开启信息成功', ['configInfo' => $configInfo]);


    }


    /**
     * 获取服务平台抽佣
     *
     * @return \Illuminate\Http\Response
     */
    public function serviceRateInfo()
    {
        $serviceRate = ConfigModel::where('alias', 'employ_percentage')->first();
        $percent = $serviceRate->rule;
        return $this->formateResponse(1000, '获取服务平台抽佣信息成功', ['percent' => $percent]);

    }


    /**
     * 获取推荐服务开启信息
     *
     * @return \Illuminate\Http\Response
     */
    public function serviceRecommendInfo()
    {
        $configInfo = [];
        //查询发布商品推荐服务上否开启
        $service = ServiceModel::where(['status' => 1, 'type' => 2, 'identify' => 'FUWUTUIJIAN'])->first();
        if (!empty($service)) {
            $configInfo['isOpen'] = 1;
            $configInfo['price'] = $service->price;
            $configInfo['unit'] = \CommonClass::getConfig('recommend_service_unit');
        } else {
            $configInfo['isOpen'] = 0;
        }

        return $this->formateResponse(1000, '获取推荐服务开启信息成功', ['configInfo' => $configInfo]);


    }


    /**
     * 我发布的作品
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myWorkList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        $merge = $request->all();
        $goodsInfo = GoodsModel::getGoodsListByUid($uid, $merge)->toArray();
        if ($goodsInfo['total']) {
            $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
            foreach ($goodsInfo['data'] as $k => $v) {
                $goodsInfo['data'][$k]['desc'] = htmlspecialchars_decode($v['desc']);
                $goodsInfo['data'][$k]['cover'] = $v['cover'] ? $domain->rule . '/' . $v['cover'] : $v['cover'];
            }
        }
        return $this->formateResponse(1000, '获取我发布的作品成功', $goodsInfo);
    }


    /**
     * 我发布的服务
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myOfferList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $all_cate = TaskCateModel::findAllCache();
        $all_cate = \CommonClass::keyBy($all_cate, 'id');
        $service = GoodsModel::select('*')->where('uid', $tokenInfo['uid'])->where('type', 2)->where('is_delete', 0);
        //状态筛选
        if ($request->get('status')) {
            switch ($request->get('status')) {
                case 1://待审核
                    $status = 0;
                    $service = $service->where('status', $status);
                    break;
                case 2://售卖中
                    $status = 1;
                    $service = $service->where('status', $status);
                    break;
                case 3://下架
                    $status = 2;
                    $service = $service->where('status', $status);
                    break;
                case 4: //审核失败
                    $status = 3;
                    $service = $service->where('status', $status);
                    break;

            }
        }
        //时间筛选
        if ($request->get('sometime')) {
            $time = date('Y-m-d H:i:s', strtotime("-" . intval($request->get('sometime')) . " month"));
            $service->where('created_at', '>', $time);
        }

        $service = $service->orderBy('created_at', 'DESC')
            ->paginate(5)->toArray();

        if ($service['total']) {
            $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
            foreach ($service['data'] as $k => $v) {
                $service['data'][$k]['name'] = $all_cate[$v['cate_id']]['name'];
                $service['data'][$k]['cover'] = $v['cover'] ? $domain->rule . '/' . $v['cover'] : $v['cover'];
                $service['data'][$k]['desc'] = htmlspecialchars_decode($v['desc']);
            }
        }
        return $this->formateResponse(1000, '获取我发布的服务信息成功', $service);

    }


    /**
     * 我购买的作品或服务的订单列表
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function goodsOrderList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $type = $request->get('type');//1:作品 2：服务
        if (!$type) {
            return $this->formateResponse(1001, '缺少参数');
        }
        $domain = url();
        $myGoods = array();

        if ($type == 1) {
            $status = $request->get('goods_status') ? $request->get('goods_status') : 0;
            $merge['type'] = $status;
            //查询我购买的作品订单列表
            $myGoods = ShopOrderModel::myBuyGoods($tokenInfo['uid'], 2, $merge,10)->toArray();
            if (!empty($myGoods['data'])) {
                foreach ($myGoods['data'] as $k => $v) {
                    $myGoods['data'][$k] = array_except($v, array('code', 'uid', 'object_type',
                        'note', 'invoice_status', 'trade_rate', 'desc', 'cover'));
                    switch ($v['status']) {
                        case 0:
                            $ss = '待付款';
                            break;
                        case 1:
                            $ss = '已付款';
                            break;
                        case 2:
                            $ss = '交易完成';
                            break;
                        case 3:
                            $ss = '维权处理';
                            break;
                        case 4:
                            $ss = '交易完成';
                            break;
                        case 5:
                            $ss = '维权结束';
                            break;
                        default:
                            $ss = '交易完成';
                            break;
                    }
                    switch ($v['unit']) {
                        case 0:
                            $unit = '件';
                            break;
                        case 1:
                            $unit = '时';
                            break;
                        case 2:
                            $unit = '份';
                            break;
                        case 3:
                            $unit = '个';
                            break;
                        case 4:
                            $unit = '张';
                            break;
                        case 5:
                            $unit = '套';
                            break;
                        default:
                            $unit = '件';
                            break;
                    }
                    $cateName = isset($v['cate_name']) ? $v['cate_name'] : '';
                    $myGoods['data'][$k]['cate_name'] = $cateName;
                    $myGoods['data'][$k]['unit'] = $unit;
                    $myGoods['data'][$k]['status'] = $ss;
                    $myGoods['data'][$k]['status_num'] = $v['status'];
                    $myGoods['data'][$k]['img'] = $domain . '/' . $v['cover'];
                    if($v['comments_num'] > 0){
                        $myGoods['data'][$k]['percent'] = intval($v['comments_num']*100/$v['comments_num']);
                    }else{
                        $myGoods['data'][$k]['percent'] = 100;
                    }
                }
            }
        } elseif ($type == 2) {
            $status = ($request->get('service_status') || $request->get('service_status') == 0) ? $request->get('service_status') : 'all';
            $data['status'] = $status;
            //查询我购买的服务(我雇佣别人)
            $employ = new EmployModel();
            $myGoods = $employ->employMine($tokenInfo['uid'], $data,10)->toArray();
            $employeeUid = [];
            if (!empty($myGoods['data'])) {
                foreach ($myGoods['data'] as $k => $v) {
                    $employeeUid[] = $v['employee_uid'];
                    $myGoods['data'][$k] = array_except($v, array('desc', 'phone', 'bounty', 'bounty_status',
                        'delivery_deadline', 'employer_uid', 'employed_at', 'employ_percentage', 'seo_title',
                        'seo_keywords', 'seo_content', 'cancel_at', 'except_max_at',
                        'end_at', 'begin_at', 'accept_deadline', 'accept_at', 'right_allow_at', 'comment_deadline',
                        'updated_at', 'user_name', 'avatar'));
                    switch ($v['status']) {
                        case 0:
                            $ss = '待受理';
                            break;
                        case 1:
                            $ss = '工作中';
                            break;
                        case 2:
                            $ss = '验收中';
                            break;
                        case 3:
                            $ss = '待评价';
                            break;
                        case 4:
                            $ss = '交易完成';
                            break;
                        case 5:
                            $ss = '拒绝雇佣';
                            break;
                        case 6:
                            $ss = '取消任务';
                            break;
                        case 7:
                            $ss = '雇主维权';
                            break;
                        case 8:
                            $ss = '威客维权';
                            break;
                        case 9:
                            $ss = '雇佣过期';
                            break;
                        default:
                            $ss = '待受理';
                            break;
                    }
                    $myGoods['data'][$k]['status'] = $ss;
                    $myGoods['data'][$k]['status_num'] = $v['status'];
                    $myGoods['data'][$k]['cash'] = $v['bounty'];
                    $myGoods['data'][$k]['img'] = $domain . '/' . $v['avatar'];
                }

            }
            $employComment = EmployCommentsModel::whereIn('to_uid',$employeeUid)->get()->toArray();
            $newComment = array_reduce($employComment,function(&$newComment,$v){
                if($v['type'] == 1){
                    $newComment[$v['to_uid']]['good_comment'][] = $v;
                }
                $newComment[$v['to_uid']]['comment'][] = $v;
                return $newComment;
            });
            if(!empty($newComment)){
                foreach ($myGoods['data'] as $k => $v) {
                    foreach($newComment as $kc => $vc){
                        if($v['employee_uid'] == $kc){
                            $goodComment = isset($vc['good_comment']) ? count($vc['good_comment']) : 0;
                            $totalComment = isset($vc['comment']) ? count($vc['comment']) : 0;
                            $myGoods['data'][$k]['comments_num'] = $totalComment;
                            $myGoods['data'][$k]['good_comment'] =$goodComment;
                            if($totalComment > 0){
                                $myGoods['data'][$k]['percent'] = intval($goodComment*100/$totalComment);
                            }else{
                                $myGoods['data'][$k]['percent'] = 100;
                            }

                        }
                    }
                }
            }
        }
        return $this->formateResponse(1000, '获取我购买的订单列表成功', $myGoods);

    }

    /**
     * 我购买的作品或服务雇佣数量统计
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function buyOrderCount(Request $request)
    {
        $type = $request->get('type');//1:作品 2:服务
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if(!$type){
            return $this->formateResponse(2001, '确实参数');
        }
        if($type == 1){//作品
            $orderStatus = [
                'all'    => [0,1,2,3,4,5],//全部
                'is_pay' => [1],//已付款
                'finish' => [2,4], //交易完成
                'other'  => [0,3,5],//其他
            ];

        }else{
            $orderStatus = [
                'all'     => [0,1,2,3,4,5,6,7,8,9],//全部
                'is_wait' => [0],   //待受理
                'working' => [1,2], //进行中
                'finish'  => [3,4], //交易完成
                'other'   => [5,6,7,8,9],//其他
            ];
        }
        $count = [];
        if(!empty($orderStatus)){
            foreach($orderStatus as $k => $v){
                $count[$k] = ShopOrderModel::buyOrderCount($uid,$type,$v);
            }
        }
        return $this->formateResponse(1000,'success',$count);
    }

    /**
     * 我卖出的服务或作品
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function saleOrderList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $type = $request->get('type');//1:作品 2：服务
        if (!$type) {
            return $this->formateResponse(1001, '缺少参数');
        }
        $domain = url();
        $myGoods = array();

        if ($type == 1) {
            $status = $request->get('goods_status') ? $request->get('goods_status') : 0;
            $merge['type'] = $status;
            //查询我卖出的作品订单列表
            $myGoods = ShopOrderModel::sellGoodsList($tokenInfo['uid'], 2, $merge,10);

            if(isset($myGoods)&&count($myGoods)){
                $myGoods = $myGoods->toArray();
            }

            if (!empty($myGoods['data'])) {
                foreach ($myGoods['data'] as $k => $v) {
                    $myGoods['data'][$k] = array_except($v, array('code', 'uid', 'object_id', 'object_type',
                        'note', 'invoice_status', 'trade_rate', 'desc', 'object_id', 'cover'));
                    switch ($v['status']) {
                        case 0:
                            $ss = '待付款';
                            break;
                        case 1:
                            $ss = '已付款';
                            break;
                        case 2:
                            $ss = '交易完成';
                            break;
                        case 3:
                            $ss = '维权处理';
                            break;
                        case 4:
                            $ss = '交易完成';
                            break;
                        case 5:
                            $ss = '维权结束';
                            break;
                        default:
                            $ss = '交易完成';
                            break;
                    }
                    switch ($v['unit']) {
                        case 0:
                            $unit = '件';
                            break;
                        case 1:
                            $unit = '时';
                            break;
                        case 2:
                            $unit = '份';
                            break;
                        case 3:
                            $unit = '个';
                            break;
                        case 4:
                            $unit = '张';
                            break;
                        case 5:
                            $unit = '套';
                            break;
                        default:
                            $unit = '件';
                            break;
                    }

                    $cateName = isset($v['cate_name']) ? $v['cate_name'] : '';
                    $myGoods['data'][$k]['cate_name'] = $cateName;
                    $myGoods['data'][$k]['unit'] = $unit;
                    $myGoods['data'][$k]['status'] = $ss;
                    $myGoods['data'][$k]['status_num'] = $v['status'];
                    $myGoods['data'][$k]['avatar'] = $domain . '/' . $v['avatar'];
                    if($v['comments_num'] > 0){
                        $myGoods['data'][$k]['percent'] = intval($v['comments_num']*100/$v['comments_num']);
                    }else{
                        $myGoods['data'][$k]['percent'] = 100;
                    }

                }
            }
        } elseif ($type == 2) {
            $status = ($request->get('service_status') || $request->get('service_status') == 0) ? $request->get('service_status') : 'all';
            $data['status'] = $status;
            //查询我承接的服务
            $myGoods = EmployModel::employMyJob($tokenInfo['uid'], $data,10)->toArray();
            $employerUid = [];
            if (!empty($myGoods['data'])) {
                foreach ($myGoods['data'] as $k => $v) {
                    $employerUid[] = $v['employer_uid'];
                    $myGoods['data'][$k] = array_except($v, array('desc', 'phone', 'bounty', 'bounty_status',
                        'delivery_deadline', 'employee_uid', 'employed_at', 'employ_percentage', 'seo_title',
                        'seo_keywords', 'seo_content', 'cancel_at', 'except_max_at',
                        'end_at', 'begin_at', 'accept_deadline', 'accept_at', 'right_allow_at', 'comment_deadline',
                        'updated_at', 'avatar'));
                    switch ($v['status']) {
                        case 0:
                            $ss = '待受理';
                            break;
                        case 1:
                            $ss = '工作中';
                            break;
                        case 2:
                            $ss = '验收中';
                            break;
                        case 3:
                            $ss = '待评价';
                            break;
                        case 4:
                            $ss = '交易完成';
                            break;
                        case 5:
                            $ss = '拒绝雇佣';
                            break;
                        case 6:
                            $ss = '取消任务';
                            break;
                        case 7:
                            $ss = '雇主维权';
                            break;
                        case 8:
                            $ss = '威客维权';
                            break;
                        case 9:
                            $ss = '雇佣过期';
                            break;
                        default:
                            $ss = '待受理';
                            break;
                    }
                    $myGoods['data'][$k]['status'] = $ss;
                    $myGoods['data'][$k]['status_num'] = $v['status'];
                    $myGoods['data'][$k]['cash'] = $v['bounty'];
                    $myGoods['data'][$k]['avatar'] = $domain . '/' . $v['avatar'];
                }
            }
            $employComment = EmployCommentsModel::whereIn('to_uid',$employerUid)->get()->toArray();
            $newComment = array_reduce($employComment,function(&$newComment,$v){
                if($v['type'] == 1){
                    $newComment[$v['to_uid']]['good_comment'][] = $v;
                }
                $newComment[$v['to_uid']]['comment'][] = $v;
                return $newComment;
            });
            if(!empty($newComment)){
                foreach ($myGoods['data'] as $k => $v) {
                    foreach($newComment as $kc => $vc){
                        if($v['employer_uid'] == $kc){

                            $goodComment = isset($vc['comment']) ? count($vc['comment']) : 0;
                            $totalComment = isset($vc['good_comment']) ? count($vc['good_comment']) : 0;
                            $myGoods['data'][$k]['comments_num'] = $goodComment;
                            $myGoods['data'][$k]['good_comment'] = $totalComment;
                            if($totalComment > 0){
                                $myGoods['data'][$k]['percent'] = intval($goodComment*100/$totalComment);
                            }else{
                                $myGoods['data'][$k]['percent'] = 100;
                            }


                        }
                    }
                }
            }
        }



        if(!count($myGoods)){
            $myGoods['data'] = [];
        }

        return $this->formateResponse(1000, '获取我卖出的订单列表成功', $myGoods);

    }

    public function saleOrderCount(Request $request)
    {
        $type = $request->get('type');//1:作品 2:服务
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if(!$type){
            return $this->formateResponse(2001, '确实参数');
        }
        if($type == 1){//作品
            $orderStatus = [
                'all'    => [0,1,2,3,4,5],//全部
                'is_pay' => [1],//已付款
                'finish' => [2,4], //交易完成
                'other'  => [0,3,5],//其他
            ];

        }else{
            $orderStatus = [
                'all'     => [0,1,2,3,4,5,6,7,8,9],//全部
                'is_wait' => [0],   //待受理
                'working' => [1,2], //进行中
                'finish'  => [3,4], //交易完成
                'other'   => [5,6,7,8,9],//其他
            ];
        }
        $count = [];
        if(!empty($orderStatus)){
            foreach($orderStatus as $k => $v){
                $count[$k] = ShopOrderModel::saleOrderCount($uid,$type,$v);
            }
        }
        return $this->formateResponse(1000,'success',$count);
    }


    /**
     * 我购买作品的订单详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function buyGoodsDetail(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('order_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('order_id');
        //查询订单详情
        $order = ShopOrderModel::where('shop_order.id', $id)->where('shop_order.object_type', 2)
            ->leftJoin('goods', 'goods.id', '=', 'shop_order.object_id')
            ->select('shop_order.id', 'shop_order.uid as buy_uid', 'shop_order.cash', 'shop_order.status',
                'goods.id as goods_id', 'goods.unit', 'goods.title', 'goods.sales_num','goods.cover',
                'goods.uid', 'goods.shop_id')->first();
        $domain = \CommonClass::getDomain();
        if (!empty($order)) {
            if ($order->buy_uid != $uid) {
                return $this->formateResponse(1003, '参数错误');
            }
            $provinceN = '';
            $cityN = '';
            //查询威客店铺地址信息
            $shopInfo = ShopModel::where('id', $order->shop_id)->where('uid', $order->uid)->first();
            if (!empty($shopInfo)) {
                $province = DistrictModel::where('id', $shopInfo->province)->first();
                if (!empty($province)) {
                    $provinceN = $province->name;
                }
                $city = DistrictModel::where('id', $shopInfo->city)->first();
                if (!empty($city)) {
                    $cityN = $city->name;
                }
            }
            $order->address = $provinceN . $cityN;
            $order->cover = $domain . '/' . $order->cover;
            //查询威客名称
            $user = UserModel::where('id',$order->uid)->first();
            if(!empty($user)){
                $order->username = $user->name;
            }else{
                $order->username = '';
            }
            $order = $order->toArray();
            switch ($order['unit']) {
                case 0:
                    $unit = '件';
                    break;
                case 1:
                    $unit = '时';
                    break;
                case 2:
                    $unit = '份';
                    break;
                case 3:
                    $unit = '个';
                    break;
                case 4:
                    $unit = '张';
                    break;
                case 5:
                    $unit = '套';
                    break;
                default:
                    $unit = '件';
                    break;
            }
            $order['unit'] = $unit;
            switch ($order['status']) {
                case 0:
                    $buttonStatus = '等待支付';
                    break;
                case 1://已支付 等待确认收货或维权
                    $buttonStatus = '处理作品';
                    break;
                case 2://已确认
                    $buttonStatus = '给予评价';
                    break;
                case 3:
                    $buttonStatus = '维权中';
                    break;
                case 4://已完成
                    $buttonStatus = '查看评价';
                    break;
                case 5://维权成功
                    $buttonStatus = '等待支付';
                    break;
                default:
                    $buttonStatus = '等待支付';
                    break;
            }
            $order['button_status'] = $buttonStatus;
            if (in_array($order['status'], [1, 2, 3, 4])) {
                //查询作品附件
                $workAtt = UnionAttachmentModel::where('object_type', 4)
                    ->where('object_id', $order['goods_id'])
                    ->select('attachment_id')->get()->toArray();
                $order['attachment'] = array();
                if (!empty($workAtt)) {
                    //获取附件关联id
                    $workId = array_flatten($workAtt);
                    if (!empty($workId)) {
                        //查询附件信息
                        $order['attachment'] = AttachmentModel::whereIn('id', $workId)->get()->toArray();
                        if (!empty($order['attachment'])) {
                            foreach ($order['attachment'] as $k => $v) {
                                $order['attachment'][$k]['url'] = $domain . '/' . $v['url'];
                            }
                        }
                    }
                }
            } else {
                $order['attachment'] = array();
            }
            return $this->formateResponse(1000, '我购买作品的订单详情成功', $order);
        } else {
            return $this->formateResponse(1003, '参数错误');
        }
    }

    /**
     * 我卖出作品的订单详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function saleGoodsDetail(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('order_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('order_id');
        //查询订单详情
        $order = ShopOrderModel::where('shop_order.id', $id)->where('shop_order.object_type', 2)
            ->leftJoin('goods', 'goods.id', '=', 'shop_order.object_id')
            ->select('shop_order.id', 'shop_order.uid as buy_uid', 'shop_order.cash', 'shop_order.status',
                'goods.id as goods_id', 'goods.unit', 'goods.title', 'goods.sales_num','goods.cover',
                'goods.uid', 'goods.shop_id')->first();
        $domain = \CommonClass::getDomain();
        if (!empty($order)) {
            if ($order->uid != $uid) {
                return $this->formateResponse(1003, '参数错误');
            }
            $provinceN = '';
            $cityN = '';
            //查询威客店铺地址信息
            $shopInfo = ShopModel::where('id', $order->shop_id)->where('uid', $order->uid)->first();
            if (!empty($shopInfo)) {
                $province = DistrictModel::where('id', $shopInfo->province)->first();
                if (!empty($province)) {
                    $provinceN = $province->name;
                }
                $city = DistrictModel::where('id', $shopInfo->city)->first();
                if (!empty($city)) {
                    $cityN = $city->name;
                }
            }
            $order->address = $provinceN . $cityN;
            $order->cover = $domain . '/' . $order->cover;
            $order = $order->toArray();
            //查询雇主的名称和头像
            $user = UserModel::where('users.id', $order['buy_uid'])->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                ->select('users.name', 'user_detail.avatar')->first();
            if (!empty($user)) {
                $order['username'] = $user->name;
                $order['avatar'] = $domain . '/' . $user->avatar;
            }
            switch ($order['unit']) {
                case 0:
                    $unit = '件';
                    break;
                case 1:
                    $unit = '时';
                    break;
                case 2:
                    $unit = '份';
                    break;
                case 3:
                    $unit = '个';
                    break;
                case 4:
                    $unit = '张';
                    break;
                case 5:
                    $unit = '套';
                    break;
                default:
                    $unit = '件';
                    break;
            }
            $order['unit'] = $unit;
            //查询评论信息
            $comment = GoodsCommentModel::where('goods_id', $order['goods_id'])->where('uid', $order['buy_uid'])->first();
            if (!empty($comment)) {
                $order['comment'] = $comment;
            } else {
                $order['comment'] = [];
            }
            return $this->formateResponse(1000, '我卖出作品的订单详情成功', $order);
        } else {
            return $this->formateResponse(1003, '参数错误');
        }
    }

    /**
     * 雇主验收某一购买作品的订单
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function confirmGoods(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('order_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('order_id');
        //订单信息
        $orderInfo = ShopOrderModel::where('id', $id)->where('object_type', 2)->first();
        if (empty($orderInfo)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $res = ShopOrderModel::confirmGoods($id, $uid);
        if ($res) {
            return $this->formateResponse(1000, '验收成功');
        } else {
            return $this->formateResponse(1001, '验收失败');
        }

    }

    /**
     * 雇主维权某一购买作品的订单
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function rightGoods(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('order_id') || !$request->get('type') || !$request->get('desc')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('order_id');
        $type = $request->get('type');
        $desc = $request->get('desc');
        //订单信息
        $orderInfo = ShopOrderModel::where('id', $id)->where('object_type', 2)->first();
        if (empty($orderInfo)) {
            return $this->formateResponse(1003, '参数错误');
        }
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
            'type' => $type,
            'object_id' => $id,
            'object_type' => 2,//购买商品维权
            'desc' => $desc,
            'status' => 0,//未处理
            'from_uid' => $uid,
            'to_uid' => $toUid,
            'created_at' => date('Y-m-d H:i:s')
        );
        $res = UnionRightsModel::buyGoodsRights($rightsArr, $id);
        if ($res) {
            return $this->formateResponse(1000, '维权信息提交成功');
        } else {
            return $this->formateResponse(1001, '维权信息提交失败');
        }

    }

    /**
     * 雇主评论某一购买作品的订单
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function commentGoods(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('order_id') || !$request->get('type') || !$request->get('desc') || !$request->get('speed_score') || !$request->get('quality_score') || !$request->get('attitude_score')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('order_id');
        //订单信息
        $orderInfo = ShopOrderModel::where('id', $id)->where('status', 2)->where('object_type', 2)->first()/*->toArray()*/;
        if (empty($orderInfo)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $type = $request->get('type') ? intval($request->get('type') - 1) : 0;
        $commentArr = array(
            'uid' => $uid,
            'goods_id' => $orderInfo['object_id'],
            'type' => $type,
            'speed_score' => $request->get('speed_score'),
            'quality_score' => $request->get('quality_score'),
            'attitude_score' => $request->get('attitude_score'),
            'comment_desc' => $request->get('desc'),
            'created_at' => date('Y-m-d H:i:s'),
            'comment_by' => 1,
        );
        $res = GoodsCommentModel::createGoodsComment($commentArr, $orderInfo);
        if ($res) {
            return $this->formateResponse(1000, '评论提交成功');
        } else {
            return $this->formateResponse(1001, '评论信息提交失败');
        }

    }

    /**
     * 雇主获取购买作品的评价信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getComment(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('order_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('order_id');
        //订单信息
        $orderInfo = ShopOrderModel::where('id', $id)->where('object_type', 2)->first();
        if (empty($orderInfo)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $res = GoodsCommentModel::where('uid',$uid)->where('goods_id',$orderInfo->object_id)->first();
        if ($res) {
            //查询我的昵称和头像
            $user = UserModel::where('users.id',$uid)->leftJoin('user_detail','user_detail.uid','=','users.id')
                ->select('users.name','user_detail.avatar')->first();
            $domain = \CommonClass::getDomain();
            if($user){
                $res->name = $user->name;
                $res->avatar = $domain.'/'.$user->avatar;
            }else{
                $res->name = '';
                $res->avatar = '';
            }
            $res = $res->toArray();
            return $this->formateResponse(1000, '获取评论信息成功',$res);
        } else {
            return $this->formateResponse(1001, '没有评论信息');
        }

    }

    /**
     * 购买作品生成订单
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function buyGoods(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('goods_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }

        $goodsId = $request->get('goods_id');

        //查询商品信息
        $goods = GoodsModel::where('id',$goodsId)->first()->toArray();
        if(empty($goods)){
            return $this->formateResponse(1003, '参数错误');
        }
        //查询商品交易成功平台提成比例
        $tradeRateArr = ConfigModel::getConfigByAlias('trade_rate');
        if ($tradeRateArr) {
            $tradeRate = $tradeRateArr->rule;
        } else {
            $tradeRate = 0;
        }
        //查询该用户是否已有该商品待付款的订单
        $order = ShopOrderModel::where('uid', $uid)->where('object_id', $goodsId)
            ->where('object_type', 2)->where('status', 0)->first();
        if (empty($order)) {
            $arr = array(
                'code' => ShopOrderModel::randomCode($uid, 'bg'),
                'title' => '购买作品' . $goods['title'],
                'uid' => $uid,
                'object_id' => $goodsId,
                'object_type' => 2, //购买商品
                'cash' => $goods['cash'],
                'status' => 0, //未支付
                'created_at' => date('Y-m-d H:i:s', time()),
                'trade_rate' => $tradeRate
            );
            //判断之前是否购买该商品
            $re = ShopOrderModel::isBuy($uid, $goodsId, 2);
            if ($goods['uid'] == $uid) {
                return $this->formateResponse(1004, '您是商品发布人，无需购买');
            } else if ($goods['status'] != 1) {
                return $this->formateResponse(1005, '该商品已经失效');
            } else {
                if ($re == false) {
                    //保存订单信息
                    $res = ShopOrderModel::create($arr);
                    if ($res) {
                        $data = array(
                            'order_id' => $res->id
                        );
                        return $this->formateResponse(1000, '订单生成成功',$data);
                    } else {
                        return $this->formateResponse(1000, '订单生成失败');
                    }
                } else {
                    return $this->formateResponse(1006, '已经购买该商品，无需继续购买');
                }
            }
        } else {
            $data = array(
                'order_id' => $order->id
            );
            return $this->formateResponse(1000, '订单生成成功',$data);
        }
    }

    /**
     * 余额支付作品购买
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cashPayGoods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'pay_type' => 'required',
            'password' => 'required'
        ], [
            'order_id.required' => '作品订单id不能为空',
            'pay_type.required' => '请选择支付方式',
            'password.required' => '请输入支付密码'
        ]);
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1003, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $data = array(
            'order_id' => $request->get('order_id'),
            'pay_type' => $request->get('pay_type'),
            'password' => $request->get('password')
        );

        //查询订单信息
        $order = ShopOrderModel::where('id', $data['order_id'])->first();

        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($order['uid'] != $tokenInfo['uid'] || $order['status'] != 0) {
            return $this->formateResponse(1002, '该作品已购买');
        }

        //查询用户的余额
        $balance = UserDetailModel::where('uid', $tokenInfo['uid'])->first();
        $balance = $balance['balance'];


        //判断用户如果选择的余额支付
        if ($balance >= $order['cash'] && $data['pay_type'] == 0) {
            //验证用户的密码是否正确
            $user = UserModel::where('id', $tokenInfo['uid'])->first();
            $password = UserModel::encryptPassword($data['password'], $user['salt']);
            if ($password != $user['alternate_password']) {
                return $this->formateResponse(1004, '您的支付密码不正确');
            }
            //支付产生订单
            $res = ShopOrderModel::buyShopGoods($tokenInfo['uid'], $data['order_id']);
            if ($res) {
                //查询商品数据
                $goodsInfo = GoodsModel::where('id', $order->object_id)->first();
                //修改商品销量
                $salesNum = intval($goodsInfo->sales_num + 1);
                GoodsModel::where('id', $goodsInfo->id)->update(['sales_num' => $salesNum]);
                return $this->formateResponse(1000, '支付成功');
            } else {
                return $this->formateResponse(1001, '支付失败，请重新支付');
            }
        } else {
            return $this->formateResponse(1005, '余额不足，请充值或切换支付方式');
        }
    }

    /**
     * 第三方支付购买作品
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ThirdCashGoodsPay(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if ($request->get('order_id')) {
            //查询订单信息
            $order = ShopOrderModel::where('id', $request->get('order_id'))->where('status', 0)->first();
        } else {
            return $this->formateResponse(1002, '缺少参数');
        }

        if ($order) {
            //判断用户是否有支付此任务的权限
            if ($order->uid != $uid) {
                return $this->formateResponse(1071, '非法操作');
            }
            $payType = $request->get('pay_type');
            if(!in_array($payType,['alipay','wechat'])){
                return $this->formateResponse(1071, '支付方式错误');
            }
            switch ($payType) {
                case 'alipay':
                    $config = ConfigModel::getConfigByAlias('app_alipay');
                    $info = [];
                    if($config && !empty($config['rule'])){
                        $info = json_decode($config['rule'],true);
                    }

                    if(!isset($info['alipay_type']) || (isset($info['alipay_type']) && $info['alipay_type']== 1)){

                        $alipay = app('alipay.mobile');
                        if(!empty($info) && isset($info['partner_id'])){
                            $alipay->setPartner($info['partner_id']);
                        }
                        if(!empty($info) && isset($info['seller_id'])){
                            $alipay->setSellerId($info['seller_id']);
                        }
                        $alipay->setNotifyUrl(url('api/alipay/notify'));
                        $alipay->setOutTradeNo($order->code);
                        $alipay->setTotalFee($order->cash);
                        $alipay->setSubject($order->title);
                        $alipay->setBody($order->note);
                        return $this->formateResponse(1000, '确认充值', ['payParam' => $alipay->getPayPara()]);
                    }else{
                        $Client = new \AopClient();//实例化支付宝sdk里面的AopClient类,下单时需要的操作,都在这个类里面

                        $seller_id = $appId = '';
                        if(!empty($info) && isset($info['appId'])){
                            $appId = $info['appId'];
                        }
                        if(!empty($info) && isset($info['seller_id'])){
                            $seller_id = $info['seller_id'];
                        }
                        $content = [
                            'seller_id' => $seller_id,
                            'out_trade_no' => $order->code,
                            'timeout_express' => "30m",
                            'subject'      => $order->title,
                            'total_amount'    => $order->cash,
                            'product_code'    => 'QUICK_MSECURITY_PAY',
                        ];
                        $con = json_encode($content);

                        $param['app_id'] = $appId;//'2017121700929928';
                        $param['method'] = 'alipay.trade.app.pay';//接口名称，固定值
                        $param['charset'] = 'utf-8';//请求使用的编码格式
                        $param['sign_type'] = 'RSA';//商户生成签名字符串所使用的签名算法类型
                        $param['timestamp'] = date("Y-m-d H:i:s");//发送请求的时间
                        $param['version'] = '1.0';//调用的接口版本，固定为：1.0
                        $param['notify_url'] = url('api/alipay/notify');
                        $param['biz_content'] = $con;//业务请求参数的集合,长度不限,json格式，即前面一步得到的
                        $private_path = storage_path('app/alipay/rsa_private_key.pem');
                        $paramStr = $Client->getSignContent($param);//组装请求签名参数
                        $sign = $Client->alonersaSign($paramStr, $private_path, 'RSA', true);//生成签名
                        $param['sign'] = $sign;
                        $str = $Client->getSignContentUrlencode($param);//最终请求参数
                        return $this->formateResponse(1000, '确认充值', ['payParam' => $str]);
                    }

                    break;
                case 'wechat':
                    $gateway = Omnipay::gateway('WechatPay');
                    $configInfo = ConfigModel::getConfigByAlias('app_wechat');
                    $config = [];
                    if($configInfo && !empty($configInfo['rule'])){
                        $config = json_decode($configInfo['rule'],true);
                    }
                    if(isset($config['appId'])){
                        $gateway->setAppId($config['appId']);
                    }
                    if(isset($config['mchId'])){
                        $gateway->setMchId($config['mchId']);
                    }
                    if(isset($config['apiKey'])){
                        $gateway->setApiKey($config['apiKey']);
                    }
                    $gateway->setNotifyUrl(url('api/wechatpay/notify'));
                    $data = [
                        'body' => $order->title,
                        'out_trade_no' => $order->code,
                        'total_fee' => $order->cash * 100, //=0.01
                        'spbill_create_ip' => Input::getClientIp(),
                        'fee_type' => 'CNY'
                    ];
                    $request = $gateway->purchase($data);
                    $response = $request->send();
                    if ($response->isSuccessful()) {
                        return $this->formateResponse(1000, '确认充值', ['params' => $response->getAppOrderData()]);
                    }
                    break;
            }
        } else {
            return $this->formateResponse(1072, '订单不存在或已经支付');
        }
    }

}
