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

namespace Playwright\Mink\Driver;

use Behat\Mink\Driver\CoreDriver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\KeyModifier;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserInterface;
use Playwright\Frame\FrameLocatorInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Network\ResponseInterface;
use Playwright\Network\Route;
use Playwright\Page\PageInterface;
use Playwright\PlaywrightClient;
use Playwright\PlaywrightFactory;

/**
 * Playwright-powered driver for Behat/Mink.
 *
 * @author Simon André
 */
final class PlaywrightDriver extends CoreDriver
{
    /** Polling interval for waiting operations (microseconds) */
    private const POLL_INTERVAL_US = 50_000; // 50ms

    /** Polling interval for condition waiting (microseconds) */
    private const WAIT_POLL_INTERVAL_US = 100_000; // 100ms

    /** Timeout for window discovery (seconds) */
    private const WINDOW_DISCOVERY_TIMEOUT_S = 1.0;

    private PlaywrightClient $client;

    private BrowserInterface $browser;

    private BrowserContextInterface $context;

    private PageInterface $page;

    private ?FrameLocatorInterface $frameScope = null;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * @var array{username: string, password: string}|null
     */
    private ?array $basicAuth = null;

    private ?ResponseInterface $lastResponse = null;

    private bool $headerRoutingInstalled = false;

    /**
     * @param array<string, mixed> $launchOptions
     * @param array<string, mixed> $contextOptions
     */
    public function __construct(
        private readonly string $browserType = 'chromium',
        private readonly bool $headless = true,
        private readonly array $launchOptions = [],
        private readonly array $contextOptions = [],
    ) {
    }

    public function start(): void
    {
        try {
            $this->client = PlaywrightFactory::create();

            $browserType = match ($this->browserType) {
                'chromium' => $this->client->chromium(),
                'firefox' => $this->client->firefox(),
                'webkit' => $this->client->webkit(),
                default => $this->client->chromium(),
            };

            $builder = $browserType->withHeadless($this->headless);

            if (isset($this->launchOptions['slowMo']) && is_int($this->launchOptions['slowMo'])) {
                $builder = $builder->withSlowMo($this->launchOptions['slowMo']);
            }

            if (isset($this->launchOptions['args']) && is_array($this->launchOptions['args'])) {
                /** @var array<int, string> $args */
                $args = array_values(array_filter($this->launchOptions['args'], 'is_string'));
                $builder = $builder->withArgs($args);
            }

            $this->browser = $builder->launch();

            $contextOptions = $this->contextOptions;
            $this->context = $this->browser->newContext($contextOptions);
            $this->page = $this->context->newPage();
            $this->frameScope = null;

            $this->page->events()->onResponse(function (ResponseInterface $r): void {
                $this->lastResponse = $r;
            });

            if (!empty($this->headers) || null !== $this->basicAuth) {
                $this->installHeaderRouting();
            }
        } catch (\Throwable $e) {
            throw new DriverException('Unable to start Playwright driver: '.$e->getMessage(), 0, $e);
        }
    }

    public function isStarted(): bool
    {
        return isset($this->browser, $this->context, $this->page);
    }

    public function stop(): void
    {
        try {
            if (isset($this->browser)) {
                try {
                    $this->browser->close();
                } catch (\Throwable) { /* swallow on shutdown */
                }
            }
        } finally {
            $this->headers = [];
            $this->basicAuth = null;
            $this->frameScope = null;
            $this->lastResponse = null;
            $this->headerRoutingInstalled = false;

            unset($this->page, $this->context, $this->browser, $this->client);
        }
    }

    public function reset(): void
    {
        try {
            $pages = $this->context->pages();
            foreach ($pages as $i => $p) {
                if ($i > 0) {
                    try {
                        $p->close();
                    } catch (\Throwable) { /* ignore */
                    }
                }
            }
            try {
                $this->page = $this->context->pages()[0] ?? $this->context->newPage();
            } catch (\Throwable) {
                $this->recreateContextAndPage();
            }
            $this->page->goto('about:blank');

            $this->context->clearCookies();
            $this->headers = [];
            $this->basicAuth = null;
            $this->frameScope = null;
            $this->lastResponse = null;
            $this->headerRoutingInstalled = false;
        } catch (\Throwable $e) {
            throw new DriverException('Unable to reset Playwright driver: '.$e->getMessage(), 0, $e);
        }
    }

    public function visit(string $url): void
    {
        try {
            $this->navigateToUrl($url);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Page not found') || str_contains($msg, 'has been closed')) {
                try {
                    $this->recoverPageOrContext();
                    $this->navigateToUrl($url);

                    return;
                } catch (\Throwable $e2) {
                    throw new DriverException('visit() failed after recovery: '.$e2->getMessage(), 0, $e2);
                }
            }

            throw new DriverException('visit() failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function navigateToUrl(string $url): void
    {
        $this->frameScope = null;
        $this->lastResponse = $this->page->goto($url) ?? $this->lastResponse;
    }

    private function recoverPageOrContext(): void
    {
        try {
            $this->page = $this->context->pages()[0] ?? $this->context->newPage();
        } catch (\Throwable) {
            $this->recreateContextAndPage();
        }
        $this->frameScope = null;
    }

    public function getCurrentUrl(): string
    {
        return $this->page->url();
    }

    public function reload(): void
    {
        $this->frameScope = null;
        $this->page->reload();
    }

    public function forward(): void
    {
        $this->page->goForward();
    }

    public function back(): void
    {
        $this->page->goBack();
    }

    public function setBasicAuth($user, string $password): void
    {
        if (false === $user) {
            $this->basicAuth = null;

            return;
        }

        $this->basicAuth = ['username' => (string) $user, 'password' => $password];
        $this->installHeaderRouting();
    }

    public function switchToWindow(?string $name = null): void
    {
        try {
            $this->page->waitForEvents();
        } catch (\Throwable) {
        }

        $pages = $this->context->pages();
        if (null === $name) {
            $this->page = $pages[0] ?? $this->page;
            $this->frameScope = null;

            return;
        }

        if (count($pages) < 2) {
            $this->pollForWindows();
            $pages = $this->context->pages();
        }

        foreach ($pages as $p) {
            $winName = '';

            try {
                $n = $p->evaluate('() => window.name');
                $winName = is_string($n) ? $n : '';
            } catch (\Throwable) {
            }

            if ($winName === $name || $p->title() === $name || str_contains($p->url(), $name)) {
                $this->page = $p;
                $this->frameScope = null;

                return;
            }
        }

        throw new DriverException("Window not found: $name");
    }

    public function switchToIFrame(?string $name = null): void
    {
        if (null === $name) {
            $this->frameScope = null;

            return;
        }
        if (str_starts_with($name, '//') || str_starts_with($name, './/') || str_starts_with($name, 'xpath=')) {
            $selector = str_starts_with($name, 'xpath=') ? $name : "xpath=$name";
            $this->frameScope = $this->page->frameLocator($selector);

            return;
        }
        if ('' !== $name && ('#' === $name[0] || '.' === $name[0])) {
            $this->frameScope = $this->page->frameLocator($name);

            return;
        }
        $escaped = addslashes($name);
        $selector = 'xpath='."//iframe[@name=\"$escaped\"] | //iframe[@id=\"$escaped\"]";
        $this->frameScope = $this->page->frameLocator($selector);
    }

    public function setRequestHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
        $this->installHeaderRouting();
    }

    public function getResponseHeaders(): array
    {
        return $this->lastResponse?->headers() ?? [];
    }

    public function setCookie(string $name, ?string $value = null): void
    {
        $url = $this->page->url() ?: 'http://localhost/';
        if (null === $value) {
            $this->context->deleteCookie($name);

            return;
        }
        $encoded = rawurlencode($value);
        $this->context->addCookies([[
            'name' => $name,
            'value' => $encoded,
            'url' => $url,
        ]]);
    }

    public function getCookie(string $name): ?string
    {
        foreach ($this->context->cookies() as $c) {
            if ($c['name'] === $name) {
                return rawurldecode($c['value']);
            }
        }

        return null;
    }

    public function getStatusCode(): int
    {
        return $this->lastResponse?->status() ?? 200;
    }

    public function getContent(): string
    {
        return $this->page->content() ?? '';
    }

    public function getScreenshot(): string
    {
        if ($this->frameScope) {
            $data = $this->frameScope->locator(':root')->screenshot(null, ['fullPage' => true]);
        } else {
            $data = $this->page->locator(':root')->screenshot(null, ['fullPage' => true]);
        }

        if (is_string($data)) {
            $decoded = base64_decode($data, true);

            return false !== $decoded ? $decoded : $data;
        }

        return '';
    }

    private function recreateContextAndPage(): void
    {
        try {
            $contextOptions = $this->contextOptions;
            $this->context = $this->browser->newContext($contextOptions);
            $this->page = $this->context->newPage();
        } catch (\Throwable) {
            $this->start();
        }
        $this->frameScope = null;
        $this->lastResponse = null;
        $this->headerRoutingInstalled = false;
        if (!empty($this->headers) || null !== $this->basicAuth) {
            $this->installHeaderRouting();
        }
    }

    /**
     * @return array<string>
     */
    public function getWindowNames(): array
    {
        $this->pollForWindows();

        $names = [];
        foreach ($this->context->pages() as $i => $p) {
            $name = null;
            try {
                $n = $p->evaluate('() => window.name');
                $name = is_string($n) && '' !== $n ? $n : null;
            } catch (\Throwable) {
            }
            if (null === $name) {
                $title = $p->title();
                $name = '' !== $title ? $title : ($p->url() ?: "window#$i");
            }
            $names[] = $name;
        }

        return $names;
    }

    public function getWindowName(): string
    {
        $title = $this->page->title();

        return '' !== $title ? $title : ($this->page->url() ?: 'window#0');
    }

    protected function findElementXpaths(string $xpath): array
    {
        $count = $this->locator($xpath)->count();
        $out = [];
        for ($i = 1; $i <= $count; ++$i) {
            $out[] = "($xpath)[$i]";
        }

        return $out;
    }

    public function getTagName(string $xpath): string
    {
        $tag = $this->safe(fn () => $this->first($xpath)->evaluate('el => el.tagName.toLowerCase()'));
        if (!is_string($tag)) {
            throw new DriverException('Unable to determine tag name');
        }

        return $tag;
    }

    public function getText(string $xpath): string
    {
        $text = $this->safe(fn () => $this->first($xpath)->innerText());
        if (!is_string($text)) {
            throw new DriverException('Unable to read text');
        }

        return $this->normalizeVisibleText($text);
    }

    public function getHtml(string $xpath): string
    {
        $html = $this->safe(fn () => $this->first($xpath)->innerHTML());
        if (!is_string($html)) {
            throw new DriverException('Unable to read HTML');
        }

        return $html;
    }

    public function getOuterHtml(string $xpath): string
    {
        $html = $this->safe(fn () => $this->first($xpath)->evaluate('el => el.outerHTML'));
        if (!is_string($html)) {
            throw new DriverException('Unable to read outerHTML');
        }

        return $html;
    }

    public function getAttribute(string $xpath, string $name): ?string
    {
        $attr = $this->safe(fn () => $this->first($xpath)->getAttribute($name));

        return is_string($attr) ? $attr : null;
    }

    /**
     * @return list<string>|string|null
     */
    public function getValue(string $xpath): string|array|null
    {
        return $this->safe(function () use ($xpath): string|array|null {
            $element = $this->first($xpath);
            $tag = $element->evaluate('el => el.tagName.toLowerCase()');
            $tag = is_string($tag) ? $tag : '';
            $type = $element->getAttribute('type');
            $type = is_string($type) ? $type : null;

            return match (true) {
                'input' === $tag && 'checkbox' === $type => $this->getCheckboxValue($element),
                'input' === $tag && 'radio' === $type => $this->getRadioValue($element),
                'option' === $tag => $this->getOptionValue($element),
                'select' === $tag => $this->getSelectValue($element),
                default => $element->inputValue(),
            };
        });
    }

    /**
     * Set the value of a form element identified by XPath.
     * Handles text, checkbox, radio, select, and file inputs.
     *
     * @param array<string>|string|bool $value
     *
     * @throws DriverException
     */
    public function setValue(string $xpath, $value): void
    {
        $element = $this->first($xpath);

        if (is_array($value)) {
            $this->setMultiSelectValue($element, $value);

            return;
        }

        if (is_bool($value)) {
            $this->setCheckboxValue($element, $value);

            return;
        }

        $tag = $this->safe(fn () => $element->evaluate('el => el.tagName.toLowerCase()'));
        $type = $this->safe(fn () => $element->getAttribute('type'));
        $textValue = (string) $value;

        match (true) {
            'input' === $tag && 'file' === $type => $this->safe(fn () => $element->setInputFiles([$textValue])),
            'select' === $tag => $this->setSelectValue($element, $textValue),
            'input' === $tag && 'radio' === $type => $this->setRadioValue($element, $textValue),
            default => $this->setTextValue($element, $textValue),
        };
    }

    public function check(string $xpath): void
    {
        $this->safe(fn () => $this->first($xpath)->check());
    }

    public function uncheck(string $xpath): void
    {
        $this->safe(fn () => $this->first($xpath)->uncheck());
    }

    public function isChecked(string $xpath): bool
    {
        return (bool) $this->safe(fn () => $this->first($xpath)->isChecked());
    }

    public function selectOption(string $xpath, string $value, bool $multiple = false): void
    {
        $this->safe(function () use ($xpath, $value, $multiple) {
            $element = $this->first($xpath);
            $tag = $element->evaluate('el => el.tagName.toLowerCase()');
            $type = $element->getAttribute('type');

            if ('select' === $tag) {
                if ($multiple) {
                    // For multi-select, need to get existing values and append the new one
                    $currentValues = $this->getValue($xpath);
                    $existing = is_array($currentValues) ? $currentValues : [];
                    $newValues = array_values(array_unique([...$existing, $value]));
                    $element->selectOption($newValues);
                } else {
                    try {
                        $element->selectOption(['value' => $value]);
                    } catch (\Throwable) {
                        $element->selectOption(['label' => $value]);
                    }
                }

                return;
            }

            if ('input' === $tag && 'radio' === $type) {
                $this->setRadioValue($element, $value);

                return;
            }

            throw new DriverException('selectOption failed: element is not a <select> or radio button');
        });
    }

    public function isSelected(string $xpath): bool
    {
        return (bool) $this->safe(fn () => $this->first($xpath)->evaluate('el => el.selected || el.checked'));
    }

    public function click(string $xpath): void
    {
        $this->safe(fn () => $this->first($xpath)->click());
    }

    public function doubleClick(string $xpath): void
    {
        $this->safe(fn () => $this->first($xpath)->dblclick());
    }

    public function rightClick(string $xpath): void
    {
        $this->safe(fn () => $this->first($xpath)->click(['button' => 'right']));
    }

    public function attachFile(string $xpath, string $path): void
    {
        $element = $this->first($xpath);
        $this->safe(function () use ($element, $path) {
            $tag = $element->evaluate('el => el.tagName.toLowerCase()');
            $type = $element->getAttribute('type');

            if ('input' !== $tag || 'file' !== $type) {
                throw new DriverException('attachFile: element is not a file input');
            }

            $element->setInputFiles([$path]);
        });
    }

    public function isVisible(string $xpath): bool
    {
        return (bool) $this->safe(fn () => $this->first($xpath)->isVisible());
    }

    public function mouseOver(string $xpath): void
    {
        $el = $this->first($xpath);
        $this->safe(fn () => $el->hover());
    }

    public function focus(string $xpath): void
    {
        $el = $this->first($xpath);
        $this->safe(fn () => $el->focus());
    }

    public function blur(string $xpath): void
    {
        $el = $this->first($xpath);
        $this->safe(fn () => $el->blur());
    }

    public function keyPress(string $xpath, $char, ?string $modifier = null): void
    {
        $key = is_int($char) ? chr($char) : (string) $char;
        $code = is_int($char) ? $char : (1 === strlen($key) ? ord($key) : 0);
        $modKey = $this->getModifierKey($modifier);

        $this->safe(function () use ($xpath, $key, $code, $modKey): void {
            $this->first($xpath)->evaluate('(el, arg) => {
                const ev = new KeyboardEvent("keypress", {
                    key: arg.key,
                    bubbles: true,
                    cancelable: true,
                    altKey: arg.mod === "Alt",
                    ctrlKey: arg.mod === "Control",
                    metaKey: arg.mod === "Meta",
                    shiftKey: arg.mod === "Shift",
                });
                Object.defineProperty(ev, "keyCode", { value: arg.code });
                Object.defineProperty(ev, "which", { value: arg.code });
                el.dispatchEvent(ev);
            }', ['key' => $key, 'code' => $code, 'mod' => $modKey]);
        });
    }

    public function keyDown(string $xpath, $char, ?string $modifier = null): void
    {
        $key = is_int($char) ? chr($char) : (string) $char;
        $modKey = $this->getModifierKey($modifier);

        $this->safe(function () use ($xpath, $key, $modKey): void {
            $this->first($xpath)->evaluate('(el, arg) => {
                const ev = new KeyboardEvent("keydown", {
                    key: arg.key,
                    bubbles: true,
                    cancelable: true,
                    altKey: arg.mod === "Alt",
                    ctrlKey: arg.mod === "Control",
                    metaKey: arg.mod === "Meta",
                    shiftKey: arg.mod === "Shift",
                });
                el.dispatchEvent(ev);
            }', ['key' => $key, 'mod' => $modKey]);
        });
    }

    public function keyUp(string $xpath, $char, ?string $modifier = null): void
    {
        $key = is_int($char) ? chr($char) : (string) $char;
        $code = is_int($char) ? $char : (1 === strlen($key) ? ord($key) : 0);
        $modKey = $this->getModifierKey($modifier);

        $this->safe(function () use ($xpath, $key, $code, $modKey): void {
            $this->first($xpath)->evaluate('(el, arg) => {
                const ev = new KeyboardEvent("keyup", {
                    key: arg.key,
                    bubbles: true,
                    cancelable: true,
                    altKey: arg.mod === "Alt",
                    ctrlKey: arg.mod === "Control",
                    metaKey: arg.mod === "Meta",
                    shiftKey: arg.mod === "Shift",
                });
                Object.defineProperty(ev, "keyCode", { value: arg.code });
                Object.defineProperty(ev, "which", { value: arg.code });
                el.dispatchEvent(ev);
            }', ['key' => $key, 'code' => $code, 'mod' => $modKey]);
        });
    }

    public function dragTo(string $sourceXpath, string $destinationXpath): void
    {
        $this->safe(function () use ($sourceXpath, $destinationXpath): void {
            $source = $this->first($sourceXpath);
            $destination = $this->first($destinationXpath);
            $source->dragTo($destination);
        });
    }

    public function executeScript(string $script): void
    {
        $fn = $this->wrapScript($script, false);
        $this->pageOrFrameEvaluate($fn);
    }

    public function evaluateScript(string $script): mixed
    {
        $fn = $this->wrapScript($script, true);

        return $this->pageOrFrameEvaluate($fn);
    }

    public function wait(int $timeout, string $condition): bool
    {
        $deadline = microtime(true) + ($timeout / 1000);
        // Normalize condition: remove "return " prefix if present
        $cond = trim($condition);
        if (str_starts_with($cond, 'return ')) {
            $cond = substr($cond, 7);
        }
        $expr = "() => !!($cond)";

        while (microtime(true) < $deadline) {
            try {
                $result = $this->pageOrFrameEvaluate($expr);
                if (true === $result) {
                    return true;
                }
            } catch (\Throwable) {
            }
            usleep(self::WAIT_POLL_INTERVAL_US);
        }

        return false;
    }

    public function resizeWindow(int $width, int $height, ?string $name = null): void
    {
        if (null !== $name) {
            $this->switchToWindow($name);
        }
        $this->page->setViewportSize($width, $height);
    }

    public function maximizeWindow(?string $name = null): void
    {
        if (null !== $name) {
            $this->switchToWindow($name);
        }
        $this->page->setViewportSize(1920, 1080);
    }

    public function submitForm(string $xpath): void
    {
        $this->safe(function () use ($xpath): void {
            $element = $this->first($xpath);
            $hasForm = (bool) $element->evaluate('el => el.tagName === "FORM" || !!el.closest("form")');
            if (!$hasForm) {
                throw new \RuntimeException('Element is not in a form');
            }
            $element->evaluate('(el) => {
                const form = el.tagName === "FORM" ? el : el.closest("form");
                if (typeof form.requestSubmit === "function") {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }');
        });
    }

    private function installHeaderRouting(): void
    {
        if ($this->headerRoutingInstalled) {
            return;
        }
        if (!isset($this->context)) {
            return;
        }
        $this->context->route('**/*', function (Route $route): void {
            $reqHeaders = $route->request()->headers();
            $headers = $this->buildStickyHeaders($reqHeaders);
            $route->continue(['headers' => $headers]);
        });
        $this->headerRoutingInstalled = true;
    }

    /**
     * @param array<string, string> $requestHeaders
     *
     * @return array<string, string>
     */
    private function buildStickyHeaders(array $requestHeaders): array
    {
        $merged = $requestHeaders;
        foreach ($this->headers as $k => $v) {
            $merged[$k] = $v;
        }
        if ($this->basicAuth) {
            $username = $this->basicAuth['username'];
            $password = $this->basicAuth['password'];
            $token = base64_encode($username.':'.$password);
            $merged['Authorization'] = 'Basic '.$token;
        }

        return $merged;
    }

    private function locator(string $xpath): LocatorInterface
    {
        $selector = str_starts_with($xpath, 'xpath=') ? $xpath : "xpath=$xpath";

        return $this->frameScope ? $this->frameScope->locator($selector) : $this->page->locator($selector);
    }

    private function first(string $xpath): LocatorInterface
    {
        $h = $this->locator($xpath)->first();
        if (0 === $h->count()) {
            throw new DriverException("No element matches xpath: $xpath");
        }

        return $h;
    }

    private function pageOrFrameEvaluate(string $expr, mixed $arg = null): mixed
    {
        return $this->safe(function () use ($expr, $arg) {
            if ($this->frameScope) {
                return $this->frameScope->locator(':root')->evaluate($expr, $arg);
            }

            return $this->page->evaluate($expr, $arg);
        });
    }

    /**
     * Wrap a script in a function for Playwright evaluation.
     */
    private function wrapScript(string $script, bool $returns): string
    {
        $s = trim($script);
        // Remove trailing semicolon for better expression handling
        $s = rtrim($s, ';');

        // If it's already a return statement, wrap in a function
        if (str_starts_with($s, 'return ')) {
            return "() => { $s; }";
        }

        // Detect IIFE patterns: (function(){...})() or (() => {})()
        $isIife = (bool) preg_match('/^\s*\([^)]*\)\s*\(/', $s);
        if ($isIife) {
            return $returns ? "() => ( $s )" : "() => { $s; }";
        }

        // Detect anonymous function expressions: function () {...} or function () {...}()
        // Check if it's an anonymous function (not a named function)
        if (str_starts_with($s, 'function ') && !preg_match('/^function\s+\w+/', $s)) {
            // Check if it already has trailing () to invoke itself (IIFE without outer parens)
            if (preg_match('/}\s*\(\s*\)$/', $s)) {
                // Already an IIFE like: function () {...}()
                // Wrap in parens to ensure it's an expression, not a statement
                return $returns ? "() => ( $s )" : "() => { ( $s ); }";
            }

            // Not yet invoked, wrap and call it: function () {...}
            return $returns ? "() => (( $s )())" : "() => { ( $s )(); }";
        }

        // Default: expression for returns, statement for execute
        return $returns ? "() => ( $s )" : "() => { $s; }";
    }

    /**
     * Get the Playwright modifier key name from Mink's KeyModifier constant.
     */
    private function getModifierKey(?string $modifier): ?string
    {
        return match ($modifier) {
            KeyModifier::CTRL => 'Control',
            KeyModifier::ALT => 'Alt',
            KeyModifier::SHIFT => 'Shift',
            KeyModifier::META => 'Meta',
            default => null,
        };
    }

    /**
     * Wait for window/page events to be processed.
     * Polls for a short duration to allow async window operations to complete.
     */
    private function pollForWindows(): void
    {
        $deadline = microtime(true) + self::WINDOW_DISCOVERY_TIMEOUT_S;
        while (microtime(true) < $deadline) {
            try {
                $this->page->waitForEvents();
            } catch (\Throwable) {
            }
            usleep(self::POLL_INTERVAL_US);
        }
    }

    private function normalizeVisibleText(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);
        $replaced = preg_replace('/\s+/u', ' ', $text);
        $text = is_string($replaced) ? $replaced : $text;

        return trim($text);
    }

    /**
     * @param array<mixed> $value
     */
    private function setMultiSelectValue(LocatorInterface $element, array $value): void
    {
        $stringValues = [];
        foreach ($value as $v) {
            if (!is_scalar($v) && !$v instanceof \Stringable) {
                continue;
            }
            $str = (string) $v;
            if ('' !== $str) {
                $stringValues[] = $str;
            }
        }
        $this->safe(fn () => $element->selectOption($stringValues));
    }

    private function setCheckboxValue(LocatorInterface $element, bool $value): void
    {
        $tag = $this->safe(fn () => $element->evaluate('el => el.tagName.toLowerCase()'));
        $type = $this->safe(fn () => $element->getAttribute('type'));

        if ('input' !== $tag || 'checkbox' !== $type) {
            throw new DriverException('Boolean value is only supported for checkboxes');
        }

        $this->safe(fn () => $value ? $element->check() : $element->uncheck());
    }

    private function setSelectValue(LocatorInterface $element, string $value): void
    {
        $this->safe(function () use ($element, $value) {
            $selected = $element->selectOption(['value' => $value]);
            if (empty($selected)) {
                $element->selectOption(['label' => $value]);
            }
        });
    }

    private function setRadioValue(LocatorInterface $element, string $value): void
    {
        $this->safe(function () use ($element, $value) {
            $element->evaluate('(el, value) => {
                const name = el.getAttribute("name");
                if (!name) {
                    const ownValue = el.getAttribute("value");
                    if (ownValue === value) { el.click(); return; }
                    throw new Error("Radio button group must have a name attribute.");
                }
                const radios = Array.from(document.querySelectorAll(`input[type="radio"][name="${name}"]`));
                const target = radios.find(r => r.form === el.form && (r.getAttribute("value") ?? "on") === value);
                if (!target) throw new Error("Radio button not found in same form");
                target.click();
            }', $value);
        });
    }

    private function setTextValue(LocatorInterface $element, string $value): void
    {
        $this->safe(function () use ($element, $value) {
            $element->fill($value);
            $element->evaluate('el => {
                el.dispatchEvent(new Event("input", { bubbles: true }));
                el.dispatchEvent(new KeyboardEvent("keyup", { bubbles: true }));
                el.dispatchEvent(new Event("change", { bubbles: true }));
            }');
        });
    }

    private function getCheckboxValue(LocatorInterface $element): ?string
    {
        return $element->isChecked() ? ($element->getAttribute('value') ?? 'on') : null;
    }

    private function getRadioValue(LocatorInterface $element): ?string
    {
        $rv = $element->evaluate('(el) => {
            const name = el.getAttribute("name");
            if (!name) {
                return el.checked ? (el.getAttribute("value") ?? "on") : null;
            }
            const group = Array.from(document.querySelectorAll(`input[type="radio"][name="${name}"]`));
            const sameForm = (r) => r.form === el.form;
            const checked = group.find(r => sameForm(r) && r.checked);
            if (!checked) return null;
            return checked.getAttribute("value") ?? "on";
        }');

        return (is_string($rv) || null === $rv) ? $rv : null;
    }

    private function getOptionValue(LocatorInterface $element): string
    {
        $val = $element->getAttribute('value');
        if (null !== $val) {
            return $val;
        }

        return $this->normalizeVisibleText($element->innerText());
    }

    /** @return list<string>|string */
    private function getSelectValue(LocatorInterface $element): string|array
    {
        $isMultiple = (bool) $element->evaluate('el => !!el.multiple');
        if (!$isMultiple) {
            return $element->inputValue();
        }

        $selected = $element->locator('option')->all();
        $values = [];
        foreach ($selected as $option) {
            $isSelected = (bool) $option->evaluate('el => el.selected');
            if ($isSelected) {
                $val = $option->getAttribute('value');
                if (null === $val) {
                    $val = $this->normalizeVisibleText($option->innerText());
                }
                $values[] = $val;
            }
        }

        return $values;
    }

    /**
     * @template TReturn
     *
     * @param callable():TReturn $fn
     *
     * @return TReturn
     */
    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            throw new DriverException($e->getMessage(), 0, $e);
        }
    }
}
