<?php

namespace App\Modules\User\Model;

use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EnterpriseAuthModel extends Model
{
    protected $table = 'enterprise_auth';
    //
    protected $fillable = [
        'id',
        'uid',
        'company_name',
        'cate_id',
        'employee_num',
        'business_license',
        'begin_at',
        'website',
        'province',
        'city',
        'area',
        'address',
        'status',
        'auth_time',
        'created_at',
        'updated_at',
        'license_img',
        'legal_person',
        'card_number',
        'card_front_side',
        'card_back_dside',
        'validation_img',
        'reason'
    ];


    /**
     * 判断用户是否进行企业认证
     * @param $uid  用户id
     * @return bool
     */
    static function isEnterpriseAuth($uid){
        $companyInfo = EnterpriseAuthModel::where('uid', $uid)->where('status',1)->first();
        $companyAuth = AuthRecordModel::where('uid',$uid)->where('status',1)->where('auth_code','enterprise')->first();
        if($companyInfo && $companyAuth){
            return true;
        }else{
            return false;
        }
    }


    /**
     * 获取用户企业认证状态
     *
     * @param $uid
     * @return null
     */
    static function getEnterpriseAuthStatus($uid)
    {
        $companyInfo = EnterpriseAuthModel::where('uid', $uid)->first();
        if ($companyInfo) {
            return $companyInfo->status;
        }
        return null;
    }

    /**
     * 后台获取认证详情
     * @param $id
     * @return null
     */
    static function getEnterpriseInfo($id)
    {
        //认证详情
        $companyInfo = EnterpriseAuthModel::where('id', $id)->first();
        $companyInfo['cate_name'] = '';
        $companyInfo['cate_parent_name'] = '';
        //企业行业分类
        if($companyInfo->cate_id){
            $cateInfo = TaskCateModel::findById($companyInfo->cate_id);
            if(!empty($cateInfo)){
                $companyInfo['cate_name'] = $cateInfo['name'];
                //查询上级行业分类
                $parentCate= TaskCateModel::findById($cateInfo['pid']);
                if(!empty($parentCate)){
                    $companyInfo['cate_parent_name'] = $parentCate['name'];
                }
            }
        }
        //企业经营地址
        $companyInfo['province_name'] = '';
        if($companyInfo->province){
            $province = DistrictModel::where('id',$companyInfo->province)->first();
            if($province){
                $companyInfo['province_name'] = $province->name;
            }

        }
        $companyInfo['city_name'] = '';
        if($companyInfo->city){
            $city = DistrictModel::where('id',$companyInfo->city)->first();
            if($city){
                $companyInfo['city_name'] = $city->name;
            }
        }
        $companyInfo['area_name'] = '';
        if($companyInfo->area && $companyInfo->area != 0){
            $area = DistrictModel::where('id',$companyInfo->area)->first();
            if($area){
                $companyInfo['area_name'] = $area->name;
            }
        }
        //认证附件查询
        $attachment = UnionAttachmentModel::where('object_id',$companyInfo->uid)
            ->where('object_type',1)->get()->toArray();
        if(!empty($attachment)){
            $attachmentId = array();
            foreach($attachment as $k => $v){
                $attachmentId[] = $v['attachment_id'];
            }
            //查询附件表
            $attachmentInfo = AttachmentModel::whereIn('id',$attachmentId)->get()->toarray();
            if(!empty($attachmentInfo)){
                $companyInfo['attachement'] = $attachmentInfo;
            }
        }else{
            $companyInfo['attachement'] = array();
        }
        return $companyInfo;

    }



    /**
     * 新增企业认证
     *
     * @param $companyInfo
     * @param $authRecordInfo
     * @return bool
     */
    static function createEnterpriseAuth($companyInfo, $authRecordInfo,$fileId)
    {
        $status = DB::transaction(function () use ($companyInfo, $authRecordInfo,$fileId) {
            //查询企业认证是否存在
            $enterprise=EnterpriseAuthModel::where("uid",$companyInfo['uid'])->first();
            if($enterprise){
                $companyInfo['status']=0;
                 DB::table('enterprise_auth')->where("uid",$companyInfo['uid'])->update($companyInfo);
                 $authRecordInfo['auth_id'] = $enterprise['id'];
            }else{
                $authRecordInfo['auth_id'] = DB::table('enterprise_auth')->insertGetId($companyInfo);
            }

            DB::table('auth_record')->insert($authRecordInfo);
//            if (!empty($fileId)) {
//               //查询认证的附件记录，排除掉店铺删除的附件记录
//               $fileAbleIds = AttachmentModel::fileAble($fileId);
//               $fileAbleIds = array_flatten($fileAbleIds);
//               foreach ($fileAbleIds as $v) {
//                   $attachmentData = array(
//                       'object_id'     => $companyInfo['uid'],
//                       'object_type'   => 1,
//                       'attachment_id' => $v,
//                       'created_at'    => date('Y-m-d H:i:s', time())
//                   );
//                   UnionAttachmentModel::create($attachmentData);
//               }
//               //修改附件的发布状态
//               $attachmentModel = new AttachmentModel();
//               $attachmentModel->statusChange($fileAbleIds);
//           }
        });
        return is_null($status) ? true : $status;
    }
    /**
     * 前台用户取消企业认证
     *
     * @return bool
     */
    static function removeEnterpriseAuth()
    {
        $status = DB::transaction(function () {
            $user = Auth::User();
            EnterpriseAuthModel::where('uid', $user->id)->delete();
            AuthRecordModel::where('auth_code', 'enterprise')->where('uid', $user->id)->delete();
        });
        return is_null($status) ? true : $status;
    }

    /**
     * 后台审核通过企业认证
     *
     * @param $id
     * @return bool
     */
    static function EnterpriseAuthPass($id)
    {
        $status = DB::transaction(function () use ($id) {
            EnterpriseAuthModel::where('id', $id)->update(array('status' => 1, 'auth_time' => date('Y-m-d H:i:s')));
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'enterprise')
                ->update(array('status' => 1, 'auth_time' => date('Y-m-d H:i:s')));
            $info = EnterpriseAuthModel::find($id);
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('emterprise_auth_success',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }

    /**
     * 后台审核失败企业认证
     *
     * @param $id
     * @return bool
     */
    static function EnterpriseAuthDeny($id,$reason='')
    {
        $status = DB::transaction(function () use ($id,$reason) {
            EnterpriseAuthModel::where('id', $id)->update(array('status' => 2,'reason' => $reason));
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'enterprise')
                ->update(array('status' => 2));
            $info = EnterpriseAuthModel::find($id);
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name,
                'reason'  =>$reason,
            ];
            \MessageTemplateClass::sendMessage('emterprise_auth_failure',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }

    /**
     * 后台批量审核通过企业认证
     * @param $idArr
     * @return bool
     */
    static function AllEnterpriseAuthPass($idArr)
    {
        //查询批量操作的id数组是否待审核状态
        $res = EnterpriseAuthModel::whereIn('id',$idArr)->get()->toArray();
        if(!empty($res) && is_array($res)){
            $id = array();
            foreach($res as $k => $v){
                if($v['status'] == 0){
                    $id[] = $v['id'];
                }
            }
        }else{
            $id = array();
        }
        $status = DB::transaction(function () use ($id) {
            EnterpriseAuthModel::whereIn('id', $id)->update(array('status' => 1, 'auth_time' => date('Y-m-d H:i:s')));
            AuthRecordModel::whereIn('auth_id', $id)
                ->where('auth_code', 'enterprise')
                ->update(array('status' => 1, 'auth_time' => date('Y-m-d H:i:s')));
        });

        return is_null($status) ? true : $status;
    }


    /**
     * 后台批量审核失败企业认证
     *
     * @param $idArr
     * @return bool
     */
    static function AllEnterpriseAuthDeny($idArr)
    {
        //查询批量操作的id数组是否待审核状态
        $res = EnterpriseAuthModel::whereIn('id',$idArr)->get()->toArray();
        if(!empty($res) && is_array($res)){
            $id = array();
            foreach($res as $k => $v){
                if($v['status'] == 0){
                    $id[] = $v['id'];
                }
            }
        }else{
            $id = array();
        }
        $status = DB::transaction(function () use ($id) {
            EnterpriseAuthModel::whereIn('id', $id)->update(array('status' => 2));
            AuthRecordModel::whereIn('auth_id', $id)
                ->where('auth_code', 'enterprise')
                ->update(array('status' => 2));
        });

        return is_null($status) ? true : $status;
    }
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
//        if ($card_front_side) {
//            $uploadMsg = json_decode(\FileClass::uploadFile($card_front_side, 'user', $allowExtension));
//            if ($uploadMsg->code != 200) {
//                $error['card_front_side'] = $uploadMsg->message;
//            } else {
//                $realnameInfo['card_front_side'] = $uploadMsg->data->url;
//            }
//        }
//        //背面照
//        if ($card_back_dside) {
//            $uploadMsg = json_decode(\FileClass::uploadFile($card_back_dside, 'user', $allowExtension));
//            if ($uploadMsg->code != 200) {
//                $error['card_back_dside'] = $uploadMsg->message;
//            } else {
//                $realnameInfo['card_back_dside'] = $uploadMsg->data->url;
//            }
//        }
//        //手持身份照
//        if ($validation_img) {
//            $uploadMsg = json_decode(\FileClass::uploadFile($validation_img, 'user', $allowExtension));
//            if ($uploadMsg->code != 200) {
//                $error['validation_img'] = $uploadMsg->message;
//            } else {
//                $realnameInfo['validation_img'] = $uploadMsg->data->url;
//            }
//        }
        //营业执照
        if ($validation_img) {
            $uploadMsg = json_decode(\FileClass::uploadFile($validation_img, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                $error['license_img'] = $uploadMsg->message;
            } else {
                $realnameInfo['license_img'] = $uploadMsg->data->url;
            }
        }
        if (!empty($error)) {
            $error = array_values($error);
            $error = ['error' => $error[0]];
            return redirect()->back()->with($error);
        }

        $user = Auth::User();

        $now = time();

        $realnameInfo['uid'] = $user->id;
        //$realnameInfo['username'] = $user->name;
       // $realnameInfo['legal_person'] = $request->get('realname');
       // $realnameInfo['card_number'] = $request->get('card_number');
        $realnameInfo['created_at'] = date('Y-m-d H:i:s', $now);
        $realnameInfo['updated_at'] = date('Y-m-d H:i:s', $now);
        $realnameInfo['company_name']=$request->get("com_name")?$request->get("com_name"):'';
        $realnameInfo['business_license']=$request->get("business_num")?$request->get("business_num"):'';
        self::create($realnameInfo);
        //$authRecordInfo['uid'] = $user->id;
       // $authRecordInfo['username'] = $user->name;
        ////$authRecordInfo['auth_code'] = 'realname';
        //$RealnameAuthModel = new RealnameAuthModel();
       // return $RealnameAuthModel->createRealnameAuth($realnameInfo, $authRecordInfo);
    }

}
