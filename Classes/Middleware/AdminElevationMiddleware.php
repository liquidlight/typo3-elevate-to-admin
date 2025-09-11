<?php

namespace LiquidLight\ElevateToAdmin\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;

class AdminElevationMiddleware implements MiddlewareInterface
{
	private const ELEVATION_TIMEOUT_MINUTES = 30;

	private const TABLE_BE_USERS = 'be_users';

	private const FIELD_IS_POSSIBLE_ADMIN = 'tx_elevate_to_admin_is_possible_admin';

	private const FIELD_ADMIN_SINCE = 'tx_elevate_to_admin_admin_since';

	private int $currentUserId;

	public function __construct(
		private readonly ConnectionPool $connectionPool
	) {
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$backendUser = $GLOBALS['BE_USER'] ?? null;

		if ($backendUser instanceof BackendUserAuthentication && $backendUser->user) {
			$this->processAdminElevation($backendUser);
		}

		return $handler->handle($request);
	}

	private function processAdminElevation(BackendUserAuthentication $backendUser): void
	{
		$this->currentUserId = (int)$backendUser->user['uid'];

		if (!$backendUser->isAdmin()) {
			return;
		}

		$adminSince = (int)($backendUser->user[self::FIELD_ADMIN_SINCE] ?? 0);
		$currentTime = time();

		// Handle different elevation states
		if ($adminSince === 0 || $this->hasElevationExpired($adminSince, $currentTime)) {
			// No elevation session or expired - remove admin privileges
			$this->updateAdminSince(0);
			$this->removeAdminPrivileges();
		} else {
			// Elevation still valid - refresh timestamp
			$this->updateAdminSince();
		}
	}

	private function hasElevationExpired(int $adminSince, int $currentTime): bool
	{
		return ($currentTime - $adminSince) > (self::ELEVATION_TIMEOUT_MINUTES * 60);
	}

	private function removeAdminPrivileges(): void
	{
		$this->updateUserRecord(['admin' => 0]);
	}

	private function updateAdminSince(?int $timestamp = null): void
	{
		$timestamp ??= time();
		$this->updateUserRecord([self::FIELD_ADMIN_SINCE => $timestamp]);
	}

	private function updateUserRecord(array $fields = []): void
	{
		// Always ensure can_elevate flag is set for admin users
		$fields[self::FIELD_IS_POSSIBLE_ADMIN] = 1;

		$connection = $this->connectionPool->getConnectionForTable(self::TABLE_BE_USERS);

		$connection->update(
			self::TABLE_BE_USERS,
			$fields,
			['uid' => $this->currentUserId]
		);

		// Update the global BE_USER to reflect changes immediately
		if ($GLOBALS['BE_USER']->user['uid'] === $this->currentUserId) {
			foreach ($fields as $field => $value) {
				$GLOBALS['BE_USER']->user[$field] = $value;
			}
		}
	}
}
