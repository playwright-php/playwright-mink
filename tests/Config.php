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

    /**
     * @param string $testCase Fully-qualified test case class name
     * @param string $test     Test method name
     */
    public function skipMessage($testCase, $test): ?string
    {
        // Defer to base rules first
        $base = parent::skipMessage($testCase, $test);
        if (null !== $base) {
            return $base;
        }

        // Temporarily skip jQuery UI drag&drop tests: Playwright's native dragAndDrop
        // path is HTML5-oriented and unreliable for these fixtures; fallback path
        // will be revisited separately.
        if ('Behat\\Mink\\Tests\\Driver\\Js\\JavascriptTest' === $testCase
            && in_array($test, ['testDragDrop', 'testDragDropOntoHiddenItself'], true)
        ) {
            return 'Skipped: jQuery UI drag&drop not supported by Playwright dragAndDrop yet.';
        }

        // Known limitation: popup tracking (window.open) not fully supported yet by the core transport;
        // skip window count tests until popup/page events are wired end-to-end.
        if ('Behat\\Mink\\Tests\\Driver\\Js\\SessionResetTest' === $testCase
            && 'testSessionResetClosesWindows' === $test
        ) {
            return 'Skipped: popup window tracking not yet supported.';
        }

        // Temporarily skip window enumeration/switching assertions
        if ('Behat\\Mink\\Tests\\Driver\\Js\\WindowTest' === $testCase
            && in_array($test, ['testWindow', 'testGetWindowNames'], true)
        ) {
            return 'Skipped: window enumeration/switching pending popup event support.';
        }

        return null;
    }
}
