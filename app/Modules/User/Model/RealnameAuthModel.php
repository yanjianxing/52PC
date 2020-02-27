<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class RealnameAuthModel extends Model
{
    protected $table = 'realname_auth';
    //
    protected $fillable = [
        'uid', 'username','com_name','business_num','business_pic', 'card_front_side', 'card_back_dside', 'validation_img', 'status', 'auth_time','card_type','type','realname','card_number','reason','end_time','effective'
    ];

    /**
     * 获取用户实名认证状态
     *
     * @param $uid
     * @return null
     */
    static function getRealnameAuthStatus($uid)
    {
        $realnameInfo = RealnameAuthModel::where('uid', $uid)->first();
        if ($realnameInfo) {
            return $realnameInfo->status;
        }
        return null;
    }

    public $transactionData;

    //身份认证数据提交
   static public function realnameAuthPost($request){
        $card_front_side = $request->file('card_front_side');
        $card_back_dside = $request->file('card_back_dside');
        $validation_img = $request->file('validation_img');
        $business_pic=$request->file('business_pic');
        $realnameInfo = array();
        $authRecordInfo = array();
        $error = array();
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png','JPG', 'GIF', 'JPEG', 'BMP', 'PNG');
        //正面照
        if ($card_front_side) {
            $uploadMsg = json_decode(\FileClass::uploadFile($card_front_side, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['card_front_side'] = $uploadMsg->message;
            } else {
                $realnameInfo['card_front_side'] = $uploadMsg->data->url;
            }
        }
        //背面照
        if ($card_back_dside) {
            $uploadMsg = json_decode(\FileClass::uploadFile($card_back_dside, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['card_back_dside'] = $uploadMsg->message;
            } else {
                $realnameInfo['card_back_dside'] = $uploadMsg->data->url;
            }
        }
        //手持身份照
        if ($validation_img) {
            $uploadMsg = json_decode(\FileClass::uploadFile($validation_img, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['validation_img'] = $uploadMsg->message;
            } else {
                $realnameInfo['validation_img'] = $uploadMsg->data->url;
            }
        }
        //营业执照
        if ($business_pic) {
            $uploadMsg = json_decode(\FileClass::uploadFile($business_pic, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['business_pic'] = $uploadMsg->message;
            } else {
                $realnameInfo['license_img'] = $uploadMsg->data->url;
            }
        }
       $user = Auth::User();

       $now = time();
       if($request->get("com_name")){
           $realnameInfo['company_name'] = $request->get("com_name");
           $realnameInfo['legal_person'] = $request->get('realname');
       }else{
           $realnameInfo['realname'] = $request->get('realname');
       }
       if($request->get("business_num")){
           $realnameInfo['business_license'] = $request->get("business_num");
       }
        if (!empty($error)) {
            $error = array_values($error);
            $error = ['error' => $error[0]];
            return redirect()->back()->with($error);
        }
        $realnameInfo['uid'] = $user->id;
        $realnameInfo['username'] = $user->name;
        $realnameInfo['card_number'] = $request->get('card_number');
        $realnameInfo['created_at'] = date('Y-m-d H:i:s', $now);
        $realnameInfo['updated_at'] = date('Y-m-d H:i:s', $now);
        $authRecordInfo['uid'] = $user->id;
        $authRecordInfo['username'] = $user->name;
        $authRecordInfo['auth_code'] = $request->get("com_name")?"enterprise":'realname';

       if($request->get("com_name")){
           $check_comname =  EnterpriseAuthModel::where('business_license',$realnameInfo['business_license'])->whereIn('status',[0,1])->first();
           if($check_comname){
                return redirect()->back()->with(["message"=>'该营业执照编号已经认证别的账号！不能重复认证！']);
           }
           return EnterpriseAuthModel::createEnterpriseAuth($realnameInfo,$authRecordInfo,$fileId='');
       }else{
            $check_realname =  RealnameAuthModel::where('card_number',$realnameInfo['card_number'])->whereIn('status',[0,1])->first();
               if($check_realname){
                    return redirect()->back()->with(["message"=>'该证件号码已经认证别的账号！不能重复认证！']);
               }
           $realnameInfo['end_time'] = $request->get('end_time');
           $realnameInfo['effective'] = $request->get('effective');
           $RealnameAuthModel = new RealnameAuthModel();
           return $RealnameAuthModel->createRealnameAuth($realnameInfo,$authRecordInfo);
       }

   }
    /**
     * 新增身份认证
     *
     * @param $realnameInfo
     * @param $authRecordInfo
     * @return bool
     */
    public function createRealnameAuth($realnameInfo,$authRecordInfo)
    {
        $status = DB::transaction(function () use ($realnameInfo,$authRecordInfo) {
            $realname = RealnameAuthModel::where("uid",$realnameInfo['uid'])->first();
            if($realname){
                $realnameInfo['status'] = 0;
                DB::table('realname_auth')->where("uid",$realnameInfo['uid'])->update($realnameInfo);
                $authRecordInfo['auth_id'] = $realname['id'];
            }else{
                $authRecordInfo['auth_id'] = DB::table('realname_auth')->insertGetId($realnameInfo);

            }
            DB::table('auth_record')->insert($authRecordInfo);
        });
        return is_null($status) ? true : $status;
    }

    /**
     * 前台用户取消实名认证
     *
     * @param $id
     * @return bool
     */
    public function removeRealnameAuth()
    {
        $status = DB::transaction(function () {
            $user = Auth::User();
            RealnameAuthModel::where('uid', $user->id)->delete();
            AuthRecordModel::where('auth_code', 'realname')->where('uid', $user->id)->delete();
        });
        return is_null($status) ? true : $status;
    }

    /**
     * 后台审核通过实名认证
     *
     * @param $id
     * @return bool
     */
    static function realnameAuthPass($id)
    {
        $status = DB::transaction(function () use ($id) {
            $realnameAuth = RealnameAuthModel::find($id);
            $realnameAuth->update(array('status' => 1, 'auth_time' => date('Y-m-d H:i:s')));
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'realname')
                ->update(array('status' => 1, 'auth_time' => date('Y-m-d H:i:s')));

            UserDetailModel::where('uid',$realnameAuth->uid)->update(array('realname' => $realnameAuth->realname, 'updated_at' => date('Y-m-d H:i:s')));

            $userInfo = UserModel::where('id',$realnameAuth->uid)->select('id','email','name','mobile')->first();
            $user = [
                'uid'    => $realnameAuth->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo['mobile']
            ];
            \MessageTemplateClass::sendMessage('realname_auth_success',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }

    /**
     * 后台审核失败实名认证
     *
     * @param $id
     * @return bool
     */
    static function realnameAuthDeny($id,$reason='')
    {
        $status = DB::transaction(function () use ($id,$reason) {
            RealnameAuthModel::where('id', $id)->update([
                'status' => 2,
                'reason' => $reason
            ]);
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'realname')
                ->update(array('status' => 2));
            $realnameAuth = RealnameAuthModel::find($id);
            $userInfo = UserModel::where('id',$realnameAuth->uid)->first();
            $user = [
                'uid'    => $realnameAuth->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->mobile
            ];
            \MessageTemplateClass::sendMessage('realname_auth_failure',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }


}
