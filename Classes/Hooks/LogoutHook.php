<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Hooks;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

class LogoutHook
{
	use AdminElevationTrait;

	/**
	 * Called before a user is logged out
	 */
	public function logoffPreProcessing(array $params, AbstractUserAuthentication $parentObject): void
	{
		// Only handle backend users
		if (!isset($parentObject->user['uid'])) {
			return;
		}

		$userId = (int)$parentObject->user['uid'];

		// Clear admin elevation fields
		$this->clearAdminElevation($userId);
	}
}
