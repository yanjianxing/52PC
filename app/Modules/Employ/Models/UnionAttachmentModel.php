<?php

namespace App\Modules\Employ\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnionAttachmentModel extends Model
{
    protected $table = 'union_attachment';
    public $timestamps = false;
    protected $fillable = [
        'object_id',
        'object_type',//对象类型 1 企业认证 2雇佣附件 3雇佣交稿附件 4商品附件 5:方案封面
        'attachment_id',
        'created_at'
    ];

    public function attachment()
    {
        return $this->hasOne('App\Modules\User\Model\AttachmentModel', 'id', 'attachment_id');
    }

}
