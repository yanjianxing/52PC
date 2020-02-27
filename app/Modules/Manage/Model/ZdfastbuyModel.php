<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;

class ZdfastbuyModel extends Model
{
    //
    protected $table = 'zdfastbuy';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'name',
        'url',
        'aurl',
        'is_delete',
        'model',
        'store',
        'created_at',
        'show_location',
    ];



    /**
     * //.获取中电快购列表
     * @param $data
     * @return bool
     */
    static public function getZdfastbuyModelList($merge=[],$paginate=10){
        //.获取中电快购列表
        $ZdfastbuyList = ZdfastbuyModel::where('id','>',0)->where('is_del',0);;

        //编号、链接
        if (isset($merge['keywords'])&&!empty($merge['keywords'])) {
            $merge['keywords']=trim($merge['keywords']);
            $ZdfastbuyList=$ZdfastbuyList->where(function($ZdfastbuyList)use($merge){
                $ZdfastbuyList->Where('id',$merge['keywords'])
                    ->orWhere("aurl",'like','%'.$merge['keywords'].'%');
            });
        }
        //位置筛选
        if (isset($merge['show_location'])&&!empty($merge['show_location'])) {
            $ZdfastbuyList->where('show_location',$merge['show_location']);
        }

        //时间筛选
        if(isset($merge['start'])&&!empty($merge['start'])){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $merge['start']);
            $start = date('Y-m-d H:i:s',strtotime($start));
            $ZdfastbuyList = $ZdfastbuyList->where('created_at','>=',$start);
        }
        if(isset($merge['end'])&&!empty($merge['end'])){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $merge['end']);
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $ZdfastbuyList = $ZdfastbuyList->where('created_at','<=',$end);
        }
        $ZdfastbuyList = $ZdfastbuyList->select('id','name','url','aurl','created_at','show_location','model','store')->orderBy('id','dese')->paginate($paginate);
        $ZdfastbuyList=isset($ZdfastbuyList)?$ZdfastbuyList:[];
        return $ZdfastbuyList;
    }

}
