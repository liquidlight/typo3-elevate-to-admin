<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Functional\Controller;

use LiquidLight\ElevateToAdmin\Controller\ElevationController;
use LiquidLight\ElevateToAdmin\Tests\Functional\FunctionalTestCase;
use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ElevationControllerFunctionalTest extends FunctionalTestCase
{
	use AdminElevationTrait;

	private ElevationController $subject;

	private int $testUserId = 998;

	protected function setUp(): void
	{
		parent::setUp();
		$loggerMock = $this->createMock(LoggerInterface::class);
		$this->subject = new ElevationController($loggerMock);
		$this->setGlobalLanguageService();
		$this->createTestUser();
	}

	protected function tearDown(): void
	{
		$this->cleanupTestUser();
		parent::tearDown();
	}

	public function testElevateActionSucceedsWithIntegration(): void
	{
		// Create backend user with database data
		$userData = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);
		$this->setGlobalBackendUser($backendUser);

		$response = $this->subject->elevateAction($this->createRequest());

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);

		$this->assertTrue($responseData['success']);
		$this->assertTrue($responseData['reload']);
		$this->assertStringStartsWith('translated_', $responseData['message']);

		// Verify database changes
		$updatedRecord = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals(1, $updatedRecord['admin']);
		$this->assertGreaterThan(0, $updatedRecord[self::FIELD_ADMIN_SINCE]);
	}

	public function testElevateActionHandlesUserWithoutPermission(): void
	{
		// Update user to not have elevation permission
		$this->updateUserRecord($this->testUserId, [
			self::FIELD_IS_POSSIBLE_ADMIN => 0,
		]);

		$userData = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);
		$this->setGlobalBackendUser($backendUser);

		$response = $this->subject->elevateAction($this->createRequest());

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);

		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testLeaveActionIntegration(): void
	{
		// Set user as elevated admin in database
		$timestamp = time();
		$this->setAdminElevation($this->testUserId, $timestamp);

		$userData = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		// Create mock backend user with admin status
		$backendUser = $this->createBackendUser($userData);

		// Mock isAdmin method to return true
		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(true);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$response = $this->subject->leaveAction($this->createRequest());

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);

		$this->assertTrue($responseData['success']);
		$this->assertTrue($responseData['reload']);
		$this->assertStringStartsWith('translated_', $responseData['message']);

		// Verify database changes
		$updatedRecord = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals(0, $updatedRecord['admin']);
		$this->assertEquals(0, $updatedRecord[self::FIELD_ADMIN_SINCE]);
	}

	public function testElevateActionRejectsAlreadyAdminUser(): void
	{
		// Set user as admin in database
		$this->updateUserRecord($this->testUserId, ['admin' => 1]);

		$userData = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($userData);

		// Mock isAdmin method to return true
		$backendUserMock = $this->getMockBuilder(get_class($backendUser))
			->onlyMethods(['isAdmin'])
			->getMock()
		;
		$backendUserMock->method('isAdmin')->willReturn(true);
		$backendUserMock->user = $userData;

		$this->setGlobalBackendUser($backendUserMock);

		$response = $this->subject->elevateAction($this->createRequest());

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);

		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testControllerPreservesUserDataIntegrity(): void
	{
		$originalUserData = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$backendUser = $this->createBackendUser($originalUserData);
		$this->setGlobalBackendUser($backendUser);

		// Leaving admin mode without being elevated should fail
		$response = $this->subject->leaveAction($this->createRequest());
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);

		// Verify user data was not modified
		$currentUserData = $this->getDatabaseRecord(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertEquals($originalUserData['admin'], $currentUserData['admin']);
		$this->assertEquals($originalUserData[self::FIELD_ADMIN_SINCE], $currentUserData[self::FIELD_ADMIN_SINCE]);
	}

	private function createTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable(self::TABLE_BE_USERS)
		;

		$hashedPassword = '$argon2i$v=19$m=65536,t=16,p=1$test'; // Pre-hashed test password

		$connection->insert(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
			'username' => 'elevation_test_user',
			'password' => $hashedPassword,
			'admin' => 0,
			self::FIELD_IS_POSSIBLE_ADMIN => 1,
			self::FIELD_ADMIN_SINCE => 0,
			'tstamp' => time(),
			'crdate' => time(),
		]);
	}

	private function cleanupTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable(self::TABLE_BE_USERS)
		;

		$connection->delete(self::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);
	}

	private function createRequest(): ServerRequestInterface
	{
		return new ServerRequest('http://localhost/test', 'POST');
	}
}
