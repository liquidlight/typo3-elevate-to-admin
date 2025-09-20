<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\EventListener;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use TYPO3\CMS\Core\Authentication\Event\BeforeUserLogoutEvent;

final class BeforeUserLogoutEventListener
{
	use AdminElevationTrait;

	public function __invoke(BeforeUserLogoutEvent $event): void
	{
		$user = $event->getUser();

		// Only handle backend users
		if (!isset($user->user['uid'])) {
			return;
		}

		$userId = (int)$user->user['uid'];

		// Clear admin elevation fields
		$this->clearAdminElevation($userId);
	}
}
