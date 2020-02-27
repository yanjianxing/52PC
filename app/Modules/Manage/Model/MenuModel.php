<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Zizaco\Entrust\Traits\EntrustUserTrait;

class MenuModel extends Model
{
    protected $table = 'menu';

    protected $fillable = [
        'name', 'pid', 'route','level','note', 'created_at', 'updated_at','sort'
    ];

    public  $timestamps = false;  //关闭自动更新时间戳


    static public function getMenuPermission()
    {
        $manager = \App\Modules\Manage\Model\ManagerModel::getManager();
        $uid = $manager->id;
        if($uid==1)
        {
            $menu = self::orderBy('sort',"asc")->get()->toArray();
            $manageMenu = \CommonClass::listToTree($menu);
        }else{
            $role_id = \App\Modules\Manage\Model\RoleUserModel::where('user_id',$uid)->first();
            $permission = \App\Modules\Manage\Model\PermissionRoleModel::where('role_id',$role_id['role_id'])->lists('permission_id')->toArray();
            $menu_ids = \App\Modules\Manage\Model\MenuPermissionModel::whereIn('permission_id',$permission)->lists('menu_id')->toArray();//menu权限id
            $menu_ids = array_unique($menu_ids);
            $manageMenuAll = self::orderBy('sort',"asc")->get()->toArray();//所有菜单
            $third_menu = self::whereIn('id',$menu_ids)->orderBy('sort',"asc")->where('level',3)->lists('id')->toArray();//三级权限所有
            $second_menu = self::whereIn('id',$menu_ids)->orderBy('sort',"asc")->where('level',2)->lists('id')->toArray();//二级权限所有
            $first_menu = self::whereIn('id',$menu_ids)->orderBy('sort',"asc")->where('level',1)->lists('id')->toArray();//一级权限所有

            //过滤三级
            foreach($manageMenuAll as $k=>$v)
            {
                if($v['level']==3 && !in_array($v['id'],$third_menu))
                {
                    $manageMenuAll = array_except($manageMenuAll,[$k]);
                }
            }
            //过滤二级
            $manageMenuAllTree = \CommonClass::listToTree($manageMenuAll);
            foreach($manageMenuAllTree as $key=>$value)
            {
                if(!empty($value['_child'])) {
                    foreach ($value['_child'] as $menukey => $menu) {
                        if (empty($menu['_child']) && !in_array($menu['id'], $second_menu)) {
                            $manageMenuAllTree[$key]['_child'] = array_except($manageMenuAllTree[$key]['_child'], [$menukey]);
                        }
                    }
                }elseif(empty($value['_child']) && !in_array($value['id'],$first_menu))
                {
                    $manageMenuAllTree = array_except($manageMenuAllTree,[$key]);
                }
            }
            //过滤一级
            foreach($manageMenuAllTree as $m=>$n)
            {
                if(empty($n['_child']) && !in_array($n['id'],$first_menu))
                {
                    $manageMenuAllTree = array_except($manageMenuAllTree,[$m]);
                }
            }
            $manageMenu = $manageMenuAllTree;
            foreach($manageMenu as $mk=>$mv){
                if(isset($mv['_child'])){
                    foreach ($mv['_child'] as $mvk=>$mvv){
                        if(isset($mvv['_child'])){
                            foreach ($mvv['_child'] as $mkk1=>$mvv1){
                                $manageMenu[$mk]['route']= $mvv1['route'];
                                continue;
                            }
                        }else{
                            $manageMenu[$mk]['route']=$mvv['route'];
                        }
                    }
                }
            }
            //处理权限链接问题
        }

        return $manageMenu;
    }
    static public function getManageMenu ()
    {
        $menu = \App\Modules\Manage\Model\MenuModel::orderBy('sort')->get()->toArray();
        $tree_menu = \CommonClass::listToTree($menu);
        return $tree_menu;
    }

    //查询菜单
    static public function getMenu($id)
    {
        $menu = self::where('id',$id)->first();
        if($menu['level']==2)
        {
            $menu_secound = self::where('id',$menu['pid'])->first()->toArray();
            $menu_data = [$menu,$menu_secound];
            $menu_ids = [$menu['id'],$menu_secound['id']];
        }elseif($menu['level']==3)
        {
            $menu_secound = self::where('id',$menu['pid'])->first()->toArray();
            $menu_third = self::where('id',$menu_secound['pid'])->first()->toArray();
            $menu_data = [$menu,$menu_secound,$menu_third];
            $menu_ids = [$menu['id'],$menu_secound['id'],$menu_third['id']];
        }else
        {
            $menu_data = \CommonClass::listToTree($menu);
            $menu_ids = [$menu['id']];
        }
        $menu_data = \CommonClass::listToTree($menu_data);
        $data = [
            'menu_data'=>$menu_data,
            'menu_ids'=>$menu_ids
        ];
        return $data;
    }
}
