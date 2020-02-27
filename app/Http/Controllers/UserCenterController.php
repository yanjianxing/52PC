<?php

namespace App\Http\Controllers;

use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\LinkModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskInviteModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\PromoteTypeModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserCenterController extends BasicController
{

    public function __construct()
    {
        parent::__construct();

        //网站关闭
        //$siteConfig = ConfigModel::getConfigByType('site');
        $siteConfig = $this->theme->get('site_config');
        if ($siteConfig['site_close'] == 2){
            abort('404');
        }
        if(isset($_COOKIE['userid'])){
            Auth::loginUsingId($_COOKIE['userid']);
        }
        //前端头部
        if (Auth::check()){
            $user = Auth::User();
            //获取购物车的数量
            $shopCartNum=ProgrammeOrderModel::where("uid",$user->id)->whereIn("status",[0,1])->sum("number");
            $this->theme->set('shopCartNum',$shopCartNum);
            $userDetail = UserDetailModel::select('alternate_tips','avatar','nickname')->where('uid', $user->id)->first();
            $this->theme->set('username', $user->name);
            $this->theme->set('mobile', $user->mobile);
            $this->theme->set('tips', empty($userDetail)?'':$userDetail->alternate_tips);
            $this->theme->set('avatar',empty($userDetail)?'':$userDetail->avatar);
            $this->theme->set('nickname', empty($userDetail)?'':$userDetail->nickname);

            //查询未读消息
            $messageCount = MessageReceiveModel::where('js_id', $user->id)->where('status', 0)->count();
            $this->theme->set('messageCount', $messageCount);

            //.获取推送给我的项目总数统计
            $taskcount=TaskInviteModel::countTaskInvite($user->id);
            $this->theme->set('taskscount', $taskcount);
        }


        //前端底部公共页脚配置
        $parentCate = ArticleCategoryModel::select('id')->where('cate_name','页脚配置')->first();
        if(!empty($parentCate)){
            $articleCate = ArticleCategoryModel::where('pid',$parentCate->id)->limit(4)->get()->toArray();
            $this->theme->set('article_cate', $articleCate);
        }

        //底部友情链接
        $link = LinkModel::where('status',1)->select('title','content')->orderBy('sort','desc')->get()->toArray();
        $this->theme->set('Link_List',$link);

        //判断是否开启IM (1=>开启)
        $basisConfig = ConfigModel::getConfigByType('basis');
        if(!empty($basisConfig)){
            $this->theme->set('basis_config',$basisConfig);
        }

        $contact = 2;
        $this->theme->set('is_IM_open',$contact);

        //判断问答是否开启
        $question_switch = \CommonClass::getConfig('question_switch');
        $this->theme->set('question_switch',$question_switch);

        //判断注册推广是否开启
        $promoteType = PromoteTypeModel::where('is_open',1)->where('code_name','ZHUCETUIGUANG')->first();
        if(!empty($promoteType)){
            if($promoteType->is_open == 1){
                $this->theme->set('promote_switch',1);
            }
        }

        //特别消息弹窗
        $specialMessage = [];
        if(Auth::check()){
            $codeName = [
                'task_delivery','task_win','agreement_documents','task_bounty_accept', 'task_pay_type','task_pay_type_success','task_pay_type_failure','employer_goods_ask','employee_goods_asked','employ_work','employ_notice'
            ];
            $specialMessage = MessageReceiveModel::whereIn('code_name',$codeName)->where('js_id',Auth::id())->where('status',0)->orderBy('id','desc')->first();
            if($specialMessage){
                $specialMessage = $specialMessage->toArray();
            }
        }
        $this->theme->set('special_message',$specialMessage);


    }


}
