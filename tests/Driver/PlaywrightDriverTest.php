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

namespace Playwright\Mink\Tests\Driver;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Playwright\Mink\Driver\PlaywrightDriver;
use Playwright\Transport\TraceableTransport;

/**
 * Unit tests for PlaywrightDriver that inspect transport-level calls.
 *
 * Note: These tests are currently skipped because they require wiring a custom
 * transport into the Playwright client, which is not straightforward with the
 * current PlaywrightFactory API. See createPlaywrightWithTransport() for details.
 */
class PlaywrightDriverTest extends TestCase
{
    private TraceableTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = $this->createTraceableTransport();

        // This will always skip because we can't inject transport
        $this->markTestSkipped('Transport injection not yet implemented. PlaywrightFactory does not support custom transports.');
    }

    private function createTraceableTransport(): TraceableTransport
    {
        // TraceableTransport is a decorator, so it needs an inner transport
        // For now, we can't easily inject this without modifying PlaywrightFactory
        // This is a placeholder that will cause tests to skip
        return new TraceableTransport(
            // Would need a real transport here, but PlaywrightFactory doesn't expose this
            new \Playwright\Transport\MockTransport()
        );
    }

    /**
     * Placeholder test - would verify visit() uses page.goto.
     * Skipped in setUp() because transport injection is not supported.
     */
    public function testVisitNavigatesUsingPageGoto(): void
    {
        // Test never runs - setUp() always calls markTestSkipped()
        $this->markTestSkipped('Transport injection not supported');
    }

    /**
     * Placeholder test - would verify click() uses locator.click.
     * Skipped in setUp() because transport injection is not supported.
     */
    public function testClickPrefersLocatorOverFrameClick(): void
    {
        // Test never runs - setUp() always calls markTestSkipped()
        $this->markTestSkipped('Transport injection not supported');
    }

    /**
     * Placeholder test - would verify type() uses keyboard API.
     * Skipped in setUp() because transport injection is not supported.
     */
    public function testTypeUsesKeyboardApi(): void
    {
        // Test never runs - setUp() always calls markTestSkipped()
        $this->markTestSkipped('Transport injection not supported');
    }

    /**
     * Normalize recorded calls.
     *
     * @return list<array{method: string|null, params: array<string,mixed>}>
     */
    private function transportCalls(): array
    {
        $calls = [];

        foreach ($this->transport->getSendCalls() as $c) {
            $msg = $c['message'] ?? [];
            $method = $msg['method'] ?? null;
            $params = $msg['params'] ?? [];

            /** @var array<string,mixed> $paramsTyped */
            $paramsTyped = is_array($params) ? $params : [];

            $calls[] = [
                'method' => is_string($method) ? $method : null,
                'params' => $paramsTyped,
            ];
        }

        foreach ($this->transport->getAsyncCalls() as $c) {
            $msg = $c['message'] ?? [];
            $method = $msg['method'] ?? null;
            $params = $msg['params'] ?? [];

            /** @var array<string,mixed> $paramsTyped */
            $paramsTyped = is_array($params) ? $params : [];

            $calls[] = [
                'method' => is_string($method) ? $method : null,
                'params' => $paramsTyped,
            ];
        }

        return $calls;
    }

    private function transportCallsPretty(): string
    {
        $raw = [
            'send' => $this->transport->getSendCalls(),
            'async' => $this->transport->getAsyncCalls(),
        ];

        return json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @phpstan-ignore method.unused
     */
    private function assertTransportHas(string $method, ?callable $predicate = null): void
    {
        foreach ($this->transportCalls() as $call) {
            if (($call['method'] ?? null) === $method && (null === $predicate || $predicate($call['params'] ?? []))) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        Assert::fail(sprintf(
            'Did not find transport call "%s". Calls were: %s',
            $method,
            $this->transportCallsPretty()
        ));
    }

    /**
     * @param list<string> $methods
     *
     * @phpstan-ignore method.unused
     */
    private function assertTransportHasAny(array $methods, ?callable $predicate = null): void
    {
        foreach ($this->transportCalls() as $call) {
            if (in_array($call['method'] ?? null, $methods, true) && (null === $predicate || $predicate($call['params'] ?? []))) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        Assert::fail(sprintf(
            'Did not find any of transport calls [%s]. Calls were: %s',
            implode(', ', $methods),
            $this->transportCallsPretty()
        ));
    }

    /**
     * @phpstan-ignore method.unused
     */
    private function assertTransportNotHas(string $method): void
    {
        foreach ($this->transportCalls() as $call) {
            if (($call['method'] ?? null) === $method) {
                Assert::fail(sprintf(
                    'Found unexpected transport call "%s". Calls were: %s',
                    $method,
                    $this->transportCallsPretty()
                ));
            }
        }

        $this->addToAssertionCount(1);
    }
}
