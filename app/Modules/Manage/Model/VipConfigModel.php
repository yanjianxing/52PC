<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VipConfigModel extends Model
{
    //
    protected $table = 'vip_config';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','jb_times','jb_price','facs_logo','facs_daohang','facs_muban','facs_slide','facs_mobile','facs_start_xunjia','facs_accept_xunjia','facs_yaoqingjb','facs_hangye_num','facs_technology_num',
        'identity_mobile','identity_label','identity_project','retail_fuwushang','retail_fanganchaoshi','appreciation_zhiding','appreciation_jiaji','appreciation_duijie','appreciation_zhitongche',
        'appreciation_zixun','appreciation_lchengyijin','appreciation_hchengyijin','vip_cika','vip_cika_price','vip_cika_num','vip_rika','vip_rika_price','vip_rika_num','created_at','tool_zk'
    ];

    public $timestamps = false;


}
