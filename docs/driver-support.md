# Driver support

Playwright PHP Mink Driver implements Mink's browser-facing driver API on top of
Playwright PHP. Existing test code continues to use Mink sessions, pages, and
nodes; the driver translates those operations to Playwright.

## Supported workflows

The driver covers the regular Mink workflows exercised by the official driver
test suite:

- navigation and page content;
- form fields, buttons, links, and selections;
- cookies and request headers;
- JavaScript evaluation;
- screenshots and file uploads;
- frames and browser windows;
- keyboard and mouse interactions.

Support is verified by `minkphp/driver-testsuite`, rather than by a separate list
of methods maintained by hand.

## Known limitations

### jQuery UI drag and drop

The upstream driver test suite contains drag-and-drop scenarios implemented
with jQuery UI's mouse-event model. Playwright's high-level drag-and-drop
operation follows the browser's HTML drag-and-drop model, so those scenarios
are currently excluded.

### Popup discovery

Scenarios that open and immediately inspect a new window are currently
excluded. Popup registration is asynchronous in Playwright PHP, while Mink's
window API expects the new window to be discoverable synchronously.

## Browser coverage

The driver accepts `chromium`, `firefox`, and `webkit`. The repository CI
currently runs the official driver test suite with Chromium on PHP 8.3 and 8.4.
Firefox, WebKit, and PHP 8.2 are supported by the package constraints but are
not part of the active CI matrix.

## Reporting a compatibility issue

When reporting a driver mismatch, include:

- the smallest Mink operation that reproduces it;
- the browser and PHP versions;
- the versions of Mink, Playwright PHP, and this driver;
- whether the same interaction succeeds through Playwright PHP directly.
