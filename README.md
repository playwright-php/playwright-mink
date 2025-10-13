<div align="center">
<a href="https://github.com/playwright-php"><img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright PHP" /></a>

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.2+-05971B?labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/playwright-php/playwright-mink/CI.yaml?branch=main&label=Tests&color=1D8D23&labelColor=09161E&logoColor=FFFFFF)
&nbsp; ![Release](https://img.shields.io/github/v/release/playwright-php/playwright-mink?label=Stable&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![License](https://img.shields.io/github/license/playwright-php/playwright-mink?label=License&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)

</div>

# Playwright PHP - Mink Driver

A [Mink](https://mink.behat.org/) driver powered by **[Playwright PHP](https://github.com/playwright-php)**.


> [!IMPORTANT]  
> This package is **experimental**. Its API may still change before the upcoming `1.0` release.  
>  
> Curious or interested? Try it out, [share your feedback](https://github.com/playwright-php/playwright-mink/issues), or ideas!


## Features

- Run real browsers: Chromium, Firefox, WebKit (headless or not)  
- Control the DOM: navigation, forms, cookies, JS, events  
- Handle windows, iframes, uploads, screenshots, dialogs

## Installation

**Requirements**

- PHP 8.2 or higher
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

**Test Results**: 212/218 tests passing (97.2%) with 491 assertions

**Start the test server**

```bash
vendor/bin/mink-test-server
```

**Run tests**

```bash
vendor/bin/phpunit
```

### Known Limitations

6 tests are skipped due to known limitations:

- **jQuery UI Drag & Drop** (2 tests): jQuery UI uses mouse events API, Playwright uses HTML5 Drag & Drop API - these are incompatible
- **Popup Window Tracking** (4 tests): Async event timing with `window.open()` requires improvements in Playwright PHP event handling

## License

This package is released by the [Playwright PHP](https://playwright-php.dev) 
project under the MIT License. See the [LICENSE](LICENSE) file for details.
