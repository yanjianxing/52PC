<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/10/27
 * Time: 13:32
 */
namespace App\Modules\Vipshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Vipshop\Models\PackagePrivilegesModel;
use App\Modules\Vipshop\Models\PackageModel;
use DB;

class PrivilegesModel extends Model
{
    use SoftDeletes;
    protected $table = 'privileges';

    protected $primaryKey = 'id';

    protected $fillable = [

        'title', 'desc', 'code', 'list', 'type', 'status', 'is_recommend', 'ico','created_at','updated_at','deleted_at'

    ];
    protected $datas = ['deleted_at'];

    /**
     * 获取特权列表或筛选后的特权列表
     * @param $data  筛选条件（可传可不传）
     * @return array
     */
    static function privilegesList($data){
        $privileges = PrivilegesModel::select('*');
        if(isset($data['title'])){
            $privileges = $privileges->where('title','like','%'.$data['title'].'%');
        }
        if(isset($data['status']) && $data['status']){
            switch($data['status']){
                case '1':      //启用
                    $status = 0;
                    $privileges = $privileges->where('status',$status);
                    break;
                case '2':      //禁用
                    $status = 1;
                    $privileges = $privileges->where('status',$status);
                    break;

            }
        }
        if(isset($data['is_recommend']) && $data['is_recommend']){
            switch($data['is_recommend']){
                case '1':      //推荐
                    $is_recommend = 1;
                    $privileges = $privileges->where('is_recommend',$is_recommend);
                    break;
                case '2':      //未推荐
                    $is_recommend = 0;
                    $privileges = $privileges->where('is_recommend',$is_recommend);
                    break;

            }
        }
        $privileges = $privileges->orderBy('list','asc')->orderBy('created_at','desc')->paginate(10);
        if($privileges->total()){
            foreach($privileges->items() as $k=>$v){
                $v->desc = substr_replace($v->desc,'...',15);
            }
        }
        return $privileges;

    }

    /**
     * 删除特权
     * @param 特权id
     * @return data
     */
    static function deletePrivileges($id){
        $id = intval($id);
        $privilegesInfo = PrivilegesModel::find($id);
        if(empty($privilegesInfo)){
            return 2;
        }
        $res = DB::transaction(function() use($id){
            $packagePrivileges = PackagePrivilegesModel::where('privileges_id',$id)->delete();
            $privileges = PrivilegesModel::where('id',$id)->delete();
        });
        return is_null($res)?1:0;          //1代表删除成功   0代表删除失败
    }

    /**
     * 启用或停用特权
     * @param 特权id
     * @return data
     */
    static function updateStatus($id){
        $id = intval($id);
        $privilegesInfo = PrivilegesModel::find($id);
        if(empty($privilegesInfo)){
            return 2;
        }
        if($privilegesInfo->status == 0){
            //修改为停用状态
            $res = DB::transaction(function() use($id){
                $privileges = PrivilegesModel::where('id',$id)->update(['status' => 1,'updated_at' => date('Y-m-d H:i:s',time())]);
                $packagePrivileges = PackagePrivilegesModel::where('privileges_id',$id)->delete();
            });
            return is_null($res)?1:0;                      //1代表修改成功  0代表修改失败
        }else{
            $res = $privilegesInfo->update(['status' => 0,'updated_at' => date('Y-m-d H:i:s',time())]);//修改为启用状态
            return $res?1:0;                             //1代表修改成功  0代表修改失败
        }
    }


    /**
     * 推荐或取消推荐
     * @param 特权id
     * @return data
     */
    static function updateRecommend($id){
        $privilegesInfo = PrivilegesModel::find(intval($id));
        if(empty($privilegesInfo)){
            return 2;
        }
        if($privilegesInfo->is_recommend == 0){
            $num = PrivilegesModel::where('is_recommend',1)->count();
            if($num >= 6){
                return 3;              //推荐特权数量超过限制
            }
            $res = $privilegesInfo->update(['is_recommend' => 1,'updated_at' => date('Y-m-d H:i:s',time())]);//修改为推荐状态
        }else{
            $res = $privilegesInfo->update(['is_recommend' => 0,'updated_at' => date('Y-m-d H:i:s',time())]);//修改为未推荐状态
        }
        return $res?1:0;                             //1代表修改成功  0代表修改失败
    }


    /**
     * 添加特权
     * @param array $data
     * @return bool
     */
    static function addPrivileges(array $data){
        $privilegeInfo = [
            'title' => $data['title'],
            'desc' => $data['desc'],
            'list' => $data['list'],
            'ico' => $data['ico'],
            'status' => $data['status']?0:1,
            'is_recommend' => $data['is_recommend']?1:0,
            'created_at' => date('Y-m-d H:i:s',time()),
            'updated_at' => date('Y-m-d H:i:s',time())
        ];
        $res = PrivilegesModel::create($privilegeInfo);
        return $res?true:false;                     //true代表添加成功  false代表添加失败
    }

    /**
     * 查询特权详情
     * @param 特权id
     * @return mixed
     */
    static function privilegesDetail($id){
        $privilegesInfo = PrivilegesModel::where('id',intval($id))->first();
        if(empty($privilegesInfo)){
            return false;
        }
        return $privilegesInfo;
    }

    /**
     * 编辑特权
     * @param 特权id array $data
     * @return bool
     */
    static function updatePrivileges($id,array $data){
        $privilegeInfo = [
            'title' => $data['title'],
            'desc' => $data['desc'],
            'list' => $data['list'],
            'status' => $data['status']?0:1,
            'is_recommend' => $data['is_recommend']?1:0,
            'created_at' => date('Y-m-d H:i:s',time()),
            'updated_at' => date('Y-m-d H:i:s',time())
        ];
        if(isset($data['ico']) && $data['ico']){
            $privilegeInfo['ico'] = $data['ico'];
        }
        $res = PrivilegesModel::where('id',intval($id))->update($privilegeInfo);
        return $res?true:false;                     //true代表修改成功  false代表修改失败
    }
}