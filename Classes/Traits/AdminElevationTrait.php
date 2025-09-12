<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Traits;

use LiquidLight\ElevateToAdmin\Constants\DatabaseConstants;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait AdminElevationTrait
{

	protected function getBackendUser(): ?BackendUserAuthentication
	{
		return $GLOBALS['BE_USER'] ?? null;
	}

	protected function updateUserRecord(int $userId, array $fields): void
	{
		$connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
		$connection = $connectionPool->getConnectionForTable(DatabaseConstants::TABLE_BE_USERS);

		$connection->update(
			DatabaseConstants::TABLE_BE_USERS,
			$fields,
			['uid' => $userId]
		);
	}

	protected function updateGlobalUserData(int $userId, array $fields): void
	{
		if ($GLOBALS['BE_USER']->user['uid'] === $userId) {
			foreach ($fields as $field => $value) {
				$GLOBALS['BE_USER']->user[$field] = $value;
			}
		}
	}

	protected function updateUserRecordAndGlobal(int $userId, array $fields): void
	{
		$this->updateUserRecord($userId, $fields);
		$this->updateGlobalUserData($userId, $fields);
	}

	protected function canUserElevate(?BackendUserAuthentication $user = null): bool
	{
		$user = $user ?? $this->getBackendUser();

		if (!$user) {
			return false;
		}

		return (bool)($user->user[DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN] ?? false);
	}

	protected function clearAdminElevation(int $userId): void
	{
		$this->updateUserRecordAndGlobal($userId, [
			'admin' => 0,
			DatabaseConstants::FIELD_ADMIN_SINCE => 0,
		]);
	}

	protected function setAdminElevation(int $userId, ?int $timestamp = null): void
	{
		$timestamp ??= time();

		$this->updateUserRecordAndGlobal($userId, [
			'admin' => 1,
			DatabaseConstants::FIELD_ADMIN_SINCE => $timestamp,
			DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN => 1,
		]);
	}

	protected function getAdminSince(?BackendUserAuthentication $user = null): int
	{
		$user = $user ?? $this->getBackendUser();

		if (!$user) {
			return 0;
		}

		return (int)($user->user[DatabaseConstants::FIELD_ADMIN_SINCE] ?? 0);
	}

	protected function isCurrentlyElevated(?BackendUserAuthentication $user = null): bool
	{
		$user = $user ?? $this->getBackendUser();

		if (!$user || !$user->isAdmin()) {
			return false;
		}

		return $this->getAdminSince($user) > 0;
	}
}
