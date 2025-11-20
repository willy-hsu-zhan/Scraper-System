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

    public function openWebDriver(bool $useProxy = false, string $proxyIpPort = '', bool $headless = true)
    {
        $serverUrl = 'http://127.0.0.1:4444'; // Selenium 伺服器地址

        $options = new FirefoxOptions();

        if ($headless) {
            $options->addArguments([
                '--headless',
                '--disable-gpu',
            ]);
        }

        if ($useProxy && !empty($proxyIpPort)) {
            [$ip, $port] = explode(':', $proxyIpPort);

            $options->setPreference('network.proxy.type', 1);
            $options->setPreference('network.proxy.http', $ip);
            $options->setPreference('network.proxy.http_port', (int)$port);
            $options->setPreference('network.proxy.ssl', $ip);
            $options->setPreference('network.proxy.ssl_port', (int)$port);
            $options->setPreference('network.proxy.no_proxies_on', ''); // 所有網站都走 proxy
        } else {
            $options->setPreference('network.proxy.type', 0); // 不使用 proxy
        }

        $capabilities = DesiredCapabilities::firefox();
        $capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

        // 增加連線與命令 timeout 避免 proxy 慢導致錯誤
        $webDriver = RemoteWebDriver::create(
            $serverUrl,
            $capabilities,
            300000, // 連線 timeout 5 分鐘
            300000  // 命令 timeout 5 分鐘
        );

        return $webDriver;
    }
}
