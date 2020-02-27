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
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class AdTargetModel extends Model
{
    protected $table = 'ad_target';
    protected $fillable = ['target_id','name','code','ad_num','pic','type_id'];
    public  $timestamps = false;  //关闭自动更新时间戳


    /**
     * 根据类型获取广告
     * @param int $typeId 1：首页 2：方案超市 3:快包项目 4：找服务商 5：方案讯 6：成功案例
     * @return array
     */
    static public function getAdByTypeId($typeId)
    {
        $targetArr = self::select('target_id','code')->where('type_id',$typeId)->where('is_open',1)->get()->toArray();
        $ad = [];
        if($targetArr){
            $targetArr = \CommonClass::setArrayKey($targetArr,'target_id');
            $adArr = AdModel::whereIn('target_id',array_keys($targetArr))
                ->where('is_open','1')
                ->where(function($query){
                    $query->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d H:i:s',time()));
                })
                ->select('id','ad_file','ad_url','target_id','ad_type','ad_js')
                ->orderBy('listorder','asc')->orderBy('created_at','desc')
                ->get()->toArray();
            if($adArr){
                $codeArr = [
                    'GOODS_H','ARTICLE_H','SUCCESS_H','SERVICE_H','TASK_H'
                ];
                $adArr = \CommonClass::setArrayKey($adArr,'target_id',2);
                foreach($targetArr as $k => $v){
                    if(in_array($k,array_keys($adArr))){
                        if(!in_array($v['code'],$codeArr)){
                            $kk = array_rand($adArr[$k],1);
                            $ad[$v['code']][] = $adArr[$k][$kk];
                        }else{
                            $ad[$v['code']] = $adArr[$k];
                        }

                    }
                }
            }
        }
        return $ad;
    }

    /**
     * 获取广告并增加曝光量
     * @param string $codePage 页面代号 HOME:首页 TASK:快包项目
     * @return array
     */
    static public function getAdByCodePage($codePage)
    {
        $targetArr = self::select('target_id','code')->where('code_page',$codePage)->where('is_open',1)->get()->toArray();
        $ad = [];
        if($targetArr){
            $targetArr = \CommonClass::setArrayKey($targetArr,'target_id');
            $adArr = AdModel::whereIn('target_id',array_keys($targetArr))
                ->where('is_open','1')
                ->where(function($query){
                    $query->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d H:i:s',time()));
                })
                ->select('id','ad_file','ad_url','target_id','ad_type','ad_js')
                ->orderBy('listorder','asc')->orderBy('created_at','desc')
                ->get()->toArray();
            $adIdArr = [];
            if($adArr){
                $codeArr = [
                    'GOODS_H','ARTICLE_H','SUCCESS_H','SERVICE_H','TASK_H'
                ];
                $adArr = \CommonClass::setArrayKey($adArr,'target_id',2);
                foreach($targetArr as $k => $v){
                    if(in_array($k,array_keys($adArr))){
                        if(!in_array($v['code'],$codeArr)){
                            $kk = array_rand($adArr[$k],1);
                            $ad[$v['code']][] = $adArr[$k][$kk];
                            $adIdArr[] = $adArr[$k][$kk]['id'];
                        }else{
                            $ad[$v['code']] = $adArr[$k];
                            if(is_array($adArr[$k]) && !empty($adArr[$k])){
                                $idArr = array_pluck($adArr[$k],'id');
                                $adIdArr = array_merge($adIdArr,$idArr);
                            }
                        }
                    }
                }
            }
            if($adIdArr){
                $adArr = AdModel::whereIn('id',$adIdArr)->where('is_open','1')->get()->toArray();
                $ip = \CommonClass::getIp();
                $dataArr = [];
                foreach($adArr as $k => $v){
                    $dataArr[] = [
                        'ad_id'      => $v['id'],
                        'target_id'  => $v['target_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'ip'         => $ip,
                        'type'       => 1
                    ];
                }
                if($dataArr){
                    $n = date('Yn');
                    (new AdStatisticModel())->setTable("ad_statistic_".$n)
                        ->insert($dataArr);
                }
            }
        }
        return $ad;
    }

    /**
     * 根据广告位置代号获取广告并增加曝光量
     * @param string $codePage 页面代号 HOME:首页 TASK:快包项目
     * @return array
     */
    static public function getAdByCode($code)
    {
        $targetArr = self::select('target_id','code')->where('code',$code)->where('is_open',1)->get()->toArray();
        $ad = [];
        if($targetArr){
            $targetArr = \CommonClass::setArrayKey($targetArr,'target_id');
            $adArr = AdModel::whereIn('target_id',array_keys($targetArr))
                ->where('is_open','1')
                ->where(function($query){
                    $query->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d H:i:s',time()));
                })
                ->select('id','ad_file','ad_url','target_id','ad_type','ad_js')
                ->orderBy('listorder','asc')->orderBy('created_at','desc')
                ->get()->toArray();
            $adIdArr = [];
            if($adArr){
                $codeArr = [
                    'GOODS_H','ARTICLE_H','SUCCESS_H','SERVICE_H','TASK_H'
                ];
                $adArr = \CommonClass::setArrayKey($adArr,'target_id',2);
                foreach($targetArr as $k => $v){
                    if(in_array($k,array_keys($adArr))){
                        if(!in_array($v['code'],$codeArr)){
                            $kk = array_rand($adArr[$k],1);
                            $ad[$v['code']][] = $adArr[$k][$kk];
                            $adIdArr[] = $adArr[$k][$kk]['id'];
                        }else{
                            $ad[$v['code']] = $adArr[$k];
                            if(is_array($adArr[$k]) && !empty($adArr[$k])){
                                $idArr = array_pluck($adArr[$k],'id');
                                $adIdArr = array_merge($adIdArr,$idArr);
                            }
                        }
                    }
                }
            }
            if($adIdArr){
                $adArr = AdModel::whereIn('id',$adIdArr)->where('is_open','1')->get()->toArray();
                $ip = \CommonClass::getIp();
                $dataArr = [];
                foreach($adArr as $k => $v){
                    $dataArr[] = [
                        'ad_id'      => $v['id'],
                        'target_id'  => $v['target_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'ip'         => $ip,
                        'type'       => 1
                    ];
                }
                if($dataArr){
                    $n = date('Yn');
                    (new AdStatisticModel())->setTable("ad_statistic_".$n)
                        ->insert($dataArr);
                }
            }
        }
        return $ad;
    }

    /**
     * 增加广告曝光统计
     * @param string $codePage  页面代号 HOME:首页 TASK:快包项目
     */
    static public function addViewCountByCode($codePage)
    {
        $ip = \CommonClass::getIp();
        $targetIdArr = self::where('code_page',$codePage)->lists('target_id')->toArray();
        /*$adArr = AdModel::whereIn('target_id',$targetIdArr)->where('is_open','1')
            ->where(function($query){
                $query->where('end_time','0000-00-00 00:00:00')
                    ->orWhere('end_time','>',date('Y-m-d H:i:s',time()));
            })->get()->toArray();*/
        $dataArr = [];
        if($targetIdArr){
            foreach($targetIdArr as $k => $v){
                $dataArr[] = [
                    'ad_id'      => 0,
                    'target_id'  => $v,
                    'created_at' => date('Y-m-d H:i:s'),
                    'ip'         => $ip,
                    'type'       => 1
                ];
            }
        }
        if($dataArr){
            $n = date('Yn');
            (new AdStatisticModel())->setTable("ad_statistic_".$n)
                ->insert($dataArr);
            //AdStatisticModel::insert($dataArr);
        }
    }

    /**
     * 根据广告位代号获取广告信息
     * @param string $targetCode 广告位代号
     * @return array
     */
    static function getAdInfo($targetCode)
    {
        $adTargetInfo = AdTargetModel::where('code',$targetCode)->select('target_id')->first();
        $ad = [];
        if($adTargetInfo['target_id']){
            $rightPicInfo = AdModel::where('target_id',$adTargetInfo['target_id'])
                ->where('is_open','1')
                ->where(function($rightPicInfo){
                    $rightPicInfo->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d H:i:s',time()));
                })
                ->select('ad_file','ad_url')
                ->get();
            if(count($rightPicInfo)){
                $ad = $rightPicInfo;
            }
            else{
                $ad = [];
            }
        }
        return $ad;
    }

}
