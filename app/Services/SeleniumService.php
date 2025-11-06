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

    public function openWebDriver(bool $useProxy = false, string $proxyIpPort = '')
    {
        $serverUrl = 'http://127.0.0.1:4444'; // Selenium 伺服器的地址

        $options = new ChromeOptions();
        $options->addArguments([
            '--headless',          // 無視窗模式
            '--disable-gpu',       // 停止 GPU 加速
            '--no-sandbox',        // Linux 環境必要
            '--disable-dev-shm-usage', // Docker 或低記憶體環境
        ]);

        // Proxy 設定 (Chrome)
        if ($useProxy && !empty($proxyIpPort) && str_contains($proxyIpPort, ':')) {
            [$ip, $port] = explode(':', $proxyIpPort, 2);
            $options->addArguments([
                "--proxy-server=http://$ip:$port"
            ]);
        }

        // 配置 capabilities
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        try {
            $webDriver = RemoteWebDriver::create($serverUrl, $capabilities);
        } catch (\Exception $e) {
            throw new \RuntimeException("無法連接 Selenium Server: " . $e->getMessage());
        }

        return $webDriver;
    }
}
