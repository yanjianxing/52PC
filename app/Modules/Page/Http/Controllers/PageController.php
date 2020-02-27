<?php
namespace App\Modules\Page\Http\Controllers;

use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Support\Facades\Session;
use App\Modules\User\Model\UserModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\Shop\Models\ShopModel;
use Illuminate\Support\Facades\DB;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use Auth;
use Cookie;
use Illuminate\Http\Request;

class PageController extends BasicIndexController
{
    
    public function yingjian(request $request){
    	$this->initTheme('hardware');
    	$this->theme->setTitle('硬件开发');
    	$fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
    	$province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 硬件开发需求列表
        $hardwareList = TaskModel::select('task.id','task.bounty','task.title','task.delivery_count','users.name as uname')
                        ->whereNotIn('task.field_id',[13])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.delivery_count','desc')->limit(12)->get()->toArray();
        // print_r($hardwareList);
        $data = [
        	'fieldArr'       => $fieldArr,
        	'province'       => $province,
            'city'           => $city,
            'hardwareList'   => $hardwareList,
        ];
        return $this->theme->scope('page.hardware', $data)->render();
    }

    public function ruanjian(request $request){
        $this->initTheme('hardware');
        $this->theme->setTitle('软件开发');
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 硬件开发需求列表
        $softwareList = TaskModel::select('task.id','task.bounty','task.title','task.view_count','task.delivery_count','users.name as uname')
                        ->whereIn('task.field_id',[13])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.delivery_count','desc')->limit(12)->get()->toArray();
        // print_r($hardwareList);
        $data = [
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'softwareList'   => $softwareList,
        ];
        return $this->theme->scope('page.software', $data)->render();
    }

    public function app(request $request){
        $this->initTheme('hardware');
        $this->theme->setTitle('app软件开发');
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 硬件开发需求列表
        $softwareList = TaskModel::select('task.id','task.bounty','task.title','task.view_count','task.delivery_count','users.name as uname')
                        ->whereIn('task.field_id',[13])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.delivery_count','desc')->limit(12)->get()->toArray();
        // print_r($hardwareList);
        $data = [
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'softwareList'   => $softwareList,
        ];
        return $this->theme->scope('page.app_exploit', $data)->render();
    }

    public function fwsrz(request $request){
        $this->initTheme('hardware');
        $this->theme->setTitle('服务商入住');
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 雇主发布的最新需求
        $newtaskList = TaskModel::select('task.id','task.bounty','task.title','task.view_count','task.delivery_count','users.name as uname')
                        ->whereIn('task.status',[2])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.verified_at','desc')->limit(6)->get()->toArray();
         // 最新成交的项目
        $newsuccessList = WorkModel::select('work.task_id','work.status','work.uid','task.title','task.id','task.bounty','users.name as uname')
                        ->whereIn('work.status',[1])
                        ->whereNotIn('work.uid',[10662])
                        ->leftjoin('users','work.uid','=','users.id')
                        ->leftjoin('task','task.id','=','work.task_id')
                        ->orderBy('task.selected_work_at','desc')->limit(6)->get()->toArray();
        //新加入快包的服务商
        $newjoinList = ShopModel::select('shop.id','shop.status','shop.shop_pic','shop.shop_name','tag_shop.cate_id')
                                  ->leftjoin("tag_shop","shop.id","=","tag_shop.shop_id")
                                  ->where("shop.shop_pic",'<>','')
                                  ->where("tag_shop.cate_id",'<>','')
                                  ->where("shop.status",'1')
                                  ->orderBy('shop.created_at','desc')
                                  ->groupBy("tag_shop.shop_id")
                                  ->limit(8)->get()->toArray();
        foreach ($newjoinList as $key => $value) {
            $newjoinList[$key]['joinfieldArr'] = ShopTagsModel::shopTag($value['id'],1);
            $newjoinList[$key]['joinskillArr'] = ShopTagsModel::shopTag($value['id'],2);
        }
        // print_r($newjoinList);
        //入驻服务商在平台赚取的酬金
        $userarr = ['43704','1156','7077','1926','37404','48978','30486','44059','358'];
        $goodPriceCount1 = WorkModel::select('uid','price')
                                    ->whereIn('status',[1])
                                    ->whereIn("uid",$userarr)
                                    ->get()
                                    ->toArray();
        $item = []; 
        foreach ($goodPriceCount1 as $k => $v) {
           if(!isset($item[$v['uid']])){
                $item[$v['uid']]=$v;
           }else{
                $item[$v['uid']]['price'] += $v['price'];
           }
        }
        //查询该店铺所有的商品
        foreach ($userarr as $key => $value) {
            $goodAll[$value] = GoodsModel::where("uid",$value)->lists("id")->toArray();
        }
        //查询店铺总金额
        foreach ($goodAll as $k => $v) {
           $goodPriceCount[$k] = ProgrammeOrderModel::getPriceCount($v);
        }
        // print_r($goodPriceCount);
        $data = [
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'newtaskList'   => $newtaskList,
            'newsuccessList'   => $newsuccessList,
            'newjoinList'   => $newjoinList,
            'goodPriceCount'   => $goodPriceCount,
            'item'   => $item,
        ];
        return $this->theme->scope('page.server_join', $data)->render();
    }

    /*
    * 获取手机验证码
    * */
    public function getPageCode(Request $request){
        $arr = $request->all();
        $code = rand(1000, 9999);
        $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
        $templateId = ConfigModel::phpSmsConfig('sendTaskCode');

        $templates = [
            $scheme => $templateId,
        ];

        $tempData = [
            'code' => $code,
        ];
        $status = \SmsClass::sendSms($arr['mobile'], $templates, $tempData);

        if ($status['success'] == true) {
            $data = [
                'code' => $code,
                'mobile' => $arr['mobile']
            ];
            Session::put('task_mobile_info', $data);
            $status = 'y';
            $info = '短信发送成功';
        } else {
            $info = '短信发送失败';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status,
            'code'	=> $code
        );
        return json_encode($data);
    }

    public function createHardware(request $request){
    	$data = $request->except('_token');
    	$authMobileInfo = session('task_mobile_info');
        if ($data['code'] == $authMobileInfo['code'] && $data['mobile'] == $authMobileInfo['mobile']){
            
        	Session::forget('task_mobile_info');
        	//查询用户是否存在
            $user = UserModel::where('mobile',$data['mobile'])->first();
            if($user){
                $uid = $user['id'];
            }else{
                $uid=DB::transaction(function() use($data){
                    $username = $username = time().\CommonClass::random(4);
                    $userInfo = [
                        'username' => $username,
                        'mobile' => $data['mobile'],
                        'password' => $data['mobile']
                    ];
                    $uid = UserModel::mobileInitUser($userInfo);
                    //发送通知短信
                    $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                    $templateId = ConfigModel::phpSmsConfig('sendLoinPassword');
                    $templates = [
                        $scheme => $templateId,
                    ];
					$tempData = [
						'code' => $data['mobile'],
					];

                    \SmsClass::sendSms($data['mobile'], $templates, $tempData);
                    return $uid;
                });
                UserModel::sendfreegrant($uid,1);//.自动发放
            }

            if($uid){	
            	$data['uid'] = $uid;
            	$data['desc'] = \CommonClass::removeXss($data['desc']);
            	$data['begin_at'] = date('Y-m-d H:i:s');
            	$data['delivery_deadline'] = preg_replace('/([\x80-\xff]*)/i', '', $data['delivery_deadline']);
		        $data['begin_at'] = date('Y-m-d H:i:s', strtotime($data['begin_at']));
		        $data['delivery_deadline'] = date('Y-m-d H:i:s', strtotime($data['delivery_deadline']));
		        $data['show_cash'] = $data['bounty'];
		        $data['project_agent'] = 1;
		        $data['phone'] = $data['mobile'];
		        $data['created_at'] = date('Y-m-d H:i:s');
		        $data['status'] = 1;
		        $result = TaskModel::createTask($data);
		        if (!$result) {
		            return redirect()->back()->with('error', '创建任务失败！');
		        }
		        Auth::loginUsingId($uid);
            	UserModel::where('id',$uid)->update(['last_login_time' => date('Y-m-d H:i:s'),'is_phone_login'=>1]);
            	return redirect()->back()->with('message', '发布成功！等待审核');
            }
            return back()->with(["error"=>"未知错误！请联系客服"])->withInput();
            
        }
        return back()->with(["message"=>"手机验证码错误"])->withInput();

    }

    public function gongkong(request $request){
        $this->initTheme('hardware');
        $this->theme->setTitle('工控');
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 硬件开发需求列表
        $softwareList = TaskModel::select('task.id','task.bounty','task.title','task.view_count','task.delivery_count','users.name as uname')
                        ->whereIn('task.field_id',[13])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.delivery_count','desc')->limit(12)->get()->toArray();
        $data = [
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'softwareList'   => $softwareList,
        ];
        return $this->theme->scope('page.control', $data)->render();
    }

    public function yingjian2(request $request){
        $this->initTheme('yingjian2');
        $this->theme->setTitle('电子软硬件外包服务平台');
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 硬件开发需求列表
        $softwareList = TaskModel::select('task.id','task.bounty','task.title','task.view_count','task.delivery_count','users.name as uname')
                        ->whereIn('task.field_id',[13])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.delivery_count','desc')->limit(12)->get()->toArray();
        $data = [
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'softwareList'   => $softwareList,
        ];
        return $this->theme->scope('page.fabaozhuanqu', $data)->render();
    }

    public function employer_tongyong(request $request){
        $this->initTheme('employerTongYong');
        $this->theme->setTitle('专业的智能软硬件开发众包服务平台—快包');
        $this->theme->set('keywords','项目外包、嵌入式开发、电路设计');
        $this->theme->set('description','上快包，一键免费发布项目需求，极速响应，项目经理一对一帮您梳理需求，对接行业专家/团队给您提供解决方案。');
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }
        // 硬件开发需求列表
        $softwareList = TaskModel::select('task.id','task.bounty','task.title','task.view_count','task.delivery_count','users.name as uname')
                        ->whereIn('task.field_id',[13])
                        ->leftjoin('users','task.uid','=','users.id')
                        ->orderBy('task.delivery_count','desc')->limit(12)->get()->toArray();
        $data = [
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'softwareList'   => $softwareList,
        ];
        return $this->theme->scope('page.employer_tongyong', $data)->render();
    }

    //.kb/create/发包落地页
    public function employer_create(request $request){
        $this->initTheme('employercreate');
        $this->theme->setTitle('快包，智能软硬件众包服务平台');

        $list = WorkModel::select('work.task_id','task.title')
                                    ->leftjoin("task",'work.task_id','=','task.id')
                                    ->whereIn('work.status',[1])
                                    ->orderBy('bid_at','desc')
                                    ->limit(20)
                                    ->get()
                                    ->toArray();
        $data= [
            'list'=>$list,
        ];
        
        return $this->theme->scope('page.employer_create', $data)->render();
    }
    //.kb/create/发包落地页
    public function orienteering_create(request $request){
        $this->initTheme('employercreate');
        $this->theme->setTitle('有150+万工程师为您提供定制化方案开发服务');
        $list = TaskModel::select("task.id","title","view_count","field_id","bounty","worker_num","uid","users.name")->leftjoin('users','task.uid','=','users.id')->where('type_id',1)->where('is_del',0)->where('task.status','>=',2)->where('task.status','<',10)
                           ->where('task.status','!=',3)->where('is_open','=','1')->whereIn('field_id',[1,3,4,9])->orderBy("task.created_at","desc")->limit(15)->get()->toArray();
        $data= [
            'list'=>$list,
        ];
        return $this->theme->scope('page.orienteering_create', $data)->render();
    }



}
