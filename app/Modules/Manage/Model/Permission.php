<?php

namespace App\Modules\Manage\Model;
use Zizaco\Entrust\EntrustPermission;

class Permission extends EntrustPermission
{
    //
    protected $table = 'permissions';

    protected $fillable = [
        'name', 'display_name', 'description','module_types','created_at', 'updated_at', 'pid','sort','level','route'
    ];
    public  $timestamps = false;  //关闭自动更新时间戳

    static public function getPermissionMenu()
    {
        //查询所有的菜单
        $menu_all = MenuModel::orderBy('sort','asc')->get()->toArray();
        foreach($menu_all as $k=>$v)
        {
            $menu_all[$k]['fid'] = $v['id'];
        }
        //查询所有的权限
        $permission_all = self::all()->toArray();
        //查询所有的权限菜单关系
        $menu_permission = MenuPermissionModel::all()->toArray();
        $menu_permission = \CommonClass::keyBy($menu_permission,'permission_id');
        //处理权限数据，增加pid指向菜单id
        foreach($permission_all as $k=>$v)
        {
            $permission_all[$k]['pid'] = $menu_permission[$v['id']]['menu_id'];
            $permission_all[$k]['fid'] = 0;
            $permission_all[$k]['name'] = $v['display_name'];
        }
        //树形话权限菜单
        $permission_menu = array_merge($menu_all,$permission_all);
        $permission_menu_tree = \CommonClass::listToTree($permission_menu,'fid','pid');
        return $permission_menu_tree;
    }
}
