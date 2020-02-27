<?php

namespace App\Modules\User\Model;

use Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CollectionModel extends Model
{
    protected $table = 'collection';
    public $timestamps = false;
    protected $fillable = [
       'id','uid', 'collec_id', 'type', 'created_at'
    ];


    //收藏
    static function collection($data,$status){
        $url = "/anli/$data[collec_id]";
        if(empty($data) || empty($status)){
            return redirect($url)->with(array('message' => '获取参数失败!'));
        }
        if($status== 1){    //收藏
            $result = self::where("uid",$data['uid'])->where("collec_id",$data['collec_id'])->where("type",$data['type'])->first();
            if($result){
                return redirect($url)->with(array('message' => '您已经收藏！请勿重复收藏'));
            }
            $res = Self::create($data);
            if(!$res){
                return redirect($url)->with(array('message' => '收藏失败！请联系管理员'));
            }
            return redirect($url)->with(array('message' => '收藏成功！'));
        }elseif($status == 2){      //取消收藏
            $result = self::where("uid",$data['uid'])->where("collec_id",$data['collec_id'])->where("type",$data['type'])->first();
            if($result){
                $res = Self::destroy($result['id']);
                if($res){
                    return redirect($url)->with(array('message' => '取消收藏成功！'));
                }
                return redirect($url)->with(array('message' => '取消收藏失败！'));
            }
            return redirect($url)->with(array('message' => '您还没有收藏！'));
        }else{
            return redirect("/anli")->with(array('message' => '您已经收藏！请勿重复收藏'));
        }
        
    }
}
