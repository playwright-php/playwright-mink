<div align="center">
<img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright PHP" />

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.3+-05971B?labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/playwright-php/playwright-mink-driver/CI.yaml?branch=main&label=Tests&color=1D8D23&labelColor=09161E&logoColor=FFFFFF)
&nbsp; ![Release](https://img.shields.io/github/v/release/playwright-php/playwright-mink-driver?label=Stable&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![License](https://img.shields.io/github/license/playwright-php/playwright-mink-driver?label=License&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)

</div>

# PlaywrightPHP - Mink Driver

A [Mink](https://mink.behat.org/) driver powered by **Playwright PHP**.  
It brings modern browser automation (Chromium, Firefox, WebKit) to the Behat/Mink ecosystem.

> [!IMPORTANT]
> Some tests are currently skipped due to Playwright PHP limitations (drag-and-drop, window/popup handling).

## Features

- Full Mink `DriverInterface` implementation
- Chromium, Firefox, WebKit support (via Playwright)
- Navigation, cookies, headers, auth
- DOM access (XPath, CSS, attributes, values)
- Form actions (check, select, attach files)
- JavaScript execution and evaluation
- Window & iframe switching
- Screenshots

## Installation

```bash
composer require --dev smnandre/mink-playwright-driver
```

You also need Node.js and Playwright browsers:

```bash
npm install playwright
npx playwright install chromium
```

## Usage

```php
use Behat\Mink\Session;
use PlaywrightPHP\Mink\Driver\PlaywrightDriver;

$driver = new PlaywrightDriver(browserType: 'chromium', headless: true);
$session = new Session($driver);

$session->start();
$session->visit('https://example.org');
echo $session->getPage()->getText();
$session->stop();
```

## Testing

This driver is validated against the official
[`minkphp/driver-testsuite`](https://github.com/minkphp/driver-testsuite).

### Install dependencies

```bash
composer install
```

### Start the test server

```bash
vendor/bin/mink-test-server
```

### Run tests

```bash
vendor/bin/phpunit
```

## CI Matrix

The GitHub Actions workflow runs:

- PHP 8.3 & 8.4
- Browsers: Chromium, Firefox, WebKit
- Coding standards (PHPStan + PHP-CS-Fixer)
