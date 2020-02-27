<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;

use App\Http\Controllers\ManageController;
use App\Modules\Manage\Http\Requests\BaseConfigRequest;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Task\Model\TaskTypeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Theme;

class TaskConfigController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('任务配置');
    }

    /**
     * 任务配置页
     * @return mixed
     */
    public function index()
    {
        $configs = ConfigModel::where('type','task_config')->get()->toArray();
        $configs_data = array();
        foreach($configs as $k => $v) {
            $configs_data[$v['alias']] = $v;
            if (!is_array($v['rule']) && \CommonClass::isJson($v['rule'])) {

                $rule = json_decode($v['rule'], true);
                $configs_data[$v['alias']]['rule'] = $rule;
            }

        }
        $arr = [
            1,2,3,4,5
        ];
        $data = [
            'config' => $configs_data,
            'arr' => $arr
        ];
        return $this->theme->scope('manage.taskconfig', $data)->render();
    }

    /**
     * 任务配置提交
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $data = $request->except('_token');
        $arr = [];
        if(!$data['start'] && !$data['end']){
            return redirect()->back()->with(['error'=>'有效时间必须设置']);
        }
        $start = '';
        if ($data['start']) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $data['start']);
            $start = date('Y-m-d H:i:s',strtotime($start));

        }
        $end = '';
        if ($data['end']) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $data['end']);
            $end = date('Y-m-d H:i:s',strtotime($end) + 24*60*60);
        }

        $arr['task_config_time'] = json_encode([
            'start' => $start,
            'end'   => $end
        ]);
        ConfigModel::where('alias','task_config_time')->update(['rule'=>$arr['task_config_time']]);

        $rule = [
            'times' => $data['times'],
            'hours' => $data['hours'],
            'sort'  => $data['sort'],
        ];
        $arr['task_config_rule'] = json_encode($rule);
        ConfigModel::where('alias','task_config_rule')->update(['rule'=>$arr['task_config_rule']]);
        return redirect()->back()->with(['massage'=>'修改成功！']);
    }


    /**
     *系统辅助功能开启和关闭
     */
    public function ajaxUpdateSys()
    {
        $status = ConfigModel::where('alias','task_config_switch')->first();

        if($status['rule'] == 0){
            $result = ConfigModel::where('alias','task_config_switch')->update(['rule'=>1]);
        }else{
            $result = ConfigModel::where('alias','task_config_switch')->update(['rule'=>0]);
        }
        if(!$result)
            return response()->json(['error'=>'修改失败！']);

        return response()->json(['massage'=>'修改成功！']);
    }


}
