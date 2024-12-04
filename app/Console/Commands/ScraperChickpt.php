<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\LineNotify;
use App\Models\JobChickpt;
use Illuminate\Support\Carbon;

class ScraperChickpt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-chickpt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $jobs = [];

    protected $lineNotify;

    public function __construct(LineNotify $lineNotify)
    {
        parent::__construct();
        $this->lineNotify = $lineNotify;
    }

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $currentPage = 1; // 小雞上工任務頁預設為第一頁

        $client = new Client();

        $url = 'https://www.chickpt.com.tw/cases?is_address_no_limit=1';

        $crawler = $client->request('GET', $url); // 默認第一頁

        while (true)
         {
            try
            {
                $link = $crawler->selectLink($currentPage)->link(); // 網頁剛好有頁碼可以這樣子抓取
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try {
                $jobList = $crawler->filter('.jobs-wrap ul#job-list.job-list.show li'); // 抓取job-list獨有的css設定
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            // Loop through each job
            $jobList->each(function ($jobNode) use (&$jobs) { // 傳值不能傳參考
                $title   = $jobNode->filter('h2')->text();
                $link    = $jobNode->filter('a')->attr('href');
                $company = $jobNode->filter('.ellipsis-job-company')->text();
                $salary  = $jobNode->filter('.salary')->text();
                $place   = $jobNode->filter('.place')->text();
                $update_time    = $jobNode->filter('.date-time')->text();

                $jobs[] = [
                    'title'   => trim($title),
                    'link'    => trim($link),
                    'company' => trim($company),
                    'salary'  => trim($salary),
                    'place'   => trim($place),
                    'update_time' => trim($update_time),
                ];
            });

            ++$currentPage;
        }
        dd($jobs);
        $sendNotifyText = "";

        foreach ($jobs as $job)
        {
            $date = Carbon::now()->toDateString();

            if (!JobChickpt::where('link', $job['link'])->first())
            {
                if(empty($sendNotifyText)) $sendNotifyText .= "\n" . "小雞上工任務列表" . "\n". "\n";

                JobChickpt::create([
                    'title'   => $job['title'],
                    'link'    => $job['link'],
                    'company' => $job['company'],
                    'salary'  => $job['salary'],
                    'place'   => $job['place'],
                    'date'    => $date,
                ]);

                $sendNotifyText .= '標題：'. $job['title'] . "\n";
                $sendNotifyText .= '任務連結：'. $job['link'] . "\n";
                $sendNotifyText .= '雇主：'. $job['company'] . "\n";
                $sendNotifyText .= '工作地方：'. $job['place'] . "\n";
                $sendNotifyText .= '薪水：'. $job['salary'] . "\n";
            }

            if(!empty($sendNotifyText)) $sendNotifyText .= "\n" . "\n";
        }

        if(!empty($sendNotifyText))
        {
            $this->info('save data success');

            $response = $this->lineNotify->sendMessage($sendNotifyText);

            $data = json_decode($response);

            if($data->status != '200' && $data->message != 'ok')
            {
                Log::error('send notify error! '. $data->message);
            }
            else
            {
                $this->info('send notify success');
            }
        }
        else
        {
            $this->info('no recent job period!');
        }

        // 定時清理舊資料 將昨天or7天前的資料刪除
    }
}
