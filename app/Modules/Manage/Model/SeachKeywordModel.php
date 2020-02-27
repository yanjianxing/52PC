<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Schema;

class SeachKeywordModel extends Model
{
    // protected $table = 'seach_keyword';
    // protected $primaryKey = 'id';
    // protected $fillable = [
    //     'id',
    //     'keyword',//关键字
    //     'times',//关键字次数
    //     'ip',//ip地址
    //     'created_at',//创建时间
    //     'type',//搜索入口 1:网站头部 2 首页头部 3 快包任务内页 4 方案超市内页 5 方案讯
    //     'updated_at',//更新时间
    // ];
    // public $timestamps = false;

    static public function getseachKeywordList1($merge,$paginate=50){
        $n = date('Yn');
        $table = (new SeachKeywordModel())->setTable("seach_keyword_".$n);
        
        // $timeFrom = $merge['timefrom'] ? $merge['timefrom'] : 'created_at';
        if(isset($merge['start']) && !empty($merge['start'])){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $merge['start']);
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $startn = date('Yn',strtotime($start));
            $table = Schema::hasTable("seach_keyword_".$startn) ? (new SeachKeywordModel())->setTable("seach_keyword_".$startn) : (new SeachKeywordModel())->setTable("seach_keyword");
            $table = $table->where('created_at','>',$start);
        }

        if(isset($merge['end']) && !empty($merge['end'])){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $merge['end']);
            $end = date('Y-m-d  23:59:59',strtotime($end));
            $endn = date('Yn',strtotime($end));
            if($table == (new SeachKeywordModel())->setTable("seach_keyword_".$n) && Schema::hasTable("seach_keyword_".$endn)){
                $table = (new SeachKeywordModel())->setTable("seach_keyword_".$endn);
            }
            $table = $table->where('created_at','<',$end);
        }
        // dd($table);
        //编号/关键词搜索
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $keyworld = trim($merge['keywords']);
            $table = $table->where(function($query) use ($keyworld){
                $query->where('keyword','like','%'.$keyworld.'%')
                    ->orwhere('id',$keyworld);
            });
        }

        //搜索入口类型搜索
        if(isset($merge['type']) && !empty($merge['type'])){
            $table = $table->where('type',$merge['type']);
        }
        //搜索次数、创建时间和更新时间排序
        $selectOne = $merge['selectOne'] ? $merge['selectOne'] : 'id';
        $order = 'desc';
        if(isset($merge['order']) && !empty($merge['order'])){
            $order = $merge['order'];
        }

        $counts = $table->sum('times');
        $reslist=$table->select(DB::raw('* , sum(times) as counttimes'))->groupBy('keyword','type')->orderBy($selectOne,$order)->paginate($paginate);
        // dd($reslist);
        $data = [
            'list' => $reslist,
            'count'=> $counts,
        ];
        return $data;
        
    }

    //.获取搜索关键词列表
    static public function getseachKeywordList($merge=[],$paginate=10){

        $reslist=SeachKeywordModel::where('id','>','0')->select('*');
        //编号/关键词搜索
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $keyworld = $merge['keywords'];
            $reslist = $reslist->where(function($query) use ($keyworld){
                $query->where('keyword','like','%'.$keyworld.'%')
                    ->orwhere('id',$keyworld);
            });
        }

        //搜索入口类型搜索
        if(isset($merge['type']) && !empty($merge['type'])){
            $reslist = $reslist->where('type',$merge['type']);
        }

        //搜索次数、创建时间和更新时间排序
        $selectOne = $merge['selectOne'] ? $merge['selectOne'] : 'times';
        if(isset($merge['order']) && !empty($merge['order'])){
            $reslist = $reslist->orderBY($selectOne,$merge['order']);
        }

        //创建时间和更新时间搜索
        $timeFrom = $merge['timefrom'] ? $merge['timefrom'] : 'created_at';
        if(isset($merge['start']) && !empty($merge['start'])){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $merge['start']);
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $reslist = $reslist->where($timeFrom,'>',$start);
        }
        if(isset($merge['end']) && !empty($merge['end'])){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $merge['end']);
            $end = date('Y-m-d  23:59:59',strtotime($end));
            $reslist = $reslist->where($timeFrom,'<',$end);
        }
        $counts = $reslist->sum('times');
        $reslist=$reslist->orderBy('id','desc')->paginate($paginate);
        
        $data = [
            'list' => $reslist,
            'count'=> $counts,
        ];
        return $data;
    }

}
