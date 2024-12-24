<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\JobChickpt;
use App\Models\JobPtt;

class DeleteOldRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-old-record';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jobChickpt = JobChickpt::all();
        $subCount = JobChickpt::all()->count() - 100; // 維持總數=100
        
        if($subCount > 0) // 防呆
        {
            for($i = 0; $i < $subCount; $i++)
            {
                $jobChickpt[$i]->delete();
            }
        }

        $jobPtt = JobPtt::all();
        $subCount = JobPtt::all()->count() - 100; // 維持總數=100

        if($subCount > 0) // 防呆
        {
            for($i = 0; $i < $subCount; $i++)
            {
                $jobPtt[$i]->delete();
            }
        }

        $this->info('delete task finished');
    }
}
