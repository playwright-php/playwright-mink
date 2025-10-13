<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP - https://github.com/playwright-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Mink\Tests;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\AbstractConfig;
use Playwright\Mink\Driver\PlaywrightDriver;

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

    protected function supportsCss(): bool
    {
        return true;
    }

    public function skipMessage($testCase, $test): ?string
    {
        $base = parent::skipMessage($testCase, $test);
        if (null !== $base) {
            return $base;
        }

        // jQuery UI drag & drop uses mousedown/mousemove/mouseup events with complex state management
        // Playwright's dragTo() uses HTML5 drag & drop API (drag/drop events) which jQuery UI doesn't recognize
        // Would require custom mouse event simulation with precise timing and coordinates
        if ('Behat\\Mink\\Tests\\Driver\\Js\\JavascriptTest' === $testCase
            && in_array($test, ['testDragDrop', 'testDragDropOntoHiddenItself'], true)
        ) {
            return 'jQuery UI drag & drop incompatible with Playwright dragTo() API';
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
