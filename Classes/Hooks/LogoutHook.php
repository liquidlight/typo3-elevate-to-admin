<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Hooks;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LogoutHook
{
	private const TABLE_BE_USERS = 'be_users';

	private const FIELD_ADMIN_SINCE = 'tx_elevate_to_admin_admin_since';

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

	private function clearAdminElevation(int $userId): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable(self::TABLE_BE_USERS)
		;

		$connection->update(
			self::TABLE_BE_USERS,
			[
				'admin' => 0,
				self::FIELD_ADMIN_SINCE => 0,
			],
			['uid' => $userId]
		);
	}
}
