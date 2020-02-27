<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;

class ServiceModel extends Model
{
    //
    protected $table = 'service';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id','title','description','price','type','created_at','updated_at','status','identify','pic'
    ];

    public $timestamps = false;

    //查询一级和二级的增值服务
    static public function getAll(){
        $serviceAll=self::where("pid",0)->where("type",1)->get();
        foreach ($serviceAll as $key=>$val){
            $serviceAll[$key]["child"]=self::where("pid",$val['id'])->where("type",1)->get();
        }
        return $serviceAll;
    }


}
