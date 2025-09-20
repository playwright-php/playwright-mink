<div align="center">
<a href="https://github.com/playwright-php"><img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright PHP" /></a>

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.3+-05971B?labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/playwright-php/playwright-mink/CI.yaml?branch=main&label=Tests&color=1D8D23&labelColor=09161E&logoColor=FFFFFF)
&nbsp; ![Release](https://img.shields.io/github/v/release/playwright-php/playwright-mink?label=Stable&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![License](https://img.shields.io/github/license/playwright-php/playwright-mink?label=License&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)

</div>

# Playwright PHP - Mink Driver

A [Mink](https://mink.behat.org/) driver powered by **[Playwright PHP](https://github.com/playwright-php)**.

## Features

- Run real browsers: Chromium, Firefox, WebKit (headless or not)  
- Control the DOM: navigation, forms, cookies, JS, events  
- Handle windows, iframes, uploads, screenshots, dialogs

## Installation

**Requirements**

- PHP 8.3 or higher
- [Playwright PHP](https://github.com/playwright-php/playwright)

**Install the driver**

```bash
composer require --dev playwright-php/playwright-mink
```

## Usage

```php
use Behat\Mink\Session;
use Playwright\Mink\Driver\PlaywrightDriver;

$driver = new PlaywrightDriver(browserType: 'chromium', headless: true);
$session = new Session($driver);

$session->start();
$session->visit('https://example.org');

echo $session->getPage()->getText();

$session->stop();
```

## Testing

This driver is validated against the official [`minkphp/driver-testsuite`](https://github.com/minkphp/driver-testsuite).

**Start the test server**

```bash
vendor/bin/mink-test-server
```

**Run tests**

```bash
vendor/bin/phpunit
```

## License

This package is released by the [Playwright PHP](https://playwright-php.dev) 
project under the MIT License. See the [LICENSE](LICENSE) file for details.
