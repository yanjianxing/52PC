<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ShopDecorateConfigModel extends Model
{
    protected $table = 'show_decorate_config';

    public $timestamps = false;

    protected $fillable = [
        'id','uid', 'title','content','type','status'
    ];

   static public function postData($request){
       if($request->get('serviceImg') || $request->get('serviceTitle') || $request->get('serviceContent')){//上传服务
           self::where('uid',Auth::user()->id)->where("status",$request->get('type'))->where('type',1)->delete();
           for($i=0;$i<count($request->get('serviceImg'));$i++){
               $service[$i]['uid']=Auth::user()->id;
               $service[$i]['url']=$request->get('serviceImg')[$i];
               $service[$i]['title']=$request->get('serviceTitle')[$i];
               $service[$i]['content']=$request->get('serviceContent')[$i];
               $service[$i]['type']=1;
               $service[$i]['status']=$request->get('type');

           }
           self::insert($service);
       }
       if($request->get('caseImg') || $request->get('caseTitle')){//上传成功案例
           self::where('uid',Auth::user()->id)->where("status",$request->get('type'))->where('type',2)->delete();
           for($i=0;$i<count($request->get('caseImg'));$i++){
               $case[$i]['uid']=Auth::user()->id;
               $case[$i]['url']=$request->get('caseImg')[$i];
               $case[$i]['title']=$request->get('caseTitle')[$i];
               $case[$i]['type']=2;
               $case[$i]['status']=$request->get('type');

           }
           self::insert($case);
       }
       if($request->get('teamImg') || $request->get('teamTitle') || $request->get('teamContent')){//上传团队
           self::where('uid',Auth::user()->id)->where("status",$request->get('type'))->where('type',4)->delete();
           for($i=0;$i<count($request->get('teamImg'));$i++){
               $team[$i]['uid']=Auth::user()->id;
               $team[$i]['url']=$request->get('teamImg')[$i];
               $team[$i]['title']=$request->get('teamTitle')[$i];
               $team[$i]['content']=$request->get('teamContent')[$i];
               $team[$i]['type']=4;
               $team[$i]['status']=$request->get('type');

           }
           self::insert($team);
       }
       if($request->get('companyImg') || $request->get('companyTitle')){//我的企业服务
           self::where('uid',Auth::user()->id)->where("status",$request->get('type'))->where('type',5)->delete();
           for($i=0;$i<count($request->get('companyImg'));$i++){
               $company[$i]['uid']=Auth::user()->id;
               $company[$i]['url']=$request->get('companyImg')[$i];
               $company[$i]['title']=$request->get('companyTitle')[$i];
               $company[$i]['type']=5;
               $company[$i]['status']=$request->get('type');

           }
           self::insert($company);
       }
       if($request->get('banner')){
           self::where('uid',Auth::user()->id)->where("status",$request->get('type'))->where('type',3)->delete();
            $bannerArry=array_filter(json_decode($request->get('banner')));
            $domin= $_SERVER['SERVER_NAME'];
            $banner=[];
            foreach($bannerArry as $k=>$v){
                $banner[$k]['uid']=Auth::user()->id;
                $banner[$k]['url']=str_replace('https://'.$domin,'',str_replace('http://'.$domin,'',$v));
                $banner[$k]['sort']=$k;
                $banner[$k]['type']=3;
                $banner[$k]['status']=$request->get('type');
             }
           self::insert($banner);
       }
       return ;
   }


}

