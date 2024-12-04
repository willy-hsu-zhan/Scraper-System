<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Goutte\Client;
use Illuminate\Support\Carbon;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\JobFacebook;
use App\Services\LineNotify;
use Exception;
use Illuminate\Support\Facades\Log;

class FacebookLoginTest extends DuskTestCase
{
    /**
     * A Dusk test example.
     */

    public function testExample(): void
    {
        $this->browse(function (Browser $browser) {
            $account  = config('facebook.account');
            $password = config('facebook.password');

            $browser->maximize(); // 設定測試瀏覽器視窗為最大畫面(觸發JS載入畫面)

            $baseUrl            = 'https://www.facebook.com/';
            $groupUrl           = 'groups/2276905072551692'; // 程式付費研究社 群組ID
            //$searchConditionUrl = '?sorting_setting=RECENT_ACTIVITY'; // 搜尋條件: 最相關
            $searchConditionUrl = '?sorting_setting=CHRONOLOGICAL'; // 搜尋條件: 最新

            $browser->visit($baseUrl)
                ->type('email', $account)
                ->type('pass', $password)
                ->press('Log In')
                ->assertPathIs('/');

            $url = $baseUrl . $groupUrl . $searchConditionUrl;

            $browser->visit($url)
                ->waitFor('.xdj266r.x11i5rnm.xat24cr.x1mh8g0r.x1vvkbs.x126k92a')
                ->script("window.scrollTo(0, document.body.scrollHeight);");
            $browser->pause(1000);

            // for( $i = 0; $i < 1; ++$i ) // 滾動網頁5次 // 暫時失效 待解決方法
            // {
            //     $browser->script("window.scrollTo(0, document.body.scrollHeight);");
            //     $browser->pause(3000);
            // }

            $this->assertTrue(true); // 判斷他是有正常畫面的

            $htmlContent = $browser->driver->getPageSource();

            $crawler = new Crawler($htmlContent);

            $classParam = '.xdj266r.x11i5rnm.xat24cr.x1mh8g0r.x1vvkbs.x126k92a';

            $jobList = $crawler->filter($classParam);

            $date = Carbon::now()->toDateString();

            $jobList->each(function ($jobNode) use (&$jobs, $date) {
                $title    = $jobNode->filter('div[style="text-align: start;"]')->eq(0)->text();
                $level    = $jobNode->filter('div[style="text-align: start;"]')->eq(1)->text();
                $language = $jobNode->filter('div[style="text-align: start;"]')->eq(2)->text();
                $salary   = $jobNode->filter('div[style="text-align: start;"]')->eq(3)->text();
                $deadline = $jobNode->filter('div[style="text-align: start;"]')->eq(4)->text();
                $deadline = mb_substr($deadline, 0, mb_strpos($deadline, '……')); // 找到"……"的位置並取開頭到此字串index的字
                $jobs[] = [
                    'title'     => trim($title),
                    'level'     => trim($level),
                    'language'  => trim($language),
                    'salary'    => trim($salary),
                    'date'      => trim($date),
                    'deadline'  => trim($deadline),
                ];
            });

            $sendNotifyText = "";

            foreach ($jobs as $job)
            {
                $date = Carbon::now()->toDateString();

                if (!JobFacebook::where('title', $job['title'])->where('deadline', $job['deadline'])->first())
                {
                    if(empty($sendNotifyText)) $sendNotifyText .= "\n" . "大學付費研究社任務列表" . "\n". "\n";

                    JobFacebook::create([
                        'title'    => $job['title'],
                        'level'    => $job['level'],
                        'language' => $job['language'],
                        'salary'   => $job['salary'],
                        'deadline' => $job['deadline'],
                        'date'     => $date,
                    ]);

                    $sendNotifyText .= $job['title'] . "\n";
                    $sendNotifyText .= $job['level'] . "\n";
                    $sendNotifyText .= $job['language'] . "\n";
                    $sendNotifyText .= $job['salary'] . "\n";
                    $sendNotifyText .= $job['deadline'] . "\n";
                }

                if(!empty($sendNotifyText)) $sendNotifyText .= "\n" . "\n";
            }

            if(!empty($sendNotifyText))
            {
                $lineNotify = app(LineNotify::class);

                $response = $lineNotify->sendMessage($sendNotifyText);

                $data = json_decode($response);

                if($data->status != '200' && $data->message != 'ok')
                {
                    Log::error('send notify error! '. $data->message);
                }
            }
        });
    }
}
