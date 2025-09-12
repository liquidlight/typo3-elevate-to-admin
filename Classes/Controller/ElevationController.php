<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Controller;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ElevationController
{
	use AdminElevationTrait;

	public function elevateAction(ServerRequestInterface $request): ResponseInterface
	{
		$backendUser = $this->getBackendUser();

		if (!$backendUser instanceof BackendUserAuthentication) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.no_backend_user')]);
		}

		$parsedBody = $request->getParsedBody();
		$action = $parsedBody['action'] ?? 'elevate';

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
		if (!$this->canUserElevate($backendUser)) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.user_not_allowed')]);
		}

		if ($backendUser->isAdmin()) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.already_admin')]);
		}

		$password = $parsedBody['password'] ?? '';
		if (empty($password)) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.password_required')]);
		}

		if (!$this->verifyPassword($backendUser, $password)) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.invalid_password')]);
		}

		$currentTime = time();
		$this->setAdminElevation((int)$backendUser->user['uid'], $currentTime);

		return new JsonResponse([
			'success' => true,
			'message' => $this->translate('success.elevated_to_admin'),
			'reload' => true,
		]);
	}

	private function leaveAdminMode(BackendUserAuthentication $backendUser): ResponseInterface
	{
		if (!$backendUser->isAdmin()) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.not_admin')]);
		}

		if (!$this->isCurrentlyElevated($backendUser)) {
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.cannot_leave_permanent')]);
		}

		$this->clearAdminElevation((int)$backendUser->user['uid']);

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
}
