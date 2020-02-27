<?php
namespace App\Modules\Im\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Requests;
use App\Modules\Im\Model\ImAttentionModel;
use App\Modules\Im\Model\ImMessageModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Auth;

class IndexController extends BasicController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('main');
    }

    /**
     * 查询用户聊天记录
     *
     * @param $uid
     * @return string
     */
    public function getMessage($uid)
    {
        $user = Auth::User();
        $uid = intval($uid);
        $list = ImMessageModel::where(['from_uid' => $user->id, 'to_uid' => $uid])
            ->orWhere(['to_uid' => $user->id, 'from_uid' => $uid])->orderBy('created_at', 'asc')
            ->leftjoin('users as ud','ud.id','=','im_message.from_uid')
            ->select('im_message.*','ud.name as from_username')
            ->get()->toArray();
        foreach($list as $k=>$v)
        {
            $list[$k]['created_at']  = date('Y/m/d H:i:s',strtotime($v['created_at']));
        }
        return \CommonClass::formatResponse('success', 200, $list);
    }

    /**
     * 新增临时联系人
     *
     * @param Request $request
     * @return string
     */
    public function addAttention(Request $request)
    {
        $user = Auth::User();

        $friend_uid = $request->get('toUid');
        $usersInfo = UserModel::select('name')->where('id', $friend_uid)->first();
        if (!empty($usersInfo)){
            $info = UserDetailModel::select('avatar', 'sign')->where('uid', $friend_uid)->first();

            $res = ImAttentionModel::where(['uid' => $user->id, 'friend_uid' => $friend_uid])->first();
            if (empty($res)){
                ImAttentionModel::insert([
                    [
                        'uid' => $friend_uid,
                        'friend_uid' => $user->id
                    ],
                    [
                        'uid' => $user->id,
                        'friend_uid' => $friend_uid
                    ]

                ]);
            }
            $data = [
                'name' => $usersInfo->name,
                'avatar' => $info->avatar,
                'friend_uid' => $friend_uid,
                'sign' => $info->sign ? $info->sign : '这家伙都懒的签名！'
            ];
            return \CommonClass::formatResponse('success', 200, $data);
        }

    }

    public function addMessageNumber(Request $request)
    {
        $fromUid = $request->get('fromUid');
        $toUid = $request->get('toUid');
        $number = $request->get('number');
        $num = ImAttentionModel::where('uid',$fromUid)->where('friend_uid',$toUid)->first();
        if($number == 0){
            if($num && $num->number > 0){
                $res = ImAttentionModel::where('uid',$fromUid)->where('friend_uid',$toUid)->update(['number' => 0]);
            }else{
                $res = 1;
            }
        }else{
            if($num){
                $res = ImAttentionModel::where('uid',$fromUid)->where('friend_uid',$toUid)->update(['number' => $num->number + 1]);
            }else{
                $res = 0;
            }
        }
        if($res){
            return \CommonClass::formatResponse('success', 200);
        }else{
            return \CommonClass::formatResponse('failure', 201);
        }

    }


    /**
     * 阿里云旺联系人列表
     * @param Request $request
     * @return string
     */
    public function imUserList(Request $request)
    {
        $uid = $request->get('fromUid');
        //是否申请阿里云旺聊天
        $messageConfig = [];
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);
        }
        if(!empty($messageConfig)) {
            $username = strval($uid);
            $c = new \TopClient();
            $c->appkey = isset($messageConfig['appkey']) ? $messageConfig['appkey'] : '';
            $c->secretKey = isset($messageConfig['secretKey']) ? $messageConfig['secretKey'] : '';

            $end = date('Ymd');
            $start = date('Ymd', strtotime("-30 day"));
            //查询用户聊天关系
            $req = new \OpenimRelationsGetRequest();
            $req->setBegDate($start);
            $req->setEndDate($end);
            $user = new \OpenImUser();
            $user->uid = $username;
            $req->setUser(json_encode($user));
            $resp = $c->execute($req);
            $openImUid = [];
            if (!empty($resp->users->open_im_user)) {
                foreach ($resp->users->open_im_user as $k => $v) {
                    $openImUid[] = $v->uid;
                }
            }
            $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();

            if (!empty($openImUid)) {
                $arrAttention = UserModel::select('users.id', 'users.name', 'user_detail.avatar', 'user_detail.autograph')->whereIn('users.id', $openImUid)
                    ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')->get()->toArray();
                if($arrAttention){
                    foreach($arrAttention as $k => $v){
                        $arrAttention[$k]['avatar'] = $v['avatar'] ? $domain->rule.'/'.$v['avatar'] : $v['avatar'];
                        $arrAttention[$k]['autograph'] = $v['autograph'] ?  mb_substr($v['autograph'], 0, 10, 'utf-8') :  '这家伙都懒的签名!';

                    }
                }
                return \CommonClass::formatResponse('success', 200,$arrAttention);
            }else{
                return \CommonClass::formatResponse('failure', 201);
            }
        }else{
            return \CommonClass::formatResponse('failure', 201);
        }
    }


    public function imBlade(Request $request)
    {
        $this->initTheme('ajaxpage');
        $data = [
            'fromUid' => $request->get('fromUid'),
            'fromAvatar' => $request->get('fromAvarar'),
            'toUid' => $request->get('toUid'),
            'toAvatar' => $request->get('toAvatar'),
            'toUsername' => $request->get('toUsername'),
        ];
        return $this->theme->scope('im.im',$data)->render();

    }

    /**
     * 获取聊天对象用户信息（方法使用中）
     * @param Request $request
     * @return string
     */
    public function getImUserInfo(Request $request)
    {
        $toUid = $request->get('toUid');
        $user = UserModel::where('users.id',$toUid)->select('users.name as username','user_detail.avatar','user_detail.sign')->leftJoin('user_detail','user_detail.uid','=','users.id')->first();
        $domainConfig = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        if($user){
            $data = [
                'username' => $user->username,
                'avatar' => $domainConfig->rule.'/'.$user->avatar,
                'sign' => $user->sign ? $user->sign : '这家伙都懒的签名！'
            ];
            return \CommonClass::formatResponse('success', 200,$data);
        }else{
            return \CommonClass::formatResponse('failure', 201);
        }
    }

}
