<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ElevationController
{
	private const TABLE_BE_USERS = 'be_users';

	private const FIELD_IS_POSSIBLE_ADMIN = 'tx_elevate_to_admin_is_possible_admin';

	private const FIELD_ADMIN_SINCE = 'tx_elevate_to_admin_admin_since';

	public function elevateAction(ServerRequestInterface $request): ResponseInterface
	{
		$backendUser = $GLOBALS['BE_USER'] ?? null;

		if (!$backendUser instanceof BackendUserAuthentication) {
			return new JsonResponse(['success' => false, 'message' => 'No backend user found']);
		}

		$parsedBody = $request->getParsedBody();
		$action = $parsedBody['action'] ?? 'elevate';

		if ($action === 'leave') {
			return $this->leaveAdminMode($backendUser);
		}

		return $this->elevateToAdmin($backendUser, $parsedBody);
	}

	private function elevateToAdmin(BackendUserAuthentication $backendUser, array $parsedBody): ResponseInterface
	{
		$canElevate = (bool)($backendUser->user[self::FIELD_IS_POSSIBLE_ADMIN] ?? false);
		if (!$canElevate) {
			return new JsonResponse(['success' => false, 'message' => 'User not allowed to elevate']);
		}

		if ($backendUser->isAdmin()) {
			return new JsonResponse(['success' => false, 'message' => 'User is already admin']);
		}

		$password = $parsedBody['password'] ?? '';
		if (empty($password)) {
			return new JsonResponse(['success' => false, 'message' => 'Password is required']);
		}

		if (!$this->verifyPassword($backendUser, $password)) {
			return new JsonResponse(['success' => false, 'message' => 'Invalid password']);
		}

		$currentTime = time();
		$this->updateUserRecord((int)$backendUser->user['uid'], [
			'admin' => 1,
			self::FIELD_ADMIN_SINCE => $currentTime,
			self::FIELD_IS_POSSIBLE_ADMIN => 1,
		]);

		$GLOBALS['BE_USER']->user['admin'] = 1;
		$GLOBALS['BE_USER']->user[self::FIELD_ADMIN_SINCE] = $currentTime;

		return new JsonResponse([
			'success' => true,
			'message' => 'Successfully elevated to admin',
			'reload' => true,
		]);
	}

	private function leaveAdminMode(BackendUserAuthentication $backendUser): ResponseInterface
	{
		if (!$backendUser->isAdmin()) {
			return new JsonResponse(['success' => false, 'message' => 'User is not admin']);
		}

		$adminSince = $backendUser->user[self::FIELD_ADMIN_SINCE] ?? 0;
		if (!$adminSince) {
			return new JsonResponse(['success' => false, 'message' => 'Cannot leave permanent admin mode']);
		}

		$this->updateUserRecord((int)$backendUser->user['uid'], [
			'admin' => 0,
			self::FIELD_ADMIN_SINCE => 0,
		]);

		$GLOBALS['BE_USER']->user['admin'] = 0;
		$GLOBALS['BE_USER']->user[self::FIELD_ADMIN_SINCE] = 0;

		return new JsonResponse([
			'success' => true,
			'message' => 'Successfully left admin mode',
			'reload' => true,
		]);
	}

	private function verifyPassword(BackendUserAuthentication $backendUser, string $password): bool
	{
		$saltedPasswordService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory::class);
		$hashInstance = $saltedPasswordService->getDefaultHashInstance('BE');

		return $hashInstance->checkPassword($password, $backendUser->user['password']);
	}

	private function updateUserRecord(int $userId, array $fields): void
	{
		$connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
		$connection = $connectionPool->getConnectionForTable(self::TABLE_BE_USERS);

		$connection->update(
			self::TABLE_BE_USERS,
			$fields,
			['uid' => $userId]
		);
	}
}
