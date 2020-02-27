<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Finance\Model\CashoutModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Order\Model\OrderModel;
use App\Modules\Order\Model\SubOrderModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\UserDepositModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\MessageReceiveModel;
use Guzzle\Http\Message\Response;
use Illuminate\Http\Request;
use Excel;
use Illuminate\Support\Facades\DB;
use Auth;

class FinanceController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'finance');
    }

    /**
     * 后台网站流水列表
     *
     * @param Request $request
     * @return mixed
     */
    public function financeList(Request $request)
    {
        $this->theme->setTitle('网站流水');
        $incomeArr = \CommonClass::incomeArr();
        $outcomeArr = \CommonClass::outcomeArr();
        $payType = \CommonClass::getPayType();
        $financeList = FinancialModel::select('financial.*', 'users.name as name')->leftJoin('users', 'financial.uid', '=', 'users.id');
        $action_result = array(5,6,7,8,11,12);
        $action_outresult = array(11);
        $action_inresult = array(5,6,7,8,12);
        $financeList = $financeList->whereIn('financial.action',$action_result);
        if ($request->get('action') == 3) {//支出
            $financeList = $financeList->whereIn('financial.action',$action_outresult);
        } elseif ($request->get('action') == 4) {//收入
            $financeList = $financeList->whereIn('financial.action', $action_inresult);
        }

        if($request->get('action')>0){
            $financeList = $financeList->where('financial.action',$request->get('action'));
        }
        if($request->get('financeid')){
            $financeList = $financeList->where('financial.id',$request->get('financeid'));
        }
        if($request->get('name')){
            $financeList = $financeList->where('users.name','like', '%'. trim($request->get('name')) . '%');
        }
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $financeList = $financeList->where('financial.created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $financeList = $financeList->where('financial.created_at', '<', $end);

        }
        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $financeList = $financeList->orderBy($by, $order)->paginate($paginate);
        

        $data = array(
            'start'         => $request->get('start'),
            'end'           => $request->get('end'),
            'income_arr'    => $action_inresult,
            'outcome_arr'   => $action_outresult,
            'pay_type'      => $payType,
            'action'        => $request->get('action'),
            'name'          => $request->get('name'),
            'financeid'     => $request->get('financeid'),
        );
        $data['finance'] = $financeList;
        $data['cashcount'] = FinancialModel::whereIn('action', $action_result)->sum('cash');
        $search = $request->all();
        $data['search'] = $search;
        $data['action_type'] = \CommonClass::getSiteFinanceAction();
        return $this->theme->scope('manage.financelist', $data)->render();
    }

    /**
     * 导出网站流水记录
     */
    public function financeListExport(Request $request)
    {
        $param = $request->all();
        $action_result = array(5,6,7,8,11,12);
        $action_outresult = array(11);
        $action_inresult = array(5,6,7,8,12);
        $incomeArr = \CommonClass::incomeArr();
        $FinanceAction = \CommonClass::getSiteFinanceAction();
        $finance = FinancialModel::select('financial.*', 'users.name')->leftJoin('users', 'financial.uid', '=', 'users.id')
            /*->where('financial.action', 3)->Where('financial.action', 4)*/;
        $finance = $finance->whereIn('financial.action',$action_result);
        if (!empty($param['start']) && $param['start'] != 'NaN') {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $finance = $finance->where('financial.created_at', '>', $start);
        }
        if (!empty($param['end']) && $param['end']!= 'NaN') {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $finance = $finance->where('financial.created_at', '<', $end);
        }
        if(intval($param['id']) > 0){
            $finance = $finance->where('financial.id',  $request->get('id'));
        }
        if(intval($param['action']) > 0){
            $finance = $finance->where('financial.action',  $request->get('action'));
        }
        if($param['name']){
            $finance = $finance->where('users.name',  $request->get('name'));
        }
        $data = [
            [
                '编号',
                '类型',
                '用户',
                '收入/支出',
                '金额',
                '时间',
            ]
        ];
        $i = 0;
        $result = $finance->get()->chunk(100);
        foreach ($result as $key => $chunk) {
            foreach ($chunk as $k => $v) {
                if(in_array($v->action,$action_outresult)){
                    $chunk[$k]['action_type'] = '支出';
                }else{
                    $chunk[$k]['action_type'] = '收入';
                }
                foreach ($FinanceAction as $keys => $values) {
                    if($v->action == $keys){
                        $v->action = $values;
                    }
                }
                $data[$i + 1] = [
                    $v->id,
                    $v->action,
                    $v->name,
                    $v->action_type,
                    '￥' . $v->cash . '元',
                    $v->created_at
                ];
                $i++;
            }
        }
        
        Excel::create(iconv('UTF-8', 'GBK', '网站流水记录'), function ($excel) use ($data) {
            $excel->sheet('score', function ($sheet) use ($data) {
                $sheet->rows($data);

            });
        })->export('xls');


    }


    /**
     * 用户流水导出
     *
     * @param Request $request
     *
     */
    public function userFinanceListExport(Request $request)
    {
        $param = $request->all();
        $userFinance = FinancialModel::whereRaw('1 = 1');
        $FinanceField = \CommonClass::getFinanceField($param['action']);
        $PayType = \CommonClass::getPayType();
        $allcomeArr = \CommonClass::allcomeArr();
        
        $by = !empty($param['by']) ? $param['by'] : 'id';
        $order = !empty($param['order']) ? $param['order'] : 'desc';
        $result = FinancialModel::getFinancialList($param,$by,$order,'');

        $actionArr = \CommonClass::getFinanceAction();
        $data = [
            $FinanceField
        ];
        $i = 0;
        foreach ($result as $chunk) {
            foreach ($chunk as $k => $v) {
                

                if(in_array($v->action,array_keys($actionArr))){
                    $v->action =  $actionArr[$v->action];
                }
                if(in_array($v->pay_type,array_keys($PayType))){
                    $v->pay_type =  $PayType[$v->pay_type];
                }
                if($v->protype==1){$v->protype = '注册推广';}elseif($v->protype==2){$v->protype = '发包推广';}else{$v->protype = '接包推广';}
                if($param['action'] == '3'){
                    $v->from = $v->balance;
                }elseif($param['action'] == '4'){
                    if($v->from==1){$v->from='PC';}else{$v->from='移动';}
                }
                switch ($param['action']) {
                    case '1':
                        $data[$i + 1] = [
                            $v->id,
                            $v->uname,
                            $v->utype==1 ? '服务商' : '雇主',
                            $v->gtitle,
                            '资金托管',
                            $v->status==1 ? '收入' : '支出',
                            '￥' . $v->cash .'元',
                            '￥' . $v->remainder .'元',
                            $v->created_at,
                        ];
                        break;
                    case '2':
                        $data[$i + 1] = [
                            $v->id,
                            $v->uname,
                            $v->status==1 ? '出售' : '购买',
                            $v->gtitle,
                            '￥' . $v->cash .'元',
                            $v->created_at,
                            '￥' . $v->remainder .'元',
                            $v->from==1 ? 'PC' : '移动',
                            
                        ];
                        break;
                    case '3':
                    case '4':
                        $data[$i + 1] = [
                            $v->id,
                            $v->name,
                            '￥' . $v->cash .'元',
                            $v->pay_type,
                            $v->pay_account,
                            $v->from,
                            $v->created_at
                        ];
                        break;
                    case '5':
                        $data[$i + 1] = [
                            $v->id,
                            $v->uname,
                            $v->stitle,
                            '￥' . $v->cash .'元',
                            '￥' . $v->coupon .'元',
                            $v->pay_type,
                            $v->pay_account,
                            $v->created_at,
                            '￥' . $v->remainder .'元',
                            $v->from==1 ? 'PC' : '移动'
                        ];
                        break;
                    case '6':
                        $data[$i + 1] = [
                            $v->id,
                            $v->uname,
                            $v->stitle,
                            '￥' . $v->cash .'元',
                            $v->coupon,
                            $v->pay_type,
                            $v->pay_account,
                            $v->created_at,
                            '￥' . $v->remainder .'元',
                            $v->from==1 ? 'PC' : '移动'
                        ];
                        break;
                    case '7':
                        $data[$i + 1] = [
                            $v->id,
                            $v->name,
                            $v->title,
                            '￥' . $v->cash .'元',
                            $v->coupon,
                            $v->pay_type,
                            $v->pay_account,
                            $v->created_at,
                            '￥' . $v->remainder .'元',
                            $v->from==1 ? 'PC' : '移动'
                        ];
                        break;
                    case '8':
                    case '9':
                        $data[$i + 1] = [
                            $v->id,
                            $v->uname,
                            $v->realname,
                            '￥' . $v->cash .'元',
                            $v->pay_type,
                            $v->pay_account,
                            $v->created_at,
                            '￥' . $v->remainder .'元',
                            $v->from==1 ? 'PC' : '移动'
                        ];
                        break;
                    case '10':
                        $data[$i + 1] = [
                            $v->id,
                            '项目',
                            $v->title,
                            $v->fname,
                            $v->tname,
                            '￥' . $v->cash .'元',
                            $v->created_at,
                        ];
                        break;
                    case '11':
                        $data[$i + 1] = [
                            $v->id,
                            $v->protype,
                            $v->uname,
                            $v->toname,
                            '￥' . $v->cash .'元',
                            $v->created_at,
                        ];
                        break;
                    case '12':
                        $data[$i + 1] = [
                            $v->id,
                            $v->vipname,
                            $v->uname,
                            '￥' . $v->cash .'元',
                            $v->coupon,
                            $v->realname,
                            $v->pay_type,
                            $v->pay_account,
                            $v->created_at,
                            '￥' . $v->remainder .'元',
                            $v->from==1 ? 'PC' : '移动'
                        ];
                        break;
                    default:
                       $data[$i + 1] = [
                            $v->id,
                            $v->action,
                            $v->name,
                            '￥' . $v->cash .'元',
                            $v->pay_type,
                            $v->pay_account,
                            $v->created_at
                        ];     
                        break;
                }  
                $i++;
            }
        }
        Excel::create(iconv('UTF-8', 'GBK', '用户流水记录'), function ($excel) use ($data) {
            $excel->sheet('score', function ($sheet) use ($data) {
                $sheet->rows($data);
            });
        })->export('xls');
    }


    /**
     * 用户流水记录
     *
     * @param Request $request
     * @return mixed
     */
    public function userFinance(Request $request)
    {
        $this->theme->setTitle('用户流水');

        $FinanceField = \CommonClass::getFinanceField($request->get('action'));
        $PayType = \CommonClass::getPayType();
        
        $keyword = $request->all();

        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = FinancialModel::getFinancialList($keyword,$by,$order,$paginate);
        $actionArr = \CommonClass::getFinanceAction();
        $data = array(
            'uid' => $request->get('uid'),
            'username' => $request->get('username'),
            'action' => $request->get('action'),
            'paginate' => $request->get('paginate'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'list' => $list,
            'action_arr' => $actionArr,
            'FinanceField' => $FinanceField,
            'PayType' => $PayType
        );
        $search = [
            'uid' => $request->get('uid'),
            'username' => $request->get('username'),
            'action' => $request->get('action'),
            'paginate' => $request->get('paginate'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'start' => $request->get('start'),
            'end' => $request->get('end'),
        ];
        $data['search'] = $search;
        return $this->theme->scope('manage.userfinance', $data)->render();
    }

    /**
     * 提现审核列表
     *
     * @param Request $request
     * @return mixed
     */
    public function cashoutList(Request $request)
    {
        $this->theme->setTitle('提现记录');

        $cashout = CashoutModel::whereRaw('1 = 1');
        if ($request->get('id')) {
            $cashout = $cashout->where('cashout.id', $request->get('id'));
        }
        if ($request->get('username')) {
            $cashout = $cashout->where('users.name','like','%'. trim($request->get('username')).'%' );
        }
        if ($request->get('cashout_type')) {
            $cashout = $cashout->where('cashout.cashout_type', $request->get('cashout_type'));
        }
        if ($request->get('status') && $request->get('status')<'99') {
            if($request->get('status')==3){
                $cashout = $cashout->where('cashout.status', '0');
            }else{
                $cashout = $cashout->where('cashout.status', $request->get('status'));  
            }
        }
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $cashout = $cashout->where('cashout.created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $cashout = $cashout->where('cashout.created_at', '<', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $list = $cashout->leftJoin('users', 'cashout.uid', '=', 'users.id')
            ->leftJoin('user_detail', 'cashout.uid', '=', 'user_detail.uid')
            ->leftJoin('alipay_auth','cashout.uid','=','alipay_auth.uid')
            ->select('cashout.*', 'users.name', 'user_detail.realname','alipay_auth.alipay_name as alipay_realname')
            ->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'id' => $request->get('id'),
            'username' => $request->get('username'),
            'cashout_type' => $request->get('cashout_type'),
            'paginate' => $request->get('paginate'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'list' => $list,
            'status' => $request->get('status'),
        );
        $search = [
            'id' => $request->get('id'),
            'username' => $request->get('username'),
            'cashout_type' => $request->get('cashout_type'),
            'paginate' => $request->get('paginate'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'status' => $request->get('status'),
        ];
        $data['search'] = $search;

        return $this->theme->scope('manage.cashoutlist', $data)->render();
    }

    /**
     * 提现审核处理
     *
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cashoutHandle($id, $action,Request $request)
    {
        $info = CashoutModel::where('id', $id)->first();
        $userInfo = DB::table('users')->where('id',$info['uid'])->select('name','id','mobile','email')->first();
        $merge = $request->all();
        switch ($action) {
            case 'pass':
                //$title="提现审核通过";
                $dealName = isset($merge['deal_name']) ? $merge['deal_name'] : '';
                $status = $info->update(array('status' => 1,'deal_name' => $dealName));
                //$content="尊敬的".$userInfo->name ."，您的提现已审核通过，请注意查看到账信息，如有疑问请致电客服。到账金额：".$info['cash']."，提现手续费：".$info['fees'];
                break;
            case 'deny':
                //$title="提现审核未通过";
                $status = CashoutModel::cashoutRefund($id,$merge);
                //$content="尊敬的".$userInfo->name ."很遗憾您的提现审核未通过，如有疑问请致电客服。";
                break;
        }
        if (isset($status) && $status){
            $user = [
                'uid'    => $userInfo->id,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username'      => $userInfo->name,

            ];
            if($action == 'pass'){
                \MessageTemplateClass::sendMessage('cashout_success',$user,$templateArr,$templateArr);
            }else{
                \MessageTemplateClass::sendMessage('cashout_failure',$user,$templateArr,$templateArr);
            }
            return redirect('manage/cashoutList')->with(array('message' => '操作成功'));
        }
        return redirect('manage/cashoutList')->with(array('message' => '操作失败'));
    }

    /**
     * 提现记录详情
     *
     * @param $id
     * @return mixed
     */
    public function cashoutInfo($id)
    {
        $info = CashoutModel::where('cashout.id', $id)
            ->leftJoin('user_detail', 'cashout.uid', '=', 'user_detail.uid')
            ->select('cashout.*', 'user_detail.realname')
            ->first();

        if (!empty($info)) {
            $data = array(
                'info' => $info
            );
            return $this->theme->scope('manage.cashoutinfo', $data)->render();
        }
    }

    /**
     * 后台充值视图
     *
     * @return mixed
     */
    public function getUserRecharge()
    {
        return $this->theme->scope('manage.recharge')->render();
    }


    /**
     * 后台用户充值
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postUserRecharge(Request $request)
    {
        $account = UserModel::where('id', $request->get('uid'))->orWhere('name', $request->get('username'))->first();
        if (!empty($account)) {
            $action = $request->get('action');
            switch ($action) {
                case 'increment':
                    //TODO:增加余额
                    $status = '';
                    break;
                case 'decrement':
                    //TODO:扣除余额
                    $status = '';
                    break;
            }
            if ($status)
                return redirect('manage/recharge')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 验证用户信息
     *
     * @param $param
     * @return string
     */
    public function verifyUser($param)
    {
        $user = UserModel::where('id', $param)->orWhere('name', $param)->first();
        $data = null;
        if (!empty($user)) {
            $userInfo = UserDetailModel::select('balance')->where('uid', $user->id)->first();
            $data = array(
                'username' => $user->name,
                'balance' => $userInfo->balance
            );
        }
        return \CommonClass::formatResponse('验证完成', 200, $data);
    }

    /**
     * 用户充值订单列表
     *
     * @param Request $request
     * @return mixed
     */
    public function rechargeList(Request $request)
    {
        $this->theme->setTitle('充值记录');

        $recharge = OrderModel::whereNull('order.task_id')->where('order.status', 0);
        if ($request->get('code')) {
            $recharge = $recharge->where('order.code', $request->get('code'));
        }
        if ($request->get('username')) {
            $recharge = $recharge->where('users.name', $request->get('username'));
        }
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $recharge = $recharge->where('order.created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $recharge = $recharge->where('order.created_at', '<', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'code';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $list = $recharge->leftJoin('users', 'order.uid', '=', 'users.id')
            ->select('order.*', 'users.name')
            ->orderBy($by, $order)->paginate($paginate);

        $data = array(
            'code' => $request->get('code'),
            'username' => $request->get('username'),
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'paginate' => $request->get('paginate'),
            'list' => $list
        );
        $search = [
            'code' => $request->get('code'),
            'username' => $request->get('username'),
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'paginate' => $request->get('paginate'),
        ];
        $data['search'] = $search;

        return $this->theme->scope('manage.rechargelist', $data)->render();
    }

    /**
     * 后台确认订单充值
     *
     * @param $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirmRechargeOrder($order)
    {
        $order = OrderModel::where('code', $order)->first();
        if (!empty($order)) {
            $status = OrderModel::adminRecharge($order);
            if ($status) {
                return redirect('manage/rechargeList')->with(array('message' => '操作成功'));
            }
        }
    }

    /**
     * 财务报表
     * @return mixed
     */
    public function financeStatement()
    {
        $this->theme->setTitle('网站收支');
        $now = strtotime(date('Y-m-d', time()));
        $oneDay = 24 * 60 * 60;
        //定义最大天数
        $maxDay = 7;
        for ($i = 0; $i < $maxDay; $i++) {
            $timeArr[$i]['min'] = date('Y-m-d H:i:s', ($now - $oneDay * ($i + 1)));
            $timeArr[$i]['max'] = date('Y-m-d H:i:s', ($now - $oneDay * $i));
        }
        //反向排序
        $timeArr = array_reverse($timeArr);

        foreach ($timeArr as $k => $v) {
            $dateArr[] = date('m', strtotime($timeArr[$k]['min'])) . '月' . date('d', strtotime($timeArr[$k]['min'])) . '日';
        }
        //充值提现取值
        $arrFinance = FinancialModel::select('action', 'cash', 'created_at')
            ->where('created_at', '<', $timeArr[6]['max'])
            ->where('created_at', '>', $timeArr[1]['min'])->get();
        //发布任务订单取值
        $arrTask = OrderModel::select('created_at', 'cash')->whereNotNull('task_id')
            ->where('created_at', '<', $timeArr[6]['max'])
            ->where('created_at', '>', $timeArr[1]['min'])->get();
        //增值服务订单取值
        $arrService = SubOrderModel::select('created_at', 'cash')->where('product_type', 3)
            ->where('created_at', '<', $timeArr[6]['max'])
            ->where('created_at', '>', $timeArr[1]['min'])->get();

        $arr = array();
        //收支数组赋值
        if (!empty($arrFinance)) {
            foreach ($arrFinance as $item) {
                switch ($item->action) {
                    case 3:
                        for ($i = 0; $i < $maxDay; $i++) {
                            if ($item->created_at > $timeArr[$i]['min'] && $item->created_at < $timeArr[$i]['max']) {
                                $arr['in'][$i][] = $item->cash;
                            }
                        }
                        break;
                    case 4:
                        for ($i = 0; $i < $maxDay; $i++) {
                            if ($item->created_at > $timeArr[$i]['min'] && $item->created_at < $timeArr[$i]['max']) {
                                $arr['out'][$i][] = $item->cash;
                            }
                        }
                        break;
                }
            }
        }
        if (!empty($arrTask)) {
            foreach ($arrTask as $item) {
                for ($i = 0; $i < $maxDay; $i++) {
                    if ($item->created_at > $timeArr[$i]['min'] && $item->created_at < $timeArr[$i]['max']) {
                        $arr['task'][$i][] = $item->cash;
                    }
                }
            }
        }
        if (!empty($arrService)) {
            foreach ($arrService as $item) {
                for ($i = 0; $i < $maxDay; $i++) {
                    if ($item->created_at > $timeArr[$i]['min'] && $item->created_at < $timeArr[$i]['max']) {
                        $arr['tool'][$i][] = $item->cash;
                    }
                }
            }
        }
        //拼接收支明细
        if (!empty($arr)) {
            if (!empty($arr['in'])) {
                for ($i = 0; $i < $maxDay; $i++) {
                    if (isset($arr['in'][$i])) {
                        $arr['in'][$i] = array_sum($arr['in'][$i]);
                    } else {
                        $arr['in'][$i] = 0;
                    }
                }
            } else {
                for ($i = 0; $i < $maxDay; $i++) {
                    $arr['in'][$i] = 0;
                }
            }
            if (!empty($arr['out'])) {
                for ($i = 0; $i < $maxDay; $i++) {
                    if (isset($arr['out'][$i])) {
                        $arr['out'][$i] = array_sum($arr['out'][$i]);
                    } else {
                        $arr['out'][$i] = 0;
                    }
                }
            } else {
                for ($i = 0; $i < $maxDay; $i++) {
                    $arr['out'][$i] = 0;
                }
            }
            if (!empty($arr['task'])) {
                for ($i = 0; $i < $maxDay; $i++) {
                    if (isset($arr['task'][$i])) {
                        $arr['task'][$i] = array_sum($arr['task'][$i]);
                    } else {
                        $arr['task'][$i] = 0;
                    }
                }
            } else {
                for ($i = 0; $i < $maxDay; $i++) {
                    $arr['task'][$i] = 0;
                }
            }
            if (!empty($arr['tool'])) {
                for ($i = 0; $i < $maxDay; $i++) {
                    if (isset($arr['tool'][$i])) {
                        $arr['tool'][$i] = array_sum($arr['tool'][$i]);
                    } else {
                        $arr['tool'][$i] = 0;
                    }
                }
            } else {
                for ($i = 0; $i < $maxDay; $i++) {
                    $arr['tool'][$i] = 0;
                }
            }
        } else {
            for ($i = 0; $i < $maxDay; $i++) {
                $arr['in'][$i] = 0;
                $arr['out'][$i] = 0;
                $arr['task'][$i] = 0;
                $arr['tool'][$i] = 0;
            }
        }
        /*$leftK = 1; $rightK = 2; $incre = 0;
        foreach ($arr as $k => $v){
            if ($k == 'in' || $k == 'task'){
                foreach ($v as $item){
                    $finance['in'][] = [$leftK, $item];
                    $leftK += 3;
                }
            }
            if ($k == 'out' || $k == 'tool'){
                foreach ($v as $item){
                    $finance['out'][] = [$rightK, $item];
                    $rightK += 3;
                }
            }
            $broken[$k] = [$incre, $arr[$k][$incre]];
        }*/
        //收支\利润数组
        $finance = [
            'in' => [
                [1, $arr['in'][0]],
                [4, $arr['in'][1]],
                [7, $arr['in'][2]],
                [10, $arr['in'][3]],
                [13, $arr['in'][4]],
                [16, $arr['in'][5]],
                [19, $arr['in'][6]]
            ],
            'out' => [
                [2, $arr['out'][0]],
                [5, $arr['out'][1]],
                [8, $arr['out'][2]],
                [11, $arr['out'][3]],
                [14, $arr['out'][4]],
                [17, $arr['out'][5]],
                [20, $arr['out'][6]]
            ],
            'task' => [
                [1, $arr['task'][0]],
                [4, $arr['task'][1]],
                [7, $arr['task'][2]],
                [10, $arr['task'][3]],
                [13, $arr['task'][4]],
                [16, $arr['task'][5]],
                [19, $arr['task'][6]]
            ],
            'tool' => [
                [2, $arr['tool'][0]],
                [5, $arr['tool'][1]],
                [8, $arr['tool'][2]],
                [11, $arr['tool'][3]],
                [14, $arr['tool'][4]],
                [17, $arr['tool'][5]],
                [20, $arr['tool'][6]]
            ]
        ];
        //折线图数组
        $broken = [
            'cash' => [
                [0, $arr['in'][0]],
                [1, $arr['in'][1]],
                [2, $arr['in'][2]],
                [3, $arr['in'][3]],
                [4, $arr['in'][4]],
                [5, $arr['in'][5]],
                [6, $arr['in'][6]],
            ],
            'out' => [
                [0, $arr['out'][0]],
                [1, $arr['out'][1]],
                [2, $arr['out'][2]],
                [3, $arr['out'][3]],
                [4, $arr['out'][4]],
                [5, $arr['out'][5]],
                [6, $arr['out'][6]],
            ],
            'task' => [
                [0, $arr['task'][0]],
                [1, $arr['task'][1]],
                [2, $arr['task'][2]],
                [3, $arr['task'][3]],
                [4, $arr['task'][4]],
                [5, $arr['task'][5]],
                [6, $arr['task'][6]],
            ],
            'tool' => [
                [0, $arr['tool'][0]],
                [1, $arr['tool'][1]],
                [2, $arr['tool'][2]],
                [3, $arr['tool'][3]],
                [4, $arr['tool'][4]],
                [5, $arr['tool'][5]],
                [6, $arr['tool'][6]],
            ]
        ];
        $data = [
            'finance' => json_encode($finance),
            'broken' => json_encode($broken),
            'dateArr' => json_encode($dateArr)
        ];
        return $this->theme->scope('manage.financeStatement', $data)->render();
    }

    /**
     * 财务报表-充值记录
     * @return mixed
     */
    public function financeRecharge(Request $request)
    {
        $this->theme->setTitle('充值记录');
        $list = FinancialModel::select('financial.id', 'users.name', 'financial.pay_type', 'financial.pay_account', 'financial.cash', 'financial.created_at')
            ->leftJoin('users', 'users.id', '=', 'financial.uid')->where('financial.action', 3);
        if ($request->get('type')) {
            switch ($request->get('type')) {
                case 'alipay':
                    $list = $list->where('financial.pay_type', 2);
                    break;
                case 'wechat':
                    $list = $list->where('financial.pay_type', 3);
                    break;
                case 'bankunion':
                    $list = $list->where('financial.pay_type', 4);
                    break;
            }
        }
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('financial.created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('financial.created_at', '<', $end);
        }

        $count = $list->count();
        $sum = $list->sum('financial.cash');

        $list = $list->orderBy('financial.id', 'DESC')->paginate(10);
        $payType = \CommonClass::getPayType();
        $data = [
            'list' => $list,
            'count' => $count,
            'sum' => $sum,
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'type' => $request->get('type'),
            'pay_type' => $payType
        ];
        $search = [
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'type' => $request->get('type')
        ];
        $data['search'] = $search;
        return $this->theme->scope('manage.financeRecharge', $data)->render();
    }

    /**
     * 充值记录导出excel
     *
     * @param $param
     */
    public function financeRechargeExport($param)
    {
        $param = \CommonClass::getParamByQueryString($param);

        $list = FinancialModel::select('financial.id', 'users.name', 'financial.pay_type', 'financial.pay_account', 'financial.cash', 'financial.created_at')
            ->leftJoin('users', 'users.id', '=', 'financial.uid')->where('financial.action', 3);
        if ($param['type'][0]) {
            switch ($param['type'][0]) {
                case 'alipay':
                    $list = $list->where('financial.pay_type', 2);
                    break;
                case 'wechat':
                    $list = $list->where('financial.pay_type', 3);
                    break;
                case 'bankunion':
                    $list = $list->where('financial.pay_type', 4);
                    break;
            }
        }
        if ($param['start'][0]) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $param['start'][0]);
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('financial.created_at', '>', $start);
        }
        if ($param['end'][0]) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $param['end'][0]);
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('financial.created_at', '<', $end);
        }

        $count = $list->count();
        $sum = $list->sum('financial.cash');
        $list = $list->get()->chunk(100);
        $data = [
            [
                '编号',
                '用户名',
                '充值方式',
                '充值账号',
                '金额',
                '充值时间'
            ]
        ];
        $i = 0;
        foreach ($list as $chunk) {
            foreach ($chunk as $k => $v) {
                switch ($v->pay_type) {
                    case 2:
                        $v->action = '支付宝';
                        break;
                    case 3:
                        $v->action = '微信';
                        break;
                    case 4:
                        $v->action = '银联';
                        break;
                }
                $data[$i + 1] = [
                    $v->id,
                    $v->name,
                    $v->action,
                    $v->pay_account,
                    '￥' . $v->cash . '元',
                    $v->created_at,
                ];
                $i++;
            }
        }
        $data[$i + 1] = [
            '总计', '', $count, '', $sum, ''
        ];
        Excel::create(iconv('UTF-8', 'GBK', '充值记录'), function ($excel) use ($data) {
            $excel->sheet('score', function ($sheet) use ($data) {
                $sheet->rows($data);
            });
        })->export('xls');
    }

    /**
     * 财务报表-提现记录
     * @return mixed
     */
    public function financeWithdraw(Request $request)
    {
        $this->theme->setTitle('提现记录');
        $list = CashoutModel::select('cashout.id', 'users.name', 'cashout.cashout_type', 'cashout.cashout_account', 'cashout.cash',
            'cashout.real_cash', 'cashout.fees', 'cashout.created_at', 'cashout.updated_at')
            ->leftJoin('users', 'cashout.uid', '=', 'users.id')->where('cashout.status', 1);

        if ($request->get('type')) {
            switch ($request->get('type')) {
                case 'alipay':
                    $list = $list->where('cashout.cashout_type', 1);
                    break;
                case 'bank':
                    $list = $list->where('cashout.cashout_type', 2);
                    break;
            }
        }
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('cashout.updated_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('cashout.updated_at', '<', $end);
        }
        //提现次数
        $count = $list->count();
        //提现金额总计
        $cashSum = $list->sum('cashout.cash');
        //到账金额总计
        $realCashSum = $list->sum('cashout.real_cash');
        //手续费总计
        $feesSum = $list->sum('cashout.fees');
        $list = $list->orderBy('cashout.id', 'DESC')->paginate(10);
        $data = [
            'list' => $list,
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'type' => $request->get('type'),
            'count' => $count,
            'cashSum' => $cashSum,
            'realCashSum' => $realCashSum,
            'feesSum' => $feesSum
        ];
        $search = [
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'type' => $request->get('type'),
        ];
        $data['search'] = $search;

        return $this->theme->scope('manage.financeWithdraw', $data)->render();
    }


    public function financeWithdrawExport($param)
    {
        $param = \CommonClass::getParamByQueryString($param);

        $list = CashoutModel::select('cashout.id', 'users.name', 'cashout.cashout_type', 'cashout.cashout_account', 'cashout.cash',
            'cashout.real_cash', 'cashout.fees', 'cashout.created_at', 'cashout.updated_at')
            ->leftJoin('users', 'cashout.uid', '=', 'users.id')->where('cashout.status', 1);

        if ($param['type'][0]) {
            switch ($param['type'][0]) {
                case 'alipay':
                    $list = $list->where('cashout.cashout_type', 1);
                    break;
                case 'bank':
                    $list = $list->where('cashout.cashout_type', 2);
                    break;
            }
        }
        if ($param['start'][0]) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $param['start'][0]);
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('cashout.updated_at', '>', $start);
        }
        if ($param['end'][0]) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $param['end'][0]);
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('cashout.updated_at', '<', $end);
        }
        //提现次数
        $count = $list->count();
        //提现金额总计
        $cashSum = $list->sum('cashout.cash');
        //到账金额总计
        $realCashSum = $list->sum('cashout.real_cash');
        //手续费总计
        $feesSum = $list->sum('cashout.fees');

        $list = $list->get()->chunk(100);
        $data = [
            [
                '编号',
                '用户名',
                '提现方式',
                '提现账号',
                '提现金额',
                '到账金额',
                '手续费',
                '提现时间',
            ]
        ];
        $i = 0;
        foreach ($list as $chunk) {
            foreach ($chunk as $k => $v) {
                switch ($v->cashout_type) {
                    case 1:
                        $v->action = '支付宝';
                        break;
                    case 2:
                        $v->action = '银行卡';
                        break;
                }
                $data[$i + 1] = [
                    $v->id,
                    $v->name,
                    $v->action,
                    $v->cashout_account,
                    $v->cash,
                    $v->real_cash,
                    $v->fees,
                    $v->created_at
                ];
                $i++;
            }
        }
        $data[$i + 1] = [
            '总计', '', $count.'次', '', $cashSum, $realCashSum, $feesSum, ''
        ];
        Excel::create(iconv('UTF-8', 'GBK', '提现记录'), function ($excel) use ($data) {
            $excel->sheet('score', function ($sheet) use ($data) {
                $sheet->rows($data);
            });
        })->export('xls');
    }

    /**
     * 财务报表-利润统计
     * @return mixed
     */
    public function financeProfit(Request $request)
    {
        $this->theme->setTitle('利润统计');

        $from = $request->get('from') ? $request->get('from') : 'task';
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
        }

        switch ($from) {
            case 'task':
                $list = OrderModel::select('order.task_id', 'users.name', 'order.cash', 'order.created_at')
                    ->whereNotNull('order.task_id')->leftJoin('users', 'order.uid', '=', 'users.id')->where('order.status', 1)
                    ->orderBy('order.created_at', 'DESC');
                if (isset($start)) {
                    $list = $list->where('order.created_at', '>', $start);
                }
                if (isset($end)) {
                    $list = $list->where('order.created_at', '<', $end);
                }
                $sum = $list->sum('order.cash');
                break;
            case 'tool':
                $list = SubOrderModel::select('users.name', 'sub_order.cash', 'sub_order.created_at')
                    ->where('sub_order.product_type', 3)->leftJoin('users', 'sub_order.uid', '=', 'users.id')
                    ->where('sub_order.status', 1)->orderBy('sub_order.created_at', 'DESC');
                if (isset($start)) {
                    $list = $list->where('sub_order.created_at', '>', $start);
                }
                if (isset($end)) {
                    $list = $list->where('sub_order.created_at', '<', $end);
                }
                $sum = $list->sum('sub_order.cash');
                break;
            case 'cashout':
                $list = CashoutModel::select('cashout.cash', 'cashout.real_cash', 'cashout.fees', 'cashout.created_at', 'users.name')
                    ->where('cashout.status', 1)->leftJoin('users', 'users.id', '=', 'cashout.uid')
                    ->orderBy('cashout.created_at', 'DESC');
                if (isset($start)) {
                    $list = $list->where('cashout.created_at', '>', $start);
                }
                if (isset($end)) {
                    $list = $list->where('cashout.created_at', '<', $end);
                }
                $sum = $list->sum('cashout.fees');
                break;
        }

        $list = $list->paginate(10);
        $data = [
            'list' => $list,
            'from' => $from,
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'sum' => $sum
        ];
        $search = [
            'from' => $from,
            'start' => $request->get('start'),
            'end' => $request->get('end'),
        ];
        $data['search'] = $search;

        return $this->theme->scope('manage.financeProfit', $data)->render();
    }
    //保证金管理
    public function depositList(Request $request){
          $list=UserDepositModel::leftJoin("users","user_deposit.uid","=","users.id");
          if($request->get("uname")){//搜索昵称
              $list=$list->where("users.name","like","%".$request->get("uname")."%");
          }
          if($request->get("type")){//搜索状态
              $list=$list->where("user_deposit.type",$request->get("type"));
          }
          //根据数据搜索
          if($request->get("start")){
              $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
              $start = date('Y-m-d H:i:s',strtotime($start));
              $list=$list->where("user_deposit.created_at",'>=',$start);
          }
          if($request->get("end")){
              $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
              $end = date('Y-m-d H:i:s',strtotime($end));
              $list=$list->where("user_deposit.created_at",'<',date("Y-m-d",strtotime($end) +3600*24));
          }
          $list=$list->where("user_deposit.status",2)->orderBy("user_deposit.id","desc")->select("users.name","user_deposit.*")->paginate(10);
           $search=[
               'uname'=>$request->get("uname")?$request->get("uname"):"",
               'type'=>$request->get("type")?$request->get("type"):0,
               'start'=>$request->get("start")?$request->get("start"):"",
               'end'=>$request->get("end")?$request->get("end"):"",
           ];
        $data=[
              'search'=>$search,
              "list"=>$list,
          ];
        return $this->theme->scope('manage.depositList', $data)->render();
    }
    //保证金申请处理
    public function depositHandle($id,$action,Request $request){
           //获取保证金记录
           $deposit=UserDepositModel::find($id);
           switch($action){
               case "pass":
                   $res=DB::transaction(function()use($deposit,$id){
                            $userDetail=UserDetailModel::where("uid",$deposit['uid'])->first();
                            //给用户添加缴纳的保证金金额
                           // UserDetailModel::where("uid",$deposit['uid'])->increment("balance",$deposit['price']);
                            //修改用户保证金状态
                            UserDetailModel::where("uid",$deposit['uid'])->update(['deposit'=>0,"balance"=>($userDetail['balance'] +$deposit['price'])]);
                            //修改该条记录状态
                            $deposit->update(["type"=>3]);
                            //生成财务记录
                            FinancialModel::create(
                                [
                                    'action'=>14,
                                    'pay_type'=>1,
                                    'cash'=>$deposit['price'],
                                    'uid'=>$deposit['uid'],
                                    'created_at'=>date("Y-m-d H:i:s"),
                                    'updated_at'=>date("Y-m-d H:i:s"),
                                    'related_id'=>$id,
                                    'status' =>1,
                                    'remainder'=>$userDetail['balance'] +$deposit['price'],
                                ]
                            );
                       //删除店铺保证金记录
                       AuthRecordModel::where("uid",$deposit['uid'])->where("auth_code","promise")->update(["status"=>0]);
                       //给用户发送短信
                       UserDepositModel::sendSms($deposit['uid'],"deposit_success",$deposit['price']);
                            return $deposit;
                   });
                   break;
               case "deny":
                   $res=DB::transaction(function()use($deposit,$request){
                       //修改该条记录状态
                       $deposit->update(["type"=>4,'reason'=>$request->get("reason")]);
                       //给用户发送短信
                       UserDepositModel::sendSms(Auth::user()->id,"deposit_failure",$deposit['price']);
                       return $deposit;
                   });
                   break;
           }
           if($res){
               return back()->with(["message"=>"操作成功"]);
           }
               return back()->with(["message"=>"操作失败"]);
    }
}
