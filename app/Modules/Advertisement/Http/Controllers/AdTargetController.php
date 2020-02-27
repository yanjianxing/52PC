<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/18
 * Time: 13:29
 */
namespace App\Modules\Advertisement\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Advertisement\Model\AdRecomeTypeModel;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\AdModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Theme;

class AdTargetController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->theme->setTitle('广告管理');
        $this->initTheme('manage');
    }

   /*
    * 广告位列表
    * */
    public function index(Request $request)
    {
        $merge = $request->all();
        $typeArr = AdRecomeTypeModel::where('status',1)->select('id','name')->get()->toArray();
        $typeId = $request->get('type_id') ? $request->get('type_id') : ($typeArr[0] ? $typeArr[0]['id'] : 1);
        $merge['type_id'] = $typeId;
        $adTargetList = AdTargetModel::where('type_id',$typeId)->where('is_open',1)->paginate(10);

        foreach($adTargetList->items() as $k=>$v){
            $deliveryNum = AdModel::where('target_id',$v->target_id)
                ->where('is_open','1')
                ->where(function($deliveryNum){
                    $deliveryNum->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d h:i:s',time()));
                })
                ->count();
            if($deliveryNum){
                $v->deliveryNum = $deliveryNum;
            }
            else{
                $v->deliveryNum = 0;
            }
        }
        $view = [
            'adTargetList' => $adTargetList,
            'typeArr'      => $typeArr,
            'merge'        => $merge
        ];
        $this->theme->setTitle('广告位管理');
        return $this->theme->scope('manage.ad.adtargetlist',$view)->render();
    }


}
