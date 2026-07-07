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
use Behat\Mink\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Playwright\Mink\Tests\Config;

/**
 * Regression test for attachFile() resolving paths against the PHP process
 * cwd instead of leaking a relative path to the Node bridge.
 *
 * @see https://github.com/playwright-php/playwright-mink/issues/ (attachFile relative path)
 */
final class AttachFileRelativePathTest extends TestCase
{
    private ?DriverInterface $driver = null;
    private ?string $originalCwd = null;

    protected function tearDown(): void
    {
        $this->driver?->stop();

        if (null !== $this->originalCwd) {
            chdir($this->originalCwd);
        }
    }

    public function testAttachFileAcceptsPathRelativeToPhpCwd(): void
    {
        $tmpDir = sys_get_temp_dir().'/playwright-mink-attach-file-test';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir);
        }
        file_put_contents($tmpDir.'/upload.txt', 'hello');

        $this->originalCwd = getcwd() ?: null;
        chdir($tmpDir);

        $this->driver = Config::getInstance()->createDriver();
        $this->driver->start();
        $this->driver->visit('data:text/html,<input type="file" id="f">');

        $this->driver->attachFile("//input[@id='f']", 'upload.txt');

        self::assertSame(
            'upload.txt',
            $this->driver->evaluateScript("document.getElementById('f').files[0].name")
        );
    }

    public function testAttachFileThrowsAClearErrorForAMissingRelativePath(): void
    {
        $this->driver = Config::getInstance()->createDriver();
        $this->driver->start();
        $this->driver->visit('data:text/html,<input type="file" id="f">');

        $this->expectException(DriverException::class);

        $this->driver->attachFile("//input[@id='f']", 'does-not-exist.txt');
    }
}
