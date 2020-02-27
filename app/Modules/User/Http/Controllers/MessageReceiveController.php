<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\User\Model\MessageReceiveModel;
use Illuminate\Http\Request;
use Auth;

class MessageReceiveController extends UserCenterController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('userfinance');//主题初始化
    }

    /**
     * 我的消息列表
     * @param Request $request
     * @param $type 1=>系统消 2=>交易动态 3=>发件箱 4=>收件箱
     * @return mixed
     */
    public function messageList(Request $request,$type)
    {
        $this->initTheme('personalindex');
        $this->theme->setTitle('消息管理');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->set("userIndex",$type);
        $this->theme->set("userOneColumn","消息管理");
        $this->theme->set("userOneColumnUrl","/user/messageList/1");
        $this->theme->set("userSecondColumn",$type==1?"系统消息":"交易信息");
        $this->theme->set("userSecondColumnUrl","/user/messageList/".$type);
        $arr = $request->all();
        $user = Auth::User();
        $userId = $user['id'];
        if($request->get('message_id')){
            $readArr = array(
                'status' => 1,
                'read_time' => date('Y-m-d H:i:s',time())
            );
            MessageReceiveModel::where('id',$request->get('message_id'))->update($readArr);
        }
        $typeInt = intval($type);
        $arrayData = [1,2,3,4];
		switch($type){
		    case 1:
		        $this->theme->set("userIndex","14");
		        break;
		    case 2:
		        $this->theme->set("userIndex","15");
		        break;
			default:
				$this->theme->set("userIndex","1");
				break;
		}
        if(in_array($typeInt,$arrayData)){
            $type = $typeInt;
        }else{
            $type = $arrayData[0];
        }
        $messageCount1 = MessageReceiveModel::where('js_id', $userId)->where('message_type', 1)->where('status', 0)->count();
        $messageCount2 = MessageReceiveModel::where('js_id', $userId)->where('message_type', 2)->where('status', 0)->count();
        if($request->get('is_read') && $request->get('is_read') == 1)
        {
            switch ($type) {
                case 1:
                    $message = MessageReceiveModel::where('js_id', $userId)->where('message_type', 1)->where('status', 0)
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    $messageCount = $messageCount1;
                    break;
                case 2:
                    $message = MessageReceiveModel::where('js_id', $userId)->where('message_type', 2)->where('status', 0)
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = $messageCount2;
                    break;
                case 3:
                    $message = MessageReceiveModel::where('message_receive.fs_id', $userId)->where('message_receive.message_type', 3)->where('message_receive.status', 0)
                        ->leftJoin('users','users.id','=','message_receive.js_id')
                        ->select('message_receive.*','users.name as username')
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('fs_id', $userId)->where('message_type', 3)->where('status', 0)->count();
                    break;
                case 4:
                    $message = MessageReceiveModel::where('message_receive.js_id', $userId)->where('message_receive.message_type', 3)->where('message_receive.status', 0)
                        ->leftJoin('users','users.id','=','message_receive.fs_id')
                        ->select('message_receive.*','users.name as username')
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('js_id', $userId)->where('message_type', 3)->where('status', 0)->count();
                    break;
            }
        }elseif($request->get('is_read') && $request->get('is_read') == 2){
            switch ($type) {
                case 1:
                    $message = MessageReceiveModel::where('js_id', $userId)->where('message_type', 1)->where('status', 1)
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    $messageCount = $messageCount1;
                    break;
                case 2:
                    $message = MessageReceiveModel::where('js_id', $userId)->where('message_type', 2)->where('status', 1)
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = $messageCount2;
                    break;
                case 3:
                    $message = MessageReceiveModel::where('message_receive.fs_id', $userId)->where('message_receive.message_type', 3)->where('message_receive.status', 1)
                        ->leftJoin('users','users.id','=','message_receive.js_id')
                        ->select('message_receive.*','users.name as username')
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('fs_id', $userId)->where('message_type', 3)->where('status', 0)->count();
                    break;
                case 4:
                    $message = MessageReceiveModel::where('message_receive.js_id', $userId)->where('message_receive.message_type', 3)->where('message_receive.status', 1)
                        ->leftJoin('users','users.id','=','message_receive.fs_id')
                        ->select('message_receive.*','users.name as username')
                        ->orderBy('receive_time', 'DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('js_id', $userId)->where('message_type', 3)->where('status', 0)->count();
                    break;
            }
        } else
        {
            switch($type)
            {
                case 1:
                    $message = MessageReceiveModel::where('js_id',$userId)->where('message_type',1)
                        ->orderBy('receive_time','DESC')->paginate(10);
                    $messageCount = $messageCount1;
                    break;
                case 2:
                    $message = MessageReceiveModel::where('js_id',$userId)->where('message_type',2)
                        ->orderBy('receive_time','DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = $messageCount2;
                    break;
                case 3:
                    $message = MessageReceiveModel::where('message_receive.fs_id',$userId)->where('message_receive.message_type',3)
                        ->leftJoin('users','users.id','=','message_receive.js_id')
                        ->select('message_receive.*','users.name as username')
                        ->orderBy('receive_time','DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('fs_id',$userId)->where('message_type',3)->where('status',0)->count();
                    break;
                case 4:
                    $message = MessageReceiveModel::where('message_receive.js_id',$userId)->where('message_receive.message_type',3)
                        ->leftJoin('users','users.id','=','message_receive.fs_id')
                        ->select('message_receive.*','users.name as username')
                        ->orderBy('receive_time','DESC')->paginate(10);
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('js_id',$userId)->where('message_type',3)->where('status',0)->count();
                    break;
            }
        }

        $view = array(
            'merge'     => $arr,
            'message'   => $message,
            'type'      => $type,
            'uid'       => $userId,
            'new_count' => $messageCount,
            'is_read'   => $request->get('is_read')?$request->get('is_read'):0
        );
        $this->theme->set('sysMessageCount', $messageCount1);
        $this->theme->set('trdMessageCount', $messageCount2);
        return $this->theme->scope('user.messagelist', $view)->render();
    }

    /**
     * 修改消息读取状态 （废弃方法）
     * @param int $id 消息id
     * @param $type  1=>系统消 4=>收件箱  2=>交易动态 3=>发件箱
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changeStatus($id,$type)
    {
        $id = intval($id);
        $data = array(
            'status' => 1,
            'read_time' => date('Y-m-d H:i:s',time())
        );
        $res = MessageReceiveModel::where('id',$id)->update($data);
        if($res)
        {
            return redirect('/user/messageList/'.$type);
        }
        else
        {
            return redirect('/user/messageList/'.$type);
        }
    }

    /**
     * 立即查看
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function readMessage($id)
    {
        $id = intval($id);
        $data = array(
            'status' => 1,
            'read_time' => date('Y-m-d H:i:s',time())
        );
        $res = MessageReceiveModel::where('id',$id)->update($data);
        $info = MessageReceiveModel::find($id);
        if($res) {
            return redirect('/user/messageList/'.$info['message_type']);
        }
        else {
            return redirect('/user/messageList/'.$info['message_type']);
        }
    }

    /**
     * 修改消息读取状态
     * @param Request $request
     * @return mixed
     */
    public function postChangeStatus(Request $request)
    {
        $id =$request->get('id');
        if(!empty($id)){
            $data = array(
                'status' => 1,
                'read_time' => date('Y-m-d H:i:s',time())
            );
            $res = MessageReceiveModel::where('id',$id)->update($data);
            if(!empty($res)){
                $data = array(
                    'code' => 1,
                    'msg' => '修改成功'
                );
            }else{
                $data = array(
                    'code' => 0,
                    'msg' => '修改失败'
                );
            }
        }else{
            $data = array(
                'code' => 0,
                'msg' => '缺少参数'
            );
        }
        return response()->json($data);
    }

    /**批量删除或修改读取状态
     * @param $ids 消息ID数组
     * @param $status 1=>已读 2=>删除
     * @param $type 1=>系统消和收件箱  2=>交易动态 3=>发件箱
     * @return \Illuminate\Http\RedirectResponse
     */
    public function allChange(Request $request)
    {
        $arr = $request->all();
        $status = $arr['status'];
        $ids = json_decode($arr['ids']);
        switch($status)
        {
            case 1:
                $data = array(
                    'status' => 1,
                    'read_time' => date('Y-m-d H:i:s',time())
                );
                $res = MessageReceiveModel::whereIn('id',$ids)->update($data);
                if($res)
                {
                    return \GuzzleHttp\json_encode(array(
                        'code' => 1,
                        'msg' => '操作成功'
                    ));
                }
                else
                {
                    return \GuzzleHttp\json_encode(array(
                        'code' => 0,
                        'msg' => '操作失败'
                    ));
                }
                break;

            case 2:
                $res = MessageReceiveModel::destroy($ids);
                if($res)
                {
                    return \GuzzleHttp\json_encode(array(
                        'code' => 1,
                        'msg' => '操作成功'
                    ));
                }
                else
                {
                    return \GuzzleHttp\json_encode(array(
                        'code' => 0,
                        'msg' => '操作失败'
                    ));
                }
                break;
        }
    }

    /**
     * 回复
     * @param Request $request
     */
    public function contactMe(Request $request)
    {
        $data = $request->all();
        $user = Auth::User();
        $userId = $user['id'];
        $arr = array(
            'message_title' => $data['title'],
            'message_content' => $data['content'],
            'message_type' => 3,
            'fs_id' => $userId,
            'js_id' => $data['js_id'],
            'receive_time' => date('Y-m-d H:i:s',time())
        );
        $res = MessageReceiveModel::create($arr);
        if($res)
        {
            return \GuzzleHttp\json_encode(array(
                'code' => 1,
                'msg' => '操作成功'
            ));
        }
        else
        {
            return \GuzzleHttp\json_encode(array(
                'code' => 0,
                'msg' => '操作失败'
            ));
        }

    }

}
