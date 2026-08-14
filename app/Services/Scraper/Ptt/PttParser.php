<?php

namespace App\Services\Scraper\Ptt;

use Carbon\Carbon;
use Facebook\WebDriver\WebDriverBy;

class PttParser
{
    public function extractTitle($element): string
    {
        return trim($element->findElement(WebDriverBy::cssSelector('.e7-title'))->getText());
    }

    public function extractLink($element, string $originUrl): string
    {
        return $originUrl.$element->getAttribute('href');
    }

    public function extractAuthor($element): string
    {
        try {
            return trim($element->findElement(
                WebDriverBy::xpath(
                    "./ancestor::div[contains(@class,'e7-container')]//div[contains(@class,'e7-meta-container')]//a"
                )
            )->getText());
        } catch (\Exception $e) {
            return '';
        }
    }

    public function extractDate($element): string
    {
        try {
            $dateText = $element->findElement(
                WebDriverBy::xpath(
                    "./ancestor::div[contains(@class,'e7-container')]//div[contains(@class,'e7-grey-text')]//span[last()]"
                )
            )->getText();

            if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $dateText)) {
                return Carbon::createFromFormat('Y/m/d H:i', trim($dateText))->format('Y-m-d');
            }
        } catch (\Exception $e) {
        }

        return now()->format('Y-m-d');
    }

    public function extractPlace(string $title): string
    {
        $locations = [
            '遠端',
            '全國',
            '多區',
            '台北',
            '新北',
            '桃園',
            '台中',
            '台南',
            '高雄',
            '基隆',
            '新竹',
            '苗栗',
            '彰化',
            '南投',
            '雲林',
            '嘉義',
            '屏東',
            '宜蘭',
            '花蓮',
            '台東',
            '澎湖',
            '金門',
        ];

        foreach ($locations as $location) {
            if (mb_strpos($title, $location) !== false) {
                return $location;
            }
        }

        return '未分類';
    }

    public function extractSalary(string $title): ?string
    {
        if (preg_match('/時薪\s?(\d+)/u', $title, $match)) {
            return '時薪 '.$match[1];
        }

        if (preg_match('/月薪\s?(\d+)/u', $title, $match)) {
            return '月薪 '.$match[1];
        }

        if (preg_match('/日薪\s?(\d+)/u', $title, $match)) {
            return '日薪 '.$match[1];
        }

        if (preg_match('/(\d+)\s?(元|\/hr|\/小時|\/時)/u', $title, $match)) {
            return $match[1].'元';
        }

        if (mb_strpos($title, '面議') !== false) {
            return '面議';
        }

        return null;
    }
}
