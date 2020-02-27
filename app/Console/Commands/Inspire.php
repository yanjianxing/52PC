<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;

class Inspire extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display an inspiring quote';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //发送邮件给372980503@qq.com
//        $user = [
//            'name'=>'sss',
//            'email'=>'863011965@qq.com'
//        ];
//        \MessagesClass::sendCodeEmail($user);
    }
}
