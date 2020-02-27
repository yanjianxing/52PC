<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeoModel extends Model
{
    //
    protected $table = 'seo';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','name','spelling','pic','desc','view_num','created_at','updated_at'
    ];

    public $timestamps = false;
   //$mode = 1 表示 添加，$mode =2 表示移除
    //$type=1,项目，$type=2 方案，$type=3 咨询
    //$taskId=[];$seo_laber=1;
   static public function seoHandle($mode,$type,$taskId,$seo_laber){
       foreach ($taskId as $v){
            switch ($mode){
               case 1:
                   foreach($seo_laber as $sv){
                       $seoRelated=SeoRelatedModel::where("related_id",$v)->where("seo_id",$sv)->where("type",$type)->first();
                       if(!$seoRelated){
                           SeoRelatedModel::insert([
                               'related_id'=>$v,
                               'seo_id'    =>$sv,
                               'type' =>$type
                           ]);
                       }
                   }
                   break;
               case 2:
                   SeoRelatedModel::where("related_id",$v)->whereIn("seo_id",$seo_laber)->where("type",$type)->delete();
                   break;
           }
       }
       return true;
   }
    //生成seo 标签
    //$title="" 标题
    //$type 1:任务，2：方案，3：咨询
    static public function createSeo($title,$type,$taskId){
        $charset="utf-8";
        //function mbstringToArray($str,$charset) {
            $strlen=mb_strlen($title);
            while($strlen){
                $array[]=mb_substr($title,0,1,$charset);
                $str=mb_substr($title,1,$strlen,$charset);
                $strlen=mb_strlen($str);
            }
            $num=1;
            foreach ($array as $v){
                $num=self::SeoDitui($num,$v,$type,$taskId);
                if($num >=3){
                    break;
                }
            }
            return $array;
      //  }
    }
    //递归存储
    static public function SeoDitui($num,$name,$type,$taskId){
         $findSeo=SeoModel::where("name","like",$name)->first();
         if($findSeo){
             $num++;
             SeoRelatedModel::insert([
                 'seo_id'=>$findSeo['id'],
                 'related_id'=>$taskId,
                 'type'=>$type,
             ]);
         }
        return $num;
    }
}
