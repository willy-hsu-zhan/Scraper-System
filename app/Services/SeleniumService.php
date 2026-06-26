<?php

namespace App\Services;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;

class SeleniumService
{
    public function __construct()
    {
    }

    public function openWebDriver(
        bool $useProxy = false,
        string $proxyIpPort = '',
        bool $headless = true
    ) {
        $serverUrl = 'http://127.0.0.1:4444';

        $options = new ChromeOptions();

        $args = [
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--remote-allow-origins=*',
        ];

        // headless
        if ($headless) {
            $args[] = '--headless=new';
        }

        // proxy
        if ($useProxy && !empty($proxyIpPort)) {
            $args[] = "--proxy-server=http://{$proxyIpPort}";
        }

        $options->addArguments($args);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(
            ChromeOptions::CAPABILITY,
            $options
        );

        return RemoteWebDriver::create(
            $serverUrl,
            $capabilities,
            300000,
            300000
        );
    }
}
