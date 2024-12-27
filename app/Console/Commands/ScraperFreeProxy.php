<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use App\Models\Proxy;

class ScraperFreeProxy extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-free-proxy';

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
        $client = new Client();

        $url = 'https://free-proxy-list.net/';

        $crawler = $client->request('GET', $url);

        $proxies = $crawler->filter('table.table tbody tr')->each(function ($row) {
            $columns = $row->filter('td')->each(function ($col) {
                return $col->text();
            });
            return $columns; // return td屬性
        });
        foreach($proxies as $index => $proxy)
        {
            if (!isset($proxy[6])) {
                unset($proxies[$index]);
                continue;
            }
        
            if (is_numeric($proxy[6])) {
                unset($proxies[$index]);
            }
        }
        $proxies = array_values($proxies);

        $ramdomProxy = $proxies[array_rand($proxies)];
        
        $url = 'http://' . $ramdomProxy[0] . ':' . $ramdomProxy[1];
        
        dd($url) ;
    }
}
