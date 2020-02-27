<?php

namespace App\Modules\Manage\Model;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Finance\Model\FinancialModel;


class ArticlePayModel extends Model
{
    protected $table = 'article_pay';
    protected $fillable = ['id','order_num','article_id','uid','price','status','created_at','payment_at'];
    public  $timestamps = false;  //关闭自动更新时间戳   
}














