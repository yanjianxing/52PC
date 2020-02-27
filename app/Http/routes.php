<?php


/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
Route::get('/', 'HomeController@index');

Route::get('/ajaxHomeGoods', 'HomeController@ajaxHomeGoods');//首页方案超市ajax替换内容
Route::get('/ajaxHomeTask', 'HomeController@ajaxHomeTask');//首页快包任务ajax替换内容
Route::get('/ajaxHomeShop', 'HomeController@ajaxHomeShop');//首页推荐服务商ajax替换内容

Route::get('/ajaxSearch', 'HomeController@ajaxSearch');//首页ajax搜索方案
Route::get('/searchGoods', 'HomeController@searchGoods');//搜索方案
Route::get('/searchTask', 'HomeController@searchTask');//搜索任务
Route::get('/searchShop', 'HomeController@searchShop');//搜索服务商
Route::get('/searchArticle', 'HomeController@searchArticle');//搜索资讯

Route::get('/adClick/{id}', 'HomeController@adClick');//广告点击
Route::get('/adClickJs', 'HomeController@adClickJs');//广告Js点击

//搜索seo标签
Route::get('/list/{type}/{id}', 'HomeController@searchSeo');//搜索seo
Route::get('/list', 'HomeController@searchSeoMore');
Route::get('/list/{spelling}', 'HomeController@searchSeoMoreSpelling');

Route::get('/404', 'HomeController@error');