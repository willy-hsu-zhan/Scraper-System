<?php

namespace App\Services;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
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
        $serverUrl = 'http://127.0.0.1:4444';

        $options = new FirefoxOptions();

        // Headless 模式
        if ($headless) {
            $options->addArguments([
                '-headless',
            ]);
        }

        // Proxy 設定
        if ($useProxy && !empty($proxyIpPort)) {
            $options->addArguments([
                "--proxy-server=http://{$proxyIpPort}",
            ]);
        }

        // 設定能力
        $capabilities = DesiredCapabilities::firefox();
        $capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

        // 建立 WebDriver（timeout 設長一點）
        return RemoteWebDriver::create(
            $serverUrl,
            $capabilities,
            300000,
            300000
        );
    }
}
