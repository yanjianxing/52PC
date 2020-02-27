<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Schema;


class SeachStatisticTable extends Command
{
    protected $signature = 'create_seach_statistic';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'create seach_statistic by date';


    public function __construct()
    {
        parent::__construct();

        $this->nowYear = date("Y");
        $this->tables = [
            'seach_keyword_'.$this->nowYear.'1',
            'seach_keyword_'.$this->nowYear.'2',
            'seach_keyword_'.$this->nowYear.'3',
            'seach_keyword_'.$this->nowYear.'4',
            'seach_keyword_'.$this->nowYear.'5',
            'seach_keyword_'.$this->nowYear.'6',
            'seach_keyword_'.$this->nowYear.'7',
            'seach_keyword_'.$this->nowYear.'8',
            'seach_keyword_'.$this->nowYear.'9',
            'seach_keyword_'.$this->nowYear.'10',
            'seach_keyword_'.$this->nowYear.'11',
            'seach_keyword_'.$this->nowYear.'12',
        ];

    }

    public function handle()
    {
        if($this->tables){
            foreach($this->tables as $v){
                if ( ! Schema::hasTable($v)) {
                    Schema::create($v, function (Blueprint $table) {
                        $table->engine = 'InnoDB';
                        $table->increments('id')->comment('编号');
                        $table->string('keyword')->index()->comment('关键字');
                        $table->integer('times')->index()->comment('关键字次数');
                        $table->string('ip')->index()->comment('ip地址');
                        $table->integer('type')->index()->comment('搜索入口 1:网站头部 2 首页头部 3 快包任务内页 4 方案超市内页 5 方案讯 6 服务商首页 7 成功案例');
                        $table->timestamp('created_at')->nullable()->comment('创建时间');
                        $table->timestamp('updated_at')->nullable()->comment('更新时间');
                    });
                    $this->info($v.'创建成功');
                }else{
                    $this->info($v.'已存在');
                }
            }
        }
    }

}
