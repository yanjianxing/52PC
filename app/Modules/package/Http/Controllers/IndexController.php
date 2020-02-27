<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Employ\Models\EmployCommentsModel;
use App\Modules\Employ\Models\EmployGoodsModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\Shop\Models\ShopFocusModel;
use Auth;
use DB;
use Omnipay;
use Teepluss\Theme\Facades\Theme;
use QrCode;

class IndexController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('common');
    }
    
//    //方案超市
//    public function fananshop(){
//        $this->initTheme('shop');
//        $data=[];
//      return $this->theme->scope('shop.fananshop', $data)->render();
//    }


    //店铺对内页面
    public function shop($shopId)
    {

        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);

        //根据店铺id获取店铺信息
        $shopInfo = ShopModel::getShopInfoById($shopId, 1);
        if($shopInfo['uid'] != Auth::id()){
            return redirect('/')->with(['message' => '没有权限']);
        }
        $workInfo = $goodsInfo = $evaluateInfo = [];
        if (!empty($shopInfo)) {
            //好评率计算
            if ($shopInfo['total_comment']) {
                $shopInfo['percent'] = $shopInfo['good_comment'] / $shopInfo['total_comment'];
                if ($shopInfo['percent']) {
                    $shopInfo['percent'] = number_format($shopInfo['percent'], 1) * 100;
                } else {
                    $shopInfo['percent'] = 100;
                }
            } else {
                $shopInfo['percent'] = 100;
            }
            //店铺描述处理
            $shopInfo['shop_desc'] = htmlspecialchars_decode($shopInfo['shop_desc']);
            //店铺累计服务
            $shopInfo['serviceNum'] = GoodsModel::where(['shop_id' => $shopId, 'status' => 1])->select('id')->sum('sales_num');
            //查询用户的绑定关系
            $authUser = AuthRecordModel::getAuthByUserId($shopInfo['uid']);
            //获取作品信息
            $workInfo = GoodsModel::select('goods.id', 'goods.title', 'goods.cover', 'goods.cash', 'cate.name')
                ->join('cate', 'goods.cate_id', '=', 'cate.id')
                ->where(['goods.shop_id' => $shopId, 'goods.type' => 1, 'goods.status' => 1])
                ->orderBy('goods.created_at', 'desc')
                ->limit(4)->get()->toArray();
            //获取服务信息
            $goodsInfo = GoodsModel::select('goods.id', 'goods.title', 'goods.cover', 'goods.cash', 'cate.name')
                ->join('cate', 'goods.cate_id', '=', 'cate.id')
                ->where(['goods.shop_id' => $shopId, 'goods.type' => 2, 'goods.status' => 1])
                ->orderBy('goods.created_at', 'desc')
                ->limit(4)->get()->toArray();
            //获取交易评价信息
            $goodsComment = GoodsCommentModel::join('goods', 'goods_comment.goods_id', '=', 'goods.id')
                ->join('users', 'goods_comment.uid', '=', 'users.id')
                ->join('user_detail', 'users.id', '=', 'user_detail.uid')
                ->where('goods.shop_id', $shopId)
                ->select('goods_comment.*', 'goods.type as sort', 'goods.title', 'goods.desc', 'goods.cash', 'users.name', 'user_detail.avatar', 'goods.id as goodId')
                ->orderBy('goods_comment.created_at', 'desc')
                ->limit(3)->get()->toArray();
            if (!empty($goodsComment)) {
                foreach ($goodsComment as $k => $v) {
                    $goodsComment[$k]['total_score'] = number_format(($v['speed_score'] + $v['quality_score'] + $v['attitude_score']) / 3, 1);
                    $goodsComment[$k]['desc'] = htmlspecialchars_decode($goodsComment[$k]['desc']);
                }
                $evaluateInfo = $goodsComment;
            }
            //获取案例信息
            $caseInfo = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')
                ->where('success_case.uid', $shopInfo['uid'])
                ->select('success_case.id', 'success_case.title', 'success_case.pic', 'success_case.view_count', 'cate.name')
                ->orderBy('success_case.created_at', 'desc')
                ->limit(4)->get()->toArray();

            //获取店铺装修轮播图信息
            $carouselIds = json_decode($shopInfo['banner_rules'],true);
            $carouselPics = AttachmentModel::whereIn('id',$carouselIds)->select('url')->get()->toArray();

        } else {
            abort('404');
        }
        $domain = \CommonClass::getDomain();

        $domainConfig = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        $arr = [
            'domain'  => $domainConfig->rule,
            'shop_id' => $shopId,
            'is_open' => $shopInfo['status'],
            'uid'     => $shopInfo['uid'],
        ];
        $arrStr = json_encode($arr);
        $img = QrCode::size('280')->generate($arrStr);

        $this->theme->setTitle('我的店铺');
        $data = array(
            'domain' => $domain,
            'shopInfo' => $shopInfo,
            'authUser' => $authUser,
            'workInfo' => $workInfo,
            'goodsInfo' => $goodsInfo,
            'caseInfo' => $caseInfo,
            'commentInfo' => $evaluateInfo,
            'carouselPics' => $carouselPics,
            'central_ad' => $shopInfo['central_ad'],
            'footer_ad' => $shopInfo['footer_ad'],
            'shopId' => $shopId,
            'img' => $img
        );
        return $this->theme->scope('shop.shop', $data)->render();
    }

    //店铺介绍
    public function shopabout($shopId)
    {
        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);
        //根据店铺id获取店铺信息
        $shopInfo = ShopModel::getShopInfoById($shopId, 1);
        if (!empty($shopInfo)) {
            if (Auth::id() != $shopInfo['uid']) {
                $shopInfo = ShopModel::getShopInfoById($shopId);
            }
        }
        if (!empty($shopInfo)) {
            //店铺描述处理
            $shopInfo['shop_desc'] = htmlspecialchars_decode($shopInfo['shop_desc']);
            //店铺累计服务
            $shopInfo['serviceNum'] = GoodsModel::where(['shop_id' => $shopId, 'status' => 1])->sum('sales_num');
            //查询用户的绑定关系
            $authUser = AuthRecordModel::getAuthByUserId($shopInfo['uid']);
            //店铺所有者联系方式信息
            $contactInfo = UserDetailModel::where('uid', $shopInfo['uid'])->select('mobile', 'mobile_status', 'qq', 'qq_status', 'wechat', 'wechat_status')->first();
            //查询用户的邮箱绑定关系
            $emailStatus = UserModel::where('id', $shopInfo['uid'])->select('email_status')->first()->email_status;
            $this->theme->setUserId($shopInfo['uid']);
        } else {
            abort('404');
        }
        //查询店铺收藏状态
        $isFocus = ShopFocusModel::shopFocusStatus($shopId);
        $this->theme->setTitle('店铺介绍');


        $domain = \CommonClass::getDomain();

        $data = array(
            'domain' => $domain,
            'shopInfo' => $shopInfo,
            'authUser' => $authUser,
            'contactInfo' => $contactInfo,
            'shopId' => $shopId,
            'emailStatus' => $emailStatus,
            'isFocus' => $isFocus
        );
        return $this->theme->scope('shop.shopabout', $data)->render();
    }

    //商城成功案例
    public function successstory(Request $request, $shopId)
    {
        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);
        $shopInfo = ShopModel::where('id', $shopId)->select('uid')->first();
        if (!empty($shopInfo)) {
            $this->theme->setUserId($shopInfo['uid']);
        } else {
            abort('404');
        }
        $cateInfo = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')
            ->join('shop', 'success_case.uid', '=', 'shop.uid')
            ->where('shop.id', $shopId)
            ->select('success_case.cate_id', 'cate.name', 'success_case.uid')->distinct()->orderBy('success_case.created_at', 'desc')->get()->toArray();
        if (!empty($cateInfo)) {
            foreach ($cateInfo as $k => $v) {
                $num = SuccessCaseModel::join('shop', 'success_case.uid', '=', 'shop.uid')->where(['success_case.cate_id' => $v['cate_id'], 'success_case.uid' => $v['uid']])->count();
                $cateInfo[$k]['num'] = $num;
            }
        }
        $caseInfo = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')
            ->join('shop', 'success_case.uid', '=', 'shop.uid')
            ->where('shop.id', $shopId);
        if ($request->get('cate_id')) {
            $caseInfo = $caseInfo->where('success_case.cate_id', intval($request->get('cate_id')));
        }
        $caseInfo = $caseInfo->select('success_case.id', 'success_case.title', 'success_case.pic', 'success_case.view_count', 'cate.name')
            ->orderBy('success_case.created_at', 'desc')
            ->paginate(12);
        $domain = \CommonClass::getDomain();
        $this->theme->setTitle('店铺案例');
        $data = [
            'cateInfo' => $cateInfo,
            'caseInfo' => $caseInfo,
            'domain' => $domain,
            'shopId' => $shopId
        ];
        return $this->theme->scope('shop.successstory', $data)->render();
    }

    //商城所有商品
    public function shopall(Request $request, $shopId)
    {
        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);
        $shopInfo = ShopModel::where('id', $shopId)->select('uid')->first();
        if (!empty($shopInfo)) {
            $this->theme->setUserId($shopInfo['uid']);
        } else {
            abort('404');
        }
        $cateInfo = GoodsModel::join('cate', 'goods.cate_id', '=', 'cate.id')
            ->where(['goods.type' => 1, 'goods.status' => 1, 'goods.shop_id' => $shopId])
            ->select('goods.cate_id', 'cate.name')->distinct()->orderBy('goods.created_at', 'desc')->get()->toArray();
        if (!empty($cateInfo)) {
            foreach ($cateInfo as $k => $v) {
                $num = GoodsModel::where(['cate_id' => $v['cate_id'], 'type' => 1, 'status' => 1, 'shop_id' => $shopId])->count();
                $cateInfo[$k]['num'] = $num;
            }
        }
        $workInfo = GoodsModel::join('cate', 'goods.cate_id', '=', 'cate.id')
            ->where(['goods.type' => 1, 'goods.status' => 1, 'goods.shop_id' => $shopId]);
        if ($request->get('cate_id')) {
            $workInfo = $workInfo->where('goods.cate_id', intval($request->get('cate_id')));
        }
        if($request->get('keywords')){
            $workInfo = $workInfo->where('goods.title','like','%'.$request->get('keywords').'%');
        }
        $workInfo = $workInfo->select('goods.id', 'goods.title', 'goods.cover', 'goods.cash', 'cate.name')
            ->orderBy('goods.created_at', 'desc')
            ->paginate(12);
        $domain = \CommonClass::getDomain();
        $this->theme->setTitle('店铺作品');
        $data = [
            'cateInfo' => $cateInfo,
            'workInfo' => $workInfo,
            'domain' => $domain,
            'shopId' => $shopId
        ];
        return $this->theme->scope('shop.shopall', $data)->render();
    }

    //商城交易评价
    public function rated(Request $request, $shopId)
    {
        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);
        //根据店铺id获取店铺信息
        $shopInfo = ShopModel::getShopInfoById($shopId, 1);
        if (!empty($shopInfo)) {
            if (Auth::id() != $shopInfo['uid']) {
                $shopInfo = ShopModel::getShopInfoById($shopId);
            }
        }
        $evaluateInfo = $goodCateInfo = $serviceCateInfo = [];
        $goodsCount = $serviceCount = $speedScore = $qualityScore = $attitudeScore = 0;
        if (!empty($shopInfo)) {
            //店铺累计服务
            $shopInfo['serviceNum'] = GoodsModel::where(['shop_id' => $shopInfo->id, 'status' => 1])->sum('sales_num');
            //查询用户的绑定关系
            $authUser = AuthRecordModel::getAuthByUserId($shopInfo['uid']);
            //查询用户的邮箱绑定关系
            $emailStatus = UserModel::where('id', $shopInfo['uid'])->select('email_status')->first()->email_status;
            //获取交易评价信息
            $goodsComment = GoodsCommentModel::join('goods', 'goods_comment.goods_id', '=', 'goods.id')
                ->join('users', 'goods_comment.uid', '=', 'users.id')
                ->join('user_detail', 'users.id', '=', 'user_detail.uid')
                ->where('goods.shop_id', $shopId);
            switch ($request->get('type')) {
                case '1':
                    $type = 0;
                    break;
                case '2':
                    $type = 1;
                    break;
                case '3':
                    $type = 2;
                    break;
                default:
                    $type = 0;
            }
            $goodsComment = $goodsComment->where('goods_comment.type', $type);

            $goodsComment = $goodsComment->select('goods_comment.*', 'goods.type as sort', 'goods.title', 'goods.desc', 'goods.cash', 'users.name', 'user_detail.avatar', 'goods.id as goodId')
                ->orderBy('goods_comment.created_at', 'desc')->paginate(4);
            if (isset($goodsComment)) {
                foreach ($goodsComment as $k => $v) {
                    $goodsComment[$k]->total_score = number_format(($v->speed_score + $v->quality_score + $v->attitude_score) / 3, 1);
                    $goodsComment[$k]->desc = htmlspecialchars_decode($goodsComment[$k]->desc);
                }
                $evaluateInfo = $goodsComment;
            }
            //商品分类信息
            $goodCateInfo = GoodsModel::join('cate', 'goods.cate_id', '=', 'cate.id')
                ->where('goods.shop_id', $shopId)
                ->where(['goods.type' => 1, 'goods.status' => 1])
                ->select('goods.cate_id', 'cate.name')->distinct()->orderBy('goods.created_at', 'desc')->limit(3)->get()->toArray();
            if (!empty($goodCateInfo)) {
                foreach ($goodCateInfo as $k => $v) {
                    $num = GoodsModel::where(['cate_id' => $v['cate_id'], 'type' => 1, 'status' => 1, 'shop_id' => $shopId])->count();
                    $goodCateInfo[$k]['num'] = $num;
                }
            }
            //服务分类信息
            $serviceCateInfo = GoodsModel::join('cate', 'goods.cate_id', '=', 'cate.id')
                ->where('goods.shop_id', $shopId)
                ->where(['goods.type' => 2, 'goods.status' => 1])
                ->select('goods.cate_id', 'cate.name')->distinct()->orderBy('goods.created_at', 'desc')->limit(3)->get()->toArray();
            if (!empty($serviceCateInfo)) {
                foreach ($serviceCateInfo as $k => $v) {
                    $num = GoodsModel::where(['cate_id' => $v['cate_id'], 'type' => 2, 'status' => 1, 'shop_id' => $shopId])->count();
                    $serviceCateInfo[$k]['num'] = $num;
                }
            }
            //商品总数
            $goodsCount = GoodsModel::where(['shop_id' => $shopId, 'status' => 1, 'type' => 1])->count();
            //服务总数
            $serviceCount = GoodsModel::where(['shop_id' => $shopId, 'status' => 1, 'type' => 2])->count();
            $goodsIds = GoodsModel::where(['shop_id' => $shopId, 'status' => 1])->select('id')->get()->toArray();
            $goodsIds = array_flatten($goodsIds);
            //速度平均分
            $gspeedScore = GoodsCommentModel::whereIn('goods_id', $goodsIds)->avg('speed_score');
            $gspeedScore = number_format($gspeedScore, 1);
            //质量平均分
            $gqualityScore = GoodsCommentModel::whereIn('goods_id', $goodsIds)->avg('quality_score');
            $gqualityScore = number_format($gqualityScore, 1);
            //态度平均分
            $gattitudeScore = GoodsCommentModel::whereIn('goods_id', $goodsIds)->avg('attitude_score');
            $gattitudeScore = number_format($gattitudeScore, 1);

            //获取店铺服务雇佣评价评分
            //雇佣(查询我是被雇用人)
            $employ = EmployModel::where('employee_uid',$shopInfo->uid)->select('id')->get()->toArray();
            $employId = array_flatten($employ);
            $employCommentSpeed = number_format(EmployCommentsModel::where('to_uid',$shopInfo->uid)->whereIn('employ_id',$employId)->avg('speed_score'),1);
            $employCommentQuality = number_format(EmployCommentsModel::where('to_uid',$shopInfo->uid)->whereIn('employ_id',$employId)->avg('quality_score'),1);
            $employCommentAtt = number_format(EmployCommentsModel::where('to_uid',$shopInfo->uid)->whereIn('employ_id',$employId)->avg('attitude_score'),1);


            //计算评分得分(未被评价不参与评分)
            if(($gattitudeScore>0 || $gspeedScore>0 || $gqualityScore>0) && ($employCommentAtt>0 || $employCommentSpeed>0 || $employCommentQuality>0)){
                $speedScore = number_format(($gspeedScore + $employCommentSpeed)/2,1);
                $qualityScore = number_format(($gqualityScore + $employCommentQuality)/2,1);
                $attitudeScore = number_format(($gattitudeScore + $employCommentAtt)/2,1);

                $totalScore = $gspeedScore + $gqualityScore + $gattitudeScore + $employCommentAtt + $employCommentSpeed + $employCommentQuality;
                $totalScore = number_format($totalScore/6,1);
            }elseif($gattitudeScore>0 || $gspeedScore>0 || $gqualityScore>0){
                $speedScore = $gspeedScore;
                $qualityScore = $gqualityScore;
                $attitudeScore = $gattitudeScore;
                $totalScore = $speedScore + $qualityScore + $attitudeScore;
                $totalScore = number_format($totalScore/3,1);
            }elseif($employCommentAtt>0 || $employCommentSpeed>0 || $employCommentQuality>0){
                $speedScore = $employCommentSpeed;
                $qualityScore = $employCommentQuality;
                $attitudeScore = $employCommentAtt;
                $totalScore = $speedScore + $qualityScore + $attitudeScore;
                $totalScore = number_format($totalScore/3,1);
            }else{
                $totalScore = 0;
            }

            //店铺所有者联系方式信息
            $contactInfo = UserDetailModel::where('uid', $shopInfo['uid'])->select('mobile', 'mobile_status', 'qq', 'qq_status', 'wechat', 'wechat_status')->first();
            $this->theme->setUserId($shopInfo['uid']);
        } else {
            abort('404');
        }

        $domain = \CommonClass::getDomain();
        //查询店铺收藏状态
        $isFocus = ShopFocusModel::shopFocusStatus($shopId);
        $this->theme->setTitle('店铺评价');

        $data = array(
            'domain' => $domain,
            'shopInfo' => $shopInfo,
            'authUser' => $authUser,
            'commentInfo' => $evaluateInfo,
            'goodCateInfo' => $goodCateInfo,
            'serviceCateInfo' => $serviceCateInfo,
            'goodsCount' => $goodsCount,
            'serviceCount' => $serviceCount,
            'speedScore' => $speedScore,
            'qualityScore' => $qualityScore,
            'attitudeScore' => $attitudeScore,
            'shopId' => $shopId,
            'emailStatus' => $emailStatus,
            'isFocus' => $isFocus,
            'contactInfo' => $contactInfo,
            'avg_score' => $totalScore,

        );

        return $this->theme->scope('shop.rated', $data)->render();
    }


    //所有服务
    public function serviceAll(Request $request, $shopId)
    {
        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);
        $shopInfo = ShopModel::where('id', $shopId)->select('uid')->first();
        if (!empty($shopInfo)) {
            $this->theme->setUserId($shopInfo['uid']);
        } else {
            abort('404');
        }
        $cateInfo = GoodsModel::join('cate', 'goods.cate_id', '=', 'cate.id')
            ->where(['goods.type' => 2, 'goods.status' => 1, 'goods.shop_id' => $shopId])
            ->select('goods.cate_id', 'cate.name')->distinct()->orderBy('goods.created_at', 'desc')->get()->toArray();
        if (!empty($cateInfo)) {
            foreach ($cateInfo as $k => $v) {
                $num = GoodsModel::where(['cate_id' => $v['cate_id'], 'type' => 2, 'status' => 1, 'shop_id' => $shopId])->count();
                $cateInfo[$k]['num'] = $num;
            }
        }
        $serviceInfo = GoodsModel::join('cate', 'goods.cate_id', '=', 'cate.id')
            ->where(['goods.type' => 2, 'goods.status' => 1, 'goods.shop_id' => $shopId]);
        if ($request->get('cate_id')) {
            $serviceInfo = $serviceInfo->where('goods.cate_id', intval($request->get('cate_id')));
        }
        if($request->get('keywords')){
            $serviceInfo = $serviceInfo->where('goods.title','like','%'.$request->get('keywords').'%');
        }
        $serviceInfo = $serviceInfo->select('goods.id', 'goods.title', 'goods.cover', 'goods.cash', 'cate.name')
            ->orderBy('goods.created_at', 'desc')
            ->paginate(12);
        $domain = \CommonClass::getDomain();
        $this->theme->setTitle('店铺服务');
        $data = [
            'cateInfo' => $cateInfo,
            'serviceInfo' => $serviceInfo,
            'domain' => $domain,
            'shopId' => $shopId
        ];
        return $this->theme->scope('shop.serviceall', $data)->render();
    }

    /**
     * 获取二级行业分类
     *
     * @param $cateId
     * @return string
     */
    public function getSecondCate($cateId)
    {
        $data = TaskCateModel::select('id', 'name')->where('pid', $cateId)->get();

        $html = '';
        if (!empty($data)) {
            foreach ($data as $item) {
                $html .= "<option value='" . $item->id . "'>" . $item->name . "</option>";
            }
        } else {
            $html = "<option value=''>没有了</option>";
        }

        return \CommonClass::formatResponse('success', 200, $html);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 开启关闭店铺
     */
    public function ajaxUpdateShop(Request $request)
    {
        $shopId = $request->get('id');
        $shopInfo = ShopModel::where('id', $shopId)->first();
        $data = [
            'uid' => $shopInfo->uid,
            'shopId' => $shopId
        ];
        if ($shopInfo['status'] == 1) {
            $res = DB::transaction(function () use ($data) {
                $shopInfo = ShopModel::where('id', $data['shopId'])->update(['status' => 2, 'updated_at' => date('Y-m-d H:i:s', time())]);
                $userDetail = UserDetailModel::where('uid', $data['uid'])->update(['shop_status' => 2, 'updated_at' => date('Y-m-d H:i:s', time())]);
                $auditInfo = GoodsModel::where(['shop_id' => $data['shopId'], 'status' => 0])->get();
                if (!empty($auditInfo)) {
                    GoodsModel::where(['shop_id' => $data['shopId'], 'status' => 0])->update(['status' => 3]);
                }
                $salesInfo = GoodsModel::where(['shop_id' => $data['shopId'], 'status' => 1])->get();
                if (!empty($salesInfo)) {
                    GoodsModel::where(['shop_id' => $data['shopId'], 'status' => 1])->update(['status' => 2]);
                }
                return true;
            });

        } else {
            $res = DB::transaction(function () use ($data) {
                $shopInfo = ShopModel::where('id', $data['shopId'])->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s', time())]);
                $userDetail = UserDetailModel::where('uid', $data['uid'])->update(['shop_status' => 1, 'updated_at' => date('Y-m-d H:i:s', time())]);
                return true;
            });

        }
        if ($res) {
            return response()->json(['code' => 1, 'message' => '修改成功']);
        } else {
            return response()->json(['code' => 0, 'message' => '修改失败！']);
        }
    }

    /**
     * 获取店铺背景图片信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxUpdatePic(Request $request)
    {
        $file = $request->file('back');

        $result = \FileClass::uploadFile($file, 'user', array('jpg', 'png', 'jpeg', 'bmp', 'png'));
        $result = json_decode($result, true);
        $backgroundurl = $result['data']['url'];
        $domain = \CommonClass::getDomain();
        return response()->json(['path' => $backgroundurl, 'domain' => $domain]);
    }


    /**
     * 修改店铺背景图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxUpdateBack(Request $request)
    {
        $uid = Auth::id();
        $backgroundurl = $request->get('src');
        $data = array(
            'shop_bg' => $backgroundurl,
            'updated_at' => date('Y-m-d H:i:s', time())
        );
        $result = ShopModel::where('uid', $uid)->update($data);
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
        $id = intval($request->get('id'));
        $result = ShopModel::where('id', $id)->update(['shop_bg' => '', 'updated_at' => date('Y-m-d H:i:s', time())]);
        $domain = \CommonClass::getDomain();
        return response()->json(['domain' => $domain]);
    }

    /**
     * 购买服务
     *
     * @param $cateId
     * @return string
     */
    public function buyService($id)
    {
        $all_cate = TaskCateModel::findAllCache();
        $all_cate = \CommonClass::keyBy($all_cate, 'id');

        //查询当前服务的信息
        $service = GoodsModel::where('id', $id)->where('type', 2)->first();

        //判断当前用户时候是服务的发布者
        $is_owner = 0;
        if ($service['uid'] == Auth::user()['id']) {
            $is_owner = 1;
        }

        if ($is_owner == 0 && $service['status'] != 1)
            return redirect()->back()->with(['error' => '当前服务未上架！']);

        //查询服务锁对应的employ_id
        $employ_id = EmployGoodsModel::where('service_id', $id)->lists('employ_id')->toArray();
        //速度平均分
        $avgSpeed = round(EmployCommentsModel::whereIn('employ_id', $employ_id)->where('to_uid',$service['uid'])->avg('speed_score'), 1);
        //质量平均分
        $avgQuality = round(EmployCommentsModel::whereIn('employ_id', $employ_id)->where('to_uid',$service['uid'])->avg('quality_score'), 1);
        //态度平均分
        $avgAttitude = round(EmployCommentsModel::whereIn('employ_id', $employ_id)->where('to_uid',$service['uid'])->avg('attitude_score'), 1);
        $avgAll = round(($avgSpeed + $avgQuality + $avgAttitude) / 3, 1);
        //查询评价信息
        $comments = EmployCommentsModel::serviceComments($employ_id);
        $comments_toArray = $comments->toArray();
        //处理评价的综合评分
        foreach ($comments_toArray['data'] as $k => $v) {
            $comments_toArray['data'][$k]['total_score'] = round(($v['speed_score'] + $v['quality_score'] + $v['attitude_score']) / 3, 1);
        }

        //查询店铺中的其他商品和服务
        $other_service = GoodsModel::where('shop_id', $service['shop_id'])->where('status', 1)->where('type', 2)->where('id', '<>', $id)->get();

        $this->theme->set('SHOPID', $service['shop_id']);
        //修改购买服务页面的title
        $this->theme->setTitle('购买服务');
        if (!empty($service['title']))
            $this->theme->setTitle($service['title']);

        $this->theme->set('keywords', $service['seo_keyword']);
        $this->theme->set('keywords', $service['seo_keyword']);
        $this->theme->set('description', $service['seo_desc']);
        $this->theme->setUserId($service['uid']);
        //查询店铺被收藏的状态
        $isFocus = ShopFocusModel::shopFocusStatus($service['shop_id']);
        //服务的浏览次数加1
        GoodsModel::where('id', $id)->increment('view_num', 1);
        //服务的抽佣比率
        $rate = \CommonClass::getConfig('employ_percentage');
        //店铺所有者联系方式信息
        $contactInfo = UserDetailModel::where('uid', $service['uid'])
            ->select('mobile', 'mobile_status', 'qq', 'qq_status', 'wechat', 'wechat_status')->first();
        $domain = url();
        $view = [
            'service' => $service,
            'other_service' => $other_service,
            'all_cate' => $all_cate,
            'comments' => $comments,
            'comments_toArray' => $comments_toArray,
            'avgAll' => $avgAll,
            'contact' => Theme::get('is_IM_open'),
            'avgSpeed' => $avgSpeed,
            'avgQuality' => $avgQuality,
            'avgAttitude' => $avgAttitude,
            'is_owner' => $is_owner,
            'isFocus' => $isFocus,
            'rate' => $rate,
            'domain' => $domain,
            'contactInfo' => $contactInfo
        ];

        return $this->theme->scope('shop.buyservice', $view)->render();
    }

    /**
     * ajax获取评论
     * @param $id
     */
    public function ajaxServiceComments(Request $request)
    {
        $this->initTheme('ajaxpage');
        $id = $request->get('id');
        $data = $request->except('id');
        $type = isset($data['type']) ? $data['type'] : 0;

        //查询服务锁对应的employ_id
        $employ_id = EmployGoodsModel::where('service_id', $id)->lists('employ_id')->toArray();

        //查询评价信息
        $comments = EmployCommentsModel::serviceComments($employ_id, $data);

        $comments_toArray = $comments->toArray();
        //处理评价的综合评分
        foreach ($comments_toArray['data'] as $k => $v) {
            $comments_toArray['data'][$k]['total_score'] = round(($v['speed_score'] + $v['quality_score'] + $v['attitude_score']) / 3, 1);
        }

        $view = [
            'comments' => $comments,
            'comments_toArray' => $comments_toArray,
            'type' => $type,
            'id' => $id
        ];
        return $this->theme->scope('shop.ajaxservicecomments', $view)->render();
    }

    /**
     * 成功案例详情页
     * @param $id 成功案例id
     * @return mixed
     */
    public function successDetail($id)
    {
        $id = intval($id);
        $successCase = SuccessCaseModel::getSuccessInfoById($id);
        //该案例浏览数加1
        SuccessCaseModel::where('id',$id)->update(array('view_count' => $successCase->view_count + 1));
        //查询该店铺其他案例
        $successCaseList = SuccessCaseModel::getOtherSuccessByUid($successCase->uid, $id, 5);
        $shopId = ShopModel::getShopIdByUid($successCase->uid);
        $this->theme->set('SHOPID', $shopId);
        $this->theme->setUserId($successCase->uid);
        $data = array(
            'success_case' => $successCase,
            'list' => $successCaseList
        );
        $this->theme->setTitle('成功案例');
        return $this->theme->scope('shop.successdetail', $data)->render();
    }

    //店铺对外页面
    public function shopOutside($shopId)
    {
        $shopId = intval($shopId);
        $this->theme->set('SHOPID', $shopId);
        //根据店铺id获取店铺信息
        $shopInfo = ShopModel::getShopInfoById($shopId);
        $workInfo = $goodsInfo = $evaluateInfo = [];
        if (!empty($shopInfo)) {
            //好评率计算
            if ($shopInfo['total_comment']) {
                $shopInfo['percent'] = $shopInfo['good_comment'] / $shopInfo['total_comment'];
                if ($shopInfo['percent']) {
                    $shopInfo['percent'] = number_format($shopInfo['percent'], 1) * 100;
                } else {
                    $shopInfo['percent'] = 100;
                }
            } else {
                $shopInfo['percent'] = 100;
            }
            //店铺描述处理
            $shopInfo['shop_desc'] = htmlspecialchars_decode($shopInfo['shop_desc']);
            //店铺累计服务
            $shopInfo['serviceNum'] = GoodsModel::where(['shop_id' => $shopId, 'status' => 1])->select('id')->sum('sales_num');
            //查询用户的绑定关系
            $authUser = AuthRecordModel::getAuthByUserId($shopInfo['uid']);
            //查询用户的邮箱绑定关系
            $UserModel = UserModel::where('id', $shopInfo['uid'])->select('email_status')->first();
			if($UserModel){
				$emailStatus=$UserModel->email_status;
			}else{
				$emailStatus=null;
			}
            //获取作品信息
            $workInfo = GoodsModel::select('goods.id', 'goods.title', 'goods.cover', 'goods.cash', 'cate.name')
                ->join('cate', 'goods.cate_id', '=', 'cate.id')
                ->where(['goods.shop_id' => $shopId, 'goods.type' => 1, 'goods.status' => 1])
                ->orderBy('goods.created_at', 'desc')
                ->limit(4)->get()->toArray();
            //获取服务信息
            $goodsInfo = GoodsModel::select('goods.id', 'goods.title', 'goods.cover', 'goods.cash', 'cate.name')
                ->join('cate', 'goods.cate_id', '=', 'cate.id')
                ->where(['goods.shop_id' => $shopId, 'goods.type' => 2, 'goods.status' => 1])
                ->orderBy('goods.created_at', 'desc')
                ->limit(4)->get()->toArray();
            //获取交易评价信息
            $goodsComment = GoodsCommentModel::join('goods', 'goods_comment.goods_id', '=', 'goods.id')
                ->join('users', 'goods_comment.uid', '=', 'users.id')
                ->join('user_detail', 'users.id', '=', 'user_detail.uid')
                ->where('goods.shop_id', $shopId)
                ->select('goods_comment.*', 'goods.type as sort', 'goods.title', 'goods.desc', 'goods.cash', 'users.name', 'user_detail.avatar', 'goods.id as goodId')
                ->orderBy('goods_comment.created_at', 'desc')
                ->limit(3)->get()->toArray();
            if (!empty($goodsComment)) {
                foreach ($goodsComment as $k => $v) {
                    $goodsComment[$k]['total_score'] = number_format(($v['speed_score'] + $v['quality_score'] + $v['attitude_score']) / 3, 1);
                    $goodsComment[$k]['desc'] = htmlspecialchars_decode($goodsComment[$k]['desc']);
                }
                $evaluateInfo = $goodsComment;
            }
            //获取案例信息
            $caseInfo = SuccessCaseModel::join('cate', 'success_case.cate_id', '=', 'cate.id')
                ->where('success_case.uid', $shopInfo['uid'])
                ->select('success_case.id', 'success_case.title', 'success_case.pic', 'success_case.view_count', 'cate.name')
                ->orderBy('success_case.created_at', 'desc')
                ->limit(4)->get()->toArray();
            //店铺所有者联系方式信息
            $contactInfo = UserDetailModel::where('uid', $shopInfo['uid'])->select('mobile', 'mobile_status', 'qq', 'qq_status', 'wechat', 'wechat_status')->first();
            $this->theme->setTitle($shopInfo['shop_name']);

            //获取店铺装修轮播图信息
            $carouselIds = json_decode($shopInfo['banner_rules'],true);
            $carouselPics = AttachmentModel::whereIn('id',$carouselIds)->select('url')->get()->toArray();

        } else {
            abort('404');
        }
        $domain = \CommonClass::getDomain();
        //查询店铺被收藏的状态
        $isFocus = ShopFocusModel::shopFocusStatus($shopId);

        $domainConfig = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        $arr = [
            'domain'  => $domainConfig->rule,
            'shop_id' => $shopId,
            'is_open' => $shopInfo['status'],
            'uid'     => $shopInfo['uid'],
        ];
        $arrStr = json_encode($arr);
        $img = QrCode::size('280')->generate($arrStr);

        $data = array(
            'domain' => $domain,
            'shopInfo' => $shopInfo,
            'authUser' => $authUser,
            'workInfo' => $workInfo,
            'goodsInfo' => $goodsInfo,
            'caseInfo' => $caseInfo,
            'commentInfo' => $evaluateInfo,
            'shopId' => $shopId,
            'emailStatus' => $emailStatus,
            'isFocus' => $isFocus,
            'contactInfo' => $contactInfo,
            'carouselPics' => $carouselPics,
            'central_ad' => $shopInfo['central_ad'],
            'footer_ad' => $shopInfo['footer_ad'],
            'img' => $img
        );
        return $this->theme->scope('shop.shopoutside', $data)->render();
    }


    /**
     * 收藏店铺
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxAdd(Request $request)
    {
        $uid = Auth::id();
        $shopId = $request->get('shop_id');
        $data = [
            'uid' => $uid,
            'shop_id' => $shopId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $res = ShopFocusModel::create($data);
        if ($res) {
            return response()->json(['code' => 1]);
        } else {
            return response()->json(['code' => 2]);
        }
    }

    /**
     * 获取最新导航信息
     * @param Request $request
     * @return mixed
     */
    public function navList(Request $request){
        $shopId = intval($request->get('shopId'));
        $shopInfo = ShopModel::where('id',$shopId)->select('nav_rules','uid')->first();
        if(empty($shopInfo)){
            return false;
        }else{
            if($shopInfo['nav_rules']){
                $nav_rules = json_decode($shopInfo['nav_rules'],true);
                foreach($nav_rules as $k=>$v){
                    switch($v['id']){
                        case 1:
                            if($v['status']){
                                if($shopInfo['uid'] == Auth::id()){
                                    $nav_rules[$k]['url'] = '/shop/manage/'.$shopId;
                                }else{
                                    $nav_rules[$k]['url'] = '/shop/'.$shopId;
                                }
                            }
                            break;
                        case 2:
                            if($v['status']){
                                $nav_rules[$k]['url'] = '/shop/work/'.$shopId;
                            }
                            break;
                        case 3:
                            if($v['status']){
                                $nav_rules[$k]['url'] = '/shop/serviceAll/'.$shopId;
                            }
                            break;
                        case 4:
                            if($v['status']){
                                $nav_rules[$k]['url'] = '/shop/successStory/'.$shopId;
                            }
                            break;
                        case 5:
                            if($v['status']){
                                $nav_rules[$k]['url'] = '/shop/rated/'.$shopId;
                            }
                            break;
                        case 6:
                            if($v['status']){
                                $nav_rules[$k]['url'] = '/shop/about/'.$shopId;
                            }
                            break;
                    }
                }
            }else{
                $nav_rules = [
                    ["id" => 1,"name" => "首页","status" => true],
                    ["id" => 2,"name" => "作品","status" => true,"url" => '/shop/work/'.$shopId],
                    ["id" => 3,"name" => "服务","status" => true,"url" => '/shop/serviceAll/'.$shopId],
                    ["id" => 4,"name" => "成功案例","status" => true,"url" => '/shop/successStory/'.$shopId],
                    ["id" => 5,"name" => "交易评价","status" => true,"url" => '/shop/rated/'.$shopId],
                    ["id" => 6,"name" => "关于我们","status" => true,"url" => '/shop/about/'.$shopId]
                ];
                if($shopInfo['uid'] == Auth::id()){
                    $nav_rules[0]['url'] = '/shop/manage/'.$shopId;
                }else{
                    $nav_rules[0]['url'] = '/shop/'.$shopId;
                }
            }

            return $nav_rules;
        }
    }

}
