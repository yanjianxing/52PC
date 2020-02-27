<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Http\Controllers\BasicController;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\SeoRelated;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\User\Model\DistrictModel;
use Illuminate\Http\Request;
use Excel;
use Illuminate\Support\Facades\DB;

class SeoController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'agreement');
    }
    //seo 标签列表
    public function seoLabelList(Request $request){
        $list=SeoModel::whereRaw("1=1");
        $paginate=$request->get("paginate")?$request->get("paginate"):10;
        if($request->get("name")){
            $list=$list->where("name","like","%".$request->get("name")."%");
        }
        if($request->get('start')){//开始时间
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where("created_at", '>', $start);
        }
        if($request->get('end')){//结束时间
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d H:i:s',strtotime($end) + 24*60*60);
            $list = $list->where("created_at", '<', $end);
        }
        $list=$list->orderBy("created_at","desc")->paginate($paginate);
        foreach($list as $k=>$v){
            $list[$k]['artNum']=SeoRelatedModel::where("seo_id",$v['id'])->where("type",3)->count();//文章
            $list[$k]['proNum']=SeoRelatedModel::where("seo_id",$v['id'])->where("type",2)->count();//方案
            $list[$k]['taskNum']=SeoRelatedModel::where("seo_id",$v['id'])->where("type",1)->count();//项目
        }
        $data =[
            'list'=>$list,
            'merge'=>$request->all(),
            'paginate'=>$paginate,
        ];
        $this->theme->setTitle('seo管理列表');
        return $this->theme->scope('manage.seo.seoLabelList', $data)->render();
    }
    //seo 标签的添加和修改
    public function seoLabelAction(Request $request){
        $seo=[];
        if($request->get('seo_id')){
            $seo=SeoModel::find($request->get('seo_id'));
        }
        $data =[
            'seo'=>$seo
        ];
        $this->theme->setTitle('seo管理添加');
        return $this->theme->scope('manage.seo.seoLabelAction', $data)->render();
    }
    //数据存储
    public function seoData(Request $request){

        if(!$request->get("name")){
            return back()->with(["message"=>"标签名称没有填写"]);
        }
        $seo=SeoModel::where("name",trim($request->get("name")))->first();
        if($seo){
            if(!$request->get('seo_id') || $request->get('seo_id') !=$seo['id'])
            return back()->with(["message"=>"该标签已存在"]);
        }
        $file = $request->file('pic');

        $arr = array(
            'name'=>$request->get('name'),
             'spelling'=>\CommonClass::getFirstCharter($request->get('name')),
            'desc'=>$request->get('desc'),
            "updated_at"=>date("Y-m-d H:i:s"),
        );
        if ($file){
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $arr['pic'] = $result['data']['url'];
        }
        if($request->get("seo_id")){
            $res = SeoModel::where("id",$request->get("seo_id"))->update($arr);
        }else{
            $arr["created_at"]=date("Y-m-d H:i:s");
            $res = SeoModel::insert($arr);
        }

        $url="/manage/seoLabelList";
        if($res){
            return redirect($url)->with(array('message' => '操作成功'));
        }
    }
    //seo标签删除
    public function seoLabelDelete($id){
        //查询该标签下面是否存在其他的东西
        $seoRelated=SeoRelatedModel::where("seo_id",$id)->first();
        if($seoRelated){
            return back()->with(["message"=>"该标签下存在相关东西"]);
        }
        $res=SeoModel::where("id",$id)->delete();
        if($res){
            return back()->with(["message"=>"删除成功"]);
        }
           return back()->with(["message"=>"删除失败"]);
    }
    //seo标签批量删除
    public function seoLabelDelAll(Request $request){
        $res=SeoModel::whereIn("id",$request->get("skillID"))->delete();
        if($res){
            return back()->with(["message"=>"删除成功"]);
        }
        return back()->with(["message"=>"删除失败"]);
    }
    //seo 标签处理
    public function seoLabelHandle(Request $request){
        $task_id=json_decode($request->get("task_id"));
        $res=SeoModel::seoHandle($request->get("mode"),$request->get("type"),$task_id,$request->get("seo_laber"));
        if($res){
            return back()->with(["message"=>"操作成功"]);
        }
        return back()->with(["message"=>"操作失败"]);
    }
    //seo 模板下载
    public function seoTempDownload(){
        $cellData = [
            ['seo标签名称','浏览量','图片地址',"内容"],
        ];
        Excel::create('seo标签',function ($excel) use ($cellData){
            $excel->sheet('score', function ($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('xls');
    }
    //seo 批量导入
    public function seoTempUp(Request $request){
        $file = $request->file('file');
        if(!in_array($file->getClientOriginalExtension(),['xls','xlsx'])){
           return back()->with(["error"=>"格式有问题"]);
        }
        Excel::load($file->getPathName(), function($reader){//读取数据
            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            //进行数据存储剔除头部
            unset($data[0]);
            //数据大，进行分段操作
            $count=count($data);
            $ResultData=[];
            $path = public_path().'/attachment/sys/skill';
           foreach($data as $key=>$val){
               $findSeo=SeoModel::where("name",trim($val[0]))->first();
               if(!$findSeo && !empty($val[0])){
                   //下载图片
                   $pic='';
                   if(!empty($val[2])){
                       $aext = explode('.', $val[2]);
                       $ext = end($aext);
                       $code = \CommonClass::random(4);
                       $validation_img = $path.'/'. $code.time() . '.' . $ext;
                       if(!is_dir($path)){
                           mkdir($path,0777, true);
                       }
                       $source = file_get_contents($val[2]);
                       if($source){
                           file_put_contents($validation_img,$source);
                       }
                       $pic = \CommonClass::get_between($validation_img, "public/");
                   }
                   $ResultData[]=[
                       'name'=>$val[0],
                       'spelling'=>\CommonClass::getFirstCharter($val[0]),
                       'pic'=>$pic,
                       'desc'=>$val[3],
                       'view_num'=>$val[1],
                       "created_at"=>date("Y-m-d H:i:s"),
                   ];
               }
           }
            for($i=0;$i<ceil($count/1000);$i++){
                SeoModel::insert(array_slice($ResultData,$i*1000,1000),true);
            }
        });
        return back()->with(["message"=>"导入成功"]);
    }
    //seo标签批量导入
    public function seoLabelAll(Request $request){
        //默认查询项目
        $type=$request->get("type")?$request->get("type"):1;
        $merge=[
            'name'=>$request->get("name"),
            'type'=>$type,
        ];
        switch($type){
            case 1: //项目
                $list=TaskModel::whereRaw("1=1");
                break;
            case 2://方案
                $list=GoodsModel::whereRaw("1=1");
                break;
            case 3://咨询
                $list=ArticleModel::whereRaw("1=1");
                break;
        }
        if($request->get("name")){
            $list=$list->where("title","like","%".$request->get("name")."%");
        }
        $paginate=$request->get("paginate")?$request->get("paginate"):200;
        $list=$list->orderBy("created_at","desc")->select("id","title","created_at")->paginate($paginate);
        foreach($list as $k=>$v){
           $list[$k]['count']=SeoRelatedModel::where("related_id",$v['id'])->where("type",$type)->count();
           $list[$k]['skill']=SeoRelatedModel::LeftJoin('seo',"seo_related.seo_id",'=','seo.id')->where("seo_related.related_id",$v['id'])->where("seo_related.type",$type)->lists('seo.name')->toArray();
            $list[$k]['skill']=implode(",",$list[$k]['skill']);
        }
        //获取所有的标签
        $seoList=SeoModel::select("id","name")->get();
        $data =[
            'list'=>$list,
            'merge'=>$merge,
            'paginate'=>$paginate,
            'seoList'=>$seoList,
        ];
        $this->theme->setTitle('seo管理列表');
        return $this->theme->scope('manage.seo.seoLabelAll', $data)->render();
    }
}
