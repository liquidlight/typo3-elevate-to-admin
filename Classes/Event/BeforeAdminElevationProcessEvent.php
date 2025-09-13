<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Event;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final class BeforeAdminElevationProcessEvent
{
	private bool $skipProcessing = false;

	public function __construct(
		private readonly BackendUserAuthentication $backendUser,
		private readonly ServerRequestInterface $request
	) {
	}

	public function getBackendUser(): BackendUserAuthentication
	{
		return $this->backendUser;
	}

	public function getRequest(): ServerRequestInterface
	{
		return $this->request;
	}

	public function skipProcessing(): void
	{
		$this->skipProcessing = true;
	}

	public function shouldSkipProcessing(): bool
	{
		return $this->skipProcessing;
	}
}
