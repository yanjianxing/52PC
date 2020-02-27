<?php

namespace App\Modules\User\Model;

use Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DistrictModel extends Model
{
    protected $table = 'district';
    public $timestamps = false;
    protected $fillable = [
       'id','upid', 'name', 'type', 'displayorder'
    ];

    /**
     * 获取下级地区信息
     *
     * @param $id
     * @return arr
     */
//    static function getZone($id)
//    {
//        return DistrictModel::where('upid', $id)->get();
//    }

    /**
     * 关联查询地区数据
     * @return array
     */
    static function findAll()
    {
        return DistrictModel::with('childrenArea')->where('type', '=', 3)->get()->toArray();
    }

    /**
     * 根据upid查询缓存中的数据
     * @param $pid
     * @return mixed
     */
    static function findTree($pid)
    {
        $data = array();
        //判断当前的pid是否为0
        if($pid==0)
        {
            $data = self::getDistrictProvince();
        }else
        {
            //查询当前的pid
            $district_relationship = self::getDistractRelationship();
            $upid = $district_relationship[$pid];
            if($upid == 0)
            {
                //查询所有城市数据
                $province_data = self::getProvinceDetail($pid);
                foreach($province_data as $v)
                {
                    if($v['upid']==$pid){
                        $data[] = $v;
                    }
                }
            }else
            {
                //上级的id是upid，查询省份数据中是否有这个数据
                $province_detail = self::getProvicneData($upid);
                if(empty($province_detail))
                {
                    return false;
                }
                //查询所有的数据
                $province_data = self::getProvinceDetail($upid);
                foreach($province_data as $v)
                {
                    if($v['upid']==$pid){
                        $data[] = $v;
                    }
                }
            }
        }
        return $data;
    }
    static function findById($id,$fild=null)
    {
		$area_data = self::refreshAreaCache();
        $data = array();
        foreach($area_data as $k=>$v)
        {
            if(is_array($id) && in_array($v['id'],$id))
            {
                if(!is_null($fild))
                {
                    $data[] = $v[$fild];
                }else
                {
                    $data[] = $v;
                }

            }elseif($v['id']==$id)
            {
                if(!is_null($fild))
                {
                    $data = $v[$fild];
                }else
                {
                    $data = $v;
                }
            }
        }
        return $data;
    }
    /**
     * 获取地区名称
     *
     * @param $id
     * @return mixed
     */
    static function getDistrictName($id)
    {
        if (is_array($id)) {
            $arrDistrictName = DistrictModel::whereIn('id', $id)->lists('name')->toArray();
            return implode('', $arrDistrictName);
        }
        $arrDistrictName = DB::table('district')->select('name')->where('id', $id)->first();
        if (!empty($arrDistrictName))
            return $arrDistrictName->name;
    }

    /**
     * 更新地区缓存
     */
    static function refreshAreaCache()
    {
        //缓存所有的id和pid关系
        $district_relationship = DistrictModel::lists('upid','id')->toArray();
        Cache::put('district_relationship',$district_relationship,24*60);
        //缓存所有pid为0的数据
        $province = DistrictModel::where('upid',0)->orderBy('displayorder')->get()->toArray();
        Cache::put('district_province',$province,24*60);
        //缓存所有每一province下边的数据
        foreach($province as $k=>$v)
        {
            //查询一级下边的二级数据
            $city_ids = DistrictModel::where('upid',$v['id'])->lists('id');
            $city_data = DistrictModel::whereIn('id',$city_ids)->orderBy('displayorder')->get()->toArray();
            //查询三级地区数据
            $area_data = DistrictModel::whereIn('upid',$city_ids)->orderBy('displayorder')->get()->toArray();
            $other_data = array_merge($city_data,$area_data);
            Cache::put('district_list_'.$v['id'],$other_data,24*60);
        }

    }

    /**
     * @return mixed
     */
    static function getDistractRelationship()
    {
        if(Cache::has('district_relationship'))
        {
            $data = Cache::get('district_relationship');
        }else{
            $data = DistrictModel::lists('upid','id')->toArray();
            Cache::put('district_relationship',$data,24*60);
        }
        return $data;
    }

    /**
     * @return mixed
     */
    static function getDistrictProvince()
    {
        if(Cache::has('district_province'))
        {
            $data = Cache::get('district_province');
        }else{
            $data = DistrictModel::where('upid',0)->get()->toArray();
            Cache::put('district_province',$data,24*60);
        }
        return $data;
    }

    /**
     * @param $id
     * @return array
     */
    static function getProvinceDetail($id)
    {
        if(Cache::has('district_list_'.$id))
        {
            $data = Cache::get('district_list_'.$id);
        }else{
            //查询一级下边的二级数据
            $city_ids = DistrictModel::where('upid',$id)->lists('id');
            $city_data = DistrictModel::whereIn('id',$city_ids)->get()->toArray();
            //查询三级地区数据
            $area_data = DistrictModel::whereIn('upid',$city_ids)->get()->toArray();
            $data = array_merge($city_data,$area_data);
            Cache::put('district_list_'.$id,$data,24*60);
        }
        return $data;
    }

    /**
     * @param $id
     * @return null
     */
    static function getProvicneData($id)
    {
        $province_datas = Self::getDistrictProvince($id);
        $data = null;
        foreach($province_datas as $k=>$v)
        {
            if($v['id']==$id)
            {
                $data = $v;
            }
        }
        return $data;
    }

    /**
     * app获取地区(显示二级地名直辖市除外)
     * @param $provinceId
     * @param $cityId
     * @return mixed
     */
    static public function getAreaName($provinceId,$cityId)
    {
        $provinceName = '';
        if($provinceId){
            $province = DistrictModel::where('id',$provinceId)->select('id','name')->first();
            if($province){
                $provinceName = $province->name;
            }
        }
        $cityName = '';
        if($cityId){
            $city = DistrictModel::where('id',$cityId)->select('id','name')->first();
            if($city){
                $cityName = $city->name;
            }
        }
        if(in_array($provinceName,['北京市','上海市','天津市','重庆市'])){
            $name = $provinceName;
        }else{
            $name = $cityName;
        }
        return $name;
    }

}
