<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Mink\Tests;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\AbstractConfig;
use PlaywrightPHP\Mink\Driver\PlaywrightDriver;

final class Config extends AbstractConfig
{
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * @param array{
     *     browser?: string,
     *     headless?: bool,
     *     launch?: array<string, mixed>,
     *     context?: array<string, mixed>,
     *     } $params
     */
    public function createDriver(array $params = []): DriverInterface
    {
        $browser = $params['browser'] ?? getenv('PLAYWRIGHT_BROWSER') ?: 'chromium';
        $headless = (bool) ($params['headless'] ?? getenv('PLAYWRIGHT_HEADLESS') ?: true);

        return new PlaywrightDriver(
            browserType: $browser,
            headless: $headless,
            launchOptions: $params['launch'] ?? [],
            contextOptions: $params['context'] ?? [],
        );
    }
}
