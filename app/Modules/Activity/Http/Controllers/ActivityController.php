<?php
namespace App\Modules\Activity\Http\Controllers;

use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Activity\Model\ActivityListModel;
use App\Modules\Activity\Model\ActivityInforMationModel;
use App\Modules\Activity\Model\ActivityDrawListModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Http\Requests;

class ActivityController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
    }
    public function wyzlkb(){
    	$this->initTheme('activity');
    	$this->theme->setTitle('唯样');
        $data= [];
        return $this->theme->scope('activity.wyzlkb', $data)->render();
    }

    public function postimg(Request $request){
        if($this->user){
            $res['uid'] = $this->user['id'];
            $res['img'] = $request->img;
            $res['created_at'] = date('Y-m-d H:i:s',time());
            $res['type'] = '1';
            $result = ActivityListModel::create($res);
            if($result){
                $data = array(
                    'code'=>'200',
                    'message'=>'上传成功',
                    'result'=>$result
                );
            }else{
                $data = array(
                    'code'=>'0',
                    'message'=>'上传失败',
                    'result'=>$result
                );
            }
        }else{
            $data = array(
                    'code'=>'0',
                    'message'=>'您还没有登陆！请先登陆！',
                ); 
        }
    	return $data;
    }

    //检查登陆 返回原页面 成功案例下载第一版
    public function checkdownloadlogin101(Request $request){
            return redirect("/activity/casedownload");
    }

    //.检查登陆 返回原页面 成功案例下载第二版无type，第三版 type为1
    public function checkdownloadlogin102(Request $request){
        if(isset($request->type) && $request->type==1){
            return redirect("/activity/casedownload3");
        }else{
            return redirect("/activity/casedownload2");
        }
    }
    //成功案例下载第一版
    public function casedownload(){

        $this->initTheme('activity');
        $this->theme->setTitle('我爱方案网100个成功案例(第一辑)');
        $downloadcount = ActivityListModel::whereRaw("type=2")->count();
        $data= [
            'downloadcount'=>$downloadcount,
            ];
        return $this->theme->scope('activity.case_download', $data)->render();
    }

    //成功案例下载第二版
    public function casedownload2(){
        $this->initTheme('activity');
        $this->theme->setTitle('我爱方案网100个成功案例(第二辑)');
        $downloadcount = ActivityListModel::whereRaw("type=24")->count();
        $data= [
            'downloadcount'=>$downloadcount,
        ];
        return $this->theme->scope('activity.case_download2', $data)->render();
    }

    //.成功案例下载第三版
    public function casedownload3(){
        $this->initTheme('activity');
        $this->theme->setTitle('我爱方案网100个成功案例(第三辑)');
        $downloadcount = ActivityListModel::whereRaw("type=25")->count();
        $data= [
            'downloadcount'=>$downloadcount,
        ];
        return $this->theme->scope('activity.case_download3', $data)->render();
    }

    //成功案例第一版保存用户信息
    public function postdownload(Request $request){
        if($this->user){
            $res['uid'] = $this->user['id'];
            $res['created_at'] = date('Y-m-d H:i:s',time());
            $res['type'] = isset($request->type)?$request->type:'2';
            $result = ActivityListModel::create($res);
            if($result){
                $data = array(
                    'code'=>'200',
                    'message'=>'下载成功',
                    'result'=>$res
                );
            }else{
                $data = array(
                    'code'=>'0',
                    'message'=>'下载失败',
                );
            }
        }else{
            $data = array(
                    'code'=>'0',
                    'message'=>'您还没有登陆！请先登陆！',
                ); 
        }
        return $data;
    }

    //成功案例第二版保存用户信息
    public function postdownload2(Request $request){
        if($this->user){
            $res['uid'] = $this->user['id'];
            $res['created_at'] = date('Y-m-d H:i:s',time());
            $res['type'] = isset($request->type)?$request->type:'24';
            $result = ActivityListModel::create($res);
            if($result){
                $data = array(
                    'code'=>'200',
                    'message'=>'下载成功',
                    'result'=>$res
                );
            }else{
                $data = array(
                    'code'=>'0',
                    'message'=>'下载失败',
                );
            }
        }else{
            $data = array(
                'code'=>'0',
                'message'=>'您还没有登陆！请先登陆！',
            );
        }
        return $data;
    }

    //.成功案例第三版保存用户信息
    public function postdownload3(Request $request){
        if($this->user){
            $res['uid'] = $this->user['id'];
            $res['created_at'] = date('Y-m-d H:i:s',time());
            $res['type'] = isset($request->type)?$request->type:'25';
            $result = ActivityListModel::create($res);
            if($result){
                $data = array(
                    'code'=>'200',
                    'message'=>'下载成功',
                    'result'=>$res
                );
            }else{
                $data = array(
                    'code'=>'0',
                    'message'=>'下载失败',
                );
            }
        }else{
            $data = array(
                'code'=>'0',
                'message'=>'您还没有登陆！请先登陆！',
            );
        }
        return $data;
    }

    public function package_list(){
        $this->initTheme('activity');
        $this->theme->setTitle('方案榜单');
        $view_count = GoodsModel::lists('view_num','id')->toArray();
        $data= [
            'view_count'=>$view_count,
        ];
        return $this->theme->scope('activity.package_list', $data)->render();
    }


    //Silicon白皮书
    public function whitepaper(){
        $this->initTheme('earthwhitepaper');
        $this->theme->setTitle('白皮书下载');
        //中奖名单
        $list = ActivityDrawListModel::whereRaw(" 1=1")->where('type','2')->get()->toArray();
        //礼品
        $prize  = array('1' =>'Thunderboard™ Sense 开发软件','2' =>'50元竞标卡','3' =>'200元竞标卡','4' =>'500元黄金卡','5' =>'谢谢参与');
        // dd($list);
        $data= [
            'list'=>$list,
            'prize'=>$prize,
        ];
        return $this->theme->scope('activity.white_paper', $data)->render();
    }

    //.ADI混合动力/电动汽车白皮书
    public function ADICarwhitepaper(){
        $this->initTheme('earthwhitepaper');
        $this->theme->setTitle('ADI混合动力/电动汽车白皮书');
        $data= [];
        return $this->theme->scope('activity.ADICarwhitepaper', $data)->render();
    }


    //.ADI锂离子电池白皮书
    public function ADIBatterywhitepaper(){
        $this->initTheme('earthwhitepaper');
        $this->theme->setTitle('ADI锂离子电池白皮书');
        $data= [];
        return $this->theme->scope('activity.ADIBatterywhitepaper', $data)->render();
    }


    //.ADI参考电路白皮书
    public function ADICircuitwhitepaper(){
        $this->initTheme('earthwhitepaper');
        $this->theme->setTitle('ADI参考电路白皮书');
        $data= [];
        return $this->theme->scope('activity.ADICircuitwhitepaper', $data)->render();
    }


    //.国体智慧开放实验室
    public function nationalLaboratory(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('国体智慧开放实验室');
        $data= [];
        return $this->theme->scope('activity.nationalLaboratory', $data)->render();
    }

    //.国体智慧开放实验室保存用户信息
    public function postnationalLaboratory(Request $request){
        $merge = $request->except("_token");
        $merge['created_at'] = date("Y-m-d H:i:s");
        $merge['uid'] = isset($this->user['id'])?$this->user['id']:'';
        $merge['type'] = isset($merge['type'])?$merge['type']:'26';
        $result = ActivityListModel::create($merge);
        if ($result){
            return redirect("/activity/nationalLaboratory")->with(array('message' => '申请成功'));
        }else{
            return redirect("/activity/nationalLaboratory")->with(array('message' => '申请失败'));
        }
    }
    //.百万扶持计划页面展示
    public function millionSupportProgram(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('百万扶持计划');
        $data= [];
        return $this->theme->scope('activity.millionSupportProgram', $data)->render();
    }
    //.百万扶持计划页面收集用户信息
    public function millionProgramgCollect(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('百万扶持计划');
        $data= [];
        return $this->theme->scope('activity.millionProgramgCollect', $data)->render();
    }

    //.百万扶持计划页面保存用户信息
    public function postmillionProgramgCollect(Request $request){
        $merge = $request->except("_token");
        $merge['created_at'] = date("Y-m-d H:i:s");
        $merge['uid'] = isset($this->user['id'])?$this->user['id']:'';
        $merge['type'] = '27';
        $result = ActivityListModel::create($merge);
        if ($result){
            return redirect("/activity/millionSupportProgram")->with(array('message' => '提交成功,审核通过后将与你联系！'));
        }else{
            return redirect("/activity/millionProgramgCollect")->with(array('message' => '资金支持失败，请重新申请！'));
        }
    }

    //Silicon抽奖
    public function Siliconlottery(Request $request){
        if(!Auth::check()){
            if(isset($request->type) && $request->type == '2'){
                return redirect("/activity/checkdownloadlogin?type=2");
            }elseif(isset($request->type) && $request->type == '3'){
                return redirect("/activity/checkdownloadlogin?type=3");
            }else{
                return redirect("/activity/checkdownloadlogin");
            }
        }
        //查找是否下载
        $is_download = ActivityListModel::where("uid" , $this->user['id'])->whereIn("type",[3,4,5,7,8,9,10])->first();
        if(!$is_download){
            return $data = ['code'=>0,'msg'=>'您还未获取抽奖资格,下载成功后即可参与本次抽奖'];
        }

        // //每个用户只有一次机会
        $uidcount = ActivityDrawListModel::where("uid" , $this->user['id'])->where('type','2')->count();
        if($uidcount>0){   
            return $data = ['code'=>0,'msg'=>'您的机会已经用完了！'];
        }
        //逢5抽书刊，逢10抽移动电源插头，其他剩下的随机出现竞标卡和现金券，百分百中奖，谢谢参与几率0 总数大于300活动结束
        $count = ActivityDrawListModel::whereRaw(" 1=1")->where('type','2')->count();
        if($count>250){
            return $data = ['code'=>0,'msg'=>'活动已经结束了！'];
        }

        $data['uid'] = $this->user['id'];
        $data['mobile'] = !empty($this->user['mobile'])?$this->user['mobile']:$is_download['mobile'];
        if($count>0 && $count%50 == 0){
            $data['prize_id'] = 1;
        }elseif($count>0 && $count%10 == 0){
            $data['prize_id'] = 4;
        }elseif($count>0 && $count%5 == 0){
            $data['prize_id'] = 3;
        }else{
            $data['prize_id'] = 2;
        }
        $data['created_at'] = date("Y-m-d H:i:s");
        $data['type'] = '2';
        $result = ActivityDrawListModel::create($data);
        if($result){
            return $data = ['code'=>200,'msg'=>'抽奖成功','prize_id'=>$data['prize_id']];
        }else{
            return $data = ['code'=>0,'msg'=>'未知错误！'];
        }
    }
    public function downloadwhitepaper(Request $request){
        $merge = $request->except("_token");
        $merge['created_at'] = date("Y-m-d H:i:s");
        $result = ActivityListModel::create($merge);
        if($result){
            if($merge['type'] == 3){
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/bps191101.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }elseif($merge['type'] == 4){
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/bps191102.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }elseif($merge['type'] == 7){
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/2019062007.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }elseif($merge['type'] == 8){
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/2019062008.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }elseif($merge['type'] == 9){
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/2019062009.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }elseif($merge['type'] == 10){
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/2019062010.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }else{
                echo "<script>window.location.href='http://www.52solution.com/themes/default/assets/images/activity/bps191103.rar';</script>";
                return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
            }
        }
        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
    }

    //世健白皮书
    public function earthwhitepaper(){
        $this->initTheme('earthwhitepaper');
        $this->theme->setTitle('世健白皮书下载');
        //中奖名单
        $list = ActivityDrawListModel::whereRaw(" 1=1")->where('type','1')->get()->toArray();
        //礼品
        $prize  = array('1' =>'二合一移动电源','2' =>'200元竞标卡','3' =>'《新概念模拟电路》书刊','4' =>'青铜会员400元现金券','5' =>'谢谢参与');
        $data= [
            'list'=>$list,
            'prize'=>$prize,
        ];
        return $this->theme->scope('activity.earth_whitepaper', $data)->render();
    }

    //下载检查
    public function checkdownload(Request $request){
        $merge = $request->except("_token");
        $data = [
                'code'=>'0',
                'message'=>'未知错误',
            ];
        if($this->user){
            if($merge['type'] == '2'){
                $earthtype = ['3','4','5','7','8','9','10'];
            }elseif($merge['type'] == '3'){
                $earthtype = ['21','22','23'];
            }else{
                $earthtype = ['15','16','17','18','19','20'];    
            }
            $res = ActivityListModel::where("uid",$this->user['id'])->whereIn("type",$earthtype)->count();
            $domain = \CommonClass::getDomain();
            if($res){
                switch ($merge['myid']) {
                    // Silicon Labs白皮书 3-10
                    case '3':
                        $url = "/themes/default/assets/images/activity/bps191101.rar";
                        break;
                    case '4':
                        $url = "/themes/default/assets/images/activity/bps191102.rar";
                        break;
                    case '5':
                        $url = "/themes/default/assets/images/activity/bps191103.rar";
                        break;
                    case '7':
                        $url = "/themes/default/assets/images/activity/2019062007.rar";
                        break;
                    case '8':
                        $url = "/themes/default/assets/images/activity/2019062008.rar";
                        break;
                    case '9':
                        $url = "/themes/default/assets/images/activity/2019062009.rar";
                        break;
                    case '10':
                        $url = "/themes/default/assets/images/activity/2019062010.rar";
                        break;
                    //世健白皮书15-20
                    case '15':
                        $url = "/themes/default/assets/images/activity/sjbps19070415.rar";
                        break;
                    case '16':
                        $url = "/themes/default//assets/images/activity/sjbps19070416.rar";
                        break;
                    case '17':
                        $url = "/themes/default//assets/images/activity/sjbps19070417.rar";
                        break;
                    case '18':
                        $url = "/themes/default//assets/images/activity/sjbps19070418.rar";
                        break;
                    case '19':
                        $url = "/themes/default//assets/images/activity/sjbps19070419.rar";
                        break;
                    case '20':
                        $url = "/themes/default//assets/images/activity/sjbps19070420.rar";
                        break;
                    case '21':
                        $url = "/themes/default//assets/images/activity/NI19071221.rar";
                        break;
                    case '22':
                        $url = "/themes/default//assets/images/activity/NI19071222.rar";
                        break;
                    case '23':
                        $url = "/themes/default//assets/images/activity/NI19071223.rar";
                        break;
                    default:
                        $url = "/themes/default//assets/images/activity/sjbps19070415.rar";
                        break;
                }
                $data = [
                    'code'=>'201',
                    'message'=>'直接下载',
                    'url'=>$url,
                    // 'name'=>$name,
                ];
            }else{
               $data = [
                    'code'=>'200',
                    'message'=>'填信息下载',
                ];
            }
        }else{
            $data = array(
                'code'=>'0',
                'message'=>'您还没有登陆！请先登陆！',
            );
        }
        return $data;
    }
    //检查登陆 返回原页面
    public function checkdownloadlogin(Request $request){
        if(isset($request->type) && $request->type == '2'){
            return redirect("/activity/whitepaper");
        }elseif(isset($request->type) && $request->type == '3'){
            return redirect("/activity/nationwhitepaper");
        }else{
            return redirect("/activity/earthwhitepaper");
        }
        
    }
    public function downloadearthwhitepaper(Request $request){
        $merge = $request->except("_token");
        $merge['created_at'] = date("Y-m-d H:i:s");
        if(!Auth::check()){
            return redirect("/activity/checkdownloadlogin");
        }
        $merge['uid'] = isset($this->user['id'])?$this->user['id']:'';
        if(empty($this->user['mobile'])){ //没有手机认证需要验证码验证
            $authMobileInfo = session('task_mobile_info');
            if ($authMobileInfo && $merge['code'] == $authMobileInfo['code'] && $merge['mobile'] == $authMobileInfo['mobile']){
                $result = ActivityListModel::create($merge);
                if($result){
                    if($merge['type'] == 3){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/bps191101.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 4){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/bps191102.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 5){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/bps191103.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 7){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062007.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 8){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062008.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 9){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062009.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 10){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062010.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 15){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070415.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 16){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070416.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 17){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070417.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 18){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070418.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 19){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070419.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 20){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070420.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 21){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/NI19071221.rar');</script>";
                        return redirect("/activity/nationwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 22){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/NI19071222.rar');</script>";
                        return redirect("/activity/nationwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 23){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/NI19071223.rar');</script>";
                        return redirect("/activity/nationwhitepaper")->with(array('message' => '下载成功'));
                    }else{
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070420.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }
                }
            }else{
                return back()->with(["error"=>"手机验证码错误"])->withInput();
            }
        }else{  
            $merge['mobile'] = isset($this->user['mobile'])?$this->user['mobile']:'';
            $result = ActivityListModel::create($merge);
            if($result){
                if($merge['type'] == 3){ 
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/bps191101.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 4){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/bps191102.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 5){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/bps191103.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 7){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062007.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 8){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062008.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 9){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062009.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 10){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/2019062010.rar');</script>";
                        return redirect("/activity/whitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 15){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070415.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 16){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070416.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 17){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070417.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 18){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070418.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 19){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070419.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 20){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070420.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 21){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/NI19071221.rar');</script>";
                        return redirect("/activity/nationwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 22){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/NI19071222.rar');</script>";
                        return redirect("/activity/nationwhitepaper")->with(array('message' => '下载成功'));
                    }elseif($merge['type'] == 23){
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/NI19071223.rar');</script>";
                        return redirect("/activity/nationwhitepaper")->with(array('message' => '下载成功'));
                    }else{
                        echo "<script>window.open('http://www.52solution.com/themes/default/assets/images/activity/sjbps19070420.rar');</script>";
                        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
                    }
            }
        }
        
        return redirect("/activity/earthwhitepaper")->with(array('message' => '下载成功'));
    }
    //世健抽奖
    public function lottery(Request $request){
        if(!Auth::check()){
            return redirect("/activity/checkdownloadlogin");
        }
        $prize  = array('0' =>'二合一移动电源（充电器）','1' =>'200元竞标卡','2' =>'《新概念模拟电路》书刊','3' =>'青铜会员400元现金券','4' =>'谢谢参与');
        //查找是否下载
        $is_download = ActivityListModel::where("uid" , $this->user['id'])->whereIn("type",[15,16,17,18,19,20])->first();
        if(!$is_download){
            return $data = ['code'=>0,'msg'=>'您还未获取抽奖资格,下载成功后即可参与本次抽奖'];
        }

        // //每个用户只有一次机会
        $uidcount = ActivityDrawListModel::where("uid" , $this->user['id'])->where('type','1')->count();
        if($uidcount>0){   
            return $data = ['code'=>0,'msg'=>'您的机会已经用完了！'];
        }
        //逢5抽书刊，逢10抽移动电源插头，其他剩下的随机出现竞标卡和现金券，百分百中奖，谢谢参与几率0 总数大于300活动结束
        $count = ActivityDrawListModel::whereRaw(" 1=1")->where('type','1')->count();
        if($count>300){
            return $data = ['code'=>0,'msg'=>'活动已经结束了！'];
        }

        $data['uid'] = $this->user['id'];
        $data['mobile'] = !empty($this->user['mobile'])?$this->user['mobile']:$is_download['mobile'];
        if($count>0 && $count%10 == 0){
            $data['prize_id'] = 1;
        }elseif($count>0 && $count%5 == 0){
            $data['prize_id'] = 3;
        }else{
            $array  = [2,4];
            $rand = $array[rand(0,1)];
            $data['prize_id'] = $rand;
        }
        $data['created_at'] = date("Y-m-d H:i:s");
        $result = ActivityDrawListModel::create($data);
        if($result){
            switch ($data['prize_id']) {
                case '1':
                    $angle = '324';
                    $text = '恭喜你获得<em>二合一移动电源</em><br>我们将在本月底之前与您取得联系';
                    break;
                case '2':
                    $angle = '252';
                    $text = '恭喜你获得 <em>200元竞标卡</em><br>我们将在本月底之前与您取得联系';
                    break;
                case '3':
                    $angle = '180';
                    $text = '恭喜你获得 <em>《新概念模拟电路》书刊</em><br>我们将在本月底之前与您取得联系';
                    break;
                case '4':
                    $angle = '108';
                    $text = '恭喜你获得 <em>青铜会员400元现金券</em><br>我们将在本月底之前与您取得联系';
                    break;
                default:
                    $angle = '36';
                    $text = '<p>感谢您的参与</p>很遗憾您未能中奖';
                    break;
            }
            return $data = ['code'=>200,'msg'=>'抽奖成功','prize_id'=>$data['prize_id'],'angle'=>$angle,'txt'=>$text];
        }else{
            return $data = ['code'=>0,'msg'=>'未知错误！'];
        }
    }

    //Nation白皮书
    public function nationwhitepaper(){
        $this->initTheme('earthwhitepaper');
        $this->theme->setTitle('Nation白皮书下载');
        //中奖名单
        $list = ActivityDrawListModel::whereRaw(" 1=1")->where('type','3')->get()->toArray();
        //礼品
        $prize  = array('1' =>'手持小风扇','2' =>'50元竞标卡','3' =>'移动电源','4' =>'苹果数据线','5' =>'谢谢参与');
        // dd($list);
        $data= [
            'list'=>$list,
            'prize'=>$prize,
        ];
        return $this->theme->scope('activity.nation_whitepaper', $data)->render();
    }

    //Nation白皮书抽奖
    public function NIlottery(Request $request){
        if(!Auth::check()){
            if(isset($request->type) && $request->type == '2'){
                return redirect("/activity/checkdownloadlogin?type=2");
            }elseif(isset($request->type) && $request->type == '3'){
                return redirect("/activity/checkdownloadlogin?type=3");
            }else{
                return redirect("/activity/checkdownloadlogin");
            }
        }

        //查找是否下载
        $is_download = ActivityListModel::where("uid" , $this->user['id'])->whereIn("type",[21,22,23])->first();
        if(!$is_download){
            return $data = ['code'=>0,'msg'=>'您还未获取抽奖资格,下载成功后即可参与本次抽奖'];
        }

        // //每个用户只有一次机会
        $uidcount = ActivityDrawListModel::where("uid" , $this->user['id'])->where('type','3')->count();
        if($uidcount>0){   
            return $data = ['code'=>0,'msg'=>'您的机会已经用完了！'];
        }
        //逢5抽书刊，逢10抽移动电源插头，其他剩下的随机出现竞标卡和现金券，百分百中奖，谢谢参与几率0 总数大于300活动结束
        $count = ActivityDrawListModel::whereRaw(" 1=1")->where('type','3')->count();
        if($count>300){
            return $data = ['code'=>0,'msg'=>'活动已经结束了！'];
        }

        $data['uid'] = $this->user['id'];
        $data['mobile'] = !empty($this->user['mobile'])?$this->user['mobile']:$is_download['mobile'];
        if($count>0 && $count%50 == 0){
            $data['prize_id'] = 1;
        }elseif($count>0 && $count%25 == 0){
            $data['prize_id'] = 3;
        }elseif($count>0 && $count%20 == 0){
            $data['prize_id'] = 4;
        }else{
            $data['prize_id'] = 2;
        }
        $data['created_at'] = date("Y-m-d H:i:s");
        $data['type'] = '3';

        $result = ActivityDrawListModel::create($data);
        if($result){
            return $data = ['code'=>200,'msg'=>'抽奖成功','prize_id'=>$data['prize_id']];
        }else{
            return $data = ['code'=>0,'msg'=>'未知错误！'];
        }
    }

    //特色专区落地页面
    public function specialzone1(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('发包专区页面');
        $list = TaskModel::select("id","title","view_count","field_id","bounty")->where('type_id',1)->where('is_del',0)->where('status','>=',2)->where('status','<',10)
                           ->where('status','!=',3)->where('is_open','=','1')->whereIn('field_id',[1,3,4,9])->orderBy("created_at","desc")->limit(15)->get()->toArray();
        
        $fieldArr = TaskCateModel::where('type',1)->lists('name','id')->toArray();
        $data= [
            'list'=>$list,
            'fieldArr'=>$fieldArr
        ];
        return $this->theme->scope('activity.special_zone1', $data)->render();
    }

    //特色专区落地页面
    public function specialzone2(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('发包专区页面');
        // $list = TaskModel::select("id","title","view_count","field_id","bounty")->where('type_id',1)->where('is_del',0)->where('status','>=',2)->where('status','<',10)
        //                    ->where('status','!=',3)->where('is_open','=','1')->whereIn('field_id',[13])->orderBy("created_at","desc")->limit(15)->get()->toArray();
        
        // $fieldArr = TaskCateModel::where('type',1)->lists('name','id')->toArray();
        $data= [
            // 'list'=>$list,
            // 'fieldArr'=>$fieldArr
        ];
        return $this->theme->scope('activity.special_zone2', $data)->render();
    }

    //特色专区落地页面
    public function specialzone3(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('发包专区页面');
        $list = TaskModel::select("id","title","view_count","field_id","bounty")->where('type_id',1)->where('is_del',0)->where('status','>=',2)->where('status','<',10)
                           ->where('status','!=',3)->where('is_open','=','1')->whereIn('field_id',[8,12,15])->orderBy("created_at","desc")->limit(15)->get()->toArray();
        
        $fieldArr = TaskCateModel::where('type',1)->lists('name','id')->toArray();
        $data= [
            'list'=>$list,
            'fieldArr'=>$fieldArr
        ];
        return $this->theme->scope('activity.special_zone3', $data)->render();
    }

    //方案超市宣传页面
    public function facsxc(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('发包专区页面');
       
        $data= [
            
        ];
        return $this->theme->scope('activity.facsxc', $data)->render();
    }

    //研讨会页面
    public function seminar(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('研讨会-网站专题页');
        $data= [
        ];
        return $this->theme->scope('activity.seminar', $data)->render();
    }
    //研讨会页面
    public function facs(){
        $this->initTheme('employerTongYong');
        $this->theme->setTitle('方案超市局域网无线模组专区-我爱方案网');
        $this->theme->set('keywords','蓝牙模块、WIFI模块、Zigbee模块、LoRa模块、方案超市');
        $this->theme->set('description','方案超市是电子软硬件方案的搜索引擎，汇聚7000多个PCBA方案、嵌入式软件方案、整机方案和IoT系统方案。专业的技术支持，让开发者更得心应手。');
        $data= [
        ];
        return $this->theme->scope('activity.facs', $data)->render();
    }

    public function postxinpian(request $request){
        $data = $request->except("_token");
        $authMobileInfo = session('task_mobile_info');
        if ($data['code'] == $authMobileInfo['code'] && $data['mobile'] == $authMobileInfo['mobile']){
            $res['model'] = $data['model'];
            $res['producer'] = $data['producer'];
            $res['title'] = $data['title'];
            $res['num'] = $data['num'];
            $res['project'] = $data['project'];
            $res['mark'] = $data['mark'];
            $res['name'] = $data['name'];
            $res['mobile'] = $data['mobile'];
            $res['created_at'] = date("Y-m-d H:i:s");
            $result = ActivityInforMationModel::create($res);
            if(!$result){
                return redirect()->back()->with('message', '提交失败！');
            }
            return redirect()->back()->with('message', '提交成功！稍后客服会联系您！');
        }
        return redirect()->back()->with('message', '验证码错误！请检查您的验证码！');
    }

    //.方案榜单
    public function facsList(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('方案榜单');
        $data= [
        ];
        return $this->theme->scope('activity.facsList', $data)->render();
    }

    //送会员活动
    public function sendvip(){
        $this->initTheme('specialzone');
        $this->theme->setTitle('送黄金会员活动');
        $data= [
        ];
        return $this->theme->scope('activity.sendvip', $data)->render();
    }


}
