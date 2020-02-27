<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/17
 * Time: 17:02
 */
namespace App\Modules\Advertisement\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class AdStatisticModel extends Model
{
    /*protected $table = 'ad_statistic';
    public  $timestamps = false;  //关闭自动更新时间戳

    protected $fillable = ['id','ad_id','target_id','created_at','ip','type'];*/


    /*传入查询的开始日期和结束日期用于计算跨越的表和达到约束表数据的目的。
    外部可以调整查询的列，还可以添加where条件*/
    public function setUnionAllTable($startTime = LARAVEL_START, $endTime = LARAVEL_START, $attributes = ['*'], $wheres = [])
    {
        //约束条件
        $whereConditions = [];
        $wheres = array_merge([['created_at', '>=', '"'.date('Y-m-d 00:00:00',$startTime).'"'], ['created_at', '<',  '"'.date('Y-m-d 23:59:59',$endTime).'"']], $wheres);
        //时间戳转日期
        $startDate = date('Y-m', $startTime);
        $endDate = date('Y-m', $endTime);
        //涉及的表数组
        $tables = [];
        //循环where数组，格式是[['字段','表达式','值',' and|or '],['字段','表达式','值',' and|or ']]
        //例子[['beauty_uid', '=', '2011654', 'and']]
        foreach ($wheres as $val) {
            //组装每个where条件
            $val[2] = isset($val[2]) ? $val[2] : "''";
            if (isset($val[3])) {
                $whereConditions[] = " {$val[3]} {$val[0]} {$val[1]} {$val[2]}";
            } else {
                $whereConditions[] = " and {$val[0]} {$val[1]} {$val[2]}";
            }
        }
        //循环开始日期和结束日期计算跨越的表
        for ($i = $startDate; $i <= $endDate; $i = date('Y-m', strtotime($i . '+1month'))) {
            if(Schema::hasTable('ad_statistic_' . date('Yn', strtotime($i)))){
                $tables[] = 'select ' . implode(',', $attributes) . ' from wafaw_ad_statistic_' . date('Yn', strtotime($i)) . ' where 1' . implode('', $whereConditions);
            }
        }
        //会得到每一个表的子查询，因为都有约束条件，所以每一个子查询得结果集都不会很多
        //用setTable的方法把这个子查询union all 后 as一个表名作为model的table属性
        //sql大概会是:(select xxx,xxx from ad_statistic_20177 where time >= 开始日期 and time < 结束日期 and xxx union all select xxx,xxx from ad_statistic_20178 where time >= 开始日期 and time < 结束日期 and xxx) as ad_statistic
        //核心是看你输入的开始日期和结束日期和约束条件，组装成一个union all的子查询然后作为table赋予model
        return $this->setTable(DB::raw('(' . implode(' union all ', $tables) . ') as ad_statistic'));
    }

    public function target()
    {
        return $this->hasOne('App\Modules\Advertisement\Model\AdTargetModel','target_id','target_id')->select('target_id','name');
    }

    public function ad()
    {
        return $this->hasOne('App\Modules\Advertisement\Model\AdModel','id','ad_id')->select('id','ad_name');
    }

}
