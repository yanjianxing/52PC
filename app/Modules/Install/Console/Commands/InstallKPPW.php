<?php

namespace App\Modules\Install\Console\Commands;

use Illuminate\Console\Command;

class InstallKPPW extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:kppw {--data=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install KPPW';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //是否带演示数据
        $boolData = $this->option('data');

        $this->call('migrate', [
            '--force' => true
        ]);

        $this->call('db:seed');

        if (!empty($boolData)){
            $seedListClass = [
                'Article', 'Task', 'Link', 'Users', 'UserDetail', 'SuccessCase'
            ];
            foreach ($seedListClass as $class){
                $this->call('db:seed', [
                    '--class' => $class . 'TableSeeder'
                ]);
            }
        }

    }
}
