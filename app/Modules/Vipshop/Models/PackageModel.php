<?php

namespace App\Modules\Vipshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Modules\Vipshop\Models\PackagePrivilegesModel;
use App\Modules\Vipshop\Models\PrivilegesModel;

class PackageModel extends Model
{
    use SoftDeletes;
    protected $table = 'package';

    protected $primaryKey = 'id';

    protected $fillable = [

        'title', 'logo', 'status', 'price_rules', 'list', 'created_at', 'updated_at','deleted_at'

    ];
    protected $datas = ['deleted_at'];

    /**
     * 获取套餐列表
     * @param $uid  用户id
     * @return array
     */
    static function packageList(){
        $packageInfo = PackageModel::select('*')->orderBy('list','asc')->orderBy('created_at','desc')->paginate(10);
        if($packageInfo->total()){
            foreach($packageInfo->items() as $k=>$v){
                $v->price = collect(array_pluck(json_decode($v->price_rules,true),'cash'))->sort()->values()->first();
            }
        }
        return $packageInfo;
    }

    /**
     * 更改套餐的上下架状态
     * @param   套餐id
     * @return data
     */
    static function updateStatus($id){
        $packageInfo = PackageModel::find(intval($id));
        if(empty($packageInfo)){
            return 2;                //套餐不存在
        }else{
            if($packageInfo->status == 0){
                $res = $packageInfo->update(['status' => 1,'updated_at' => date('Y-m-d H:i:s',time())]);
            }else{
                $num = PackageModel::where('status',0)->count();
                if($num >= 5){
                    return 3;              //已上架套餐超过限制
                }
                $res = $packageInfo->update(['status' => 0,'updated_at' => date('Y-m-d H:i:s',time())]);
            }
            return $res?1:0;                     //1代表修改成功 0代表修改失败
        }
    }

    /**
     * 删除套餐
     * @param   套餐id
     * @return data
     */
    static function deletePackage($id){
        $packageInfo = PackageModel::find(intval($id));
        if(empty($packageInfo)){
            return 2;           //套餐不存在
        }
        $res = $packageInfo->delete();
        return $res?1:0;        //1代表删除成功 0代表删除失败
    }

    /**
     * 添加套餐
     * @param   array $data
     * @return bool
     */
    static function addPackage(array $data){
        $price_rules = json_encode($data['price_rules']);
        $packageInfo = [
            'title' => $data['title'],
            'logo' => $data['logo'],
            'status' => $data['status']?0:1,
            'price_rules' => $price_rules,
            'list' => $data['list'],
            'created_at' => date('Y-m-d H:i:s',time()),
            'updated_at' => date('Y-m-d H:i:s',time())
        ];
        $package['privileges'] = $data['privileges'];
        $package['packageInfo'] = $packageInfo;
        $res = DB::transaction(function()use($package){
            $packageId = PackageModel::insertGetId($package['packageInfo']);
            $privilegesInfo = [];
            foreach($package['privileges'] as $k=>$v){
                $privilegesInfo[$k]['package_id'] = $packageId;
                $privilegesInfo[$k]['privileges_id'] = $v;
            }
            PackagePrivilegesModel::insert($privilegesInfo);
        });
        return is_null($res)?true:false;    //true代表添加套餐成功 false代表添加套餐失败
    }

    /**
     * 查看所有已启用的特权内容
     * @return array
     */
    static function privileges(){
        $privileges = [];
        $privilegesInfo = PrivilegesModel::where('status',0)->orderBy('list','asc')->select('id','title')->get()->toArray();
        if(!empty($privilegesInfo)){
            $privileges = $privilegesInfo;
        }
        return $privileges;
    }

    /**
     * 查询套餐详情
     * @param   套餐id
     * @return mixed
     */
    static function packageInfo($id){
        $packageInfo = PackageModel::where('id',intval($id))->first();
        if(empty($packageInfo)){
            return false;
        }
        $packageInfo['price_rules'] = json_decode($packageInfo['price_rules'],true);
        $privilegesChk = [];
        $privileges = PackagePrivilegesModel::where('package_id',intval($id))->select('privileges_id')->get()->toArray();
        if(!empty($privileges)){
            $privilegesChk = array_flatten($privileges);
        }
        $packageInfo['privileges'] = $privilegesChk;
        return $packageInfo;

    }

    /**
     * 编辑套餐
     * @param   array $data  套餐id
     * @return bool
     */
    static function updatePackage($id,array $data){
        $price_rules = json_encode($data['price_rules']);
        $packageInfo = [
            'title' => $data['title'],
            //'logo' => $data['logo'],
            'status' => $data['status']?0:1,
            'price_rules' => $price_rules,
            'list' => $data['list'],
            'updated_at' => date('Y-m-d H:i:s',time())
        ];
        if(isset($data['logo']) && $data['logo']){
            $packageInfo['logo'] = $data['logo'];
        }
        $package['privileges'] = $data['privileges'];
        $package['packageInfo'] = $packageInfo;
        $package['id'] = intval($id);
        $res = DB::transaction(function()use($package){
            $packageInfo = PackageModel::where('id',$package['id'])->update($package['packageInfo']);
            PackagePrivilegesModel::where('package_id',$package['id'])->delete();
            $privilegesInfo = [];
            foreach($package['privileges'] as $k=>$v){
                $privilegesInfo[$k]['package_id'] = $package['id'];
                $privilegesInfo[$k]['privileges_id'] = $v;
            }
            PackagePrivilegesModel::insert($privilegesInfo);
        });
        return is_null($res)?true:false;       //true代表编辑成功  false代表编辑失败
    }
}
