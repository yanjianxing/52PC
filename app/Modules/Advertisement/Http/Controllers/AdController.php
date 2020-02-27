<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/18
 * Time: 13:29
 */
namespace App\Modules\Advertisement\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Advertisement\Model\AdModel;
use App\Modules\Advertisement\Model\AdStatisticModel;
use App\Modules\Advertisement\Model\AdTargetModel;
use Illuminate\Http\Request;
use Theme;
use Validator;
use Excel;

class AdController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('广告管理');
    }

    /**
     * 查询广告列表
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function adlist(Request $request){

        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $adList = AdModel::leftJoin('ad_target','ad_target.target_id','=','ad.target_id')
            ->where('ad.is_open','<>','3')
            ->select('ad.*','ad_target.name')
            ->orderBy($by, $order);
        if ($request->get('ad_name')) {
            $adList = $adList->where('ad.ad_name','like','%'.$request->get('ad_name').'%');
        }

        if($request->get('target_id') != 0){
            $adList = $adList->where('ad.target_id','=',$request->get('target_id'));
        }

        if($request->get('is_open') != 0){
            $adList = $adList->where('ad.is_open','=',$request->get('is_open'));
        }
        $adList = $adList->paginate($paginate);

        $adTargetInfo = AdTargetModel::select('target_id','name')->get();

        $view = [
            'adList'       => $adList,
            'search'       => $request->all(),
            'adTargetInfo' => $adTargetInfo
        ];

        return $this->theme->scope('manage.ad.adlist', $view)->render();
    }

    /*
    * 加载创建广告页面
    * */
    public function getInsertAd(Request $request)
    {
        $adTargetInfo = AdTargetModel::select('target_id','name','code')->get();
        $view = [
            'target_id'    => $request->get('target_id') ? $request->get('target_id') : 0,
            'adTargetInfo' => $adTargetInfo
        ];
        return $this->theme->scope('manage.ad.adadd',$view)->render();
    }

    /**
     * 创建广告信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function storeAdInfo(Request $request){
        $data = $request->except('_token');
        $validator = Validator::make($request->all(), [
            'ad_name'   => 'required',
            'target_id' => 'required',
            //'ad_file'   => 'required',
            //'ad_url'    => 'required|url'
        ],[
            'ad_name.required'   => '请输入广告名称',
            'target_id.required' => '请选择广告位置',
            //'ad_file.required'   => '请上传图片',
            //'ad_url.required'    => '请输入链接',
            //'ad_url.url'         => '请输入有效的url'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if(count($error)){
            return redirect()->back()->with(['error'=>$validator->errors()->first()]);
        }

        if(is_array($data['target_id'])){
            if($data['target_id']){
                foreach($data['target_id'] as $k => $v) {
                    $ad_num = AdTargetModel::where('target_id',intval($v))->pluck('ad_num');
                    $num = AdModel::where('target_id',intval($v))
                        ->where(function($num){
                            $num->where('end_time','0000-00-00 00:00:00')
                                ->orWhere('end_time','>',date('Y-m-d h:i:s',time()));
                        })
                        ->where('is_open',1)
                        ->count();

                    if(isset($ad_num) && $ad_num <= $num){
                        $errorData['message'] = '该广告位已满';
                        return redirect()->back()->with(['error'=>'该广告位已满！']);
                    }
                }
            }
        }else{
            $ad_num = AdTargetModel::where('target_id',intval($data['target_id']))->pluck('ad_num');
            $num = AdModel::where('target_id',intval($data['target_id']))
                ->where(function($num){
                    $num->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d h:i:s',time()));
                })
                ->where('is_open',1)
                ->count();

            if(isset($ad_num) && $ad_num <= $num){
                $errorData['message'] = '该广告位已满';
                return redirect()->back()->with(['error'=>'该广告位已满！']);
            }
        }


        $data['start_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['start_time']);
        $data['end_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['end_time']);
        $newData = [
            'ad_type'         => $data['ad_type'],
            'ad_name'         => $data['ad_name'],
            'start_time'      => date('Y-m-d h:i:s',strtotime($data['start_time'])),
            'end_time'        => date('Y-m-d h:i:s',strtotime($data['end_time'])),
            'listorder'       => $data['listorder'],
            'is_open'         => $data['is_open'],
            'created_at'      => date('Y-m-d h:i:s',time()),
            'updated_at'      => date('Y-m-d h:i:s',time()),
            'contract_custom' => $data['contract_custom'],
            'contract_name'   => $data['contract_name'],
            'contract_mobile' => $data['contract_mobile'],

        ];

        if($data['ad_type'] == 'image'){
            $file = $request->file('ad_file');
            if($file){
                //上传文件
                $result = \FileClass::uploadFile($file,'sys');
                $result = json_decode($result,true);
                if($result['code'] != 200){
                    return redirect()->back()->with(['error'=>'图片上传失败！']);
                }
                $newData['ad_file'] =  $result['data']['url'];
            }
            $newData['ad_url'] = $data['ad_url'];
        }elseif($data['ad_type'] == 'js'){
            $newData['ad_js'] = $data['ad_js'];
        }

        if(is_array($data['target_id'])){
            foreach($data['target_id'] as $k => $v){
                $newDataArr = $newData;
                $newDataArr['target_id'] = $v;
                $res = AdModel::create($newDataArr);
                if($data['ad_type'] == 'js'){
                    $html = '<!DOCTYPE html>'.
                        '<html>'.

                        '<head>'.
                        '<meta charset="UTF-8">'.
                        '<title></title>'.
                        '<script src="../../themes/default/assets/plugins/jquery/jquery.min.js"></script>'.
                        '</head>'.
                        '<body>'.

                        '<div onclick="adClick('.$res['id'].')">'. $data['ad_js'].'</div>'.
                        '</body>'.
                        '<script>'.
                        'function adClick(ad_id){
                                    $.ajax({
                                        type: "get",
                                        url: "/adClickJs",
                                        data: {ad_id:ad_id},
                                        dataType:"json",
                                        success: function(data){

                                        }
                                    });
                                }'.
                        '</script>'.
                        '</html>';
                    $file1 = 'attachment/html/';
                    $file = public_path().'/'.$file1;
                    $name = 'ad'.$res['id'].'.html';
                    if(!is_dir($file)){
                        mkdir($file,0777, true);
                    }
                    file_put_contents($file.$name, $html, true);
                }
            }
            return redirect('/advertisement/adList')->with(['message'=>'广告创建成功！']);
        }else{
            $newData['target_id'] = $data['target_id'];
            $res = AdModel::create($newData);
            if($data['ad_type'] == 'js'){
                $html = '<!DOCTYPE html>'.
                    '<html>'.

                    '<head>'.
                    '<meta charset="UTF-8">'.
                    '<title></title>'.
                    '<script src="../../themes/default/assets/plugins/jquery/jquery.min.js"></script>'.
                    '</head>'.
                    '<body>'.

                    '<div onclick="adClick('.$res['id'].')">'. $data['ad_js'].'</div>'.
                    '</body>'.
                    '<script>'.
                    'function adClick(ad_id){
                                    $.ajax({
                                        type: "get",
                                        url: "/adClickJs",
                                        data: {ad_id:ad_id},
                                        dataType:"json",
                                        success: function(data){

                                        }
                                    });
                                }'.
                    '</script>'.
                    '</html>';
                $file1 = 'attachment/html/';
                $file = public_path().'/'.$file1;
                $name = 'ad'.$res['id'].'.html';
                if(!is_dir($file)){
                    mkdir($file,0777, true);
                }
                file_put_contents($file.$name, $html, true);
            }
            if($res){
                return redirect('/advertisement/adList?target_id='.$data['target_id'])->with(['message'=>'广告创建成功！']);
            }
        }

        return redirect()->back()->with(['message'=>'广告创建失败！']);
    }

    /**
     * 加载修改广告页面
     * @param $id
     * @return mixed
     */
    public function getUpdateAd($id)
    {
        $adTargetInfo = AdTargetModel::select('target_id','name','code')->get();
        $adInfo = AdModel::where('id',$id)->select('*')->first();
        $view = [
            'adTargetInfo' => $adTargetInfo,
            'adInfo'       => $adInfo,
            'ad_id'        => $id
        ];
        return $this->theme->scope('manage.ad.adedit',$view)->render();
    }

    /**
     * 修改广告信息
     *
     * @param Request $request,$ad_id
     * @return \Illuminate\Http\Response
     */
    public function updateAdInfo(Request $request,$ad_id){
        if(!$ad_id){
            return redirect()->back()->with(['error'=>'传送参数不能为空！']);
        }
        $adInfo = AdModel::find(intval($ad_id));
        if(!$adInfo){
            return redirect()->back()->with(['error'=>'传送参数错误！']);
        }

        $data = $request->except('_token');
        $validator = Validator::make($request->all(), [
            'ad_name'   => 'required',
            'target_id' => 'required',
            //'ad_url'    => 'required|url'
        ],[
            'ad_name.required'   => '请输入广告名称',
            'target_id.required' => '请选择广告位置',
            //'ad_url.required'    => '请输入链接',
            //'ad_url.url'         => '请输入有效的url'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if(count($error)){
            return redirect()->back()->with(['error'=>$validator->errors()->first()]);
        }
        $data['start_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['start_time']);
        $data['end_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['end_time']);
        $newData = [
            'ad_type'         => $data['ad_type'],
            'target_id'       => $data['target_id'],
            'ad_name'         => $data['ad_name'],
            ''          => $data['ad_url'],
            'start_time'      => date('Y-m-d h:i:s',strtotime($data['start_time'])),
            'end_time'        => date('Y-m-d h:i:s',strtotime($data['end_time'])),
            'listorder'       => $data['listorder'],
            'is_open'         => $data['is_open'],
            'contract_custom' => $data['contract_custom'],
            'contract_name'   => $data['contract_name'],
            'contract_mobile' => $data['contract_mobile'],
            'updated_at'      => date('Y-m-d h:i:s',time()),

        ];
        if($data['ad_type'] == 'image'){
            $file = $request->file('ad_file');
            if(!empty($file)){
                //上传图片
                $result = \FileClass::uploadFile($file,'sys');
                $result = json_decode($result,true);
                if($result['code'] != 200){
                    return redirect()->back()->with(['error'=>'图片上传失败！']);
                }
                $newData['ad_file'] = $result['data']['url'];
            }
            $newData['ad_url'] = $data['ad_url'];
        }else{
            $newData['ad_js'] = $data['ad_js'];
        }

        $res = $adInfo->update($newData);
        if($res){
            if($data['ad_type'] == 'js'){
                $html = '<!DOCTYPE html>'.
                    '<html>'.

                    '<head>'.
                    '<meta charset="UTF-8">'.
                    '<title></title>'.
                    '<script src="../../themes/default/assets/plugins/jquery/jquery.min.js"></script>'.
                    '</head>'.
                    '<body>'.

                    '<div onclick="adClick('.$ad_id.')">'. $data['ad_js'].'</div>'.
                    '</body>'.
                    '<script>'.
                        'function adClick(ad_id){
                            $.ajax({
                                type: "get",
                                url: "/adClickJs",
                                data: {ad_id:ad_id},
                                dataType:"json",
                                success: function(data){

                                }
                            });
                        }'.
                    '</script>'.
                    '</html>';
                $file1 = 'attachment/html/';
                $file = public_path().'/'.$file1;
                $name = 'ad'.$ad_id.'.html';
                if(!is_dir($file)){
                    mkdir($file,0777, true);
                }
                file_put_contents($file.$name, $html, true);
            }
            return redirect('/advertisement/adList?target_id='.$data['target_id'])->with(['message'=>'修改成功！']);
        }
        else{
            return redirect()->back()->with(['message'=>'修改失败！']);
        }
    }

    /**
     * 删除广告信息
     *
     * @param $ad_id
     * @return \Illuminate\Http\Response
     */
    public function deleteAdInfo($ad_id)
    {
        $adInfo = AdModel::find($ad_id);
        if(empty($adInfo)){
            return redirect()->back()->with(['error'=>'传送参数错误！']);
        }
        $res = $adInfo->update(['is_open' => '3']);
        if($res){
            return redirect()->back()->with(['message'=>'删除成功！']);
        }
        else{
            return redirect()->back()->with(['message'=>'删除失败！']);
        }
    }


    /**
     * 广告统计
     * @param Request $request
     * @return mixed
     */
    public function allStatistic(Request $request)
    {
        try{
            $merge = [];
            $readMerge = [
                ['type','=',1]
            ];
            $clickMerge = [
                ['type','=',2]
            ];
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = strtotime($start);
            }else{
                $start = strtotime('2019-01-01');
            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = strtotime($end);
            }else{
                $end = time();
            }
            if($request->get('target_id') && $request->get('target_id') > 0){
                $merge = array_merge([['target_id','=',$request->get('target_id'),'and']],$merge);
                $readMerge = array_merge([['target_id','=',$request->get('target_id'),'and']],$readMerge);
                $clickMerge = array_merge([['target_id','=',$request->get('target_id'),'and']],$clickMerge);
            }
            if($request->get('ad_name')){
                $adIdarr = AdModel::where('ad_name','like','%'.$request->get('ad_name').'%')->lists('id')->toArray();
                $adIdStr = '('.implode(',',$adIdarr).')';
                $merge = array_merge([['ad_id','in',$adIdStr,'and']],$merge);
                $readMerge = array_merge([['ad_id','in',$adIdStr,'and']],$readMerge);
                $clickMerge = array_merge([['ad_id','in',$adIdStr,'and']],$clickMerge);
            }
            if($request->get('type')){
                $merge = array_merge([['type','=',$request->get('type'),'and']],$merge);
            }
            $adStatisticModel = new AdStatisticModel();

            $list = $adStatisticModel->setUnionAllTable($start, $end, ['ad_id','target_id','created_at','ip','type'], $merge)
                ->select('*')
                ->orderBy('created_at', 'desc')->paginate(15);
            $list->load(['target','ad']);
            $readTimes = $adStatisticModel->setUnionAllTable($start, $end,['ad_id','target_id','created_at','ip','type'], $readMerge)->count();
            $clickTimes = $adStatisticModel->setUnionAllTable($start, $end, ['ad_id','target_id','created_at','ip','type'], $clickMerge)->count();
        }catch (\Exception $e) {
            /*$error =  $e->getMessage();
            dd($error);*/
            $list = [];
            $readTimes = 0;
            $clickTimes = 0;
        }

        $adTarget = AdTargetModel::where('is_open',1)->get()->toArray();
        $view = [
            'adTarget'   => $adTarget,
            'list'       => $list,
            'readTimes'  => $readTimes,
            'clickTimes' => $clickTimes,
            'merge'      => $request->all(),
        ];

        return $this->theme->scope('manage.ad.statisticlist', $view)->render();
    }

    /**
     * 广告统计(某个广告位置)
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function statistic($id,Request $request)
    {

        try{
            $merge = [
                ['ad_id','=',$id,'and']
            ];
            $readMerge = [
                ['ad_id','=',$id,'and'],
                ['type','=',1]
            ];
            $clickMerge = [
                ['ad_id','=',$id,'and'],
                ['type','=',2]
            ];
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = strtotime($start);
            }else{
                $start = strtotime('2019-01-01');
            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = strtotime($end);
            }else{
                $end = time();
            }
            if($request->get('target_id')){
                $merge = array_merge([['target_id','=',$request->get('target_id'),'and']],$merge);
            }
            if($request->get('type')){
                $merge = array_merge([['type','=',$request->get('type'),'and']],$merge);
            }
            $adStatisticModel = new AdStatisticModel();

            $list = $adStatisticModel->setUnionAllTable($start, $end, ['ad_id','target_id','created_at','ip','type'], $merge)
                ->select('*')
                ->orderBy('created_at', 'desc')->paginate(15);
            $list->load(['target','ad']);
            $readTimes = $adStatisticModel->setUnionAllTable($start, $end,['ad_id','target_id','created_at','ip','type'],$readMerge)->count();
            $clickTimes = $adStatisticModel->setUnionAllTable($start, $end, ['ad_id','target_id','created_at','ip','type'], $clickMerge)->count();
        }catch (\Exception $e) {
            /*$error =  $e->getMessage();
            dd($error);*/
            $list = [];
            $readTimes = 0;
            $clickTimes = 0;
        }

        $view = [
            'id'         => $id,
            'list'       => $list,
            'readTimes'  => $readTimes,
            'clickTimes' => $clickTimes,
            'merge'      => $request->all(),
        ];

        return $this->theme->scope('manage.ad.statistic', $view)->render();

    }


    /**
     * 广告统计导出
     * @param Request $request
     */
    public function adStatisticExport(Request $request)
    {
        $info = AdModel::find($request->get('id'));
        $adTarget = AdTargetModel::where('target_id',$info->target_id)->first();
        $merge = [
            ['ad_id','=',$request->get('id'),'and']
        ];
        $merge = array_merge([['target_id','=',$info->target_id,'and']],$merge);
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = strtotime($start);
        }else{
            $start = strtotime('2019-01-01');
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = strtotime($end);
        }else{
            $end = time();
        }

        if($request->get('type')){
            $merge = array_merge([['type','=',$request->get('type'),'and']],$merge);
        }
        $adStatisticModel = new AdStatisticModel();

        $list = $adStatisticModel->setUnionAllTable($start, $end, ['ad_id','target_id','created_at','ip','type'], $merge)
            ->select('*')
            ->orderBy('created_at', 'desc')->get()->chunk(100);
        $i = 0;
        $data = [
            [
                '广告编号',
                '广告名称',
                '广告位置',
                'IP',
                '类型',
                '时间',
            ]
        ];
        foreach ($list as $key => $chunk) {
            foreach ($chunk as $k => $v) {
                $data[$i + 1] = [
                    $v->ad_id,
                    $info->ad_name,
                    $adTarget->name,
                    $v->ip,
                    $v->type == 1 ? '曝光' : '点击',
                    isset($v->created_at) ? $v->created_at : ''
                ];
                $i++;
            }
        }
        Excel::create(iconv('UTF-8', 'GBK', '广告统计'), function ($excel) use ($data) {
            $excel->sheet('score', function ($sheet) use ($data) {
                $sheet->rows($data);

            });
        })->export('xlsx');

    }


    /**
     * 广告统计列表导出
     * @param Request $request
     */
    public function adStatisticListExport(Request $request)
    {
        try{
            $merge = [];
            if($request->get('start')){
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = strtotime($start);
            }else{
                $start = strtotime('2019-01-01');
            }
            if($request->get('end')){
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = strtotime($end);
            }else{
                $end = time();
            }
            if($request->get('target_id') && $request->get('target_id') > 0){
                $merge = array_merge([['target_id','=',$request->get('target_id'),'and']],$merge);
            }
            if($request->get('ad_name')){
                $adIdarr = AdModel::where('ad_name','like','%'.$request->get('ad_name').'%')->lists('id')->toArray();
                $adIdStr = '('.implode(',',$adIdarr).')';
                $merge = array_merge([['ad_id','in',$adIdStr,'and']],$merge);

            }
            if($request->get('type')){
                $merge = array_merge([['type','=',$request->get('type'),'and']],$merge);
            }
            $adStatisticModel = new AdStatisticModel();

            $list = $adStatisticModel->setUnionAllTable($start, $end, ['ad_id','target_id','created_at','ip','type'], $merge)
                ->select('*')
                ->orderBy('created_at', 'desc')->with(['target','ad'])->get()->chunk(100);
            $i = 0;
            $data = [
                [
                    '广告编号',
                    '广告名称',
                    '广告位置',
                    'IP',
                    '类型',
                    '时间',
                ]
            ];
            foreach ($list as $key => $chunk) {
                foreach ($chunk as $k => $v) {
                    $data[$i + 1] = [
                        isset($v->ad['id']) ? $v->ad['id'] : '',
                        isset($v->ad['ad_name']) ? $v->ad['ad_name'] : '' ,
                        isset($v->target['name']) ? $v->target['name'] : '',
                        $v->ip,
                        $v->type == 1 ? '曝光' : '点击',
                        isset($v->created_at) ? $v->created_at : ''
                    ];
                    $i++;
                }
            }
            Excel::create(iconv('UTF-8', 'GBK', '广告统计'), function ($excel) use ($data) {
                $excel->sheet('score', function ($sheet) use ($data) {
                    $sheet->rows($data);

                });
            })->export('xlsx');

        }catch (\Exception $e) {
            /*$error =  $e->getMessage();
            dd($error);*/
            return redirect()->back()->with('error','导出错误');
        }

    }
}
