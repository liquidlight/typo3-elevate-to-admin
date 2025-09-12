<?php

namespace LiquidLight\ElevateToAdmin\Middleware;

use LiquidLight\ElevateToAdmin\Constants\DatabaseConstants;
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

		$adminSince = $this->getAdminSince($backendUser);
		$currentTime = time();

		if ($backendUser->isAdmin()) {
			// Handle different elevation states
			if ($adminSince === 0) {
				// User is admin but has no elevation timestamp - this means they're a permanent admin
				// Demote them and set them as possible admin
				$this->updateUserRecordAndGlobal($this->currentUserId, [
					'admin' => 0,
					DatabaseConstants::FIELD_ADMIN_SINCE => 0,
					DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN => 1,
				]);
			} elseif ($this->hasElevationExpired($adminSince, $currentTime)) {
				// Elevation expired - remove admin privileges
				$this->clearAdminElevation($this->currentUserId);
			} else {
				// Elevation still valid - refresh timestamp
				$this->updateUserRecordAndGlobal($this->currentUserId, [
					DatabaseConstants::FIELD_ADMIN_SINCE => $currentTime,
					DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN => 1,
				]);
			}
		}
	}

	private function hasElevationExpired(int $adminSince, int $currentTime): bool
	{
		return ($currentTime - $adminSince) > (self::ELEVATION_TIMEOUT_MINUTES * 60);
	}
}
