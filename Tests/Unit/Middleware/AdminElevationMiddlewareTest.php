<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Unit\Middleware;

use LiquidLight\ElevateToAdmin\Event\BeforeAdminElevationProcessEvent;
use LiquidLight\ElevateToAdmin\Middleware\AdminElevationMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdminElevationMiddlewareTest extends TestCase
{
	private AdminElevationMiddleware $subject;

	private $eventDispatcherMock;

	private $backendUserMock;

	private $requestMock;

	private $handlerMock;

	private $responseMock;

	private $connectionMock;

	private $connectionPoolMock;

	private $loggerMock;

	protected function setUp(): void
	{
		parent::setUp();

		$this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);
		$this->subject = new AdminElevationMiddleware($this->eventDispatcherMock, $this->loggerMock);
		$this->backendUserMock = $this->createMock(BackendUserAuthentication::class);
		$this->requestMock = $this->createMock(ServerRequestInterface::class);
		$this->handlerMock = $this->createMock(RequestHandlerInterface::class);
		$this->responseMock = $this->createMock(ResponseInterface::class);
		$this->connectionMock = $this->createMock(Connection::class);
		$this->connectionPoolMock = $this->createMock(ConnectionPool::class);

		$this->connectionPoolMock
			->method('getConnectionForTable')
			->willReturn($this->connectionMock)
		;

		GeneralUtility::addInstance(ConnectionPool::class, $this->connectionPoolMock);

		$this->handlerMock
			->method('handle')
			->with($this->requestMock)
			->willReturn($this->responseMock)
		;
	}

	protected function tearDown(): void
	{
		unset($GLOBALS['BE_USER']);
		GeneralUtility::purgeInstances();
		parent::tearDown();
	}

	public function testProcessReturnsHandlerResponseWhenNoBackendUser(): void
	{
		unset($GLOBALS['BE_USER']);

		$result = $this->subject->process($this->requestMock, $this->handlerMock);

		$this->assertSame($this->responseMock, $result);
	}

	public function testProcessReturnsHandlerResponseWhenBackendUserHasNoUserData(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = null;

		$result = $this->subject->process($this->requestMock, $this->handlerMock);

		$this->assertSame($this->responseMock, $result);
	}

	public function testProcessDispatchesEvent(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = ['uid' => 123];

		$this->eventDispatcherMock
			->expects($this->once())
			->method('dispatch')
			->willReturnCallback(function ($event) {
				$this->assertInstanceOf(BeforeAdminElevationProcessEvent::class, $event);
				return $event;
			})
		;

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$result = $this->subject->process($this->requestMock, $this->handlerMock);

		$this->assertSame($this->responseMock, $result);
	}

	public function testProcessHandlesPermanentAdminWhenAdminSinceIsZero(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevatetoadmin_admin_since' => 0,
		];

		$this->eventDispatcherMock
			->method('dispatch')
			->willReturnCallback(function ($event) {
				return $event;
			})
		;

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(true)
		;

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				[
					'admin' => 0,
					'tx_elevatetoadmin_admin_since' => 0,
					'tx_elevatetoadmin_is_possible_admin' => 1,
				],
				['uid' => 123]
			)
		;

		$result = $this->subject->process($this->requestMock, $this->handlerMock);

		$this->assertSame($this->responseMock, $result);
	}

	public function testProcessClearsElevationWhenExpired(): void
	{
		$expiredTime = time() - (15 * 60); // 15 minutes ago

		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevatetoadmin_admin_since' => $expiredTime,
		];

		$this->eventDispatcherMock
			->method('dispatch')
			->willReturnCallback(function ($event) {
				return $event;
			})
		;

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(true)
		;

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				[
					'admin' => 0,
					'options' => 3,
					'tx_elevatetoadmin_admin_since' => 0,
				],
				['uid' => 123]
			)
		;

		$result = $this->subject->process($this->requestMock, $this->handlerMock);

		$this->assertSame($this->responseMock, $result);
	}

	public function testProcessRefreshesTimestampWhenNotExpired(): void
	{
		$recentTime = time() - (6 * 60); // 6 minutes ago (past halfway point, should refresh)

		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevatetoadmin_admin_since' => $recentTime,
		];

		$this->eventDispatcherMock
			->method('dispatch')
			->willReturnCallback(function ($event) {
				return $event;
			})
		;

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(true)
		;

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				$this->callback(function ($fields) {
					return $fields['tx_elevatetoadmin_is_possible_admin'] === 1
						&& is_int($fields['tx_elevatetoadmin_admin_since'])
						&& $fields['tx_elevatetoadmin_admin_since'] > 0;
				}),
				['uid' => 123]
			)
		;

		$result = $this->subject->process($this->requestMock, $this->handlerMock);

		$this->assertSame($this->responseMock, $result);
	}

	public function testElevationTimeoutIsSetTo10Minutes(): void
	{
		$reflection = new \ReflectionClass(AdminElevationMiddleware::class);
		$constant = $reflection->getConstant('ELEVATION_TIMEOUT_MINUTES');

		$this->assertEquals(10, $constant);
	}
}
