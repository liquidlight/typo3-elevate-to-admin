# Elevate to Admin

Allow users to elevate themselves to admin if they have permission to do so.

Conforms TYPO3 to Cyber Essentials/ISO 27001 standards where users are not supposed to be logging in with an Admin account.

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

## TODO:

- Allow potential admins to login when TYPO3 is locked for editors - v12+
- PHP 7.4 compatibility
- TYPO3 12 & 13 compatibility
