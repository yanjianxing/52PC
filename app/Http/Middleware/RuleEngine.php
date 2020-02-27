<?php
/**
 * ------------------------
 * Created by PhpStorm.
 * ------------------------
 *
 * ------------------------
 * Author: frank
 * Date: 16-4-12
 * Desc:
 * ------------------------
 *
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use App\Modules\Bre\Model\BreRuleModel;
use App\Modules\Bre\Model\BreDecisionModel;
use App\Modules\Bre\Model\BreActionModel;
use App\Modules\Task\Model\TaskModel;

class RuleEngine
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

        /**
         * TODO
         * 规则引擎实现思路:
         * 1.从请求参数获取rule_id
         * 2.根据rule_id获取规则信息,以及规则对应的决策表(decision)信息
         * 3.根据当前请求信息
         *
         *
         */

        // 信息提示
        $msg = '';

        // 获取参数
        $params = json_decode($request->get('params'), true);

        // 验证参数
        if (isset($params['rule_id']) && intval($params['rule_id']) > 0) {

            //var task = currenttask
            //get rule_id = 1
            //get task_beforeStatus (=0) -> 1
            //get operation
            //decision = bre.eval(rule_id, task_status, operation)
            //task.status = d.after_status = 1 -> 2
            //update task to db
            //redirect to d.action -> /bre/task/create?task={task_id}

            /*
             * rule_id, beforeStatus, operation
             * (1, 0, init)         => url = create, afterStatus = 1
             * (1, 1, createTask)   => url = details, afterStatus = 2
             *
             * case 1:
             * rule_id = 1, status = 0, operation = init
             * rule_id = 1, status = 1, operation = create.createTask
             * rule_id = 1, status = 2, operation = details.modify
             *
             */

            $rule_id = intval($params['rule_id']); // 规则ID
            $bre_rule = BreRuleModel::find($rule_id); // 获取规则信息

            // 验证规则是否存在且状态是否可用
            if (isset($bre_rule->status) && $bre_rule->status == 1) {

                // 根据规则ID获取对应规则决策表信息
                $bre_decision = $this->getDecisionActionInfo($rule_id);

                /* TODO 任务引擎 */

                //验证任务ID参数
                if (isset($params['task_id'])) {
                    // 任务当前状态
                    $task_status = 0;

                    /* 根据任务ID查询任务当前状态 */
                    $task_id = intval($params['task_id']);
                    if ($task_id > 0) {
                        $task = TaskModel::find($task_id);
                        if (isset($task->status)) {
                            // 根据任务状态判断
                            $msg = 'Task status is :' . $task->status;
                        } else {
                            $msg = 'Task is not exist!';
                        }
                    } else {
                        // 如果任务ID不存在,则新建任务
                        if (isset($bre_decision[0])) {

                            $class = $bre_decision[0]['class'];
                            $method = $bre_decision[0]['function'];
                            $param = empty($bre_decision[0]['params'])?'':$bre_decision[0]['params'];
                            $data = $this->operate($class, $method,[$param]);
                            dd($data);
                        } else {
                            $msg = 'Lack of decision';
                        }
                    }
                }

            } else {
                $msg = 'Rule is not exist or disabled';
            }

        } else {
            $msg = 'Lack of BRE id';
        }

        return $next($request);

    }

    /**
     * 获取决策行为信息
     *
     * @param $rule_id
     * @return array
     */
    private function getDecisionActionInfo($rule_id)
    {
        $data = BreDecisionModel::select('before_status', 'after_status', 'sort', 'action.*')
            ->leftJoin('bre_action as action', 'bre_decision.action_id', '=', 'action.id')
            ->where('rule_id', $rule_id)
            ->get()->toArray();
        return $data;
    }

    /**
     * action 调用
     *
     * @param $class
     * @param $method
     * @param array $params
     * @return mixed|string
     */
    private function operate($class, $method, $params = array())
    {
        $stat = false;
        if (empty($class) || empty($method)) return $stat;

        if (class_exists($class)) {
            $obj = new $class();
            if (method_exists($obj, $method)) {
                $stat = call_user_func_array(array($obj, $method), $params);
            }
        }

        return $stat;

    }
}
