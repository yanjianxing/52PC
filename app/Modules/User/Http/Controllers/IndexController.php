<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\Manage\Http\Controllers\ConfigController;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\SpaceModel;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Validator;
use Crypt;
use Storage;

class IndexController extends UserCenterController
{

    /**
     * 创建用户
     *
     */
    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|sometimes|email',
            'password' => 'required|alpha_dash'
        ]);
        if ($validator->fails()) {
            return Response('参数错误');
        }
        $userInfo = array(
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Crypt::encrypt($request->get('password'))
        );

        User::create($userInfo);
        return Response('创建成功');
    }

    /**
     * 修改支付提示状态
     * @param Request $request
     * @return mixed
     */
    public function updateTips(Request $request)
    {
        $user = Auth::user();
        $arr = array(
            'alternate_tips' => 1
        );
        $res = UserDetailModel::where('uid',$user->id)->update($arr);
        if($res){
            $data = array(
                'code' => 1,
                'msg' => 'success'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => 'failure'
            );
        }
        return response()->json($data);

    }

    //用户上传头像
    public function ajaxChangeAvatar(Request $request)
    {
        $user = Auth::user();
        $data = $request->except('_token');
        $file = (object)$data;

        //上传图片
        $result = \FileClass::headUpload($file, $user['id']);

        $result = json_decode($result, true);
        $avatar = $result['data']['url'];
        $arr = array(
            'avatar' => $avatar
        );
        $res = UserDetailModel::where('uid',$user['id'])->update($arr);
        if($res){
            $data = array(
                'code' => 1,
                'msg' => '上传成功'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => '上传失败'
            );
        }
        return json_encode($data);

    }


    /**
     * 检测手机号是否占用
     *
     * @param Request $request
     * @return string
     */
    public function checkMobile(Request $request)
    {
        $mobile = $request->get('param');
        $status = UserModel::where('mobile', $mobile)->first();
        $detail = UserDetailModel::where('mobile', $mobile)->first();
        if (empty($status) && empty($detail)){
            //手机绑定模板
            $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
            $templateId = ConfigModel::phpSmsConfig('sendMobileCode');
            $templates = [
                $scheme => $templateId,
            ];
            $tempData = [
                'code' => rand(1000, 9999),
                'time' => '5'
            ];
            $status = \SmsClass::sendSms($mobile, $templates, $tempData);
            if ($status['success']){
                $info = [
                    'mobile' => $mobile,
                    'code' => $tempData['code']
                ];
                Session::put('mobile_bind_info', $info);
                $info = '短信发送成功';
                $status = '1000';
            } else {
                $info = '短信发送失败';
                $status = '1001';
            }
        } else {
            $info = '手机已占用';
            $status = '1002';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }
}
