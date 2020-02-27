<?php
/**
 * Created by PhpStorm.
 * User: quanke
 * Date: 2016/10/28
 * Time: 15:30
 */
namespace App\Http\Middleware;

use App\Modules\Manage\Model\SubstationModel;
use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Route;
class Substation
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
        $path = $request->path();
        $pos = strrpos($path,'/');
        $id = intval(substr($path,$pos+1));
        //查询已设置为热门的站点
        $hotSub = SubstationModel::where('status',1)->get()->toArray();
        if(!empty($hotSub) && is_array($hotSub)){
            foreach ($hotSub as $item) {
                $subIds[] = $item['district_id'];
            }

        }
        if(empty($subIds) || !in_array($id,$subIds)){
            abort('404');
        }

        return $next($request);

    }
}
