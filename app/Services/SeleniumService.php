<?php

namespace App\Services;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Firefox\FirefoxProfile;

class SeleniumService
{
    protected $telegram;
    protected $apiKey;

    public function __construct()
    {

    }

    public function openWebDriver()
    {
        $serverUrl = 'http://127.0.0.1:4444/wd/hub';

        $profile = new FirefoxProfile();
        $profile->setPreference('general.useragent.override', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');

        $options = new FirefoxOptions();
        $options->setProfile($profile);
        $options->addArguments([
            '--headless',
            '--disable-gpu',
        ]);

        $capabilities = DesiredCapabilities::firefox();
        $capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

        try {
            $webDriver = RemoteWebDriver::create($serverUrl, $capabilities);
            return $webDriver;
        } catch (\Exception $e) {
            return null;
        }
    }

}
