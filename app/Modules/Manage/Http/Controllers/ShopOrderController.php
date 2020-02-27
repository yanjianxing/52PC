<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ProgrammeOrderFollowModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Shop\Models\ProgrammeOrderSubModel;
use App\Modules\Shop\Models\ProgrammeRightsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserModel;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopOrderController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('店铺管理');
        $this->theme->set('manageType', 'auth');
    }

    /**
     * 方案订单管理列表
     *
     * @param Request $request
     * @return mixed
     */
    public function orderList(Request $request)
    {
        $merge = $request->all();
        $orderList = ProgrammeOrderModel::where("programme_order.status","!=",0);
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $orderList = $orderList->where(function($query) use ($keywords){
                $query->where('programme_order.id', 'like', '%' . $keywords . '%')
                    ->orWhere('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_order.nickname', 'like', '%' . $keywords . '%');

            });
        }
        if (($request->get('status') || $request->get('status') == '0') && $request->get('status') != -1) {
            $orderList = $orderList->where('programme_order.status', $request->get('status'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $orderList = $orderList->where('programme_order.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $orderList = $orderList->where('programme_order.created_at', '<=', $end);
        }
        $by = $request->get('by') ? $request->get('by') : 'programme_order.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $orderList = $orderList->leftJoin('goods', 'goods.id', '=', 'programme_order.programme_id')
            ->leftJoin("user_detail","programme_order.uid","=","user_detail.uid")
            ->leftJoin('shop','shop.id','=','goods.shop_id')
            ->select('programme_order.*', 'goods.title','goods.shop_id','user_detail.mobile as u_mobile','shop.shop_name')
            ->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'merge' => $merge,
            'list'  => $orderList,
        );
        $this->theme->setTitle('方案订单');
        return $this->theme->scope('manage.shop.orderlist', $data)->render();
    }

    /**
     * 方案询价列表
     * @param Request $request
     * @return mixed
     */
    public function enquiryList(Request $request)
    {
        $merge = $request->all();
        $orderList = ProgrammeEnquiryMessageModel::where('programme_enquiry_message.type',1);
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $orderList = $orderList->where(function($query) use ($keywords){
                $query->where('programme_enquiry_message.id', 'like', '%' . $keywords . '%')
                    ->orWhere('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_enquiry_message.nickname', 'like', '%' . $keywords . '%');

            });
        }
        if ($request->get('consult_type')) {
            $orderList = $orderList->where('programme_enquiry_message.consult_type', $request->get('consult_type'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $orderList = $orderList->where('programme_enquiry_message.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $orderList = $orderList->where('programme_enquiry_message.created_at', '<=', $end);
        }
        $by = $request->get('by') ? $request->get('by') : 'programme_enquiry_message.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $orderList = $orderList->leftJoin('goods', 'goods.id', '=', 'programme_enquiry_message.programme_id')
                     ->leftJoin("users as ud","programme_enquiry_message.uid","=","ud.id")
                     ->leftJoin("users as ud1","programme_enquiry_message.consultant_id","=","ud1.id")
                     // ->leftJoin("shop","programme_enquiry_message.consultant_id","=","shop.uid")
            ->select('programme_enquiry_message.*', 'goods.title','ud.name as consultant_name',"ud.mobile as ud_mobile","ud1.name as nickname"/*,'shop.shop_name'*/)//,'ud.nickname as consultant_name',"ud.mobile","ud."
            ->orderBy($by, $order)->paginate($paginate);
        $data = array(
            'merge' => $merge,
            'list'  => $orderList,
        );
        $this->theme->setTitle('方案询价');
        return $this->theme->scope('manage.shop.enquirylist', $data)->render();
    }

    /**
     * 方案留言
     * @param Request $request
     * @return mixed
     */
    public function messageList(Request $request)
    {
        $merge = $request->all();
        $orderList = ProgrammeEnquiryMessageModel::where('programme_enquiry_message.type',2);
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $orderList = $orderList->where(function($query) use ($keywords){
                $query->where('programme_enquiry_message.id', 'like', '%' . $keywords . '%')
                    ->orWhere('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_enquiry_message.nickname', 'like', '%' . $keywords . '%');

            });
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $orderList = $orderList->where('programme_enquiry_message.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $orderList = $orderList->where('programme_enquiry_message.created_at', '<=', $end);
        }
        $by = $request->get('by') ? $request->get('by') : 'programme_enquiry_message.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $orderList = $orderList->leftJoin('goods', 'goods.id', '=', 'programme_enquiry_message.programme_id')
            // ->leftJoin("shop","programme_enquiry_message.consultant_id","=","shop.uid")
            ->select('programme_enquiry_message.*', 'goods.title'/*,'shop.shop_name'*/)
            ->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'merge' => $merge,
            'list'  => $orderList,
        );
        $this->theme->setTitle('方案询价');
        return $this->theme->scope('manage.shop.messagelist', $data)->render();
    }


    /**
     * 方案订单详情
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function shopOrderInfo($id)
    {
        $id = intval($id);
        $orderInfo = ProgrammeOrderModel::programmeOrderInfo($id);
        if(!$orderInfo){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        $district = DistrictModel::whereIn('id',[$orderInfo->province,$orderInfo->city,$orderInfo->area])->select('id','name')->get()->toArray();
        $district = \CommonClass::setArrayKey($district,'id');
        $subOrder = ProgrammeOrderSubModel::where('programme_order_sub.order_id',$id)->leftJoin('goods','goods.id','=','programme_order_sub.programme_id')->leftJoin('attachment','attachment.id','=','goods.cover')->select('programme_order_sub.*','goods.title','attachment.url')->get()->toArray();

        $data = array(
            'order_info' => $orderInfo,
            'district'   => $district,
            'order_sub'  => $subOrder,
            'id'         => $id
        );
        $this->theme->setTitle('方案订单详情');
        return $this->theme->scope('manage.shop.orderinfo', $data)->render();
    }

    /**
     * 方案维权列表
     * @param Request $request
     * @return mixed
     */
    public function rightsList(Request $request)
    {
        $merge = $request->all();
        $rightsList = ProgrammeRightsModel::where('programme_order_rights.status','!=',-1);
        //维权人
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $rightsList = $rightsList->where(function($query) use ($keywords){
                $query->where('programme_order_rights.id', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_order_rights.goods_name', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_order_rights.deal_name', 'like', '%' . $keywords . '%')
                    ->orWhere('users.name', 'like', '%' . $keywords . '%')
                    ->orWhere('to_user.name', 'like', '%' . $keywords . '%');

            });
        }
        //维权类型
        $typeArr = [
            1 => '欺骗'
        ];
        if ($request->get('type')) {
            $rightsList = $rightsList->where('programme_order_rights.type', $request->get('type'));
        }
        //维权状态
        $statusArr = [
            1 => '待处理',
            2 => '已处理',
            3 => '维权无效'
        ];
        if ($request->get('status')) {
            $rightsList = $rightsList->where('programme_order_rights.status', $request->get('status'));
        }

        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $rightsList = $rightsList->where('programme_order_rights.created_at', '>=',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $rightsList = $rightsList->where('programme_order_rights.created_at', '<=',$end);
        }
        $by = $request->get('by') ? $request->get('by') : 'programme_order_rights.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $rightsList = $rightsList->leftJoin('users', 'users.id', '=', 'programme_order_rights.from_uid')->leftJoin('users as to_user', 'to_user.id', '=', 'programme_order_rights.to_uid')
            ->select('programme_order_rights.*', 'users.name','to_user.name as to_name')
            ->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'merge'       => $merge,
            'rights_list' => $rightsList,
            'typeArr'     => $typeArr,
            'statusArr'   => $statusArr
        );
        $this->theme->setTitle('交易维权');
        return $this->theme->scope('manage.shop.rightslist', $data)->render();
    }

    /**
     * 维权详情
     * @param int $id  维权id
     * @return mixed
     */
    public function shopRightsInfo($id)
    {
        $id = intval($id);

        $rightsInfo = ProgrammeRightsModel::rightsInfoById($id);
        //维权类型
        $typeArr = [
            1 => '欺骗'
        ];
        //维权状态
        $statusArr = [
            1 => '待处理',
            2 => '已处理',
            3 => '维权无效'
        ];
        $data = array(
            'rights_info' => $rightsInfo,
            'typeArr'     => $typeArr,
            'statusArr'   => $statusArr
        );
        $this->theme->setTitle('交易维权详情');
        return $this->theme->scope('manage.shop.rightsinfo', $data)->render();
    }

    /**
     * 维权成功
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function ShopRightsSuccess(Request $request,$id)
    {
        $id = intval($id);
        $rightsInfo = ProgrammeRightsModel::find($id);
        if(!$rightsInfo || $rightsInfo->status != 1){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        //查询订单信息
        $orderInfo = ProgrammeOrderModel::where('id',$rightsInfo->programme_order_id)->first();
        //查询实际支付的金额
        $financial=FinancialModel::where("action",2)->where("related_id",$rightsInfo->programme_order_id)->first();
        //$subOrderInfo = ProgrammeOrderSubModel::where('id',$rightsInfo->sub_order_id)->first();
        $rightsInfo['cash'] = 0.00;
        if($orderInfo){
            $rightsInfo['cash'] = $orderInfo->price + $orderInfo->freight;
        }
        if($financial){
            $rightsInfo['cash']=$financial['cash']-$financial['coupon'];
        }
        $fromPrice = $request->get('from_price') ? $request->get('from_price') : 0.00;
        $toPrice = $request->get('to_price') ? $request->get('to_price') : 0.00;
        if(floatval($fromPrice) + floatval($toPrice) > $rightsInfo['cash']){
            return redirect()->back()->with(['error' => '分配金额错误']);

        }
        $dealName = $request->get('deal_name');
        $status = ProgrammeRightsModel::dealGoodsRights($id,$fromPrice,$toPrice,$dealName);
        if($status){
            //发送消息
            $userInfo = UserModel::find($rightsInfo['from_uid']);
            $user = [
                'uid'    => $userInfo->id,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username'      => $userInfo->name,
                'title'         => $rightsInfo->goods_name
            ];
            \MessageTemplateClass::sendMessage('task_right_deal',$user,$templateArr,$templateArr);
        }
        return redirect('/manage/shopRightsInfo/'.$id);

    }

    /**
     * 维权不成立
     * @param int $id 维权id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function ShopRightsFailure($id)
    {
        $id = intval($id);
        $rightsInfo = ProgrammeRightsModel::find($id);
        if(!$rightsInfo || $rightsInfo->status != 1){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        $res=DB::transaction(function()use($id,$rightsInfo){
            //修改维权状态
            ProgrammeRightsModel::where('id', $id)->update(['status' => 3,'handle_uid'=>$this->manager->id]);
            //修改订单状态
            $programmeOrder=ProgrammeOrderModel::where("id",$rightsInfo['programme_order_id'])->first();
            ProgrammeOrderModel::where("id",$rightsInfo['programme_order_id'])->update([
                'status'=>$programmeOrder['pre_status'],
            ]);
        });


        return redirect('/manage/shopRightsInfo/'.$id);
    }


    /**
     * 跟进记录
     * @param Request $request
     * @param int $id 商品id
     * @return mixed
     */
    public function orderFollow(Request $request,$id)
    {
        $id = intval($id);
        $type = $request->get('type') ? $request->get('type') : 1;
        $list = ProgrammeOrderFollowModel::where('order_id',$id)->where('type',$type)->paginate(10);
        $data = array(
            'id'   => $id,
            'list' => $list,
            'type' => $type
        );
        $this->theme->setTitle('跟进记录');
        return $this->theme->scope('manage.shop.orderfollow', $data)->render();
    }

    public function orderFollowAdd(Request $request)
    {
        $orderId = $request->get('order_id');
        $id = $request->get('id') ? $request->get('id') : '';
        $type = $request->get('type') ? $request->get('type') : 1;
        $name = $this->manager['username'];
        $info = [];
        if($id){
            $info = ProgrammeOrderFollowModel::find($id);
            $name = $info->manager_name;
        }
        $data = array(
            'info'       => $info,
            'order_id'   => $orderId,
            'type'       => $type,
            'id'         => $id,
            'name'       => $name
        );
        $this->theme->setTitle('跟进记录');
        return $this->theme->scope('manage.shop.orderfollowadd', $data)->render();
    }

    /**
     * 保存方案跟进
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveOrderFollow(Request $request)
    {
        $data = $request->except('_token');
        $data['time'] = date('Y-m-d H:i:s',strtotime(preg_replace('/([\x80-\xff]*)/i', '', $data['time'])));
        if(isset($data['id']) && !empty($data['id'])){
            $data['updated_at'] = date('Y-m-d H:i:s');
            $res = ProgrammeOrderFollowModel::where('id',$data['id'])->update($data);
        }else{
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $res = ProgrammeOrderFollowModel::create($data);
        }
        if($res){
            $url = '/manage/orderFollow/'.$data['order_id'].'?type='.$data['type'];
            return redirect($url)->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }

    public function orderFollowDelete($id)
    {
        $res = ProgrammeOrderFollowModel::where('id',$id)->delete();
        if($res){
            return redirect()->back()->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }

}
