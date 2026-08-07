<?php

namespace App\Services\Scraper\JudicialAuction;

use App\Models\JudicialAuction;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;

class JudicialAuctionScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;
    protected JudicialAuctionParser $parser;
    protected JudicialAuctionFilter $filter;
    protected JudicialAuctionFormatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new JudicialAuctionParser();
        $this->filter = new JudicialAuctionFilter();
        $this->formatter = new JudicialAuctionFormatter();
    }

    public function scrape(): array
    {
        $courts = [
            'KLD' => '臺灣基隆地方法院',
        ];

        $driver = $this->seleniumService->openWebDriver();
        $items = [];

        try {
            foreach ($courts as $code => $name) {
                $driver->get('https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02.htm');
                sleep(2);

                $driver->switchTo()->frame($driver->findElement(WebDriverBy::id('v1')));

                $courtOptions = $driver->findElements(WebDriverBy::cssSelector('#court option'));
                foreach ($courtOptions as $option) {
                    if (str_contains($option->getAttribute('value'), $code)) {
                        $option->click();
                        break;
                    }
                }

                $driver->findElement(WebDriverBy::id('proptype_c51c52'))->click();
                $driver->findElement(WebDriverBy::id('rrange_ALL'))->click();
                $driver->findElement(WebDriverBy::cssSelector('label[for="column_date"]'))->click();
                $driver->findElement(WebDriverBy::id('query_btn'))->click();

                sleep(5);

                $driver->switchTo()->defaultContent();
                $driver->switchTo()->frame($driver->findElement(WebDriverBy::id('v2')));
                sleep(2);

                $rows = $driver->findElements(WebDriverBy::cssSelector('tr[id="trdata"]'));

                foreach ($rows as $row) {
                    try {
                        $cols = $row->findElements(WebDriverBy::cssSelector('td'));
                        if (count($cols) < 7) {
                            continue;
                        }

                        $caseNo = trim($cols[2]->getText());
                        $auctionDate = trim($cols[3]->getText());
                        $area = trim($cols[4]->getText());

                        $address = trim($cols[5]->getText());
                        $pdfUrl = null;

                        $links = $cols[5]->findElements(WebDriverBy::cssSelector('a'));
                        if (! empty($links)) {
                            $link = $links[0];
                            $text = trim($link->getText());
                            if (! empty($text)) {
                                $address = $text;
                            }

                            $pdfFile = $link->getAttribute('data-filenm');
                            if (! empty($pdfFile)) {
                                $pdfUrl = "https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02/DO_VIEWPDF.htm?filenm={$pdfFile}";
                            }
                        }

                        $priceRaw = trim($cols[6]->getText());
                        $priceNumber = (int) preg_replace('/[^\d]/', '', $priceRaw);

                        $isHandover = null;
                        if (isset($cols[7])) {
                            $handoverText = trim($cols[7]->getText());
                            if (str_contains($handoverText, '不點交')) {
                                $isHandover = '不點交';
                            } elseif (str_contains($handoverText, '點交')) {
                                $isHandover = '點交';
                            } else {
                                $isHandover = $handoverText ?: null;
                            }
                        }

                        $items[] = [
                            'court' => $name,
                            'case_no' => $caseNo,
                            'auction_date' => $auctionDate,
                            'area' => $area,
                            'address' => $address,
                            'price' => $priceNumber,
                            'is_handover' => $isHandover,
                            'pdf_url' => $pdfUrl,
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        } finally {
            $driver->quit();
        }

        return $items;
    }

    public function filter(array $item): bool
    {
        return $this->filter->shouldKeep($item);
    }

    public function format(array $item): string
    {
        return $this->formatter->format($item);
    }

    public function save(array $items): void
    {
        foreach ($items as $item) {
            $this->saveItem($item);
        }
    }

    protected function saveItem(array $item): ?array
    {
        if (empty($item['court']) || empty($item['case_no'])) {
            return null;
        }

        if (JudicialAuction::where('court', $item['court'])->where('case_no', $item['case_no'])->exists()) {
            return null;
        }

        JudicialAuction::create($item);

        return $item;
    }
}
