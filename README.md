<div align="center">
<a href="https://github.com/playwright-php"><img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright PHP" /></a>

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.2+-05971B?labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/playwright-php/playwright-mink/CI.yml?branch=main&label=Tests&color=1D8D23&labelColor=09161E&logoColor=FFFFFF)
&nbsp; [![Release](https://img.shields.io/github/v/release/playwright-php/playwright-mink?label=Stable&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)](https://packagist.org/packages/playwright-php/playwright-mink)
&nbsp; ![License](https://img.shields.io/github/license/playwright-php/playwright-mink?label=License&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)

</div>

# Playwright PHP Mink Driver

A [Mink](https://mink.behat.org/) driver powered by
[Playwright PHP](https://github.com/playwright-php/playwright).

Use it to keep an existing Mink test suite while running its browser interactions
in Chromium, Firefox, or WebKit through Playwright.

## Installation

The driver requires PHP 8.2 or later, Mink 1.10 or later, and Playwright PHP 1.x.

```bash
composer require --dev playwright-php/playwright-mink
vendor/bin/playwright-install --browsers
```

## Usage

Create the driver, pass it to a Mink session, and use the regular Mink API:

```php
<?php

use Behat\Mink\Session;
use Playwright\Mink\Driver\PlaywrightDriver;

$driver = new PlaywrightDriver(
    browserType: 'chromium',
    headless: true,
);

$session = new Session($driver);
$session->start();

try {
    $session->visit('https://example.com');

    echo $session->getPage()->getText();
} finally {
    $session->stop();
}
```

The constructor accepts:

- `browserType`: `chromium`, `firefox`, or `webkit`;
- `headless`: whether to run without a visible browser window;
- `launchOptions`: browser launch options such as `slowMo` and `args`;
- `contextOptions`: options passed to the Playwright browser context.

```php
$driver = new PlaywrightDriver(
    browserType: 'firefox',
    headless: false,
    launchOptions: [
        'slowMo' => 100,
    ],
    contextOptions: [
        'viewport' => ['width' => 1440, 'height' => 900],
        'locale' => 'en-US',
    ],
);
```

## Documentation

See [Driver support](docs/driver-support.md) for the tested Mink surface and
known limitations.

## Contributing

The driver is tested against the official
[`minkphp/driver-testsuite`](https://github.com/minkphp/driver-testsuite).

Install dependencies, start its test server, then run PHPUnit:

```bash
composer install
vendor/bin/playwright-install --browsers
vendor/bin/mink-test-server
```

In another terminal:

```bash
vendor/bin/phpunit
```

The test suite currently excludes scenarios that depend on jQuery UI drag and
drop or asynchronous popup discovery. These limitations are documented in the
driver support page.

## License

Playwright PHP Mink Driver is released under the [MIT License](LICENSE).
