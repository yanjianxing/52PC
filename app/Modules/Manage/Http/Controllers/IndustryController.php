<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskTemplateModel;
use App\Modules\User\Model\TagsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Toplan\TaskBalance\Task;

class IndustryController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('行业管理');
        $this->theme->set('manageType', 'industry');
    }
    /**
     * 行业列表
     */
    public function industryList(Request $request)
    {
        $category_data = TaskCateModel::where("pid",0)->where("type",1);
        if($request->get("name")){
            $category_data=$category_data->where("name","like","%".e($request->get("name"))."%");
        }
        $category_data=$category_data->orderBy("sort","asc")->get();
        $data = [
            'category_data'=>$category_data,
            'merge'=>$request->all(),
        ];
        return $this->theme->scope('manage.industrylist', $data)->render();
    }
     /*
      * 新增行业
      * */
    public function industryAdd($type){
        $data=[
            "type"=>$type,
        ];
        return $this->theme->scope('manage.industryAdd',$data)->render();
    }
    /*
     * 添加行业数据提交
     * */
    public function industryAddData(Request $request){
        if(!$request->get("name")){
            return back()->with(["message"=>"行业名称没有填写"]);
        }
        $cate=TaskCateModel::where("name",trim($request->get("name")))->where("type",$request->get('type'))->first();
        if($cate){
            return back()->with(["message"=>"该行业已存在"]);
        }
        $file = $request->file('pic');
        $pic='';
        if ($file){
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        $arr = array(
            'pic' => $pic,
            'name'=>$request->get('name'),
            'sort'=>$request->get('sort'),
            'type'=>$request->get('type'),
            "created_at"=>date("Y-m-d H:i:s"),
            "updated_at"=>date("Y-m-d H:i:s"),
        );
        $res = TaskCateModel::insert($arr);
        $url="/manage/industry";
        switch($request->get('type')){
            case 1:
                $url="/manage/industry";
                break;
            case 2:
                $url="/manage/skillList";
                break;
            case 3:
                $url="/manage/tradPlatform";
                break;
            case 4:
                $url="/manage/transactionList";
                break;
            case 6:
                $url="manage/functionalList";
                break;
            case 7:
                $url="manage/jobLevelList";
                break;
        }
        if($res){
            return redirect($url)->with(array('message' => '操作成功'));
        }
    }
    /**
     * 删除一个分类数据
     * @param $id
     */
    public function industryDelete($id)
    { 
        $cate=TaskCateModel::find($id);
        if(!$cate){
            return back();
        }
        $result =$cate->delete();
        if(!$result)
        {
            return response()->json(['errCode'=>0,'errMsg'=>'删除失败！']);
        }
        Cache::forget('task_cate');
        return back();
       // return response()->json(['errCode'=>1,'id'=>$id]);
    }

    /**
     * 创建和修改数据
     * @param Request $request
     */
    public function industryCreate(Request $request)
    {
        $data = $request->except('_token');
        
        //确定upid
        if(!empty($data['second']) && $data['third']==$data['second'])
        {
            $pid = $data['second'];
            $path = '-0-'.$pid.'-';
        }elseif(!empty($data['third']) && $data['third']!=$data['second'])
        {
            $pid = $data['third'];
            $path = '-0-'.$data['second'].'-'.$data['third'].'-';
        }else
        {
            $pid = 0;
            $path = '-0-';
        }
        //修改或者添加数据
        foreach($data['name'] as $k=>$v)
        {
            $change_ids = explode(' ',$data['change_ids']);
            if(in_array($k,$change_ids)){
                $result = TaskCateModel::where('pid',$pid)->where('id',$k)->update(['name'=>$v,'sort'=>$data['sort'][$k]]);
                //同时修改一个用户标签
                if(!empty($data['third']) && $result)
                {
                    TagsModel::where('cate_id',$k)->update(['tag_name'=>$v]);
                    //更新标签的cache
                    TagsModel::betteringCache();
                }
                if(!$result)
                {
                    $task_cate = TaskCateModel::firstOrCreate(['name'=>$v,'pid'=>$pid,'path'=>$path,'sort'=>$data['sort'][$k]]);
                    if(!empty($data['third']) && $task_cate)
                    {
                        $tags = TagsModel::firstOrCreate(['tag_name' => $task_cate['name']]);
                        TagsModel::where('id',$tags['id'])->update(['cate_id'=>$task_cate['id']]);
                        //更新标签的cache
                        TagsModel::betteringCache();
                    }
                }
            }
        }
        Cache::forget('task_cate');
        return redirect()->back()->with(['massage'=>'修改成功！']);
    }

    /**
     * ajax获取一级行业数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxSecond(Request $request)
    {
        $id = intval($request->get('id'));
        if(is_null($id)){
            return response()->json(['errMsg'=>'参数错误！']);
        }
        $province = TaskCateModel::findByPid([$id]);
        $domain = \CommonClass::getDomain();
        if(!empty($province)){
            foreach($province as $k => $v){
                $province[$k]['pic'] = $domain.'/'.$v['pic'];
            }
        }

        $data = [
            'province'=>$province,
            'id'=>$id
        ];
        return response()->json($data);
    }

    /**
     * ajax获取地区的数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxThird(Request $request)
    {
        $id = intval($request->get('id'));
        if(is_null($id)){
            return response()->json(['errMsg'=>'参数错误！']);
        }
        $area = TaskCateModel::findByPid([$id]);
        $domain = \CommonClass::getDomain();
        if(!empty($area)){
            foreach($area as $k => $v){
                $area[$k]['pic'] = $domain.'/'.$v['pic'];
            }
        }
        return response()->json($area);
    }

    /**
     * 实例添加
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function taskTemplates($id)
    {
        //查询当前行业信息
        $industry = TaskCateModel::find($id);
        //检查当前分类是否是一级分类
        if((isset($industry['pid']) && $industry['pid']!=0) || !isset($industry['pid']))
        {
            return redirect()->back()->with(['error'=>'只有一级分类才能添加实例！']);
        }

        //查询当前的行业的实例
        $task_template = TaskTemplateModel::where('cate_id',$id)->first();

        $data = [
            'template'=>$task_template,
            'industry'=>$industry,
        ];
        return $this->theme->scope('manage.tasktemplate', $data)->render();
    }

    /**
     * @param Request $request
     */
    public function templateCreate(Request $request)
    {
        $data = $request->except('_token');
        $data['content'] = e($data['desc']);
        $data['status'] = 1;
        $data['created_at'] = date('Y-m-d H:i:s',time());

        $template = TaskTemplateModel::where('cate_id',$data['cate_id'])->first();

        if($template)
        {
            $result = TaskTemplateModel::where('id',$template['id'])->update(['title'=>$data['title'],'content'=>$data['content']]);
        }else{
           $result =  TaskTemplateModel::create($data);
        }

        if(!$result)
            return redirect()->back()->with(['error'=>'操作失败！']);

        return redirect()->back()->with(['message'=>'操作成功']);
    }


    /**
     * 编辑行业分类图标视图
     * @param $id
     * @return mixed
     */
    public function industryInfo($id)
    {
        $cate = TaskCateModel::find($id);
        $view = array(
            'cate'        => $cate
        );
        return $this->theme->scope('manage.industryInfo', $view)->render();
    }

    /**
     * 编辑行业分类图标
     * @param Request $request
     * @return mixed
     */
    public function postIndustryInfo(Request $request)
    {
        if(!$request->get("name")){
            return back()->with(["message"=>"请领域名称"]);
        }
        $file = $request->file('pic');
        $cate=TaskCateModel::where("name",$request->get("name"))->first();
        if($cate && $cate['id'] !=$request->get('id')){
            return back()->with(["message"=>"该领域已存在"]);
        }
        if (!$file) {
            $cate = TaskCateModel::find($request->get('id'));
            $pic = $cate['pic'];
        }else{
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        $arr = array(
            'pic' => $pic,
            'name'=>$request->get('name'),
            'sort'=>$request->get('sort'),
            'type'=>$request->get('type'),
            "updated_at"=>date("Y-m-d H:i:s"),
        );
        $url="manage/industry";
        switch(intval($request->get('type'))){
            case 1:
                $url="manage/industry";
                break;
            case 2:
                $url="manage/skillList";
                break;
            case 3:
                $url="manage/tradPlatform";
                break;
            case 4:
                $url="manage/transactionList";
                break;
            case 6:
                $url="manage/functionalList";
                break;
            case 7:
                $url="manage/jobLevelList";
                break;
        }
        $res = TaskCateModel::where('id',$request->get('id'))->update($arr);
        if($res){
            return redirect($url)->with(array('message' => '操作成功'));
        }
        return back()->with(array('message' => '操作失败'));
    }

    /*
     * 技能标签
     * */
    public function skillList(Request $request){
        $skillList=TaskCateModel::where("type",2);
        if($request->get("name")){
            $skillList=$skillList->where("name","like","%".$request->get("name")."%");
        }
        $skillList=$skillList->orderBy("sort","asc")->paginate(10);
        $data=[
            'skillList'=>$skillList,
            'merge' =>$request->all(),
        ];
        return $this->theme->scope('manage.skillList', $data)->render();
    }
    /*
     * 技能标签添加
     * */
    public function skillAdd($type){
        $data=[
            "type"=>$type,
        ];
        return $this->theme->scope('manage.industryAdd',$data)->render();
    }
    /*
     * 技能标签修改
     * */
    public function skillUpdate($id){
        $cate = TaskCateModel::find($id);
        $view = array(
            'cate'        => $cate
        );
        return $this->theme->scope('manage.industryInfo', $view)->render();
    }
    /*
     * 开放平台
     * */
    public function tradPlatform(Request $request){
        $cate=TaskCateModel::where("type",3);
        if($request->get('name')){
            $cate=$cate->where("name","like","%".$request->get('name')."%");
        }
        $cate=$cate->orderBy('sort',"asc")->paginate(10);
        $data=[
            'cate'=>$cate,
            'merge'=>$request->all(),
        ];
        return $this->theme->scope('manage.tradPlatform', $data)->render();
    }
    /*
     *开放平台添加
     * */
    public function tradPlatformAdd($type){
        $data=[
            "type"=>$type,
        ];
        return $this->theme->scope('manage.industryAdd',$data)->render();
    }
    /*
     * 开放平台修改
     * */
    public function tradPlatformUpdate($id){
        $cate = TaskCateModel::find($id);
        $view = array(
            'cate'        => $cate
        );
        return $this->theme->scope('manage.industryInfo', $view)->render();
    }
    /*
     * 交付形式
     * */
    public function transactionList(Request $request){
        $cate=TaskCateModel::where("type",4);
        if($request->get('name')){
            $cate=$cate->where("name","like","%".$request->get('name')."%");
        }
        $cate=$cate->orderBy('sort',"asc")->paginate(10);
        $data=[
            'cate'=>$cate,
            'merge'=>$request->all(),
        ];
        return $this->theme->scope('manage.transactionList', $data)->render();
    }
    //交付形式添加
    public function transactionAdd($type){
        $data=[
            "type"=>$type,
        ];
        return $this->theme->scope('manage.industryAdd',$data)->render();
    }
    //交付形式修改
    public function transactionUpdate($id){
        $cate = TaskCateModel::find($id);
        $view = array(
            'cate'        => $cate
        );
        return $this->theme->scope('manage.industryInfo', $view)->render();
    }
    //职能
    public function functionalList(Request $request){
        $cate=TaskCateModel::where("type",6);
        if($request->get('name')){
            $cate=$cate->where("name","like","%".$request->get('name')."%");
        }
        $cate=$cate->orderBy('sort',"asc")->paginate(10);
        $data=[
            'cate'=>$cate,
            'merge'=>$request->all(),
        ];
        return $this->theme->scope('manage.functionalList', $data)->render();
    }
    //职能添加
    public function functionalAdd($type){
        $data=[
            "type"=>$type,
        ];
        return $this->theme->scope('manage.industryAdd',$data)->render();
    }
    //职能修改
    public function functionalUpdate($id){
        $cate = TaskCateModel::find($id);
        $view = array(
            'cate'        => $cate
        );
        return $this->theme->scope('manage.industryInfo', $view)->render();
    }
    //职位级别
    public function jobLevelList(Request $request){
        $cate=TaskCateModel::where("type",7);
        if($request->get('name')){
            $cate=$cate->where("name","like","%".$request->get('name')."%");
        }
        $cate=$cate->orderBy('sort',"asc")->paginate(10);
        $data=[
            'cate'=>$cate,
            'merge'=>$request->all(),
        ];
        return $this->theme->scope('manage.jobLevelList', $data)->render();
    }
    //职位级别添加
    public function jobLevelAdd($type){
        $data=[
            "type"=>$type,
        ];
        return $this->theme->scope('manage.industryAdd',$data)->render();
    }
    //职位级别修改
    public function jobLevelUpdate($id){
        $cate = TaskCateModel::find($id);
        $view = array(
            'cate'        => $cate
        );
        return $this->theme->scope('manage.industryInfo', $view)->render();
    }
}
