<?php

namespace App\Services;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class SeleniumService
{
    protected $telegram;
    protected $apiKey;

    public function __construct()
    {

    }

    public function openWebDriver(bool $useProxy = false, string $proxyIpPort = '', bool $headless = true)
    {
        // 你自己啟動的 ChromeDriver 位置
        // 建議用: chromedriver --port=4444
        $serverUrl = 'http://127.0.0.1:4444';

        $options = new ChromeOptions();

        // Headless 模式
        if ($headless) {
            $options->addArguments([
                '--headless=new',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-software-rasterizer',
                '--disable-extensions',
                '--disable-infobars',
                '--window-size=1920,1080',
            ]);
        }

        // Proxy 設定
        if ($useProxy && !empty($proxyIpPort)) {
            $options->addArguments([
                "--proxy-server=http://{$proxyIpPort}",
            ]);
        }

        // 設定能力
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        // 建立 WebDriver（timeout 設長一點）
        return RemoteWebDriver::create(
            $serverUrl,
            $capabilities,
            300000,
            300000
        );
    }
}
