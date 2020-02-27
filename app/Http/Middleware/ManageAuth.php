<?php

namespace App\Http\Middleware;

use App\Modules\Manage\ManagerModel;
use Closure;
use Illuminate\Support\Facades\Session;

class ManageAuth
{


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Session::get('manager')) {
            return redirect('/manage/login');
        } else {
            /*$manager = ManagerModel::getManager();
            $path = $request->path();
            if (!$manager->can($path)) {
                return redirect('/manage');
            }*/
        }
        return $next($request);
    }
}
