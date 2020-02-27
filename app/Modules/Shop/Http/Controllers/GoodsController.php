<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Employ\Models\UnionRightsModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Shop\Models\ShopFocusModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
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
     * 发布方案
     * @return mixed
     */
    public function pubGoods()
    {
        $this->initTheme('shopenquiry');
        $this->theme->setTitle('发布方案');
        if(!Auth::check()){
            return redirect("/login");
        }
        //判断是否开通店铺
        $findShop=ShopModel::where("uid",Auth::user()->id)->where("status",1)->first();

        if(!$findShop){
            return redirect("/user/shop")->with(["message"=>"你还未申请开通店铺,请先开通店铺再上传方案"]);
        }
        //.上传方案前如果是个人店铺，判断用户是否进行实名认证->无认证，先认证，再上传
        if(isset($findShop['type'])&&!empty($findShop['type'])){
            if($findShop['type']==1){

                //获取个人认证信息
                $realnameInfo = RealnameAuthModel::where('uid',Auth::user()->id)->first();
               if(empty($realnameInfo)){
                   return redirect("/user/realnameAuth")->with(["message"=>"你还未进行实名认证,请先进行实名认证再上传方案"]);
               }
            }
        }

        $cate = TaskCateModel::whereIn('type',[1,2,3,4])->get()->toArray();
        $cate = \CommonClass::setArrayKey($cate,'type',2);
        //应用领域
        $field = in_array(1,array_keys($cate)) ? $cate[1] : [];
        //开发平台
        $plate = in_array(3,array_keys($cate)) ? $cate[3] : [];
        $this->theme->set('ALL_PLATE',$plate);
        //交付形式
        $delivery = in_array(4,array_keys($cate)) ? $cate[4] : [];
        $this->theme->set('DELIVERY',$delivery);
        $typeArr = [
            1 => '方案销售',
            // 2 => '参考设计',
            // 2=> '定制开发',
        ];
        /*$ad = AdTargetModel::getAdByTypeId(2);
        AdTargetModel::addViewCountByCode('GOODSDETAIL');*/
        $ad = AdTargetModel::getAdByCodePage('GOODSDETAIL');
        $views = [
            'field'      => $field,
            'plate'      => $plate,
            'delivery'   => $delivery,
            'type_arr'   => $typeArr,
            'ad'         => $ad,
        ];
        return $this->theme->scope('shop.pubgoods', $views)->render();
    }

    /**
     * 保存方案
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function savePubGoods(Request $request)
    {
        $data = $request->except('_token');
        $deliveryCateIdStr = '';
        if(isset($data['delivery_cate_id']) && $data['delivery_cate_id']){
            if(is_array($data['delivery_cate_id'])){
                $deliveryCateIdStr = implode(',',$data['delivery_cate_id']);
            }else{
                $deliveryCateIdStr = $data['delivery_cate_id'];
            }
        }

        $uid = Auth::id();
        $shopId = 0;
        $shop = ShopModel::where('uid',$uid)->first();
        if($shop){
            $shopId = $shop->id;
        }
        $arr = [
            'title'                 => $data['title'],
            'cate_id'               => isset($data['cate_id'])?$data['cate_id']:1,
            'type'                  => isset($data['type'])?$data['type']:1,
            'is_customized'         => isset($data['type']) && $data['type'] == 1 && isset($data['is_customized']) ? $data['is_customized'] : 0,
            'ide_cate_id'           => isset($data['ide_cate_id']) && $data['ide_cate_id'] ? $data['ide_cate_id'] : '',
            'ide_desc'              => isset($data['ide_desc']) && $data['ide_desc'] ? $data['ide_desc'] : '',
            'cash'                  => isset($data['type']) && $data['type'] == 1 && isset($data['cash']) ? $data['cash'] : '0.00',
            'application_scene'     => $data['application_scene'],
            'performance_parameter' => isset($data['performance_parameter'])?$data['performance_parameter']:'',
            'delivery_cate_id'      => $deliveryCateIdStr,
            'freight'               =>isset($data['freight'])?$data['freight']:0,
            'status'                => 0,
            'desc'                  => isset($data['desc']) ? $data['desc'] : '',
            'is_recommend'          => 0,
            'cover'                 => isset($data['is_default']) && $data['is_default'] ? $data['is_default'] : '',
            'uid'                   => $uid,
            'shop_id'               => $shopId,
            'sort'                  => isset($data['sort']) && $data['sort'] ? $data['sort'] : 0,
        ];

        $fileIds = isset($data['file_ids']) && $data['file_ids'] ? $data['file_ids'] : [];

        $gooddoc = isset($data['GoodsDoc_ids']) && $data['GoodsDoc_ids'] ? $data['GoodsDoc_ids'] : [];

        /*$times = GoodsModel::where('uid',Auth::id())->where('created_at','>=',date('Y-m-d 00:00:00'))->where('created_at','<=',date('Y-m-d H:i:s'))->count();
        $vipConfig = UserVipConfigModel::getConfigByUid(Auth::id());
        $maxTimes = $vipConfig['scheme_num'];
        if($times >= $maxTimes){
            return redirect()->back()->with(array('message' => '今日发布方案次数使用完毕'));
        }*/
        $res = GoodsModel::saveGoodsInfo($arr,$fileIds,$gooddoc,$request);
        if($res['id']){
            UserModel::sendfreegrant($uid,6);//发布方案成功自动发放
            return redirect('/shop/goodsuccess/'.$res['id']);
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }

    /**
     * 成功发布方案
     */
    public function goodsuccess($id)
    {
        $this->theme->setTitle('发布方案');
        $id = intval($id);
        //验证任务是否是状态2
        $good = GoodsModel::where('id',$id)->first();

        if($good['status']!=0){
            return redirect()->back()->with(['error'=>'数据错误，当前任务不处于等待审核状态！']);
        }
        $view = [
            'id' => $id,
        ];

        return $this->theme->scope('shop.goodsuccess',$view)->render();
    }


    /**
     * 文件上传控制
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileUpload(Request $request)
    {
        $file = $request->file('file');
        $relation_type = $request->get('relation_type');
        $result = array();
        //判断关联对象是否存在
        if(!$relation_type){
            $result['status'] = 'failure';
            $result['message'] = '非法操作';
            return $result;
        }

        //判断文件是否上传成功
        $result1 = json_decode(\FileClass::uploadFile($file,$relation_type),true);
        if(!is_array($result1['data']) || $result1['code'] != 200) {
            $result['status'] = 'failure';
            $result['message'] = $result1['message'];
            return $result;
        }

        $attachment = AttachmentModel::create([
            'name'       => $result1['data']['name'],
            'type'       => $result1['data']['type'],
            'size'       => $result1['data']['size'],
            'url'        => $result1['data']['url'],
            'disk'       => $result1['data']['disk'],
            'user_id'    => $result1['data']['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $data = $result1['data'];
        $data['id'] = $attachment['id'];
        $html = AttachmentModel::getAttachmentGoodsCoverHtml($data);//生成html
        $result['data'] = $data;
        $result['html'] = $html;
        $result['status'] = 'success';
        return $result;
    }

    /**
     * 文件doc上传控制
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gooddocUpload(Request $request)
    {
        $file = $request->file('file');
        $relation_type = $request->get('relation_type');
        $result = array();
        //判断关联对象是否存在
        if(!$relation_type){
            $result['status'] = 'failure';
            $result['message'] = '非法操作';
            return $result;
        }

        //判断文件是否上传成功
        $result1 = json_decode(\FileClass::uploadFile($file,$relation_type),true);
        if(!is_array($result1['data']) || $result1['code'] != 200) {
            $result['status'] = 'failure';
            $result['message'] = $result1['message'];
            return $result;
        }

        $attachment = AttachmentModel::create([
            'name'       => $result1['data']['name'],
            'type'       => $result1['data']['type'],
            'size'       => $result1['data']['size'],
            'url'        => $result1['data']['url'],
            'disk'       => $result1['data']['disk'],
            'user_id'    => $result1['data']['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $data = $result1['data'];
        $data['id'] = $attachment['id'];
        $html = AttachmentModel::getAttachmentGoodsDocHtml($data);//生成html
        $result['data'] = $data;
        $result['html'] = $html;
        $result['status'] = 'success';
        return $result;
    }

    /**
     * 附件删除
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileDelete(Request $request)
    {
        $result = AttachmentModel::destroy($request->get('id'));
        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => '删除失败！']);
        }
        return response()->json(['errCode' => 1, 'errMsg' => '删除成功！']);
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




}
