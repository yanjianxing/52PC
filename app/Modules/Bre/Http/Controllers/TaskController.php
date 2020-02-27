<?php
/**
 * ------------------------
 * Created by PhpStorm.
 * ------------------------
 *
 * ------------------------
 * Author: frank
 * Date: 16-4-19
 * Desc:
 * ------------------------
 *
 */

namespace App\Modules\Bre\Http\Controllers;

use App\Http\Controllers\IndexController;
use Illuminate\Routing\Controller;
use App\Http\Requests\Request;

class TaskController extends IndexController
{

    /**
     * 创建任务页面
     *
     * @return mixed
     */
    public function create()
    {

        return 'create';
        $this->initTheme('manage');

        return $this->theme->scope('bre.index')->render();

    }

    /**
     * 任务记录创建
     *
     * @return mixed
     */
    public function taskCreate(Request $request)
    {

        $this->initTheme('manage');

        return $this->theme->scope('bre.index')->render();

    }

    /**
     * 任务详情展示页
     *
     * @param Request $request
     * @return bool
     */
    public function taskDetail($task_id){
        $this->initTheme('manage');

        return $this->theme->scope('bre.index')->render();
    }

    /**
     * 托管赏金页面
     *
     * @param $task_id
     * @return mixed
     */
    public function bounty($task_id){
        echo $task_id;
        return $this->theme->scope('bre.bounty')->render();
    }

    /**
     * 托管赏金记录创建
     *
     * @param $task_id
     * @return mixed
     */
    public function bountyCreate(Request $request){
        return $this->theme->scope('bre.bounty')->render();
    }

    /**
     * 管理员任务审核
     *
     * @param Request $request
     * @return mixed
     */
    public function taskVerify(Request $request){
        return true;
    }


}