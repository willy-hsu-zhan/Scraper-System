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

    public function openWebDriver(bool $useProxy = false, string $proxyIpPort = '')
    {
        $serverUrl = 'http://127.0.0.1:4444'; // Selenium 伺服器的地址

        // 設定 Firefox 參數 先寫死默認都用firefox
        $options = new FirefoxOptions();
        $options->addArguments([
            '--headless',  // 無視窗模式
            '--disable-gpu',  // 停止 GPU 加速
        ]);

        // 若要使用 proxy，解析 IP 與 Port 並加入 Firefox profile 設定
        if ($useProxy && !empty($proxyIpPort)) {
            [$ip, $port] = explode(':', $proxyIpPort);

            // 設定 HTTP 和 HTTPS 代理
            $options->setPreference('network.proxy.type', 1);
            $options->setPreference('network.proxy.http', $ip);
            $options->setPreference('network.proxy.http_port', (int)$port);
            $options->setPreference('network.proxy.ssl', $ip);
            $options->setPreference('network.proxy.ssl_port', (int)$port);
        }  else {
            // 明確關閉代理
            $options->setPreference('network.proxy.type', 0);
        }

        $capabilities = DesiredCapabilities::firefox();
        $capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

        $webDriver = RemoteWebDriver::create($serverUrl, $capabilities);

        return $webDriver;
    }
}
