<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Controller;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;

#[Autoconfigure(public: true)]
class ElevationController
{
	use AdminElevationTrait;

	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * The password is confirmed by TYPO3's sudo mode (see the `sudoMode` option
	 * of the `elevate_admin` AJAX route) before this action is reached
	 */
	public function elevateAction(ServerRequestInterface $request): ResponseInterface
	{
		$backendUser = $this->getBackendUser();

		if (!$backendUser instanceof BackendUserAuthentication) {
			$this->logger->warning('No backend user found during elevation attempt', $this->createLogContext());
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.authentication_required')]);
		}

		return $this->elevateToAdmin($backendUser);
	}

	public function leaveAction(ServerRequestInterface $request): ResponseInterface
	{
		$backendUser = $this->getBackendUser();

		if (!$backendUser instanceof BackendUserAuthentication) {
			$this->logger->warning('No backend user found during leave attempt', $this->createLogContext());
			return new JsonResponse(['success' => false, 'message' => $this->translate('error.authentication_required')]);
		}

		return $this->leaveAdminMode($backendUser);
	}

	private function getLanguageService(): LanguageService
	{
		return $GLOBALS['LANG'];
	}

	private function translate(string $key): string
	{
		return $this->getLanguageService()->sL('LLL:EXT:ll_elevate_to_admin/Resources/Private/Language/locallang.xlf:' . $key);
	}

	private function elevateToAdmin(BackendUserAuthentication $backendUser): ResponseInterface
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
}
