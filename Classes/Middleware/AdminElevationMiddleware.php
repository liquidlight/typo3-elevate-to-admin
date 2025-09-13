<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Middleware;

use LiquidLight\ElevateToAdmin\Event\BeforeAdminElevationProcessEvent;
use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

class AdminElevationMiddleware implements MiddlewareInterface
{
	use AdminElevationTrait;

	private const ELEVATION_TIMEOUT_MINUTES = 10;

	public function __construct(
		private readonly EventDispatcherInterface $eventDispatcher
	) {
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$backendUser = $this->getBackendUser();

		if ($backendUser instanceof BackendUserAuthentication && $backendUser->user) {
			$this->processAdminElevation($backendUser, $request);
		}

		return $handler->handle($request);
	}

	private function processAdminElevation(BackendUserAuthentication $backendUser, ServerRequestInterface $request): void
	{
		$event = new BeforeAdminElevationProcessEvent($backendUser, $request);
		$this->eventDispatcher->dispatch($event);

		if ($event->shouldSkipProcessing()) {
			return;
		}

		if (!$backendUser->isAdmin()) {
			return;
		}

		$userId = (int)$backendUser->user['uid'];
		$adminSince = $this->getAdminSince($backendUser);

		if ($adminSince === 0) {
			$this->handlePermanentAdmin($userId);
		} elseif ($this->hasElevationExpired($adminSince, time())) {
			$this->clearAdminElevation($userId);
		} else {
			$this->refreshElevationTimestamp($userId);
		}
	}

	private function handlePermanentAdmin(int $userId): void
	{
		$this->updateUserRecordAndGlobal($userId, [
			'admin' => 0,
			self::FIELD_ADMIN_SINCE => 0,
			self::FIELD_IS_POSSIBLE_ADMIN => 1,
		]);
	}

	private function refreshElevationTimestamp(int $userId): void
	{
		$this->updateUserRecordAndGlobal($userId, [
			self::FIELD_ADMIN_SINCE => time(),
			self::FIELD_IS_POSSIBLE_ADMIN => 1,
		]);
	}

	private function hasElevationExpired(int $adminSince, int $currentTime): bool
	{
		return ($currentTime - $adminSince) > (self::ELEVATION_TIMEOUT_MINUTES * 60);
	}
}
