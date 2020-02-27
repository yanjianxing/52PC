<?php

namespace App\Modules\Article\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class ArticleModel extends Model
{
    protected $table = 'article';
    protected $fillable = [
        'id','cat_id','cate_id','title','user_id','user_name','author','summary','content','pr_leader','status','technology_id','online_time','view_times','from','fromurl','url','thumb','tag','pic','display_order','seotitle','keywords','description','created_at','updated_at','is_recommended','attachment_id','articlefrom','reprint_url',"price",'is_free',
    ];

    public $timestamps = false;
}














