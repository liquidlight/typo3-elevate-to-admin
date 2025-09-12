<?php

namespace LiquidLight\ElevateToAdmin\Middleware;

use LiquidLight\ElevateToAdmin\Event\BeforeAdminElevationProcessEvent;
use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;

class AdminElevationMiddleware implements MiddlewareInterface
{
	use AdminElevationTrait;

	private const ELEVATION_TIMEOUT_MINUTES = 10;

	private int $currentUserId;

	private ConnectionPool $connectionPool;

	private EventDispatcherInterface $eventDispatcher;

	public function __construct(
		ConnectionPool $connectionPool,
		EventDispatcherInterface $eventDispatcher
	) {
		$this->connectionPool = $connectionPool;
		$this->eventDispatcher = $eventDispatcher;
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

		$this->currentUserId = (int)$backendUser->user['uid'];

		if (!$backendUser->isAdmin()) {
			return;
		}

		$adminSince = $this->getAdminSince($backendUser);
		$currentTime = time();

		// Handle different elevation states
		if ($adminSince === 0 || $this->hasElevationExpired($adminSince, $currentTime)) {
			// No elevation session or expired - remove admin privileges
			$this->clearAdminElevation($this->currentUserId);
		} else {
			// Elevation still valid - refresh timestamp
			$this->updateUserRecordAndGlobal($this->currentUserId, [
				self::FIELD_ADMIN_SINCE => $currentTime,
				self::FIELD_IS_POSSIBLE_ADMIN => 1,
			]);
		}
	}

	private function hasElevationExpired(int $adminSince, int $currentTime): bool
	{
		return ($currentTime - $adminSince) > (self::ELEVATION_TIMEOUT_MINUTES * 60);
	}
}
