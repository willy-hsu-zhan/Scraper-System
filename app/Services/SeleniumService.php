<?php

namespace App\Services;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class SeleniumService
{
    protected $telegram;
    protected $apiKey;

    public function __construct()
    {

    }

    public function openWebDriver()
    {
        $serverUrl = 'http://127.0.0.1:4444'; // Selenium 伺服器的地址

        // 設定 Firefox 參數 先寫死默認都用firefox
        $options = new FirefoxOptions();
        $options->addArguments([
            '--headless',  // 無視窗模式
            '--disable-gpu',  // 停止 GPU 加速
        ]);

        $capabilities = DesiredCapabilities::firefox();
        $capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

        $webDriver = RemoteWebDriver::create($serverUrl, $capabilities);

        return $webDriver;
    }
}
