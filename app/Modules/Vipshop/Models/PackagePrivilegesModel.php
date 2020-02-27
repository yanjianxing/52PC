<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/10/27
 * Time: 13:40
 */
namespace App\Modules\Vipshop\Models;

use Illuminate\Database\Eloquent\Model;

class PackagePrivilegesModel extends Model
{
    //
    protected $table = 'package_privileges';

    protected $primaryKey = 'id';

    protected $fillable = [

        'package_id', 'privileges_id'

    ];
}