<?php

declare(strict_types=1);

/*
 * This file is part of the Playwright PHP community project.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PlaywrightPHP\Mink\Tests;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\AbstractConfig;
use PlaywrightPHP\Mink\Driver\PlaywrightDriver;

/**
 * Configuration for the Mink driver tests suite.
 *
 * @author Simon André
 */
final class Config extends AbstractConfig
{
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function createDriver(array $params = []): DriverInterface
    {
        $browser = isset($params['browser']) && is_string($params['browser'])
            ? $params['browser']
            : (string) (getenv('PLAYWRIGHT_BROWSER') ?: 'chromium');
        $headless = (bool) ($params['headless'] ?? (getenv('PLAYWRIGHT_HEADLESS') ?: true));

        /** @var array<string, mixed> $launch */
        $launch = isset($params['launch']) && is_array($params['launch']) ? $params['launch'] : [];
        /** @var array<string, mixed> $context */
        $context = isset($params['context']) && is_array($params['context']) ? $params['context'] : [];

        return new PlaywrightDriver(
            browserType: $browser,
            headless: $headless,
            launchOptions: $launch,
            contextOptions: $context,
        );
    }

    public function skipMessage($testCase, $test): ?string
    {
        $base = parent::skipMessage($testCase, $test);
        if (null !== $base) {
            return $base;
        }

        if ('Behat\\Mink\\Tests\\Driver\\Js\\JavascriptTest' === $testCase
            && in_array($test, ['testDragDrop', 'testDragDropOntoHiddenItself'], true)
        ) {
            return 'Skipped: jQuery UI drag&drop not supported by Playwright dragAndDrop yet.';
        }

        if ('Behat\\Mink\\Tests\\Driver\\Js\\SessionResetTest' === $testCase
            && 'testSessionResetClosesWindows' === $test
        ) {
            return 'Skipped: popup window tracking not yet supported.';
        }

        if ('Behat\\Mink\\Tests\\Driver\\Js\\WindowTest' === $testCase
            && in_array($test, ['testWindow', 'testGetWindowNames'], true)
        ) {
            return 'Skipped: window enumeration/switching pending popup event support.';
        }

        return null;
    }
}
