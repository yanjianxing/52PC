<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class attachmentModel extends Model
{
    //
    protected $table = 'attachment';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','name','type','size','url','status','user_id','disk','created_at'
    ];

    public $timestamps = false;


}
