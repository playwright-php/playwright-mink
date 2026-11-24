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

use Behat\Mink\Driver\DriverInterface;
use PHPUnit\Framework\TestCase;
use Playwright\Mink\Tests\Config;

/**
 * `httpCredentials` given in the context options are used instead of the driver's own ones.
 */
final class ContextHttpCredentialsTest extends TestCase
{
    private ?DriverInterface $driver = null;

    protected function tearDown(): void
    {
        $this->driver?->stop();
    }

    public function testContextHttpCredentialsAuthenticate(): void
    {
        $config = Config::getInstance();
        $this->driver = $config->createDriver([
            'context' => ['httpCredentials' => ['username' => 'mink-user', 'password' => 'mink-password']],
        ]);
        $this->driver->start();
        $this->driver->visit(rtrim($config->getWebFixturesUrl(), '/').'/basic_auth.php');

        self::assertSame('is authenticated', trim($this->driver->getText('//body')));
    }
}
