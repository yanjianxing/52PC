<?php

namespace App\Http\Middleware;

use App\Modules\Manage\Model\ManagerModel;
use App\Modules\Manage\Model\PermissionRoleModel;
use App\Modules\Manage\Model\RoleUserModel;
use Closure;
use Illuminate\Support\Facades\Route;


class RolePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $route = Route::currentRouteName();
        //$route = $request->getRequestUri();
        //echo $route;
        $manager = ManagerModel::getManager();
        $user = $manager->username;
        $user = ManagerModel::where('username','=',$user)->first();
       if($manager->id !=  1) {
           if(!$user->can($route))
            return redirect()->back()->with(['message' => '没有权限']);
       }
        return $next($request);
    }
}
