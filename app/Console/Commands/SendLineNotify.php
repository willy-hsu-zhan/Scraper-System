<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LineNotify;

class SendLineNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-line-notify';

    protected $lineNotify;

    public function __construct(LineNotify $lineNotify)
    {
        parent::__construct();
        $this->lineNotify = $lineNotify;
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
        $message = "\n" . 'Hello, this is a Line Notify message from Laravel!';
        $response = $this->lineNotify->sendMessage($message);
        //return response()->json(['response' => $response]);
    }
}
