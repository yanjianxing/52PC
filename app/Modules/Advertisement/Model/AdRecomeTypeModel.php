<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/17
 * Time: 17:02
 */
namespace App\Modules\Advertisement\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AdRecomeTypeModel extends Model
{
    protected $table = 'ad_recom_type';
    protected $fillable = ['id','name','status','created_at','updated_at'];


}
