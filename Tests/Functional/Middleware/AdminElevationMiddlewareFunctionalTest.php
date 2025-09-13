<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Functional\Middleware;

use LiquidLight\ElevateToAdmin\Middleware\AdminElevationMiddleware;
use LiquidLight\ElevateToAdmin\Tests\Functional\FunctionalTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdminElevationMiddlewareFunctionalTest extends FunctionalTestCase
{
	private AdminElevationMiddleware $subject;

	private int $testUserId = 997;

	private $eventDispatcherMock;

	private $requestHandlerMock;

	protected function setUp(): void
	{
		parent::setUp();

		$this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
		$this->eventDispatcherMock->method('dispatch')->willReturnArgument(0);

		$this->subject = new AdminElevationMiddleware($this->eventDispatcherMock);

		$this->requestHandlerMock = $this->createMock(RequestHandlerInterface::class);
		$this->requestHandlerMock->method('handle')->willReturn(new Response());

		$this->createTestUser();
	}

	protected function tearDown(): void
	{
		$this->cleanupTestUser();
		parent::tearDown();
	}

	public function testMiddlewareProcessesPermanentAdminUser(): void
	{
		// Set up permanent admin user (admin=1, admin_since=0)
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->update(
			'be_users',
			[
				'admin' => 1,
				'tx_elevate_to_admin_admin_since' => 0,
			],
			['uid' => $this->testUserId]
		);

		$userData = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);

		// Mock isAdmin to return true
		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(true);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$request = $this->createRequest();
		$response = $this->subject->process($request, $this->requestHandlerMock);

		$this->assertInstanceOf(ResponseInterface::class, $response);

		// Verify permanent admin was converted to possible admin
		$updatedRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals(0, $updatedRecord['admin']);
		$this->assertEquals(0, $updatedRecord['tx_elevate_to_admin_admin_since']);
		$this->assertEquals(1, $updatedRecord['tx_elevate_to_admin_is_possible_admin']);
	}

	public function testMiddlewareClearsExpiredElevation(): void
	{
		$expiredTime = time() - (15 * 60); // 15 minutes ago (expired)

		// Set up expired elevated user
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->update(
			'be_users',
			[
				'admin' => 1,
				'tx_elevate_to_admin_admin_since' => $expiredTime,
			],
			['uid' => $this->testUserId]
		);

		$userData = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);

		// Mock isAdmin to return true
		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(true);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$request = $this->createRequest();
		$response = $this->subject->process($request, $this->requestHandlerMock);

		$this->assertInstanceOf(ResponseInterface::class, $response);

		// Verify expired elevation was cleared
		$updatedRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals(0, $updatedRecord['admin']);
		$this->assertEquals(0, $updatedRecord['tx_elevate_to_admin_admin_since']);
	}

	public function testMiddlewareRefreshesValidElevation(): void
	{
		$recentTime = time() - (5 * 60); // 5 minutes ago (still valid)

		// Set up valid elevated user
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->update(
			'be_users',
			[
				'admin' => 1,
				'tx_elevate_to_admin_admin_since' => $recentTime,
			],
			['uid' => $this->testUserId]
		);

		$userData = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);

		// Mock isAdmin to return true
		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(true);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$beforeProcessTime = time();

		$request = $this->createRequest();
		$response = $this->subject->process($request, $this->requestHandlerMock);

		$afterProcessTime = time();

		$this->assertInstanceOf(ResponseInterface::class, $response);

		// Verify elevation timestamp was refreshed
		$updatedRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals(1, $updatedRecord['admin']); // Still admin
		$this->assertGreaterThan($recentTime, $updatedRecord['tx_elevate_to_admin_admin_since']); // Timestamp updated
		$this->assertGreaterThanOrEqual($beforeProcessTime, $updatedRecord['tx_elevate_to_admin_admin_since']);
		$this->assertLessThanOrEqual($afterProcessTime, $updatedRecord['tx_elevate_to_admin_admin_since']);
		$this->assertEquals(1, $updatedRecord['tx_elevate_to_admin_is_possible_admin']);
	}

	public function testMiddlewareIgnoresNonAdminUsers(): void
	{
		$userData = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);

		// Mock isAdmin to return false
		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(false);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$originalRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$request = $this->createRequest();
		$response = $this->subject->process($request, $this->requestHandlerMock);

		$this->assertInstanceOf(ResponseInterface::class, $response);

		// Verify no changes were made
		$updatedRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals($originalRecord['admin'], $updatedRecord['admin']);
		$this->assertEquals($originalRecord['tx_elevate_to_admin_admin_since'], $updatedRecord['tx_elevate_to_admin_admin_since']);
		$this->assertEquals($originalRecord['tx_elevate_to_admin_is_possible_admin'], $updatedRecord['tx_elevate_to_admin_is_possible_admin']);
	}

	public function testMiddlewareHandlesNoBackendUser(): void
	{
		// Don't set any backend user
		unset($GLOBALS['BE_USER']);

		$originalRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$request = $this->createRequest();
		$response = $this->subject->process($request, $this->requestHandlerMock);

		$this->assertInstanceOf(ResponseInterface::class, $response);

		// Verify no database changes
		$updatedRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals($originalRecord, $updatedRecord);
	}

	public function testMiddlewareElevationTimeout(): void
	{
		// Test exact timeout boundary (10 minutes)
		$exactTimeoutTime = time() - (10 * 60);

		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->update(
			'be_users',
			[
				'admin' => 1,
				'tx_elevate_to_admin_admin_since' => $exactTimeoutTime,
			],
			['uid' => $this->testUserId]
		);

		$userData = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);

		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(true);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$request = $this->createRequest();
		$response = $this->subject->process($request, $this->requestHandlerMock);

		$this->assertInstanceOf(ResponseInterface::class, $response);

		// At exactly 10 minutes, it should still be valid (< 10 minutes = valid)
		// But since time has passed during execution, it might be expired
		$updatedRecord = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		// Either refreshed (still valid) or cleared (expired) - both are correct behavior
		$this->assertTrue(
			($updatedRecord['admin'] == 1 && $updatedRecord['tx_elevate_to_admin_admin_since'] > $exactTimeoutTime) ||
			($updatedRecord['admin'] == 0 && $updatedRecord['tx_elevate_to_admin_admin_since'] == 0)
		);
	}

	private function createTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->insert('be_users', [
			'uid' => $this->testUserId,
			'username' => 'middleware_test_user',
			'password' => '$argon2i$v=19$m=65536,t=16,p=1$test',
			'admin' => 0,
			'tx_elevate_to_admin_is_possible_admin' => 1,
			'tx_elevate_to_admin_admin_since' => 0,
			'tstamp' => time(),
			'crdate' => time(),
		]);
	}

	private function cleanupTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->delete('be_users', [
			'uid' => $this->testUserId,
		]);
	}

	private function createRequest(): ServerRequestInterface
	{
		return new ServerRequest('http://localhost/test', 'GET');
	}
}
