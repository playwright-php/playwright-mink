<div align="center">
<img src="https://github.com/playwright-php/.github/raw/main/profile/playwright-php.png" alt="Playwright PHP" />

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.3+-05971B?labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/playwright-php/playwright-mink/CI.yaml?branch=main&label=Tests&color=1D8D23&labelColor=09161E&logoColor=FFFFFF)
&nbsp; ![Release](https://img.shields.io/github/v/release/playwright-php/playwright-mink?label=Stable&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)
&nbsp; ![License](https://img.shields.io/github/license/playwright-php/playwright-mink?label=License&labelColor=09161E&color=1D8D23&logoColor=FFFFFF)

</div>

# Playwright PHP - Mink Driver

A [Mink](https://mink.behat.org/) driver powered by **[Playwright PHP](https://github.com/playwright-php)**.

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

**Requirements**

- PHP 8.3 or higher
- [Playwright PHP](https://github.com/playwright-php/playwright) (installed automatically)

You can install the driver via Composer:

```bash
composer require --dev playwright-php/playwright-mink
```

## Usage

### Basic Example

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

This driver is validated against the official [`minkphp/driver-testsuite`](https://github.com/minkphp/driver-testsuite).

### Starting the test server

```bash
vendor/bin/mink-test-server
```

### Run tests

```bash
vendor/bin/phpunit
```

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
