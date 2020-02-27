<?php

namespace App\Modules\User\Http\Controllers;

use App\Http\Requests;
use App\Modules\Question\Models\AnswerModel;
use App\Modules\Question\Models\QuestionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends UserCenterController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('apple');//主题初始化
    }

    /**
     * 我的回答
     * @param Request $request
     * @return mixed
     */
    public function myAnswer(Request $request)
    {
        $this->theme->setTitle('我的回答');
        $uid = Auth::user()['id'];
        $data = $request->all();
        //查询我的回答
        $myanwser = AnswerModel::myAnswer($uid,$data);

        $myanwser_toArray = $myanwser->toArray();
        $domain = url();

        $view  = [
            'myanwser'=>$myanwser,
            'myanwser_toArray'=>$myanwser_toArray,
            'domain'=>$domain
        ];
        return $this->theme->scope('user.quetion.myAnswer',$view)->render();
    }

    /**
     * 我的提问
     * @param Request $request
     * @return mixed
     */
    public function myQuestion(Request $request)
    {
        $this->theme->setTitle('我的提问');
        $uid = Auth::user()['id'];
        $data = $request->all();

        //查询我的回答
        $myquestion = QuestionModel::myQuestion($uid,$data);
        $myquestion_toArray = $myquestion->toArray();
        $domian=url();
        $view = [
            'myquestion'=>$myquestion,
            'myquestion_toArray'=>$myquestion_toArray,
            'domain'=>$domian
        ];
        return $this->theme->scope('user.quetion.myquestion',$view)->render();
    }


    /**
     * 推广代码
     * @param Request $request
     * @return mixed
     */
    public function extendcode(Request $request)
    {
        return $this->theme->scope('user.extendcode')->render();
    }

    /**
     * 推广收益
     * @param Request $request
     * @return mixed
     */
    public function extendprofit(Request $request)
    {
        return $this->theme->scope('user.extendprofit')->render();
    }
}
