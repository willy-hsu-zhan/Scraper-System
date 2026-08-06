<?php

namespace App\Console\Commands;

use App\Services\LineNotifyService;
use Illuminate\Console\Command;

class SendLineNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-line-notify';

    protected $lineNotifyService;

    public function __construct(LineNotifyService $lineNotifyService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
    }

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
        $message = "\n".'Hello, this is a Line Notify message from Laravel!';
    }
}
