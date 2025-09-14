# Elevate to Admin

Allow users to elevate themselves to admin if they have permission to do so.

Conforms TYPO3 to Cyber Essentials/ISO 27001 standards where users are not supposed to be logging in with an Admin account.

## Installation

1. `composer require liquidlight/typo3-elevate-to-admin`
2. That's it - every user which was set as an admin will now be a non-admin when they login. They can elevate themselves to admin mode by using the dropdown in the top right

![Screenshot of dropdown](./Documentation/Images/drop-down.png)

The user can exit admin mode by using the same dropdown.

## Events

The extension dispatches PSR-14 events that allow you to customize the behaviour:

### BeforeAdminElevationProcessEvent

This event is dispatched before the admin elevation processing begins. You can use it to skip the elevation process entirely based on custom conditions.

#### Example: Make everyone admin in development mode

```php
<?php

namespace MyVendor\MyExtension\EventListener;

use LiquidLight\ElevateToAdmin\Event\BeforeAdminElevationProcessEvent;
use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use TYPO3\CMS\Core\Core\Environment;

final class DevModeAdminListener
{
    use AdminElevationTrait;

    public function __invoke(BeforeAdminElevationProcessEvent $event): void
    {
        if (Environment::getContext()->isDevelopment()) {
            $user = $event->getBackendUser();

            // Make user admin if they can elevate and aren't already admin
            if ($this->canUserElevate($user) && !$user->isAdmin()) {
                $this->setAdminElevation((int)$user->user['uid']);
            }

            // Skip normal processing since we've handled it
            $event->skipProcessing();
        }
    }
}
```

Register the event listener in `Configuration/Services.yaml`:

```yaml
services:
  MyVendor\MyExtension\EventListener\DevModeAdminListener:
    tags:
      - name: event.listener
        identifier: 'dev-mode-admin'
        event: LiquidLight\ElevateToAdmin\Event\BeforeAdminElevationProcessEvent
```

## Testing

This extension includes comprehensive unit and functional tests with database integration.

### Quick Testing

Run tests using the unified test runner:

```bash
# Run functional tests (default)
./run-tests

# Run unit tests
./run-tests -s unit

# Run all tests
./run-tests -s all

# Run with verbose output
./run-tests -v
```

### Alternative Methods

**Direct PHPUnit:**
```bash
.Build/vendor/bin/phpunit --testsuite="Functional tests"
.Build/vendor/bin/phpunit --testsuite="Unit tests"
```

### Test Database

Functional tests use:
- **SQLite in-memory** (default) - Fast, isolated
- **DDev MySQL** - When using DDev commands
- **Environment variables** - For CI/custom setups
