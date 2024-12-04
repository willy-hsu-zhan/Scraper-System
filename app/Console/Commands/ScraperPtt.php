<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\JobPtt;
use App\Services\LineNotify;

class ScraperPtt extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-ptt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public $locations = ['遠端', '多區', '台北', '新北', '桃園', '台中', '台南', '高雄', '基隆', '新竹', '新竹', '苗栗', '彰化', '南投', '雲林', '嘉義', '嘉義', '屏東', '宜蘭', '花蓮', '台東', '澎湖', '金門', '連江'];

    protected $lineNotify;

    public function __construct(LineNotify $lineNotify)
    {
        parent::__construct();
        $this->lineNotify = $lineNotify;
    }

    public function handle()
    {
        // 偶爾更新 所以排程可以每小時跑就好

        $currentPage = 1; // 默認第一頁為當天工作

        $maxPage = 5;

        $client = new Client();

        $baseUrl = 'https://www.ptt.cc';

        // $url = 'https://www.ptt.cc/bbs/part-time'; // part-time版
        $url = 'https://www.ptt.cc/bbs/part-time/index3950.html';

        $crawler = $client->request('GET', $url);


        while( $currentPage <= $maxPage )
        {
            try
            {
                $link = $crawler->selectLink('上頁')->link(); // 網頁只有上頁or下頁
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try
            {
                $jobList = $crawler->filter('.r-ent'); // 抓取job-list獨有的css設定

            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            $jobList->each(function ($jobNode) use (&$jobs, $baseUrl) { // 傳值不能傳參考
                $title   = $jobNode->filter('a')->text();
                $link    = $baseUrl . $jobNode->filter('a')->attr('href');
                $company = $jobNode->filter('.author')->text();
                $salary  = 0; // 進去網頁內的參數也是不定參數 所以直接給他設為0
                $place   = $this->fliterPlace($jobNode->filter('a')->text());
                $date    = $jobNode->filter('.date')->text();

                $jobs[] = [
                    'title'   => trim($title),
                    'link'    => trim($link),
                    'company' => trim($company),
                    'salary'  => trim($salary),
                    'place'   => trim($place),
                    'date'    => trim($date),
                ];
            });

            ++$currentPage;
        }


        $client = new Client();

        $url = 'https://www.ptt.cc/bbs/Soft_Job'; //軟工版

        $currentPage = 1; // 默認第一頁為當天工作

        $crawler = $client->request('GET', $url);

        while( $currentPage <= $maxPage )
        {
            try
            {
                $link = $crawler->selectLink('上頁')->link(); // 網頁只有上頁or下頁
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try
            {
                $jobList = $crawler->filter('.r-ent'); // 抓取job-list獨有的css設定
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            $jobList->each(function ($jobNode) use (&$jobs, $baseUrl) { // 傳值不能傳參考
                $titleNode = $jobNode->filter('a');
                $title = $titleNode->count() > 0 ? $titleNode->text() : '';

                $linkNode = $jobNode->filter('a');
                $link = $linkNode->count() > 0 ? $baseUrl . $linkNode->attr('href') : '';

                $company = $jobNode->filter('.author')->text();
                $salary  = 0; // 進去網頁內的參數也是不定參數 所以直接給他設為0
                $place   = $this->fliterPlace($title); // 使用 $jobNode->filter('a')->text() 來過濾地點
                $date    = $jobNode->filter('.date')->text();

                if (mb_strpos($title, '徵才') !== false) {
                    $jobs[] = [
                        'title' => trim($title),
                        'link' => trim($link),
                        'company' => trim($company),
                        'salary' => trim($salary),
                        'place' => trim($place),
                        'date' => trim($date),
                    ];
                }
            });

            ++$currentPage;
        }

        foreach( $jobs as $key => $job )
        {
            if( $job['place'] == '公告' )
            {
                unset($jobs[$key]);
            }
            if (mb_strpos($job['title'], '已徵到') !== false)
            {
                unset($jobs[$key]);
            }
        }

        $jobs = array_values($jobs);
        dd($jobs);
        $sendNotifyText = "";

        foreach ($jobs as $job)
        {
            $date = Carbon::now()->toDateString();

            if (!JobPtt::where('link', $job['link'])->first())
            {
                if(empty($sendNotifyText)) $sendNotifyText .= "\n" . "PTT任務列表" . "\n". "\n";

                JobPtt::create([
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
               // $sendNotifyText .= '薪水：'. $job['salary'] . "\n";
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
    }

    private function fliterPlace(string $notFilterPlace): string
    {
        $filterPlace = '';

        foreach( $this->locations as $location )
        {
            if( mb_strpos($notFilterPlace, $location) !== false )
            {
                $filterPlace = $location;
                break;
            }
        }
        if( empty($filterPlace) )
        {
            $filterPlace = mb_substr($notFilterPlace, 1, 2);
        }

        return $filterPlace;
    }
}
