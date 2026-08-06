<?php

namespace App\Console\Commands;

use App\Services\LineNotifyService;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class Scraper104 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-104';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $lineNotifyService;

    public function __construct(LineNotifyService $lineNotifyService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Artisan::call('dusk', [
            '--filter' => 'SearchJob104Test',
        ]);

        // $searchParams = [
        //     'indcat'     => '1001000000', // 條件:電子資訊／軟體／半導體相關業
        //     //'indcat'     => '1001000000', // 條件:軟體及網路相關業
        //     'keyword'    => '外包接案', // 關鍵字
        //     'remoteWork' => 1, // 條件:遠端工作
        //     // isnew => 0 // 條件:本日最新
        // ];

        // $baseUrl = 'https://www.104.com.tw/jobs/search/?';

        // $url = $baseUrl . http_build_query($searchParams);

        // //dd($url); // https://www.104.com.tw/jobs/search/?

        // $client = new Client();

        // $crawler = $client->request('GET', $url);

        // $filename = public_path('/newfile.html');

        // $htmlContent = $crawler->html();

        // $classParam = '.b-block--top-bord.job-list-item.b-clearfix.js-job-item';

        // $jobList = $crawler->filter($classParam);

        // //dd($jobList);

        // //b-block--top-bord job-list-item b-clearfix js-job-item

        // $htmlContent = $jobList->html();

        // File::put($filename, $htmlContent);

        // dd($jobList->html());
    }
}
