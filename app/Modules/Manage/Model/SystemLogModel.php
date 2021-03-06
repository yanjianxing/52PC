<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/30
 * Time: 13:50
 */
namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SystemLogModel extends Model
{
    protected $table = 'system_log';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'log_type',
        'uid',
        'username',
        'user_type',
        'log_content',
        'created_at',
        'IP'
    ];

    public $timestamps = false;


}
