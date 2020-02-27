<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\NavigationModel;
use App\Modules\Manage\Model\IndustryModel;
use App\Modules\Manage\Model\ServiceObjectModel;
use App\Modules\Manage\Model\StyleModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Manage\Model\CateModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\User\Model\DistrictModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\Shop\Models\ShopModel;
use Theme;
use DB;


class SuccessCaseController extends ManageController
{
    public $user;
    public function __construct()
    {
        parent::__construct();
        $this->user = $this->manager;
        $this->initTheme('manage');
        $this->theme->setTitle('成功案例管理');
        $this->theme->set('manageType', 'successCase');
    }

    /**
     * 成功案例列表
     * @param Request $request
     * @return mixed
     */
    public function successCaseList(Request $request)
    {
        $data = $request->all();
        $query = SuccessCaseModel::select('success_case.*');
        //标题筛选
        if($request->get('title'))
        {
            $query = $query->where('success_case.title','like',"%".e($request->get('title')).'%');
        }
        // 应用领域筛选
        if($request->get('cate_id') && $request->get('cate_id')>0 ){
            $query = $query->where('success_case.cate_id', '=', $request->get('cate_id'));
        }
        //来自筛选
        if($request->get('from') && $request->get('from')!=0)
        {
            if($request->get('from')==1)
            {
                $query = $query->where('success_case.type','=',0);
            }else{
                $query = $query->where('success_case.type','=',1);
            }
        }
        //状态筛选
        if($request->get('status') && $request->get('status')!= 0){
            $query = $query->where('success_case.status','=',$request->get('status'));
        }
        //排序筛选
        $orderBy = 'id';
        if($request->get('orderBy'))
        {
            $orderBy = $request->get('orderBy');
        }
        $orderByType = 'acs';
        if($request->get('orderByType'))
        {
            $orderByType = $request->get('orderByType');
        }
        //分页条数筛选
        $page_size = 10;
        if($request->get('pageSize'))
        {
            $page_size = $request->get('pageSize');
        }
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $query = $query->where('success_case.created_at','>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $query = $query->where('success_case.created_at','<',$end);
        }
        $comments_page = $query->with('detail','field','user')
            ->orderBy($orderBy,$orderByType)
            ->paginate($page_size);
        $comments = $comments_page->toArray();
        /*if($comments['total'] > 0){
            foreach($comments['data'] as $k => $v){
                if(!empty($v['cate_id'])){
                    $cate = TaskCateModel::findById($v['cate_id']);

                    if(!empty($cate)){
                        $comments['data'][$k]['cate_name'] = $cate['name'];
                    }else{
                        $comments['data'][$k]['cate_name'] = '';
                    }
                }else{
                    $comments['data'][$k]['cate_name'] = '';
                }
            }
        }*/
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //查询技术分类
        $technology = CateModel::where('pid','=','0')->where('type','=','2')->get()->toArray();

        $view = [
            'data'=>$comments,
            'merge'=>$data,
            'comments_page'=>$comments_page,
            'application' =>$application,
            'technology'  =>$technology,
            'cate' => $request->get('cate_id')
            
            
        ];
        $this->theme->setTitle('案例管理');
        return $this->theme->scope('manage.successCaseList',$view)->render();
    }

    /**
     * 添加一个成功案例页面
     */
    public function create(Request $request)
    {
        $data = $request->all();
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //查询技术分类
        $technology = CateModel::where('pid','=','0')->where('type','=','2')->get()->toArray();

        //一级行业
        $cateFirst = TaskCateModel::findByPid([0],['id','name']);
        if(isset($data['id']))
        {
            $successcase = SuccessCaseModel::getSuccessInfoById($data['id']);
            //查询二级行业分类
            $cateSecond = TaskCateModel::findByPid([$successcase->cate_pid]);
            $view = array(
                'cate_first' => $cateFirst,
                'cate_second' => $cateSecond,
                'application' =>$application,
                'technology'  =>$technology,
                'success_case' => $successcase,
                'city' => DistrictModel::getDistrictName($successcase['city']),
                'area' => DistrictModel::getDistrictName($successcase['area']),
                'task'=>[],
                'biddinguser' => [],
            );
        }else{
            $task=[];
            $biddinguser = [];
            if($request->get("task_id")){
                $task=TaskModel::find($request->get("task_id"));
                //获取中标人
                $biddinguser = WorkModel::leftJoin("users","work.uid","=","users.id")->where("work.task_id",$task['id'])
                    ->where("work.status","!=",0)->select("users.name","users.id","work.bid_at")->first();
                if($biddinguser){
                    $shop = ShopModel::where('uid','=',$biddinguser['id'])->first();
                    if($shop){
                        //获取中标者的店铺名
                        $biddinguser['shopname'] = $shop->shop_name;
                    }else{
                        $biddinguser['shopname'] = '';
                    }
                }else{
                    $biddinguser['shopname'] = '';
                }

                //获取竞标人数
                $biddinguser['bidd_num'] = WorkModel::where("task_id",$task['id'])->count();
            }
            if(!empty($cateFirst)){
                //二级行业
                $cateSecond = TaskCateModel::findByPid([$cateFirst[0]['id']],['id','name']);
            }else{
                $cateSecond = array();
            }
            $domain = \CommonClass::getDomain();
            $view = [
                'cate_first' => $cateFirst,
                'application' =>$application,
                'technology'  =>$technology,
                'cate_second' => $cateSecond,
                'task'=>$task,
                'biddinguser' => $biddinguser,
                'city' =>isset($task['city'])?DistrictModel::getDistrictName($task['city']):'',
                'domain' => $domain
            ];
        }
        //地区
        $province = DistrictModel::findTree(0);
        $view['province'] = $province;
        //方案分类
        $view['cate_categroy'] = \CommonClass::cate_category();
        $request->get('id')?$this->theme->setTitle('案例编辑'):$this->theme->setTitle('案例添加');
        return $this->theme->scope('manage.successcaseadd', $view)->render();
    }

    /**
     *添加修改一个成功案例
     * @param Request $request
     */
    public function update(Request $request)
    {
        $data = $request->except('_token');
        $file = $request->file('pic');
        //上传文件
        if($request->get('period_starttime')){
            $period_starttime = preg_replace('/([\x80-\xff]*)/i', '', $request->get('period_starttime'));
            $period_starttime = date('Y-m-d H:i:s',strtotime($period_starttime));
        }
        if($request->get('period_endtime')){
            $period_endtime = preg_replace('/([\x80-\xff]*)/i', '', $request->get('period_endtime'));
            $period_endtime = date('Y-m-d H:i:s',strtotime($period_endtime));
        }
        if($request->get('deal_at')){
            $deal_at = preg_replace('/([\x80-\xff]*)/i', '', $request->get('deal_at'));
            $deal_at = date('Y-m-d H:i:s',strtotime($deal_at));
        }
        if(!empty($data['username']) && empty($data['uid'])){
            $uid = ShopModel::where("shop_name",'=',$data['username'])->first();
            $uid = !empty($uid)?$uid['uid']:'';
        }else{
            $uid = isset($data['uid'])?$data['uid']:'';
        }
        $success_case = [
            'uid'=>$uid,
            'username'=>isset($data['username'])?$data['username']:'',
            'desc' => isset($data['desc'])?$data['desc']:'',
            'title'=>isset($data['title'])?$data['title']:'',
            'bidd_num' =>isset($data['bidd_num'])?$data['bidd_num']:'',
            'cate_id' => isset($data['cate_id'])?$data['cate_id']:'',
            'technology_id' => isset($data['technology_id'])?$data['technology_id']:'',
            'cash' => isset($data['cash'])?$data['cash']:'',
            'period_starttime' => isset($period_starttime)?$period_starttime:'',
            'period_endtime' => isset($period_endtime)?$period_endtime:'',
            'deal_at' => isset($deal_at)?$deal_at:'',
            'pub_uid' => $this->manager->id,
            'appraise' => isset($data['appraise'])?$data['appraise']:'',
            'province' => isset($data['province'])?$data['province']:'',
            'city' => isset($data['city'])?$data['city']:'',
            'area' => isset($data['area'])?$data['area']:'',
            'cate_category' => $data['cate_category'],
            'is_recommend' => isset($data['is_recommend'])?$data['is_recommend']:0,
            'workspead' => isset($data['workspead'])?$data['workspead']:5,
            'workquality' => isset($data['workquality'])?$data['workquality']:5,
            'workmanner' => isset($data['workmanner'])?$data['workmanner']:5,
            'url' =>isset($data['success_url'])?$data['success_url']:'',
        ];
        if(!empty($data['task_id'])){ $success_case['type'] = '2';$success_case['task_id']=$data['task_id']; }
        if(empty($data['id'])){
            $success_case['view_count'] = '0';
            $success_case['created_at'] = date("Y-m-d H:i:s",time());
        }
        /*图片上传*/
        $pic = '';
        if ($file) {
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        /*end图片*/
        //修改图片
        if(!empty($pic)) { $success_case['pic'] = $pic; }
        if(isset($data['id']))
        {
            $result2 = SuccessCaseModel::where('id',$data['id'])->update($success_case);
            if(!$result2)
                return redirect()->back()->with('error','成功案例修改失败！');
        }else{
            $result3=DB::transaction(function() use($success_case){
                SuccessCaseModel::create($success_case);
                if(isset($success_case['task_id'])){
                    TaskModel::where("id",$success_case['task_id'])->update(['is_set_success'=>1]);
                }
                return $success_case;
            });
            if(!$result3)
                return redirect()->back()->with('error','成功案例添加失败！');
        }
        return redirect()->to('manage/successCaseList')->with('massage','操作成功！');
    }

    /**
     * 成功案例删除处理
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function successCaseDel($id)
    {
        $attachment = SuccessCaseModel::where('id', $id)->first();
        if (!empty($attachment)){
            $status = $attachment->delete();
            if ($status){
                return redirect()->back()->with(['message' => '操作成功']);
            }
        }
        return redirect()->back()->with(['error' => '删除失败']);
    }

    public function releasetatus($id,$status){
        $url = '/manage/successCaseList';
        $arr = array('status'=>$status);
        $result = SuccessCaseModel::where('id',$id)->update($arr);
        if (!$result) {
            return redirect()->to($url)->with(array('message' => '操作失败'));
        }
        return redirect()->to($url)->with(array('message' => '操作成功'));
    }

    /**
     * ajax获取二级行业分类
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetSecondCate(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $cate = TaskCateModel::findByPid([$id]);
        $data = [
            'cate' => $cate
        ];
        return response()->json($data);
    }

}