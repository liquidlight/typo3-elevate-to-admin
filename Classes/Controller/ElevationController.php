<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Controller;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(public: true)]
class ElevationController
{
	use AdminElevationTrait;

	private const ALLOWED_ACTIONS = ['elevate', 'leave'];

	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	public function elevateAction(ServerRequestInterface $request): ResponseInterface
	{
		$backendUser = $this->getBackendUser();

		if (!$backendUser instanceof BackendUserAuthentication) {
			$this->logger->warning('No backend user found during elevation attempt', $this->createLogContext());
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.authentication_required')]);
		}

		$parsedBody = $request->getParsedBody();
		$action = $this->validateAction($parsedBody['action'] ?? 'elevate');

		if ($action === null) {
			$this->logger->warning('Invalid action provided', $this->createLogContext(['user_id' => $backendUser->user['uid'], 'action' => $parsedBody['action'] ?? 'null']));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.invalid_request')]);
		}

		if ($action === 'leave') {
			return $this->leaveAdminMode($backendUser);
		}

		return $this->elevateToAdmin($backendUser, $parsedBody);
	}

	private function getLanguageService(): LanguageService
	{
		return $GLOBALS['LANG'];
	}

	private function translate(string $key): string
	{
		return $this->getLanguageService()->sL('LLL:EXT:elevate_to_admin/Resources/Private/Language/locallang.xlf:' . $key);
	}

	private function elevateToAdmin(BackendUserAuthentication $backendUser, array $parsedBody): ResponseInterface
	{
		$userId = (int)($backendUser->user['uid'] ?? 0);

		if (!$this->canUserElevate($backendUser)) {
			$this->logger->warning('User attempted elevation without permission', $this->createLogContext(['user_id' => $userId]));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.access_denied')]);
		}

		if ($backendUser->isAdmin()) {
			$this->logger->info('User attempted elevation while already admin', $this->createLogContext(['user_id' => $userId]));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.access_denied')]);
		}

		$password = $this->validatePassword($parsedBody['password'] ?? '');
		if ($password === '') {
			$this->logger->warning('Empty password provided for elevation', $this->createLogContext(['user_id' => $userId]));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.access_denied')]);
		}

		if (!$this->verifyPassword($backendUser, $password)) {
			$this->logger->warning('Invalid password provided for elevation', $this->createLogContext(['user_id' => $userId]));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.access_denied')]);
		}

		$currentTime = time();
		$this->setAdminElevation($userId, $currentTime);
		$this->logger->info('User elevated to admin successfully', $this->createLogContext(['user_id' => $userId]));

		return new JsonResponse([
			'success' => true,
			'message' => $this->translate('success.elevated_to_admin'),
			'reload' => true,
		]);
	}

	private function leaveAdminMode(BackendUserAuthentication $backendUser): ResponseInterface
	{
		$userId = (int)($backendUser->user['uid'] ?? 0);

		if (!$backendUser->isAdmin()) {
			$this->logger->warning('Non-admin user attempted to leave admin mode', $this->createLogContext(['user_id' => $userId]));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.access_denied')]);
		}

		if (!$this->isCurrentlyElevated($backendUser)) {
			$this->logger->warning('User attempted to leave permanent admin mode', $this->createLogContext(['user_id' => $userId]));
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.access_denied')]);
		}

		$this->clearAdminElevation($userId);
		$this->logger->info('User left admin mode successfully', $this->createLogContext(['user_id' => $userId]));

		return new JsonResponse([
			'success' => true,
			'message' => $this->translate('success.left_admin_mode'),
			'reload' => true,
		]);
	}

	private function verifyPassword(BackendUserAuthentication $backendUser, string $password): bool
	{
		$saltedPasswordService = GeneralUtility::makeInstance(PasswordHashFactory::class);
		$hashInstance = $saltedPasswordService->getDefaultHashInstance('BE');

		return $hashInstance->checkPassword($password, $backendUser->user['password']);
	}

	private function validateAction(string $action): ?string
	{
		return in_array($action, self::ALLOWED_ACTIONS, true) ? $action : null;
	}

	private function validatePassword(string $password): string
	{
		$password = trim($password);

		if (strlen($password) === 0) {
			return '';
		}

		if (strlen($password) > 255) {
			return '';
		}

		return $password;
	}
}
