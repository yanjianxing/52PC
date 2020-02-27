<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/6/27
 * Time: 14:10
 */
namespace App\Modules\Api\Http\Controllers;

use App\Http\Requests;
use App\Modules\User\Model\EnterpriseAuthModel;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiBaseController;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\AlipayAuthModel;

use Validator;
use DB;
use Illuminate\Support\Facades\Crypt;

class AuthController extends ApiBaseController
{
    /**
     * 创建实名认证信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function realnameAuth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'realname' => 'required|string|between:2,5',
            'card_number' => 'required|alpha_num|between:15,18',
            'card_front_side' => 'required',
            'card_back_dside' => 'required',
            'type' => 'required'
        ], [

            'realname.required' => '请输入真实姓名',
            'realname.string' => '请输入正确的格式',
            'realname.between' => '真实姓名:min - :max 个字符',

            'card_number.required' => '请输入身份证号码',
            'card_number.alpha_num' => '请输入正确的身份证格式',
            'card_number.between' => '身份证号码长度在:min - :max 位',

            'card_front_side.required' => '请上传身份证正面图片',
            'card_back_dside.required' => '请上传身份证反面图片',

            'type.required' => '请选择认证类型'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }

        $card_front_side = $request->file('card_front_side');
        $card_back_dside = $request->file('card_back_dside');
        if ($request->get('type') == '1') {
            if (!$request->file('validation_img')) {
                return $this->formateResponse(1027, '手持身份证正面图片必传');
            }
            $validation_img = $request->file('validation_img');
        } else if ($request->get('type') == '2') {
            $validation_img = '';
        }

        $realnameInfo = array();
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png');
        if ($card_front_side) {
            $uploadMsg = json_decode(\FileClass::uploadFile($card_front_side, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                return $this->formateResponse(1024, $uploadMsg->message);
            } else {
                $realnameInfo['card_front_side'] = $uploadMsg->data->url;
            }
        }
        if ($card_back_dside) {
            $uploadMsg = json_decode(\FileClass::uploadFile($card_back_dside, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                return $this->formateResponse(1025, $uploadMsg->message);
            } else {
                $realnameInfo['card_back_dside'] = $uploadMsg->data->url;
            }
        }
        if ($validation_img) {
            $uploadMsg = json_decode(\FileClass::uploadFile($validation_img, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                return $this->formateResponse(1026, $uploadMsg->message);
            } else {
                $realnameInfo['validation_img'] = $uploadMsg->data->url;
            }
        } else {
            $realnameInfo['validation_img'] = '';
        }

        $now = time();

        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));

        $realnameInfo['uid'] = $tokenInfo['uid'];
        $realnameInfo['username'] = $tokenInfo['name'];
        $realnameInfo['realname'] = $request->get('realname');
        $realnameInfo['card_number'] = $request->get('card_number');
        $realnameInfo['created_at'] = date('Y-m-d H:i:s', $now);
        $realnameInfo['updated_at'] = date('Y-m-d H:i:s', $now);
        $realnameInfo['type'] = $request->get('type');

        $res = DB::transaction(function () use ($realnameInfo) {
            $realnameInfo = RealnameAuthModel::create($realnameInfo);
            $data = [
                'auth_id' => $realnameInfo->id,
                'uid' => $realnameInfo->uid,
                'username' => $realnameInfo->username,
                'auth_code' => 'realname',
                'auth_time' => date('Y-m-d H:i:s')
            ];

            AuthRecordModel::create($data);
            return $realnameInfo;

        });
        if (!isset($res)) {
            return $this->formateResponse(1028, '创建失败');
        }
        return $this->formateResponse(1000, 'success', ['auth_id' => Crypt::encrypt($res['id'])]);
    }

    /**
     * 银行卡认证信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function bankAuth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'depositName' => 'required|string|between:4,20',
            'bankname' => 'required|string|between:4,20',
            'bankAccount' => 'required|alpha_num',
            'confirmBankAccount' => 'required|same:bankAccount'
        ], [
            'depositName.required' => '请输入开户行名称',
            'depositName.between' => '开户行名称长度在 :min - :max 位',
            'bankname.required' => '请选择开户银行',
            'bankAccount.required' => '请输入银行卡号',
            'confirmBankAccount.required' => '请输入确认银行卡号',
            'confirmBankAccount.same' => '确认银行卡号与银行卡号不一致'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userDetail = UserDetailModel::where('uid', $tokenInfo['uid'])->first();

        //查询该银行卡是否被绑定（绑定成功或正在绑定中）
        $isBank = BankAuthModel::where('bank_account',$request->get('bankAccount'))->where('uid',$tokenInfo['uid'])->whereIn('status',[0,1,2])->first();
        if($isBank){
            return $this->formateResponse(1001, '该银行卡号已经被绑定');
        }


        //批量赋值写入银行认证和认证记录表
        $depositArea = '';//app端不用传地区
        $bankAuthInfo = array();
        $now = time();
        $bankAuthInfo['uid'] = $tokenInfo['uid'];
        $bankAuthInfo['username'] = $tokenInfo['name'];
        $bankAuthInfo['realname'] = $userDetail['realname'];
        $bankAuthInfo['bank_name'] = $request->get('bankname');
        $bankAuthInfo['bank_account'] = $request->get('bankAccount');
        $bankAuthInfo['deposit_name'] = $request->get('depositName');
        $bankAuthInfo['deposit_area'] = $depositArea;
        $bankAuthInfo['created_at'] = date('Y-m-d H:i:s', $now);
        $bankAuthInfo['updated_at'] = date('Y-m-d H:i:s', $now);
        $bankAuthInfo['status'] = 0;

        $res = DB::transaction(function () use ($bankAuthInfo) {
            $bankInfo = BankAuthModel::create($bankAuthInfo);
            $data = [
                'auth_id' => $bankInfo->id,
                'uid' => $bankInfo->uid,
                'username' => $bankInfo->username,
                'auth_code' => 'bank',
                'auth_time' => date('Y-m-d H:i:s')
            ];

            AuthRecordModel::create($data);
            return $bankInfo;

        });
        if ($res) {
            return $this->formateResponse(1000, 'success', ['auth_id' => Crypt::encrypt($res['id'])]);
        } else {
            return $this->formateResponse(1029, '创建失败');
        }

    }


    /**
     * 获取可以绑定的银行名称
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getBankAuth(Request $request)
    {
        $bankname = array(
            '农业银行', '交通银行', '招商银行', '工商银行', '建设银行', '中国银行', '工商银行', '邮政储蓄银行', '民生银行', '浦发银行', '华夏银行'
        );

        return $this->formateResponse(1000, 'success', $bankname);
    }


    /**
     * 获取银行认证的信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function bankAuthInfo(Request $request)
    {
        $bankAuthId = Crypt::decrypt($request->get('auth_id'));
        if (!$bankAuthId) {
            return $this->formateResponse(1030, '认证银行卡id信息传送错误');
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));

        $authInfo = BankAuthModel::where('id', $bankAuthId)->where('uid', $tokenInfo['uid'])->first();
        if (empty($authInfo)) {
            return $this->formateResponse(1031, '传送数据错误');
        }
        $authInfo['districtname'] = '';
        if($authInfo->deposit_area){
            $arrDistrict = explode(',', $authInfo->deposit_area);

            $authInfo['districtname'] = DistrictModel::getDistrictName($arrDistrict);
        }


        return $this->formateResponse(1000, 'success', $authInfo);
    }


    /**
     * 获取实名认证信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function realnameAuthInfo(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $realnameInfo = RealnameAuthModel::where('uid', $tokenInfo['uid'])->orderBy('created_at', 'desc')->first();
        if (empty($realnameInfo)) {
            $status = 3;
            $realname = '';
            $card = '';
        }else {
            $status = $realnameInfo->status;
            $realname = $realnameInfo->realname;
            $card = \CommonClass::starReplace($realnameInfo->card_number, 4, 10);
        }

        $data = array(
            'realname' => $realname,
            'card_number' => $card,
            'status' => $status
        );

        return $this->formateResponse(1000, 'success', $data);
    }

    /**
     * 支付宝认证
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function alipayAuth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alipayName' => 'required|string|between:2,10',
            'alipayAccount' => 'required|string',
            'confirmAlipayAccount' => 'required|same:alipayAccount'
        ], [
            'alipayName.required' => '请输入支付宝姓名',
            'alipayAccount.required' => '请输入支付宝账户',
            'alipayAccount.string' => '请输入正确的支付宝账户格式',
            'confirmAlipayAccount.required' => '请确认支付宝账户',
            'confirmAlipayAccount.same' => '确认账户与支付宝账户不匹配'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userDetail = UserDetailModel::where('uid', $tokenInfo['uid'])->first();
        $alipayAuthInfo = array();
        $alipayAuthInfo['uid'] = $tokenInfo['uid'];
        $alipayAuthInfo['username'] = $tokenInfo['name'];
        $alipayAuthInfo['realname'] = $userDetail['realname'];
        $alipayAuthInfo['alipay_name'] = $request->get('alipayName');
        $alipayAuthInfo['alipay_account'] = $request->get('alipayAccount');
        $alipayAuthInfo['created_at'] = date('Y-m-d H:i:s');
        $alipayAuthInfo['updated_at'] = date('Y-m-d H:i:s');
        $alipayAuthInfo['status'] = 0;

        $res = DB::transaction(function () use ($alipayAuthInfo) {
            $alipayInfo = AlipayAuthModel::create($alipayAuthInfo);
            $data = [
                'auth_id' => $alipayInfo->id,
                'uid' => $alipayInfo->uid,
                'username' => $alipayInfo->username,
                'auth_code' => 'alipay',
                'auth_time' => date('Y-m-d H:i:s')
            ];

            AuthRecordModel::create($data);
            return $alipayInfo;

        });
        if ($res) {
            return $this->formateResponse(1000, 'success', ['auth_id' => Crypt::encrypt($res['id'])]);
        } else {
            return $this->formateResponse(1029, '创建失败');
        }

    }


    /**
     * 获取支付宝认证信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function alipayAuthInfo(Request $request)
    {
        $alipayAuthId = Crypt::decrypt($request->get('auth_id'));
        //查找认证信息
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $alipayAuthInfo = AlipayAuthModel::where('id', $alipayAuthId)->where('uid', $tokenInfo['uid'])->first();

        if (empty($alipayAuthInfo)) {
            return $this->formateResponse(1033, '传送数据错误');
        }

        return $this->formateResponse(1000, 'success', $alipayAuthInfo);

    }


    /**
     * 验证支付宝认证金额
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function verifyAlipayAuthCash(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cash' => 'required|numeric',
            'auth_id' => 'required|string'
        ], [
            'cash.required' => '请输入打款金额',
            'cash.numeric' => '请输入正确的格式',
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $authId = Crypt::decrypt($request->get('auth_id'));
        //查询认证信息
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $alipayAuthInfo = AlipayAuthModel::where('uid', $tokenInfo['uid'])->where('id', $authId)->first();

        if (empty($alipayAuthInfo)) {
            return $this->formateResponse(1034, '传送数据错误');
        }
        //验证金额
        $view = array();
        $view['alipayAuthInfo'] = $alipayAuthInfo;
        $alipayAuthInfo->user_get_cash = $request->get('cash');
        $alipayAuthInfo->auth_time = date('Y-m-d H:i:s');
        if ($alipayAuthInfo['pay_to_user_cash'] == $request->get('cash')) {
            $alipayAuthInfo->status = 2;
            $res = $alipayAuthInfo->save();
            if (!$res) {
                return $this->formateResponse(1035, '支付宝认证状态更新失败');
            }
            return $this->formateResponse(1000, 'success');
        } else {
            $alipayAuthInfo->status = 3;
            $res = $alipayAuthInfo->save();
            if (!$res) {
                return $this->formateResponse(1035, '支付宝认证状态更新失败');
            }
            return $this->formateResponse(1036, '支付宝认证金额失败');
        }

    }


    /**
     * 验证银行卡认证金额
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function verifyBankAuthCash(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cash' => 'required|numeric',
            'auth_id' => 'required|string'
        ], [
            'cash.required' => '请输入打款金额',
            'cash.numeric' => '请输入正确的格式',
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $authId = Crypt::decrypt($request->get('auth_id'));
        //查询认证信息
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $bankAuthInfo = BankAuthModel::where('uid', $tokenInfo['uid'])->where('id', $authId)->first();

        if (empty($bankAuthInfo)) {
            return $this->formateResponse(1036, '传送数据错误');
        }
        //验证金额
        $bankAuthInfo->user_get_cash = $request->get('cash');
        if ($bankAuthInfo['pay_to_user_cash'] == $request->get('cash')) {
            $bankAuthInfo->auth_time = date('Y-m-d H:i:s');
            $bankAuthInfo->status = 2;
            $res = $bankAuthInfo->save();
            if (!$res) {
                return $this->formateResponse(1037, '银行卡认证状态更新失败');
            }
            return $this->formateResponse(1000, 'success');
        } else {
            $bankAuthInfo->status = 3;
            $res = $bankAuthInfo->save();
            if (!$res) {
                return $this->formateResponse(1037, '银行卡认证状态更新失败');
            }
            return $this->formateResponse(1038, '银行卡认证金额失败');
        }


    }


    /**
     * 获取用户认证的银行卡列表信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function bankList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $bankList = BankAuthModel::where('uid', $tokenInfo['uid'])->get()->toArray();
        if (count($bankList)) {
            foreach ($bankList as $k => $v) {
                $bankList[$k]['id'] = Crypt::encrypt($bankList[$k]['id']);
                $arrDistrict = explode(',', $bankList[$k]['deposit_area']);
                $bankList[$k]['districtname'] = DistrictModel::getDistrictName($arrDistrict);
            }
        }
        return $this->formateResponse('1000', '获取银行卡列表信息成功', $bankList);

    }


    /**
     * 获取用户认证的支付宝列表信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function alipayList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $alipayList = AlipayAuthModel::where('uid', $tokenInfo['uid'])->get()->toArray();
        if (count($alipayList)) {
            foreach ($alipayList as $k => $v) {
                $alipayList[$k]['id'] = Crypt::encrypt($alipayList[$k]['id']);

            }
        }
        return $this->formateResponse('1000', '获取支付宝列表信息成功', $alipayList);
    }


    /**
     * 重新企业认证
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function enterpriseAuthRestart(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        $status = EnterpriseAuthModel::getEnterpriseAuthStatus($uid);
        if ($status == 2) {
            $res = EnterpriseAuthModel::removeEnterpriseAuth();
            if ($res) {
                return $this->formateResponse('1000', '删除原企业认证记录成功');
            } else {
                return $this->formateResponse('1002', '删除原企业认证记录失败');
            }
        } else {
            return $this->formateResponse('1001', '没有进行企业认证失败的记录');
        }

    }

    /**
     * 保存企业认证信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function enterpriseAuth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required',
            'cate_id' => 'required',
            'employee_num' => 'required',
            'business_license' => 'required',
            'begin_at' => 'required',
            'province' => 'required',
            'city' => 'required',
            'address' => 'required',
            'file_id' => 'required'
        ], [
            'company_name.required' => '请输入公司名称',
            'cate_id.required' => '请输入所属行业二级分类id',
            'employee_num.required' => '请输入员工人数',
            'business_license.required' => '请确认营业执照',
            'begin_at.required' => '请输入开始经营年数',
            'province.required' => '请输入经营地址省级id',
            'city.required' => '请输入经营地址市级id',
            'address.required' => '请输入经营地址详细地址',
            'file_id.required' => '请上传相关资质'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $uid = $tokenInfo['uid'];
        //查询是否已经认证
        $r = EnterpriseAuthModel::where('uid',$uid)->first();
        if(!empty($r)){
            return $this->formateResponse(1003, '已经进行过认证');
        }
        $companyInfo = array(
            'uid'              => $uid,
            'company_name'     => $request->get('company_name'),
            'cate_id'          => $request->get('cate_id'),
            'employee_num'     => $request->get('employee_num'),
            'business_license' => $request->get('business_license'),
            'begin_at'         => date('Y-m-d H:i:s',strtotime($request->get('start'))),
            'website'          => $request->get('website'),
            'province'         => $request->get('province'),
            'city'             => $request->get('city'),
            'area'             => 0,//app端只有两级地区，area默认为0
            'address'          => $request->get('address'),
            'status'           => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        );
        $authRecordInfo = array(
            'uid'       => $uid,
            'auth_code' => 'enterprise',
            'status'    => 0
        );
        $fileId = !empty($request->get('file_id')) ? $request->get('file_id') : '';
        if(!empty($fileId)){
            $fileId = explode(',',$fileId);
        }
        $res = EnterpriseAuthModel::createEnterpriseAuth($companyInfo,$authRecordInfo,$fileId);
        if ($res) {
            return $this->formateResponse(1000, '保存成功');
        } else {
            return $this->formateResponse(1002, '保存失败');
        }


    }
}