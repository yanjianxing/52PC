<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Http\Requests;
use App\Modules\Manage\Model\MessageTemplateModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->setTitle('短信模版管理');
        $this->theme->set('manageType', 'message');

    }

    /**
     * 消息模板列表
     * @param Request $request
     * @return mixed
     */
    public function messageList(Request $request)
    {
        $message = MessageTemplateModel::orderBy('id','asc')->paginate(15);
        $data = array(
            'message_list' => $message
        );
        $this->theme->setTitle('消息模板');
        return $this->theme->scope('manage.messagelist', $data)->render();
    }

    /**
     * 编辑短信模板视图
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function editMessage(Request $request,$id)
    {
        $id = intval($id);
        $messageInfo = MessageTemplateModel::where('id',$id)->first();
        //查询所有短信模板
        $message = MessageTemplateModel::get()->toArray();
        $data = array(
            'message_info'  => $messageInfo,
            'message'       => $message,
            'id'            => $id
        );
        return $this->theme->scope('manage.editmessage', $data)->render();
    }

    /**
     * 编辑短信模板
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEditMessage(Request $request)
    {
        $data = $request->except('_token');
        $data['content'] = htmlspecialchars($data['content']);
        if(mb_strlen($data['content']) > 4294967295/3){
            $error['content'] = '内容太长，建议减少上传图片';
            if (!empty($error)) {
                return redirect('/manage/editMessage/'.$data['id'])->withErrors($error);
            }
        }
        $rule = "/\{\{[\\w](.*?)\}\}/";
        preg_match_all($rule,$data['content'],$matches);
        $params = empty($matches[0])?:array_unique($matches[0]);
        $number = count($params);
        if(!empty($params) && is_array($params)){
            foreach($params as $k => $v)
            {
                $params[$k] = substr($v,2,-2);
            }
            $variableStr = implode($params,',');
        }else{
            $variableStr = '';
        }
        $arr = array(
            'name'                  => $data['name'],
            'code_name'             => $data['code_name'],
            'content'               => $data['content'],
            'code_mobile'           => $data['code_mobile'],
            'mobile_code_content'   => $data['mobile_code_content'],
            'num'                   => $number,
            'variable_str'          => $variableStr,
            'updated_at'            => date('Y-m-d H:i:s',time())
        );
        $res = MessageTemplateModel::where('id',$data['id'])->update($arr);
        if($res)
        {
            return redirect('/manage/messageList')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 改变基本配置
     * @param Request $request
     * @param $id
     * @param $isName 1=是否开启 2=站内短信 3=发送邮件
     * @param $status
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changeStatus(Request $request,$id,$isName,$status)
    {
        $id = intval($id);
        $isName = intval($isName);
        switch($isName) {
            case 1:
                $arr = array(
                    'is_open' => $status,
                    'updated_at' => date('Y-m-d H:i:s',time())
                );
                $res = MessageTemplateModel::where('id',$id)->update($arr);
                break;
            case 2:
                $arr = array(
                    'is_on_site' => $status,
                    'updated_at' => date('Y-m-d H:i:s',time())
                );
                $res = MessageTemplateModel::where('id',$id)->update($arr);
                break;
            case 3:
                $arr = array(
                    'is_send_email' => $status,
                    'updated_at' => date('Y-m-d H:i:s',time())
                );
                $res = MessageTemplateModel::where('id',$id)->update($arr);
                break;
            case 4:
                $arr = array(
                    'is_send_mobile' => $status,
                    'updated_at' => date('Y-m-d H:i:s',time())
                );
                $res = MessageTemplateModel::where('id',$id)->update($arr);
                break;

        }
        if(isset($res) &&$res) {
            return redirect()->back()->with(array('message' => '操作成功'));
        }
        else {
            return redirect()->back()->with(array('message' => '操作失败'));
        }
    }

    /**
     * 站内群发消息
     * @param Request $request
     * @return mixed
     */
    public function messageSite(Request $request)
    {
        //获取行业标签
        $industry = CateModel::where("type",1)->select("id","name")->get();
        //获取技能标签
        $skill = CateModel::where("type",2)->select("id","name")->get();
        //获取会员等级
        $vipGrade = VipModel::where("status",2)->select("name","id","grade")->get();
        $authtype = [
                        '1'=>'个人',
                        '2'=>'企业'
                    ];
        //查询地区
        $province=DistrictModel::where("upid",0)->get();
        $city=DistrictModel::where("upid",$province[0]->id)->get();
        //获取用户列表
        $userList = [];//UserModel::select("id","name")->get();

        $data = array(
            'industry'  => $industry,
            'skill'     => $skill,
            'userList'  => $userList,
            'vipGrade'  => $vipGrade,
            'authtype'  => $authtype,
            'province'  => $province,
            'city'  => $city,
        );
        $this->theme->setTitle('站内群发消息');
        return $this->theme->scope('manage.messagesite', $data)->render();
    }

    /**
     * 站内信发送查看
     * @param Request $request
     * @return array
     */
    public function searchSite(Request $request)
    {
        $merge = $request->all();
        $industry = $merge['industry'] ? $merge['industry'] : '';
        $skill   = $merge['skill'] ? $merge['skill'] : '';
        $shopId=ShopTagsModel::whereRaw(" 1=1");
        $arrres = [];
        if(!empty($industry) && !empty($skill) ){
            $arrres = [$merge['industry'],$merge['skill']];
        }elseif(!empty($industry)){
            $arrres = [$merge['industry']];
        }elseif(!empty($skill)){
            $arrres = [$merge['skill']];
        }

        if(count($arrres) > 0){
            $shopId = $shopId->whereIn("cate_id",$arrres)->distinct()->lists("shop_id")->toArray();
        }else{
            $shopId = $shopId->distinct()->lists("shop_id")->toArray();
        }
            
        
        //获取用户id
        $userId=UserModel::leftJoin('shop','users.id','=','shop.uid');
        if(!empty($merge['level'])){
            $userId = $userId->where("users.level",'=',$merge['level']);
        }
        if(!empty($merge['auth_type'])){
            $userId = $userId->where("shop.type",'=',$merge['auth_type']);
        }
        if(!empty($merge['province'])){
            $userId = $userId->where("shop.province",'=',$merge['province']);
        }
        
        $userId = $userId->whereIn("shop.id",$shopId)->count();
        return count($userId)>0?$userId:0;

    }

    /**
     * 站内信发送查看
     * @param Request $request
     * @return array
     */
    public function SitePush(Request $request)
    {
        $merge = $request->except("_token");
        if($merge['industry'] == '0' && $merge['skill'] == '0' && $merge['level'] == '0' && $merge['auth_type'] == '0' && $merge['province'] == '0'){
            return back()->with(["message"=>"请选择至少一个条件"]);
        }
        if($merge['title'] == '' || $merge['coutents'] == '请填写发送内容'){
            return back()->with(["message"=>"请填写完整发送内容，标题"]);
        }
        $industry = $merge['industry'] ? $merge['industry'] : '';
        $skill   = $merge['skill'] ? $merge['skill'] : '';
        $shopId=ShopTagsModel::whereRaw(" 1=1");
        $arrres = [];
        if(!empty($industry) && !empty($skill) ){
            $arrres = [$merge['industry'],$merge['skill']];
        }elseif(!empty($industry)){
            $arrres = [$merge['industry']];
        }elseif(!empty($skill)){
            $arrres = [$merge['skill']];
        }
        if(count($arrres) > 0){
            $shopId = $shopId->whereIn("cate_id",$arrres)->distinct()->lists("shop_id")->toArray();
        }else{
            $shopId = $shopId->distinct()->lists("shop_id")->toArray();
        }
        //获取用户id
        $userId=UserModel::leftJoin('shop','users.id','=','shop.uid');
        if(!empty($merge['level'])){
            $userId = $userId->where("users.level",'=',$merge['level']);
        }
        if(!empty($merge['auth_type'])){
            $userId = $userId->where("shop.type",'=',$merge['auth_type']);
        }
        if(!empty($merge['province'])){
            $userId = $userId->where("shop.province",'=',$merge['province']);
        }
        
        $userId = $userId->whereIn("shop.id",$shopId)->lists("users.id")->toArray();
        
        foreach ($userId as $val) {
            //获取信息内容
            if(isset($merge['title']) && !empty($merge['title'])){
                $data = [
                    'message_title'   => $merge['title'],
                    'message_content' => $merge['coutents'],
                    'js_id'           => $val,
                    'message_type'    => 1,
                    'receive_time'    => date('Y-m-d H:i:s',time()),
                    'status'          => 0,
                ];
                $res = MessageReceiveModel::create($data);
                if($res){
                    return back()->with(["message"=>"发送成功"]);
                }
                return back()->with(["message"=>"发送失败"]);
            }
        }
        return back()->with(["message"=>"发送失败"]);

    }

}

