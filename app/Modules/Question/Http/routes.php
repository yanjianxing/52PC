<?php

/*
|--------------------------------------------------------------------------
| Module Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for the module.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::group(['prefix' => 'question'], function() {
    Route::get('index/{status?}','IndexController@index')->name('index');//首页
    Route::get('child/{id}','IndexController@getChild');//获取问题子类类型
    Route::get('answerlist/{id}','IndexController@answerlist')->name('answerlist');//回答列表
    Route::get('wkanswerlist/{id}','IndexController@wkanswerlist')->name('wkanswerlist');//威客回答列表
    Route::get('/check/{id}','IndexController@check');//根据用户是否是提问人进行不同的操作
    Route::get('reply/{questionid}','IndexController@reply')->name('reply');//回答
});
Route::group(['prefix' => 'question','middleware' => 'auth'], function() {
    Route::get('quiz','IndexController@quiz')->name('quiz');//提问
    Route::get('myquestionlist','MyQuestionController@myquestionlist');//用户问题中心
    Route::post('add','IndexController@add');//提交问题
    Route::get('add/{questionid}','IndexController@addquestion');//提交问题后刷新
    Route::post('answeradd','IndexController@answeradd');//提交答案
    Route::get('wkanswerlist/addpraise/{num}/{uid}/{answerid}/{questionid}','IndexController@addpraise');//威客点赞
    Route::get('answerlist/addpraise/{num}/{uid}/{answerid}/{questionid}','IndexController@addpraise');//点赞
    Route::get('answerlist/adopt/{adoptid}/{questionid}','IndexController@adopt');//答案采纳
    Route::get('reward/{adoptid}/{questionid}','IndexController@reward');//打赏页面
    //Route::get('rewar/{answerid}/{questionid}/{qid}/{aid}/{password}/{money}','IndexController@money');//打赏
    Route::post('reward/add','IndexController@money');//打赏
    Route::get('myquestion','IndexController@myquestion');//用户问题中心
});